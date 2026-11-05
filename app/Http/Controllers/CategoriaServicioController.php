<?php

namespace App\Http\Controllers;

use App\Models\CategoriaServicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoriaServicioController extends Controller
{
    public function index(): View
    {
        return view('categoria-servicios.index', [
            'categorias' => CategoriaServicio::withCount('servicios')->orderBy('nombre')->paginate(15),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        CategoriaServicio::create($request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:300'],
        ]));

        return back()->with('exito', 'Categoría creada correctamente.');
    }

    public function update(Request $request, CategoriaServicio $categoriaServicio): RedirectResponse
    {
        $categoriaServicio->update($request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:300'],
        ]));

        return back()->with('exito', 'Categoría actualizada.');
    }

    public function destroy(CategoriaServicio $categoriaServicio): RedirectResponse
    {
        if ($categoriaServicio->servicios()->exists()) {
            return back()->with('error', 'No se puede eliminar: la categoría tiene servicios.');
        }

        $categoriaServicio->delete();

        return back()->with('exito', 'Categoría eliminada.');
    }
}
