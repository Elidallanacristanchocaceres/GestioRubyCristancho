<?php

namespace App\Http\Controllers;

use App\Models\Atencion;
use App\Models\Cliente;
use App\Models\Estilista;
use App\Models\Servicio;
use App\Services\AuditoriaService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Registro diario de clientes: qué se le realizó a cada cliente y el total.
 *
 * Independiente del consumo de productos. No descuenta inventario.
 */
class AtencionController extends Controller
{
    public function index(Request $request): View
    {
        [$desde, $hasta] = $this->rango($request);

        $filtros = [
            'cliente' => $request->integer('cliente') ?: null,
            'servicio' => $request->integer('servicio') ?: null,
            'estilista' => $request->integer('estilista') ?: null,
            'estado' => $request->filled('estado') ? $request->string('estado')->value() : null,
        ];

        $base = Atencion::query()
            ->whereBetween('fecha', [$desde, $hasta])
            ->when($filtros['cliente'], fn ($q) => $q->where('cliente_id', $filtros['cliente']))
            ->when($filtros['estilista'], fn ($q) => $q->where('estilista_id', $filtros['estilista']))
            ->when($filtros['servicio'], fn ($q) => $q->whereHas('detalles', fn ($s) => $s->where('servicio_id', $filtros['servicio'])))
            ->when($filtros['estado'], fn ($q) => $q->where('estado', $filtros['estado']));

        $atenciones = (clone $base)
            ->with(['cliente', 'estilista', 'usuario', 'detalles.servicio'])
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        $resumen = (clone $base)
            ->where('estado', Atencion::ESTADO_ACTIVO)
            ->selectRaw('COALESCE(SUM(total), 0) AS total, COALESCE(SUM(pagado), 0) AS pagado, COUNT(*) AS n')
            ->first();

        return view('atenciones.index', [
            'atenciones' => $atenciones,
            'desde' => $desde,
            'hasta' => $hasta,
            'periodo' => $request->str('periodo', 'dia')->value(),
            'totalFacturado' => Money::normal($resumen->total ?? 0),
            'totalPagado' => Money::normal($resumen->pagado ?? 0),
            'totalSaldo' => Money::sub($resumen->total ?? 0, $resumen->pagado ?? 0),
            'totalRegistros' => (int) ($resumen->n ?? 0),
            'hayRegistrosTotales' => Atencion::where('estado', Atencion::ESTADO_ACTIVO)->exists(),
            'clientes' => Cliente::where('activo', true)->orderBy('nombre')->get(),
            'servicios' => Servicio::where('activo', true)->orderBy('nombre')->get(),
            'estilistas' => Estilista::where('activo', true)->orderBy('nombre')->get(),
            'filtros' => $filtros,
        ]);
    }

    public function create(Request $request): View
    {
        return view('atenciones.create', $this->formulario([
            'token' => (string) Str::uuid(),
            'atencion' => null,
            'lineas' => [],
        ]));
    }

    public function edit(Atencion $atencion): View
    {
        abort_unless($atencion->esActivo(), 403, 'No se puede editar una atención anulada.');

        $atencion->load('detalles.servicio');

        $lineas = $atencion->detalles->map(fn ($l) => [
            'id' => $l->id,
            'servicio_id' => $l->servicio_id,
            'nombre' => $l->nombre,
            'descripcion' => $l->descripcion,
            'precio_unitario' => (string) $l->precio_unitario,
        ])->values()->all();

        return view('atenciones.create', $this->formulario([
            'atencion' => $atencion,
            'token' => $atencion->token,
            'lineas' => $lineas,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $atencion = new Atencion([
            'token' => $datos['token'] ?? (string) Str::uuid(),
            'cliente_id' => $datos['cliente_id'] ?? null,
            'cita_id' => $datos['cita_id'] ?? null,
            'estilista_id' => $datos['estilista_id'] ?? null,
            'fecha' => $datos['fecha'],
            'descripcion' => $datos['descripcion'] ?? null,
            'observaciones' => $datos['observaciones'] ?? null,
            'metodo_pago' => $datos['metodo_pago'] ?? null,
            'pagado' => $datos['pagado'] ?? 0,
            'estado' => Atencion::ESTADO_ACTIVO,
            'created_by' => $request->user()->id,
        ]);
        $atencion->save();

        $this->sincronizarDetalles($atencion, $datos['detalles']);

        AuditoriaService::registrar('crear', 'atenciones', Atencion::class, $atencion->id, [], [
            'cliente_id' => $atencion->cliente_id,
            'total' => (string) $atencion->total,
        ]);

        return redirect()->route('atenciones.show', $atencion)
            ->with('exito', 'Atención registrada correctamente.');
    }

    public function update(Request $request, Atencion $atencion): RedirectResponse
    {
        abort_unless($atencion->esActivo(), 403, 'No se puede editar una atención anulada.');

        $datos = $this->validar($request);

        $antes = $atencion->only(['cliente_id', 'total', 'pagado', 'descripcion']);
        $atencion->fill([
            // El registro de clientes no pide datos del cliente: si no viene,
            // se conserva el que ya tuviera la atención.
            'cliente_id' => $datos['cliente_id'] ?? $atencion->cliente_id,
            'cita_id' => $datos['cita_id'] ?? null,
            'estilista_id' => $datos['estilista_id'] ?? null,
            'fecha' => $datos['fecha'],
            'descripcion' => $datos['descripcion'] ?? null,
            'observaciones' => $datos['observaciones'] ?? null,
            'metodo_pago' => $datos['metodo_pago'] ?? null,
            'pagado' => $datos['pagado'] ?? 0,
        ]);
        $atencion->save();

        $atencion->detalles()->delete();
        $this->sincronizarDetalles($atencion, $datos['detalles']);

        AuditoriaService::registrar('actualizar', 'atenciones', Atencion::class, $atencion->id, $antes, $atencion->only(array_keys($antes)));

        return redirect()->route('atenciones.show', $atencion)
            ->with('exito', 'Atención actualizada correctamente.');
    }

    public function show(Atencion $atencion): View
    {
        $atencion->load(['detalles.servicio', 'detalles.estilista', 'cliente', 'estilista', 'usuario', 'cita', 'venta']);

        return view('atenciones.show', [
            'atencion' => $atencion,
            'saldo' => $atencion->saldo(),
        ]);
    }

    public function anular(Request $request, Atencion $atencion): RedirectResponse
    {
        abort_unless($atencion->esActivo(), 403, 'Esta atención ya fue anulada.');

        $atencion->update([
            'estado' => Atencion::ESTADO_ANULADO,
            'reversado_por' => $request->user()->id,
            'reversado_en' => now(),
        ]);

        AuditoriaService::registrar('anular', 'atenciones', Atencion::class, $atencion->id, ['estado' => Atencion::ESTADO_ACTIVO], ['estado' => Atencion::ESTADO_ANULADO]);

        return redirect()->route('atenciones.show', $atencion)
            ->with('exito', 'Atención anulada correctamente.');
    }

    public function destroy(Atencion $atencion): RedirectResponse
    {
        if ($atencion->esActivo()) {
            return back()->withErrors(['atencion' => 'Anula la atención antes de eliminarla.']);
        }

        AuditoriaService::registrar('eliminar', 'atenciones', Atencion::class, $atencion->id, ['total' => (string) $atencion->total], []);
        $atencion->detalles()->delete();
        $atencion->delete();

        return redirect()->route('atenciones.index')
            ->with('exito', 'Atención eliminada correctamente.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $detalles
     */
    private function sincronizarDetalles(Atencion $atencion, array $detalles): void
    {
        $total = Money::cero();

        foreach ($detalles as $linea) {
            // Cada línea es un servicio (del catálogo o manual) con su precio.
            $precio = (string) ($linea['precio_unitario'] ?? '0');
            $subtotal = Money::mul('1', $precio, Money::ESCALA_DINERO);

            $atencion->detalles()->create([
                'servicio_id' => $linea['servicio_id'] ?? null,
                'nombre' => $linea['nombre'] ?? null,
                'descripcion' => $linea['descripcion'] ?? null,
                'cantidad' => '1',
                'precio_unitario' => $precio,
                'subtotal' => $subtotal,
                'estilista_id' => $linea['estilista_id'] ?? $atencion->estilista_id,
            ]);

            $total = Money::add($total, $subtotal);
        }

        $atencion->update(['total' => $total]);
        $atencion->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'token' => ['nullable', 'string', 'max:64'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'cita_id' => ['nullable', 'exists:citas,id'],
            'estilista_id' => ['nullable', 'exists:estilistas,id'],
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'metodo_pago' => ['nullable', 'string', 'max:60'],
            'pagado' => ['nullable', 'numeric', 'min:0'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.servicio_id' => ['nullable', 'integer', 'exists:servicios,id'],
            'detalles.*.nombre' => ['nullable', 'string', 'max:255'],
            'detalles.*.descripcion' => ['nullable', 'string', 'max:255'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
        ], [
            'fecha.required' => 'Indica la fecha de la atención.',
            'fecha.date' => 'La fecha no es válida.',
            'detalles.required' => 'Agrega al menos un servicio realizado.',
            'detalles.min' => 'Agrega al menos un servicio realizado.',
            'detalles.*.precio_unitario.required' => 'Indica el precio del servicio.',
            'detalles.*.precio_unitario.min' => 'El precio no puede ser negativo.',
        ]);

        $hayServicioODatos = collect($datos['detalles'])->contains(
            fn ($l) => ! empty($l['servicio_id']) || filled($l['nombre'] ?? null) || filled($l['descripcion'] ?? null)
        );

        if (! $hayServicioODatos) {
            throw ValidationException::withMessages([
                'detalles' => 'Cada línea debe tener un servicio, un nombre o una descripción.',
            ]);
        }

        return $datos;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function formulario(array $extra = []): array
    {
        return array_merge([
            'servicios' => Servicio::where('activo', true)->orderBy('nombre')->get(),
            'estilistas' => Estilista::where('activo', true)->orderBy('nombre')->get(),
            'atencion' => null,
            'lineas' => [],
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
