<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Estilista;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Servicio;
use App\Models\Venta;
use App\Services\VentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VentaController extends Controller
{
    public function __construct(private readonly VentaService $ventas) {}

    public function index(Request $request): JsonResponse
    {
        $ventas = Venta::with(['cliente', 'detalles'])
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha', '<=', $request->date('hasta')))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->str('estado')))
            ->when($request->filled('q'), fn ($q) => $q->where('numero', 'like', '%'.$request->str('q').'%'))
            ->orderByDesc('fecha_hora')
            ->paginate(15);

        return response()->json([
            'data' => $ventas->getCollection()->map(fn ($v) => $this->resumen($v)),
            'meta' => [
                'current_page' => $ventas->currentPage(),
                'last_page' => $ventas->lastPage(),
                'total' => $ventas->total(),
            ],
        ]);
    }

    public function opciones(): JsonResponse
    {
        return response()->json([
            'clientes' => Cliente::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'estilistas' => Estilista::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'servicios' => Servicio::where('activo', true)->with('productos.producto')->orderBy('nombre')->get()->map(fn ($s) => [
                'id' => $s->id, 'nombre' => $s->nombre, 'precio' => (string) $s->precio,
                'productos' => $s->productos->map(fn ($p) => [
                    'id' => $p->producto_id, 'nombre' => $p->producto?->nombre,
                    'cantidad' => (string) $p->cantidad_sugerida,
                    'unidad' => $p->producto?->unidadAbrev(), 'stock' => (string) $p->producto?->stock,
                ])->values(),
            ]),
            'productos' => Producto::where('activo', true)
                ->orderBy('nombre')->get()
                ->map(fn (Producto $p) => [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'precio_venta' => $p->precio_venta !== null ? (string) $p->precio_venta : null,
                    'stock' => (string) $p->stock,
                    'unidad_control' => $p->unidad_control,
                    'presentacion' => $p->presentacionLabel(),
                    'contenido_envase' => $p->contenido_envase !== null ? (string) $p->contenido_envase : null,
                    'unidad_abrev' => $p->unidadAbrev(),
                ])->values(),
            'metodos' => MetodoPago::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'cita_id' => ['nullable', 'exists:citas,id'],
            'estilista_id' => ['nullable', 'exists:estilistas,id'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.tipo' => ['required', 'in:servicio,producto'],
            'detalles.*.servicio_id' => ['nullable', 'exists:servicios,id'],
            'detalles.*.producto_id' => ['nullable', 'exists:productos,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.001'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'detalles.*.descuento' => ['nullable', 'numeric', 'min:0'],
            'pagos' => ['nullable', 'array'],
            'pagos.*.metodo_pago_id' => ['required_with:pagos', 'exists:metodo_pagos,id'],
            'pagos.*.monto' => ['required_with:pagos', 'numeric', 'min:0'],
        ]);

        $detalles = $datos['detalles'];
        $venta = $this->ventas->crear([
            'cliente_id' => $datos['cliente_id'] ?? null,
            'cita_id' => $datos['cita_id'] ?? null,
            'estilista_id' => $datos['estilista_id'] ?? null,
            'descuento' => $datos['descuento'] ?? '0',
            'notas' => $datos['notas'] ?? null,
            'detalles' => $detalles,
        ]);

        $pagos = array_values(array_filter($datos['pagos'] ?? [], fn ($p) => (float) ($p['monto'] ?? 0) > 0));
        if ($pagos) {
            $this->ventas->registrarPagos($venta, $pagos, false);
            $venta->refresh();
        }

        return response()->json(['data' => $this->detalle($venta)], 201);
    }

    public function show(Venta $venta): JsonResponse
    {
        $venta->load(['cliente', 'cita', 'estilista', 'detalles.servicio', 'detalles.producto', 'pagos.metodoPago', 'consumos.producto']);

        return response()->json([
            'data' => $this->detalle($venta),
            'metodos' => MetodoPago::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function registrarPago(Request $request, Venta $venta): JsonResponse
    {
        $datos = $request->validate([
            'pagos' => ['required', 'array', 'min:1'],
            'pagos.*.metodo_pago_id' => ['required', 'exists:metodo_pagos,id'],
            'pagos.*.monto' => ['required', 'numeric', 'min:0.01'],
            'pagos.*.referencia' => ['nullable', 'string', 'max:120'],
        ]);

        $venta = $this->ventas->registrarPagos($venta, $datos['pagos'], true);
        $venta->load(['cliente', 'estilista', 'detalles', 'pagos.metodoPago', 'consumos.producto']);

        return response()->json(['data' => $this->detalle($venta)]);
    }

    public function anular(Request $request, Venta $venta): JsonResponse
    {
        $datos = $request->validate(['motivo' => ['required', 'string', 'max:300']]);
        $venta = $this->ventas->anular($venta, $datos['motivo']);
        $venta->load(['cliente', 'estilista', 'detalles', 'pagos.metodoPago', 'consumos.producto']);

        return response()->json(['data' => $this->detalle($venta)]);
    }

    private function resumen(Venta $v): array
    {
        return [
            'id' => $v->id, 'numero' => $v->numero,
            'fecha_hora' => $v->fecha_hora?->toIso8601String(),
            'cliente_nombre' => $v->cliente?->nombre,
            'total' => (string) $v->total, 'total_pagado' => (string) $v->total_pagado,
            'saldo_pendiente' => (string) $v->saldo_pendiente, 'estado' => $v->estado,
        ];
    }

    private function detalle(Venta $venta): array
    {
        return [
            'id' => $venta->id, 'numero' => $venta->numero,
            'fecha_hora' => $venta->fecha_hora?->toIso8601String(),
            'estado' => $venta->estado, 'tipo' => $venta->tipo,
            'subtotal' => (string) $venta->subtotal, 'descuento' => (string) $venta->descuento,
            'total' => (string) $venta->total, 'total_pagado' => (string) $venta->total_pagado,
            'saldo_pendiente' => (string) $venta->saldo_pendiente, 'notas' => $venta->notas,
            'cliente_nombre' => $venta->cliente?->nombre, 'estilista_nombre' => $venta->estilista?->nombre,
            'detalles' => $venta->detalles->map(fn ($d) => [
                'descripcion' => $d->descripcion, 'tipo' => $d->tipo,
                'cantidad' => (string) $d->cantidad, 'precio_unitario' => (string) $d->precio_unitario,
                'descuento' => (string) $d->descuento, 'subtotal' => (string) $d->subtotal,
            ])->values(),
            'pagos' => $venta->pagos->map(fn ($p) => [
                'metodo_nombre' => $p->metodoPago?->nombre,
                'fecha_hora' => $p->fecha_hora?->toIso8601String(), 'monto' => (string) $p->monto,
            ])->values(),
            'consumos' => $venta->consumos->map(fn ($c) => [
                'producto_nombre' => $c->producto?->nombre, 'cantidad' => (string) $c->cantidad,
                'costo_total' => (string) $c->costo_total, 'estado' => $c->estado,
            ])->values(),
        ];
    }
}
