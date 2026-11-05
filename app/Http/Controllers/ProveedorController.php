<?php

namespace App\Http\Controllers;

use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Compra;
use App\Services\CompraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProveedorController extends Controller
{
    public function index(Request $request): View
    {
        $proveedores = Proveedor::withCount('compras')
            ->when($request->filled('q'), fn ($q) => $q->where('nombre', 'like', '%'.$request->str('q').'%')->orWhere('nit', 'like', '%'.$request->str('q').'%'))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('proveedores.index', compact('proveedores'));
    }

    public function create(): View
    {
        return view('proveedores.create', ['proveedor' => new Proveedor(['activo' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $proveedor = Proveedor::create($this->validar($request));

        return redirect()->route('proveedores.show', $proveedor)->with('exito', 'Proveedor registrado correctamente.');
    }

    public function show(Proveedor $proveedor): View
    {
        $proveedor->load(['productos', 'compras' => fn ($q) => $q->orderByDesc('fecha_compra')]);

        $compras = $proveedor->compras()->withCount('detalles')->orderByDesc('fecha_compra')->paginate(12);
        $totalComprado = $proveedor->compras()->sum('total');
        $saldoPendiente = $proveedor->compras()->sum('saldo_pendiente');

        return view('proveedores.show', compact('proveedor', 'compras', 'totalComprado', 'saldoPendiente'));
    }

    public function edit(Proveedor $proveedor): View
    {
        return view('proveedores.edit', compact('proveedor'));
    }

    public function update(Request $request, Proveedor $proveedor): RedirectResponse
    {
        $proveedor->update($this->validar($request));

        return redirect()->route('proveedores.show', $proveedor)->with('exito', 'Proveedor actualizado correctamente.');
    }

    public function destroy(Proveedor $proveedor): RedirectResponse
    {
        if ($proveedor->compras()->exists()) {
            $proveedor->update(['activo' => false]);

            return redirect()->route('proveedores.index')->with('exito', 'El proveedor tiene compras; se desactivó en lugar de eliminarlo.');
        }

        $proveedor->delete();

        return redirect()->route('proveedores.index')->with('exito', 'Proveedor eliminado correctamente.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:200'],
            'nit' => ['nullable', 'string', 'max:30'],
            'contacto' => ['nullable', 'string', 'max:200'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:300'],
            'observaciones' => ['nullable', 'string'],
        ]) + ['activo' => $request->boolean('activo', true)];
    }
}
