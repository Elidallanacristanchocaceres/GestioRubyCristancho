<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CategoriaGasto;
use App\Models\Compra;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\CompraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompraController extends Controller
{
    public function __construct(private readonly CompraService $compras) {}

    public function index(Request $request): JsonResponse
    {
        $compras = Compra::with(['proveedor', 'creador'])
            ->withCount('detalles')
            ->when($request->filled('proveedor'), fn ($q) => $q->where('proveedor_id', $request->integer('proveedor')))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha_compra', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha_compra', '<=', $request->date('hasta')))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->str('estado')))
            ->orderByDesc('fecha_compra')
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json([
            'data' => $compras->getCollection()->map(fn ($c) => $this->resumen($c)),
            'meta' => [
                'current_page' => $compras->currentPage(),
                'last_page' => $compras->lastPage(),
                'total' => $compras->total(),
            ],
            'proveedores' => Proveedor::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function opciones(): JsonResponse
    {
        return response()->json([
            'proveedores' => Proveedor::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'productos' => Producto::where('activo', true)->with('categoria')->orderBy('nombre')->get()->map(fn ($p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'unidad_control' => $p->unidad_control,
                'unidad_abrev' => $p->unidadAbrev(),
                'unidad_label' => $p->unidadLabel(),
                'contenido_envase' => $p->contenido_envase !== null ? (string) $p->contenido_envase : null,
                'precio_costo' => $p->precio_costo !== null ? (string) $p->precio_costo : null,
                'precio_venta' => $p->precio_venta !== null ? (string) $p->precio_venta : null,
                'stock' => (string) $p->stock,
                'categoria_nombre' => $p->categoria?->nombre,
            ])->values(),
            'metodos' => MetodoPago::orderBy('nombre')->get(['id', 'nombre']),
            'categorias_gasto' => CategoriaGasto::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'numero_factura' => ['nullable', 'string', 'max:80'],
            'fecha_compra' => ['required', 'date'],
            'fecha_recepcion' => ['nullable', 'date'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string'],
            'categoria_gasto_id' => ['nullable', 'exists:categoria_gastos,id'],
            'metodo_pago_id' => ['nullable', 'exists:metodo_pagos,id'],
            'monto_pagado' => ['nullable', 'numeric', 'min:0'],
            'registrar_gasto' => ['boolean'],
            'afectar_caja' => ['boolean'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'exists:productos,id'],
            'detalles.*.cantidad_envases' => ['required', 'numeric', 'min:0.001'],
            'detalles.*.contenido_envase' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'detalles.*.descuento' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.cantidad' => ['nullable', 'numeric', 'min:0'],
        ], [], ['proveedor_id' => 'proveedor', 'detalles' => 'productos']);

        $datos['registrar_gasto'] = $request->boolean('registrar_gasto', true);
        $datos['afectar_caja'] = $request->boolean('afectar_caja', true);

        $compra = $this->compras->registrar($datos);
        $compra->load(['proveedor', 'detalles.producto', 'pagos.metodoPago', 'gasto', 'creador']);

        return response()->json(['data' => $this->detalle($compra)], 201);
    }

    public function show(Compra $compra): JsonResponse
    {
        $compra->load(['proveedor', 'detalles.producto', 'pagos.metodoPago', 'gasto', 'creador']);

        return response()->json([
            'data' => $this->detalle($compra),
            'metodos' => MetodoPago::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function registrarPago(Request $request, Compra $compra): JsonResponse
    {
        $datos = $request->validate([
            'metodo_pago_id' => ['required', 'exists:metodo_pagos,id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'referencia' => ['nullable', 'string', 'max:120'],
            'afectar_caja' => ['boolean'],
        ]);

        $compra = $this->compras->registrarPago($compra, $datos, $datos['afectar_caja'] ?? true);
        $compra->load(['proveedor', 'detalles.producto', 'pagos.metodoPago', 'gasto', 'creador']);

        return response()->json(['data' => $this->detalle($compra)]);
    }

    private function resumen(Compra $c): array
    {
        return [
            'id' => $c->id,
            'numero_factura' => $c->numero_factura,
            'proveedor_nombre' => $c->proveedor?->nombre,
            'proveedor_id' => $c->proveedor_id,
            'fecha_compra' => $c->fecha_compra?->toDateString(),
            'fecha_recepcion' => $c->fecha_recepcion?->toDateString(),
            'total' => (string) $c->total,
            'total_pagado' => (string) $c->total_pagado,
            'saldo_pendiente' => (string) $c->saldo_pendiente,
            'subtotal' => (string) $c->subtotal,
            'descuento' => (string) $c->descuento,
            'estado' => $c->estado,
            'detalles_count' => (int) ($c->detalles_count ?? $c->detalles->count()),
        ];
    }

    private function detalle(Compra $c): array
    {
        $base = $this->resumen($c);

        return $base + [
            'notas' => $c->notas,
            'gasto_id' => $c->gasto_id,
            'creador_nombre' => $c->creador?->name,
            'detalles' => $c->detalles->map(fn ($d) => [
                'id' => $d->id,
                'producto_id' => $d->producto_id,
                'producto_nombre' => $d->producto?->nombre,
                'unidad' => $d->unidad,
                'unidad_abrev' => $d->producto?->unidadAbrev(),
                'cantidad' => (string) $d->cantidad,
                'cantidad_envases' => (string) $d->cantidad_envases,
                'contenido_envase' => (string) $d->contenido_envase,
                'precio_unitario' => (string) $d->precio_unitario,
                'descuento' => (string) $d->descuento,
                'subtotal' => (string) $d->subtotal,
            ])->values(),
            'pagos' => $c->pagos->map(fn ($p) => [
                'id' => $p->id,
                'metodo_pago_id' => $p->metodo_pago_id,
                'metodo_nombre' => $p->metodoPago?->nombre,
                'fecha_hora' => $p->fecha_hora?->toIso8601String(),
                'monto' => (string) $p->monto,
                'referencia' => $p->referencia,
            ])->values(),
        ];
    }
}
