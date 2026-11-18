<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Estilista;
use App\Models\Servicio;
use App\Services\CitaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CitaController extends Controller
{
    public function __construct(private readonly CitaService $citas) {}

    public function index(Request $request): JsonResponse
    {
        $vista = $request->str('vista', 'dia')->toString();
        $fecha = $request->filled('fecha') ? Carbon::parse($request->date('fecha')) : Carbon::today();

        if (! in_array($vista, ['dia', 'semana', 'mes', 'lista'], true)) {
            $vista = 'dia';
        }

        $query = Cita::with(['cliente', 'estilista', 'servicio'])
            ->when($request->filled('estilista'), fn ($q) => $q->where('estilista_id', $request->integer('estilista')))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->str('estado')));

        if ($vista === 'dia') {
            $query->whereDate('fecha_hora', $fecha->toDateString());
        } elseif ($vista === 'semana') {
            $query->whereBetween('fecha_hora', [$fecha->copy()->startOfWeek(), $fecha->copy()->endOfWeek()]);
        } elseif ($vista === 'mes') {
            $query->whereBetween('fecha_hora', [$fecha->copy()->startOfMonth(), $fecha->copy()->endOfMonth()]);
        }

        $citas = $query->orderBy('fecha_hora')->paginate($vista === 'lista' ? 20 : 50);

        return response()->json([
            'data' => $citas->getCollection()->map(fn ($c) => $this->resumen($c)),
            'meta' => ['current_page' => $citas->currentPage(), 'last_page' => $citas->lastPage(), 'total' => $citas->total()],
            'estilistas' => Estilista::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $cita = $this->citas->crear($this->validar($request));

        return response()->json(['data' => $this->detalle($cita)], 201);
    }

    public function show(Cita $cita): JsonResponse
    {
        $cita->load(['cliente', 'estilista', 'servicio', 'venta.detalles', 'venta.pagos', 'consumos.producto', 'pagos.metodoPago']);

        $historial = Cita::where('cliente_id', $cita->cliente_id)
            ->where('id', '!=', $cita->id)
            ->with('servicio')
            ->orderByDesc('fecha_hora')
            ->limit(5)
            ->get()
            ->map(fn ($h) => $this->resumen($h));

        return response()->json([
            'data' => $this->detalle($cita),
            'historial' => $historial,
        ]);
    }

    public function update(Request $request, Cita $cita): JsonResponse
    {
        $cita = $this->citas->actualizar($cita, $this->validar($request));

        return response()->json(['data' => $this->detalle($cita)]);
    }

    public function destroy(Request $request, Cita $cita): JsonResponse
    {
        $datos = $request->validate([
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);
        $motivo = $datos['motivo'] ?? 'Cancelación de cita';
        $cita = $this->citas->cancelar($cita, $motivo);

        return response()->json(['message' => 'Cita cancelada correctamente.', 'data' => $this->detalle($cita)]);
    }

    public function cambiarEstado(Request $request, Cita $cita): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['required', 'in:pendiente,confirmada,en_progreso,completada,cancelada'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        if ($datos['estado'] === Cita::ESTADO_CANCELADA) {
            $cita = $this->citas->cancelar($cita, $datos['motivo'] ?? 'Cambio de estado');

            return response()->json(['data' => $this->detalle($cita)]);
        }

        $cita->update(['estado' => $datos['estado']]);

        return response()->json(['data' => $this->detalle($cita->fresh())]);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'cliente_id' => ['required', 'exists:clientes,id'],
            'estilista_id' => ['required', 'exists:estilistas,id'],
            'servicio_id' => ['required', 'exists:servicios,id'],
            'fecha_hora' => ['required', 'date'],
            'duracion_minutos' => ['required', 'integer', 'min:5', 'max:600'],
            'precio' => ['nullable', 'numeric', 'min:0'],
            'anticipo' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['required', 'in:pendiente,confirmada,en_progreso,completada,cancelada'],
            'notas' => ['nullable', 'string'],
        ], [], [
            'cliente_id' => 'cliente',
            'estilista_id' => 'profesional',
            'servicio_id' => 'servicio',
            'fecha_hora' => 'fecha y hora',
        ]);
    }

    private function resumen(Cita $c): array
    {
        return [
            'id' => $c->id,
            'fecha_hora' => $c->fecha_hora?->toIso8601String(),
            'cliente_nombre' => $c->cliente?->nombre,
            'estilista_nombre' => $c->estilista?->nombre,
            'servicio_nombre' => $c->servicio?->nombre,
            'duracion_minutos' => (int) $c->duracion_minutos,
            'precio' => (string) $c->precio,
            'anticipo' => (string) ($c->anticipo ?? '0'),
            'saldo_pendiente' => (string) ($c->saldo_pendiente ?? '0'),
            'estado' => $c->estado,
            'estado_label' => $c->estadoLabel(),
        ];
    }

    private function detalle(Cita $c): array
    {
        $c->loadMissing(['cliente', 'estilista', 'servicio', 'venta', 'consumos.producto', 'pagos.metodoPago']);

        return $this->resumen($c) + [
            'cliente_id' => $c->cliente_id,
            'estilista_id' => $c->estilista_id,
            'servicio_id' => $c->servicio_id,
            'venta_id' => $c->venta_id,
            'notas' => $c->notas,
            'venta' => $c->venta ? [
                'id' => $c->venta->id,
                'numero' => $c->venta->numero,
            ] : null,
            'consumos' => $c->consumos->map(fn ($co) => [
                'producto_nombre' => $co->producto?->nombre,
                'cantidad' => (string) $co->cantidad,
                'costo_total' => (string) $co->costo_total,
                'estado' => $co->estado,
            ])->values(),
        ];
    }
}
