<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CategoriaServicio;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoriaServicioController extends Controller
{
    public function index(): JsonResponse
    {
        $categorias = CategoriaServicio::withCount('servicios')->orderBy('nombre')->paginate(15);

        return response()->json([
            'data' => $categorias->getCollection()->map(fn ($c) => $this->resumen($c)),
            'meta' => ['current_page' => $categorias->currentPage(), 'last_page' => $categorias->lastPage(), 'total' => $categorias->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);

        $existente = CategoriaServicio::withTrashed()->where('nombre', $datos['nombre'])->first();
        if ($existente) {
            if ($existente->trashed()) {
                $existente->restore();
                $existente->update($datos);
                AuditoriaService::registrar('actualizar', 'categoria_servicios', CategoriaServicio::class, $existente->id, [], $existente->only(['nombre']));

                return response()->json(['data' => $this->resumen($existente)], 201);
            }

            return response()->json(['message' => 'Ya existe una categoría con ese nombre.', 'errors' => ['nombre' => ['Ya existe una categoría con ese nombre.']]], 422);
        }

        $categoria = CategoriaServicio::create($datos);
        AuditoriaService::registrar('crear', 'categoria_servicios', CategoriaServicio::class, $categoria->id, [], ['nombre' => $categoria->nombre]);

        return response()->json(['data' => $this->resumen($categoria)], 201);
    }

    public function update(Request $request, CategoriaServicio $categoriaServicio): JsonResponse
    {
        $datos = $this->validar($request, $categoriaServicio->id);
        $antes = $categoriaServicio->only(['nombre']);
        $categoriaServicio->update($datos);
        AuditoriaService::registrar('actualizar', 'categoria_servicios', CategoriaServicio::class, $categoriaServicio->id, $antes, $categoriaServicio->only(array_keys($antes)));

        return response()->json(['data' => $this->resumen($categoriaServicio)]);
    }

    public function destroy(CategoriaServicio $categoriaServicio): JsonResponse
    {
        if ($categoriaServicio->servicios()->exists()) {
            return response()->json(['message' => 'No se puede eliminar: la categoría tiene servicios.'], 422);
        }

        $categoriaServicio->delete();

        return response()->json(['message' => 'Categoría eliminada.']);
    }

    private function validar(Request $request, ?int $ignorarId = null): array
    {
        $reglaUnica = Rule::unique('categoria_servicios', 'nombre')->whereNull('deleted_at');
        if ($ignorarId) {
            $reglaUnica->ignore($ignorarId);
        }

        return $request->validate([
            'nombre' => ['required', 'string', 'max:100', $reglaUnica],
        ], [
            'nombre.unique' => 'Ya existe una categoría con ese nombre.',
            'nombre.required' => 'El nombre es obligatorio.',
        ]);
    }

    private function resumen(CategoriaServicio $c): array
    {
        return [
            'id' => $c->id,
            'nombre' => $c->nombre,
            'servicios_count' => $c->servicios_count ?? $c->servicios()->count(),
        ];
    }
}
