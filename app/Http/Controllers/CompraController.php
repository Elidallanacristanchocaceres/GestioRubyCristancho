<?php

namespace App\Http\Controllers;

use App\Models\CategoriaGasto;
use App\Models\Compra;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\CompraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompraController extends Controller
{
    public function __construct(private readonly CompraService $compras) {}

    public function index(Request $request): View
    {
        $compras = Compra::with(['proveedor', 'creador'])
            ->withCount('detalles')
            ->when($request->filled('proveedor'), fn ($q) => $q->where('proveedor_id', $request->integer('proveedor')))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha_compra', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha_compra', '<=', $request->date('hasta')))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->str('estado')))
            ->orderByDesc('fecha_compra')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('compras.index', [
            'compras' => $compras,
            'proveedores' => Proveedor::orderBy('nombre')->get(),
        ]);
    }

    public function create(): View
    {
        return view('compras.create', [
            'proveedores' => Proveedor::where('activo', true)->orderBy('nombre')->get(),
            'productos' => Producto::where('activo', true)->with('categoria')->orderBy('nombre')->get(),
            'metodos' => MetodoPago::orderBy('nombre')->get(),
            'categoriasGasto' => CategoriaGasto::orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
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

        return redirect()->route('compras.show', $compra)->with('exito', 'Compra registrada y existencias actualizadas.');
    }

    public function show(Compra $compra): View
    {
        $compra->load(['proveedor', 'detalles.producto', 'pagos.metodoPago', 'gasto', 'creador']);

        return view('compras.show', [
            'compra' => $compra,
            'metodos' => MetodoPago::orderBy('nombre')->get(),
        ]);
    }

    public function registrarPago(Request $request, Compra $compra): RedirectResponse
    {
        $datos = $request->validate([
            'metodo_pago_id' => ['required', 'exists:metodo_pagos,id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'referencia' => ['nullable', 'string', 'max:120'],
            'afectar_caja' => ['boolean'],
        ]);

        $this->compras->registrarPago($compra, $datos, $datos['afectar_caja'] ?? true);

        return back()->with('exito', 'Pago a proveedor registrado.');
    }
}
