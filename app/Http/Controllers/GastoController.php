<?php

namespace App\Http\Controllers;

use App\Models\CategoriaGasto;
use App\Models\Gasto;
use App\Models\MetodoPago;
use App\Models\Proveedor;
use App\Services\GastoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GastoController extends Controller
{
    public function __construct(private readonly GastoService $gastos) {}

    public function index(Request $request): View
    {
        $gastos = Gasto::with(['categoria', 'metodoPago', 'proveedor', 'creador'])
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha', '<=', $request->date('hasta')))
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria_gasto_id', $request->integer('categoria')))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->str('estado')))
            ->when($request->filled('q'), fn ($q) => $q->where('descripcion', 'like', '%'.$request->str('q').'%'))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $total = (clone $gastos)->getCollection()->sum('monto');

        return view('gastos.index', [
            'gastos' => $gastos,
            'categorias' => CategoriaGasto::orderBy('nombre')->get(),
            'totalPagina' => $total,
        ]);
    }

    public function create(): View
    {
        return view('gastos.create', $this->opciones());
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);
        $this->gastos->registrar($datos);

        return redirect()->route('gastos.index')->with('exito', 'Gasto registrado correctamente.');
    }

    public function show(Gasto $gasto): View
    {
        $gasto->load(['categoria', 'metodoPago', 'proveedor', 'creador', 'compra']);

        return view('gastos.show', compact('gasto'));
    }

    public function edit(Gasto $gasto): View
    {
        return view('gastos.edit', array_merge($this->opciones(), compact('gasto')));
    }

    public function update(Request $request, Gasto $gasto): RedirectResponse
    {
        $this->gastos->actualizar($gasto, $this->validar($request));

        return redirect()->route('gastos.index')->with('exito', 'Gasto actualizado correctamente.');
    }

    public function destroy(Request $request, Gasto $gasto): RedirectResponse
    {
        $this->gastos->eliminar($gasto, $request->str('motivo')->toString() ?: null);

        return redirect()->route('gastos.index')->with('exito', 'Gasto eliminado correctamente.');
    }

    private function opciones(): array
    {
        return [
            'categorias' => CategoriaGasto::where('activo', true)->orderBy('nombre')->get(),
            'metodos' => MetodoPago::orderBy('nombre')->get(),
            'proveedores' => Proveedor::where('activo', true)->orderBy('nombre')->get(),
        ];
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01'],
            'categoria_gasto_id' => ['nullable', 'exists:categoria_gastos,id'],
            'descripcion' => ['required', 'string', 'max:500'],
            'fecha' => ['required', 'date'],
            'metodo_pago_id' => ['nullable', 'exists:metodo_pagos,id'],
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'estado' => ['required', 'in:pagado,pendiente'],
            'comprobante' => ['nullable', 'string', 'max:300'],
            'afectar_caja' => ['boolean'],
        ], [], ['monto' => 'valor', 'categoria_gasto_id' => 'categoría', 'metodo_pago_id' => 'medio de pago'])
            + ['afectar_caja' => $request->boolean('afectar_caja', true)];
    }
}
