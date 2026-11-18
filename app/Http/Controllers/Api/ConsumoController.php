<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Consumo;
use App\Models\Estilista;
use App\Models\Producto;
use App\Models\Servicio;
use App\Services\ConsumoService;
use App\Services\RegistroDiarioService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ConsumoController extends Controller
{
    public function __construct(private readonly ConsumoService $servicio) {}

    public function index(Request $request): JsonResponse
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
            ->paginate(15);

        $resumenDiario = RegistroDiarioService::resumen($desde, $hasta, $filtros);

        $ingresos = (clone $base)
            ->where('estado', Consumo::ESTADO_ACTIVO)
            ->selectRaw('COALESCE(SUM(precio_cobrado), 0) AS ingresos')
            ->first();

        $totalIngresos = Money::normal($ingresos->ingresos ?? 0);

        return response()->json([
            'data' => $consumos->getCollection()->map(fn ($c) => $this->resumen($c)),
            'meta' => [
                'current_page' => $consumos->currentPage(),
                'last_page' => $consumos->lastPage(),
                'total' => $consumos->total(),
            ],
            'desde' => $desde,
            'hasta' => $hasta,
            'periodo' => $request->str('periodo', 'dia')->value(),
            'resumen' => $resumenDiario,
            'total_ingresos' => $totalIngresos,
            'total_registros' => $consumos->total(),
            'hay_registros_totales' => Consumo::where('estado', Consumo::ESTADO_ACTIVO)->exists(),
            'por_reponer' => Producto::where('activo', true)
                ->whereColumn('stock', '<=', 'stock_minimo')->orderBy('stock')->limit(8)->get()->map(fn ($p) => [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'stock' => (string) $p->stock,
                    'stock_minimo' => (string) $p->stock_minimo,
                    'unidad_abrev' => $p->unidadAbrev(),
                    'esta_agotado' => $p->estaAgotado(),
                ])->values(),
            'clientes' => Cliente::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'servicios' => Servicio::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'estilistas' => Estilista::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'productos' => Producto::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'filtros' => $filtros,
        ]);
    }

    public function opciones(): JsonResponse
    {
        $todos = Producto::with('categoria')->where('activo', true)->orderBy('nombre')->get()->map(fn ($p) => [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'stock' => (string) $p->stock,
            'costo' => RegistroDiarioService::costoPorGramo($p),
            'tiene_costo' => RegistroDiarioService::costoPorGramo($p) !== null,
            'grupo' => RegistroDiarioService::grupoDe($p),
        ])->values();

        return response()->json([
            'estilistas' => Estilista::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'productos_por_grupo' => RegistroDiarioService::productosPorGrupo(),
            'productos' => $todos,
            'grupos' => RegistroDiarioService::GRUPOS,
            'costo_tinte' => RegistroDiarioService::COSTO_GRAMO_TINTE,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $consumo = $this->servicio->registrar($this->validar($request));

        return response()->json(['data' => $this->detalle($consumo)], 201);
    }

    public function show(Consumo $consumo): JsonResponse
    {
        $consumo->load(['detalles.producto.categoria', 'cliente', 'servicio', 'estilista', 'usuario']);

        return response()->json([
            'data' => $this->detalle($consumo),
            'ingresos' => Money::normal($consumo->precio_cobrado ?? 0),
            'costo' => Money::normal($consumo->costo_total),
            'diferencia' => Money::sub($consumo->precio_cobrado ?? 0, $consumo->costo_total),
        ]);
    }

    public function update(Request $request, Consumo $consumo): JsonResponse
    {
        $consumo = $this->servicio->actualizar($consumo, $this->validar($request));

        return response()->json(['data' => $this->detalle($consumo)]);
    }

    public function destroy(Consumo $consumo): JsonResponse
    {
        $this->servicio->eliminar($consumo);

        return response()->json(['message' => 'Registro eliminado: el inventario fue devuelto a sus lotes.']);
    }

    public function anular(Consumo $consumo): JsonResponse
    {
        $consumo = $this->servicio->anular($consumo);

        return response()->json(['data' => $this->detalle($consumo)]);
    }

    /**
     * Guarda un único producto (línea) de la jornada, idempotente por token.
     */
    public function guardarProducto(Request $request): JsonResponse
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

        $consumo = $this->servicio->guardarLinea($this->cabecera($request), [
            'producto_id' => $datos['producto_id'],
            'cantidad' => $datos['cantidad'],
            'costo_unitario' => $datos['costo_unitario'] ?? null,
            'notas' => $datos['notas'] ?? null,
            'detalle_id' => $datos['detalle_id'] ?? null,
        ]);

        $linea = $consumo->detalles->sortByDesc('id')->first();

        return response()->json([
            'ok' => true,
            'consumo_id' => $consumo->id,
            'token' => $consumo->token,
            'costo_total' => (string) $consumo->costo_total,
            'linea' => $linea ? [
                'id' => $linea->id,
                'producto' => $linea->producto?->nombre,
                'producto_id' => $linea->producto_id,
                'cantidad' => (string) $linea->cantidad,
                'costo_unitario' => (string) $linea->costo_unitario,
                'costo_total' => (string) $linea->costo_total,
            ] : null,
            'data' => $this->detalle($consumo),
        ]);
    }

    public function actualizarCabecera(Request $request, Consumo $consumo): JsonResponse
    {
        abort_unless($consumo->esActivo(), 422, 'No se puede modificar un registro anulado.');

        $datos = $request->validate([
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'precio_cobrado' => ['nullable', 'numeric', 'min:0'],
            'estilista_id' => ['nullable', 'exists:estilistas,id'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        $consumo = $this->servicio->actualizarCabecera($consumo, $datos);

        return response()->json([
            'ok' => true,
            'consumo_id' => $consumo->id,
            'data' => $this->detalle($consumo),
        ]);
    }

    public function quitarProducto(Request $request, Consumo $consumo): JsonResponse
    {
        $datos = $request->validate([
            'detalle_id' => ['required', 'integer'],
        ]);

        $consumo = $this->servicio->quitarLinea($consumo, (int) $datos['detalle_id']);

        return response()->json([
            'ok' => true,
            'costo_total' => (string) $consumo->costo_total,
            'data' => $this->detalle($consumo),
        ]);
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

    private function resumen(Consumo $c): array
    {
        return [
            'id' => $c->id,
            'token' => $c->token,
            'fecha' => $c->fecha?->toDateString(),
            'descripcion' => $c->descripcion,
            'precio_cobrado' => $c->precio_cobrado !== null ? (string) $c->precio_cobrado : null,
            'costo_total' => (string) $c->costo_total,
            'estado' => $c->estado,
            'estado_label' => $c->estadoLabel(),
            'cliente_nombre' => $c->cliente?->nombre,
            'cliente_id' => $c->cliente_id,
            'servicio_nombre' => $c->servicio?->nombre,
            'servicio_id' => $c->servicio_id,
            'estilista_nombre' => $c->estilista?->nombre,
            'estilista_id' => $c->estilista_id,
            'productos_count' => (int) ($c->productos_count ?? $c->detalles->count()),
            'gramos' => (string) ($c->gramos_count ?? '0.000'),
        ];
    }

    private function detalle(Consumo $c): array
    {
        $c->loadMissing(['detalles.producto.categoria', 'cliente', 'servicio', 'estilista', 'usuario']);

        $base = $this->resumen($c);

        return $base + [
            'token' => $c->token,
            'cliente_id' => $c->cliente_id,
            'cita_id' => $c->cita_id,
            'servicio_id' => $c->servicio_id,
            'estilista_id' => $c->estilista_id,
            'observaciones' => $c->observaciones,
            'estado' => $c->estado,
            'creador_nombre' => $c->usuario?->name,
            'lineas' => $c->detalles->map(function ($l) {
                $grupo = RegistroDiarioService::grupoDe($l->producto);

                return [
                    'id' => $l->id,
                    'producto_id' => $l->producto_id,
                    'producto_nombre' => $l->producto?->nombre,
                    'grupo' => $grupo,
                    'grupo_label' => RegistroDiarioService::etiqueta($grupo),
                    'cantidad' => (string) $l->cantidad,
                    'costo_unitario' => (string) $l->costo_unitario,
                    'costo_total' => (string) $l->costo_total,
                    'notas' => $l->notas,
                    'estado' => $l->estado,
                ];
            })->values(),
        ];
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
