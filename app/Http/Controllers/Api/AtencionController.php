<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Atencion;
use App\Models\Cliente;
use App\Models\Estilista;
use App\Models\Servicio;
use App\Services\AuditoriaService;
use App\Services\CierreDiarioService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AtencionController extends Controller
{
    public function __construct(private readonly CierreDiarioService $cierres) {}

    public function index(Request $request): JsonResponse
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
            ->paginate(15);

        $resumen = (clone $base)
            ->where('estado', Atencion::ESTADO_ACTIVO)
            ->selectRaw('COALESCE(SUM(total), 0) AS total, COALESCE(SUM(pagado), 0) AS pagado, COUNT(*) AS n')
            ->first();

        return response()->json([
            'data' => $atenciones->getCollection()->map(fn ($a) => $this->resumen($a)),
            'meta' => [
                'current_page' => $atenciones->currentPage(),
                'last_page' => $atenciones->lastPage(),
                'total' => $atenciones->total(),
            ],
            'desde' => $desde,
            'hasta' => $hasta,
            'periodo' => $request->str('periodo', 'dia')->value(),
            'total_facturado' => Money::normal($resumen->total ?? 0),
            'total_pagado' => Money::normal($resumen->pagado ?? 0),
            'total_saldo' => Money::sub($resumen->total ?? 0, $resumen->pagado ?? 0),
            'total_registros' => (int) ($resumen->n ?? 0),
            'hay_registros_totales' => Atencion::where('estado', Atencion::ESTADO_ACTIVO)->exists(),
            'clientes' => Cliente::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'servicios' => Servicio::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'estilistas' => Estilista::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'filtros' => $filtros,
        ]);
    }

    public function opciones(): JsonResponse
    {
        return response()->json([
            'servicios' => Servicio::where('activo', true)->orderBy('nombre')->get()->map(fn ($s) => [
                'id' => $s->id,
                'nombre' => $s->nombre,
                'precio' => (string) $s->precio,
            ])->values(),
            'estilistas' => Estilista::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'clientes' => Cliente::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);
        $this->cierres->assertDiaAbierto($datos['fecha']);

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

        $atencion->load(['detalles.servicio', 'detalles.estilista', 'cliente', 'estilista', 'usuario', 'cita', 'venta']);

        return response()->json(['data' => $this->detalle($atencion)], 201);
    }

    public function show(Atencion $atencion): JsonResponse
    {
        $atencion->load(['detalles.servicio', 'detalles.estilista', 'cliente', 'estilista', 'usuario', 'cita', 'venta']);

        return response()->json([
            'data' => $this->detalle($atencion),
            'saldo' => $atencion->saldo(),
        ]);
    }

    public function update(Request $request, Atencion $atencion): JsonResponse
    {
        abort_unless($atencion->esActivo(), 403, 'No se puede editar una atención anulada.');

        $datos = $this->validar($request);

        $this->cierres->assertDiaAbierto($atencion->fecha->toDateString());
        $this->cierres->assertDiaAbierto($datos['fecha']);

        $antes = $atencion->only(['cliente_id', 'total', 'pagado', 'descripcion']);
        $atencion->fill([
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

        $atencion->load(['detalles.servicio', 'detalles.estilista', 'cliente', 'estilista', 'usuario', 'cita', 'venta']);

        return response()->json(['data' => $this->detalle($atencion)]);
    }

    public function anular(Request $request, Atencion $atencion): JsonResponse
    {
        abort_unless($atencion->esActivo(), 403, 'Esta atención ya fue anulada.');

        $this->cierres->assertDiaAbierto($atencion->fecha->toDateString());

        $atencion->update([
            'estado' => Atencion::ESTADO_ANULADO,
            'reversado_por' => $request->user()->id,
            'reversado_en' => now(),
        ]);

        AuditoriaService::registrar('anular', 'atenciones', Atencion::class, $atencion->id, ['estado' => Atencion::ESTADO_ACTIVO], ['estado' => Atencion::ESTADO_ANULADO]);

        $atencion->load(['detalles.servicio', 'detalles.estilista', 'cliente', 'estilista', 'usuario', 'cita', 'venta']);

        return response()->json(['data' => $this->detalle($atencion)]);
    }

    public function destroy(Atencion $atencion): JsonResponse
    {
        if ($atencion->esActivo()) {
            throw ValidationException::withMessages(['atencion' => 'Anula la atención antes de eliminarla.']);
        }

        $this->cierres->assertDiaAbierto($atencion->fecha->toDateString());

        AuditoriaService::registrar('eliminar', 'atenciones', Atencion::class, $atencion->id, ['total' => (string) $atencion->total], []);
        $atencion->detalles()->delete();
        $atencion->delete();

        return response()->json(['message' => 'Atención eliminada correctamente.']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $detalles
     */
    private function sincronizarDetalles(Atencion $atencion, array $detalles): void
    {
        $total = Money::cero();

        foreach ($detalles as $linea) {
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

    private function resumen(Atencion $a): array
    {
        $saldo = $a->saldo();
        $serviciosLabel = $a->detalles->map(fn ($d) => $d->etiqueta())->take(3)->implode(' · ')
            ?: ($a->descripcion ?? '—');

        return [
            'id' => $a->id,
            'fecha' => $a->fecha?->toDateString(),
            'cliente_nombre' => $a->cliente?->nombre,
            'cliente_id' => $a->cliente_id,
            'estilista_nombre' => $a->estilista?->nombre,
            'estilista_id' => $a->estilista_id,
            'total' => (string) $a->total,
            'pagado' => (string) $a->pagado,
            'saldo' => $saldo,
            'estado' => $a->estado,
            'estado_label' => $a->estadoLabel(),
            'servicios_label' => $serviciosLabel,
            'detalles_count' => $a->detalles->count(),
        ];
    }

    private function detalle(Atencion $a): array
    {
        $a->loadMissing(['detalles.servicio', 'detalles.estilista', 'cliente', 'estilista', 'usuario', 'cita', 'venta']);

        $base = $this->resumen($a);

        return $base + [
            'token' => $a->token,
            'cita_id' => $a->cita_id,
            'venta_id' => $a->venta_id,
            'descripcion' => $a->descripcion,
            'observaciones' => $a->observaciones,
            'metodo_pago' => $a->metodo_pago,
            'creador_nombre' => $a->usuario?->name,
            'detalles' => $a->detalles->map(fn ($d) => [
                'id' => $d->id,
                'servicio_id' => $d->servicio_id,
                'nombre' => $d->nombre,
                'descripcion' => $d->descripcion,
                'precio_unitario' => (string) $d->precio_unitario,
                'subtotal' => (string) $d->subtotal,
                'etiqueta' => $d->etiqueta(),
                'es_manual' => $d->servicio_id === null,
                'estilista_id' => $d->estilista_id,
            ])->values(),
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
