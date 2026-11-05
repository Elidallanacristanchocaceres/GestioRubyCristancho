<?php

namespace App\Http\Controllers;

use App\Models\CategoriaProducto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoriaProductoController extends Controller
{
    public function index(): View
    {
        return view('categoria-productos.index', [
            'categorias' => CategoriaProducto::withCount('productos')->orderBy('nombre')->paginate(15),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:300'],
        ]);

        CategoriaProducto::create($datos);

        return back()->with('exito', 'Categoría creada correctamente.');
    }

    public function update(Request $request, CategoriaProducto $categoriaProducto): RedirectResponse
    {
        $categoriaProducto->update($request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:300'],
        ]));

        return back()->with('exito', 'Categoría actualizada.');
    }

    public function destroy(CategoriaProducto $categoriaProducto): RedirectResponse
    {
        if ($categoriaProducto->productos()->exists()) {
            return back()->with('error', 'No se puede eliminar: la categoría tiene productos.');
        }

        $categoriaProducto->delete();

        return back()->with('exito', 'Categoría eliminada.');
    }
}
