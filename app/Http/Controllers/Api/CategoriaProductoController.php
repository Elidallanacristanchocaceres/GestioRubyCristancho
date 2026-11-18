<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CategoriaProducto;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoriaProductoController extends Controller
{
    public function index(): JsonResponse
    {
        $categorias = CategoriaProducto::withCount('productos')->orderBy('nombre')->paginate(15);

        return response()->json([
            'data' => $categorias->getCollection()->map(fn ($c) => $this->resumen($c)),
            'meta' => ['current_page' => $categorias->currentPage(), 'last_page' => $categorias->lastPage(), 'total' => $categorias->total()],
            'grupos' => CategoriaProducto::GRUPOS,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);

        $existente = CategoriaProducto::withTrashed()->where('nombre', $datos['nombre'])->first();
        if ($existente) {
            if ($existente->trashed()) {
                $existente->restore();
                $existente->update($datos);
                AuditoriaService::registrar('actualizar', 'categoria_productos', CategoriaProducto::class, $existente->id, [], $existente->only(['nombre', 'grupo']));

                return response()->json(['data' => $this->resumen($existente)], 201);
            }

            return response()->json(['message' => 'Ya existe una categoría con ese nombre.', 'errors' => ['nombre' => ['Ya existe una categoría con ese nombre.']]], 422);
        }

        $categoria = CategoriaProducto::create($datos);
        AuditoriaService::registrar('crear', 'categoria_productos', CategoriaProducto::class, $categoria->id, [], ['nombre' => $categoria->nombre]);

        return response()->json(['data' => $this->resumen($categoria)], 201);
    }

    public function update(Request $request, CategoriaProducto $categoriaProducto): JsonResponse
    {
        $datos = $this->validar($request, $categoriaProducto->id);
        $antes = $categoriaProducto->only(['nombre', 'grupo']);
        $categoriaProducto->update($datos);
        AuditoriaService::registrar('actualizar', 'categoria_productos', CategoriaProducto::class, $categoriaProducto->id, $antes, $categoriaProducto->only(array_keys($antes)));

        return response()->json(['data' => $this->resumen($categoriaProducto)]);
    }

    public function destroy(CategoriaProducto $categoriaProducto): JsonResponse
    {
        if ($categoriaProducto->productos()->exists()) {
            return response()->json(['message' => 'No se puede eliminar: la categoría tiene productos.'], 422);
        }

        $categoriaProducto->delete();

        return response()->json(['message' => 'Categoría eliminada.']);
    }

    private function validar(Request $request, ?int $ignorarId = null): array
    {
        $reglaUnica = Rule::unique('categoria_productos', 'nombre')->whereNull('deleted_at');
        if ($ignorarId) {
            $reglaUnica->ignore($ignorarId);
        }

        return $request->validate([
            'nombre' => ['required', 'string', 'max:100', $reglaUnica],
            'grupo' => ['nullable', 'string', 'in:'.implode(',', array_keys(CategoriaProducto::GRUPOS))],
        ], [
            'nombre.unique' => 'Ya existe una categoría con ese nombre.',
            'nombre.required' => 'El nombre es obligatorio.',
        ]);
    }

    private function resumen(CategoriaProducto $c): array
    {
        return [
            'id' => $c->id,
            'nombre' => $c->nombre,
            'grupo' => $c->grupo,
            'grupo_label' => $c->grupoLabel(),
            'productos_count' => $c->productos_count ?? $c->productos()->count(),
        ];
    }
}
