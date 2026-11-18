<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Services\VentaService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Ventas de productos (potes, unidades, bolsas) separadas del consumo en
 * servicios. Reutiliza VentaService: descuenta inventario una sola vez y
 * conserva el costo de adquisición en la línea de venta.
 */
class ProductoVendidoController extends Controller
{
    public function __construct(private readonly VentaService $ventas) {}

    public function index(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->rango($request);

        $base = VentaDetalle::with(['venta.cliente', 'producto'])
            ->where('tipo', 'producto')
            ->whereHas('venta', fn ($q) => $q
                ->whereBetween('fecha', [$desde, $hasta])
                ->whereNotIn('estado', ['anulada']))
            ->when($request->filled('producto'), fn ($q) => $q->where('producto_id', $request->integer('producto')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $texto = $request->str('q');
                $q->where(function ($sub) use ($texto) {
                    $sub->where('descripcion', 'like', '%'.$texto.'%')
                        ->orWhereHas('producto', fn ($p) => $p->where('nombre', 'like', '%'.$texto.'%'))
                        ->orWhereHas('venta', fn ($v) => $v->where('numero', 'like', '%'.$texto.'%'));
                });
            });

        $detalles = (clone $base)
            ->orderByDesc('id')
            ->paginate(20);

        $items = collect($detalles->items())->map(fn (VentaDetalle $d) => $this->linea($d))->values();

        $total = Money::cero();
        $costo = Money::cero();
        $unidades = Money::cero(Money::ESCALA_CANTIDAD);

        foreach ($items as $item) {
            $total = Money::add($total, $item['total']);
            $costo = Money::add($costo, $item['costo_total']);
            $unidades = Money::add($unidades, $item['cantidad'], Money::ESCALA_CANTIDAD);
        }

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $detalles->currentPage(),
                'last_page' => $detalles->lastPage(),
                'total' => $detalles->total(),
            ],
            'desde' => $desde,
            'hasta' => $hasta,
            'total' => $total,
            'total_costo' => $costo,
            'cantidad' => $unidades,
            'productos' => Producto::where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
            'opciones' => $this->opcionesData(),
        ]);
    }

    public function opciones(): JsonResponse
    {
        return response()->json($this->opcionesData());
    }

    /**
     * @return array<string, mixed>
     */
    private function opcionesData(): array
    {
        return [
            'clientes' => Cliente::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'productos' => Producto::with('categoria')
                ->where('activo', true)
                ->orderBy('nombre')
                ->get()
                ->map(fn (Producto $p) => [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'marca' => $p->marca,
                    'categoria_nombre' => $p->categoria?->nombre,
                    'presentacion' => $p->presentacionLabel(),
                    'contenido_envase' => $p->contenido_envase !== null ? (string) $p->contenido_envase : null,
                    'unidad_control' => $p->unidad_control,
                    'unidad_abrev' => $p->unidadAbrev(),
                    'precio_venta' => $p->precio_venta !== null ? (string) $p->precio_venta : null,
                    'stock' => (string) $p->stock,
                    'costo_unitario' => $p->costo_unitario !== null ? (string) $p->costo_unitario : null,
                ])->values(),
            'metodos' => MetodoPago::orderBy('nombre')->get(['id', 'nombre']),
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'notas' => ['nullable', 'string', 'max:1000'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'exists:productos,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'detalles.*.descuento' => ['nullable', 'numeric', 'min:0'],
            'pagar' => ['boolean'],
            'metodo_pago_id' => ['nullable', 'exists:metodo_pagos,id'],
        ]);

        $productos = Producto::whereIn('id', collect($datos['detalles'])->pluck('producto_id'))->get()->keyBy('id');

        foreach ($datos['detalles'] as $detalle) {
            $producto = $productos->get($detalle['producto_id']);
            if (! $producto) {
                return response()->json([
                    'message' => 'El producto seleccionado no existe.',
                ], 422);
            }
        }

        $venta = $this->ventas->crear([
            'cliente_id' => $datos['cliente_id'] ?? null,
            'fecha' => $datos['fecha'],
            'fecha_hora' => Carbon::parse($datos['fecha'])->setTimeFrom(now()),
            'descuento' => $datos['descuento'] ?? '0',
            'notas' => $datos['notas'] ?? null,
            'tipo' => 'producto',
            'detalles' => collect($datos['detalles'])->map(fn ($d) => [
                'tipo' => 'producto',
                'producto_id' => $d['producto_id'],
                'cantidad' => $d['cantidad'],
                'precio_unitario' => $d['precio_unitario'],
                'descuento' => $d['descuento'] ?? '0',
            ])->all(),
        ]);

        if ($request->boolean('pagar') && ! empty($datos['metodo_pago_id'])) {
            $this->ventas->registrarPagos($venta, [[
                'metodo_pago_id' => $datos['metodo_pago_id'],
                'monto' => (string) $venta->total,
            ]], false);
            $venta->refresh();
        }

        return response()->json([
            'message' => 'Producto vendido y descontado del inventario.',
            'data' => $this->detalleVenta($venta->load(['cliente', 'detalles.producto'])),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function linea(VentaDetalle $d): array
    {
        $venta = $d->venta;
        $subtotal = Money::normal((string) $d->subtotal);
        $descuentoAsignado = Money::cero();

        if ($venta && Money::mayorQue((string) $venta->descuento, '0')
            && Money::mayorQue((string) $venta->subtotal, '0')) {
            $descuentoAsignado = Money::div(
                Money::mul((string) $venta->descuento, $subtotal),
                (string) $venta->subtotal
            );
        }

        return [
            'id' => $d->id,
            'venta_id' => $d->venta_id,
            'numero' => $venta?->numero,
            'fecha' => $venta?->fecha?->toDateString(),
            'cliente_nombre' => $venta?->cliente?->nombre,
            'producto_id' => $d->producto_id,
            'producto_nombre' => $d->producto?->nombre ?? $d->descripcion,
            'presentacion' => $d->presentacion ?? $d->producto?->presentacionLabel(),
            'contenido' => $d->contenido_envase !== null
                ? (string) $d->contenido_envase
                : ($d->producto?->contenido_envase !== null ? (string) $d->producto->contenido_envase : null),
            'unidad' => $d->unidad,
            'cantidad' => (string) $d->cantidad,
            'precio_unitario' => (string) $d->precio_unitario,
            'descuento' => (string) $d->descuento,
            'descuento_asignado' => $descuentoAsignado,
            'subtotal' => $subtotal,
            'total' => Money::sub($subtotal, $descuentoAsignado),
            'costo_unitario' => (string) ($d->costo_unitario ?? '0'),
            'costo_total' => (string) ($d->costo_total ?? '0'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detalleVenta(Venta $venta): array
    {
        return [
            'id' => $venta->id,
            'numero' => $venta->numero,
            'fecha' => $venta->fecha?->toDateString(),
            'cliente_nombre' => $venta->cliente?->nombre,
            'estado' => $venta->estado,
            'total' => (string) $venta->total,
            'total_pagado' => (string) $venta->total_pagado,
            'saldo_pendiente' => (string) $venta->saldo_pendiente,
            'detalles' => $venta->detalles->map(fn (VentaDetalle $d) => [
                'id' => $d->id,
                'producto_nombre' => $d->producto?->nombre ?? $d->descripcion,
                'presentacion' => $d->presentacion ?? $d->producto?->presentacionLabel(),
                'cantidad' => (string) $d->cantidad,
                'unidad' => $d->unidad,
                'precio_unitario' => (string) $d->precio_unitario,
                'total' => (string) $d->subtotal,
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
