<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Consumo;
use App\Models\Estilista;
use App\Models\Producto;
use App\Models\Servicio;
use App\Services\ConsumoService;
use App\Services\RegistroDiarioService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ConsumoController extends Controller
{
    public function index(Request $request): View
    {
        [$desde, $hasta] = $this->rango($request);

        $filtros = [
            'cliente' => $request->integer('cliente') ?: null,
            'servicio' => $request->integer('servicio') ?: null,
            'estilista' => $request->integer('estilista') ?: null,
            'producto' => $request->integer('producto') ?: null,
            'estado' => $request->filled('estado') ? $request->string('estado')->value() : null,
        ];

        $base = Consumo::query()
            ->whereBetween('fecha', [$desde, $hasta])
            ->when($filtros['cliente'], fn ($q) => $q->where('cliente_id', $filtros['cliente']))
            ->when($filtros['servicio'], fn ($q) => $q->where('servicio_id', $filtros['servicio']))
            ->when($filtros['estilista'], fn ($q) => $q->where('estilista_id', $filtros['estilista']))
            ->when($filtros['producto'], fn ($q) => $q->whereHas('detalles', fn ($s) => $s->where('producto_id', $filtros['producto'])))
            ->when($filtros['estado'], fn ($q) => $q->where('estado', $filtros['estado']));

        $consumos = (clone $base)
            ->with(['cliente', 'servicio', 'estilista', 'usuario'])
            ->withCount(['detalles as productos_count', 'detalles as gramos_count' => fn ($q) => $q->selectRaw('COALESCE(SUM(cantidad), 0)')])
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        // Resumen del período por categoría: gramos y costo.
        $resumenDiario = RegistroDiarioService::resumen($desde, $hasta, $filtros);

        // Lo cobrado a los clientes en el período.
        $ingresos = (clone $base)
            ->where('estado', Consumo::ESTADO_ACTIVO)
            ->selectRaw('COALESCE(SUM(precio_cobrado), 0) AS ingresos')
            ->first();

        $totalIngresos = Money::normal($ingresos->ingresos ?? 0);

        return view('consumos.index', [
            'consumos' => $consumos,
            'desde' => $desde,
            'hasta' => $hasta,
            'periodo' => $request->str('periodo', 'dia')->value(),
            'resumenDiario' => $resumenDiario,
            'totalIngresos' => $totalIngresos,
            'totalRegistros' => $consumos->total(),
            'hayRegistrosTotales' => Consumo::where('estado', Consumo::ESTADO_ACTIVO)->exists(),
            'totalCantidad' => $resumenDiario['total_gramos'],
            'porReponer' => Producto::where('activo', true)
                ->whereColumn('stock', '<=', 'stock_minimo')->orderBy('stock')->limit(8)->get(),
            'clientes' => Cliente::where('activo', true)->orderBy('nombre')->get(),
            'servicios' => Servicio::where('activo', true)->orderBy('nombre')->get(),
            'estilistas' => Estilista::where('activo', true)->orderBy('nombre')->get(),
            'productos' => RegistroDiarioService::productosPorGrupo(),
            'filtros' => $filtros,
        ]);
    }

    public function create(Request $request): View
    {
        return view('consumos.create', $this->formulario([
            'token' => (string) Str::uuid(),
        ]));
    }

    public function edit(Consumo $consumo): View
    {
        abort_unless($consumo->esActivo(), 403, 'No se puede editar un registro anulado.');

        $consumo->load('detalles.producto.categoria');

        $lineas = $consumo->detalles->map(fn ($linea) => [
            'id' => $linea->id,
            'producto_id' => $linea->producto_id,
            'cantidad' => (string) $linea->cantidad,
            'costo_unitario' => (string) $linea->costo_unitario,
            'notas' => $linea->notas,
            'grupo' => RegistroDiarioService::grupoDe($linea->producto),
        ])->values()->all();

        return view('consumos.create', $this->formulario([
            'consumo' => $consumo,
            'token' => $consumo->token,
            'lineas' => $lineas,
            'mostrarOtros' => collect($lineas)->contains(fn ($l) => $l['grupo'] === null),
        ]));
    }

    public function store(Request $request, ConsumoService $servicio): RedirectResponse
    {
        $consumo = $servicio->registrar($this->validar($request));

        return redirect()->route('consumos.show', $consumo)
            ->with('exito', 'Registro guardado: el inventario fue descontado.');
    }

    /**
     * Guarda un único producto (línea) de la jornada vía AJAX.
     */
    public function guardarProducto(Request $request, ConsumoService $servicio): JsonResponse
    {
        $datos = $request->validate([
            'token' => ['nullable', 'string', 'max:64'],
            'fecha' => ['required', 'date'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'servicio_id' => ['nullable', 'exists:servicios,id'],
            'estilista_id' => ['nullable', 'exists:estilistas,id'],
            'precio_cobrado' => ['nullable', 'numeric', 'min:0'],
            'descripcion' => ['required', 'string', 'max:1000'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'costo_unitario' => ['nullable', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string', 'max:255'],
            'detalle_id' => ['nullable', 'integer'],
        ]);

        $consumo = $servicio->guardarLinea($this->cabecera($request), [
            'producto_id' => $datos['producto_id'],
            'cantidad' => $datos['cantidad'],
            'costo_unitario' => $datos['costo_unitario'] ?? null,
            'notas' => $datos['notas'] ?? null,
            'detalle_id' => $datos['detalle_id'] ?? null,
        ]);

        $linea = $consumo->detalles->last();

        return response()->json([
            'ok' => true,
            'consumo_id' => $consumo->id,
            'token' => $consumo->token,
            'show_url' => route('consumos.show', $consumo),
            'costo_total' => (string) $consumo->costo_total,
            'linea' => $linea ? [
                'id' => $linea->id,
                'producto' => $linea->producto?->nombre,
                'cantidad' => (string) $linea->cantidad,
                'costo_total' => (string) $linea->costo_total,
            ] : null,
        ]);
    }

    /**
     * Guarda solo los datos de la jornada (qué se hizo, cuánto se cobró).
     */
    public function actualizarCabecera(Request $request, Consumo $consumo, ConsumoService $servicio): JsonResponse
    {
        abort_unless($consumo->esActivo(), 422, 'No se puede modificar un registro anulado.');

        $datos = $request->validate([
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'precio_cobrado' => ['nullable', 'numeric', 'min:0'],
            'estilista_id' => ['nullable', 'exists:estilistas,id'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        $servicio->actualizarCabecera($consumo, $datos);

        return response()->json([
            'ok' => true,
            'show_url' => route('consumos.show', $consumo),
        ]);
    }

    /**
     * Quita una línea guardada del registro, devolviendo su stock.
     */
    public function quitarProducto(Request $request, Consumo $consumo, ConsumoService $servicio): JsonResponse
    {
        $datos = $request->validate([
            'detalle_id' => ['required', 'integer'],
        ]);

        $consumo = $servicio->quitarLinea($consumo, (int) $datos['detalle_id']);

        return response()->json([
            'ok' => true,
            'costo_total' => (string) $consumo->costo_total,
        ]);
    }

    public function update(Request $request, Consumo $consumo, ConsumoService $servicio): RedirectResponse
    {
        $servicio->actualizar($consumo, $this->validar($request));

        return redirect()->route('consumos.show', $consumo)
            ->with('exito', 'Registro actualizado: el inventario fue recalculado.');
    }

    public function destroy(Consumo $consumo, ConsumoService $servicio): RedirectResponse
    {
        $servicio->eliminar($consumo);

        return redirect()->route('consumos.index')
            ->with('exito', 'Registro eliminado: el inventario fue devuelto a sus lotes.');
    }

    public function show(Consumo $consumo): View
    {
        $consumo->load(['detalles.producto', 'cliente', 'servicio', 'estilista', 'usuario']);

        return view('consumos.show', [
            'consumo' => $consumo,
            'ingresos' => Money::normal($consumo->precio_cobrado ?? 0),
            'costo' => Money::normal($consumo->costo_total),
            'diferencia' => Money::sub($consumo->precio_cobrado ?? 0, $consumo->costo_total),
        ]);
    }

    public function anular(Consumo $consumo, ConsumoService $servicio): RedirectResponse
    {
        $servicio->anular($consumo);

        return redirect()->route('consumos.show', $consumo)
            ->with('exito', 'Registro anulado: el inventario fue devuelto a sus lotes.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        return $request->validate([
            'token' => ['nullable', 'string', 'max:64'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'cita_id' => ['nullable', 'exists:citas,id'],
            'venta_id' => ['nullable', 'exists:ventas,id'],
            'servicio_id' => ['nullable', 'exists:servicios,id'],
            'estilista_id' => ['nullable', 'exists:estilistas,id'],
            'fecha' => ['required', 'date'],
            'precio_cobrado' => ['nullable', 'numeric', 'min:0'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'detalles.*.costo_unitario' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.notas' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * Cabecera del registro diario.
     *
     * Solo se incluyen los campos que llegan en la petición: así, un guardado
     * que no menciona cliente, servicio, descripción ni precio NO borra lo que
     * ya estaba guardado (y lo que sí llega, se puede vaciar).
     *
     * @return array<string, mixed>
     */
    private function cabecera(Request $request): array
    {
        $cabecera = [
            'token' => $request->input('token'),
            'fecha' => $request->input('fecha'),
        ];

        foreach (['cliente_id', 'servicio_id', 'estilista_id', 'precio_cobrado', 'descripcion', 'observaciones'] as $campo) {
            if ($request->has($campo)) {
                $cabecera[$campo] = $request->input($campo);
            }
        }

        return $cabecera;
    }

    /**
     * @return array<string, mixed>
     */
    private function formulario(array $extra = []): array
    {
        return array_merge([
            'estilistas' => Estilista::where('activo', true)->orderBy('nombre')->get(),
            'productos' => RegistroDiarioService::productosPorGrupo() + ['otros' => []],
            'consumo' => null,
            'lineas' => [],
            'mostrarOtros' => false,
        ], $extra);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function rango(Request $request): array
    {
        $hoy = Carbon::today();

        [$desde, $hasta] = match ($request->string('periodo', 'dia')->value()) {
            'dia' => [$hoy->toDateString(), $hoy->toDateString()],
            'semana' => [$hoy->copy()->startOfWeek()->toDateString(), $hoy->copy()->endOfWeek()->toDateString()],
            'mes' => [$hoy->copy()->startOfMonth()->toDateString(), $hoy->copy()->endOfMonth()->toDateString()],
            'todo' => ['1970-01-01', $hoy->copy()->addYears(5)->toDateString()],
            default => [
                $request->filled('desde') ? Carbon::parse($request->date('desde'))->toDateString() : $hoy->toDateString(),
                $request->filled('hasta') ? Carbon::parse($request->date('hasta'))->toDateString() : $hoy->toDateString(),
            ],
        };

        if (Carbon::parse($desde)->gt(Carbon::parse($hasta))) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [$desde, $hasta];
    }
}
