<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CategoriaServicio;
use App\Models\Producto;
use App\Models\Servicio;
use App\Models\ServicioProducto;
use App\Services\AuditoriaService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServicioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $servicios = Servicio::with('categoria')
            ->when($request->filled('q'), fn ($q) => $q->where('nombre', 'like', '%'.$request->str('q').'%'))
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria_servicio_id', $request->integer('categoria')))
            ->when($request->filled('estado'), fn ($q) => $q->where('activo', $request->str('estado') === 'activos'))
            ->orderBy('nombre')
            ->paginate(15);

        return response()->json([
            'data' => $servicios->getCollection()->map(fn ($s) => $this->resumen($s)),
            'meta' => ['current_page' => $servicios->currentPage(), 'last_page' => $servicios->lastPage(), 'total' => $servicios->total()],
            'categorias' => CategoriaServicio::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $servicio = Servicio::create($this->validar($request));
        AuditoriaService::registrar('crear', 'servicios', Servicio::class, $servicio->id, [], ['nombre' => $servicio->nombre]);

        return response()->json(['data' => $this->resumen($servicio)], 201);
    }

    public function show(Servicio $servicio): JsonResponse
    {
        $servicio->load(['categoria', 'productos.producto']);

        return response()->json([
            'data' => $this->resumen($servicio, true),
            'productos_disponibles' => Producto::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function update(Request $request, Servicio $servicio): JsonResponse
    {
        $antes = $servicio->only(['nombre', 'precio', 'costo_mano_obra']);
        $servicio->update($this->validar($request));
        AuditoriaService::registrar('actualizar', 'servicios', Servicio::class, $servicio->id, $antes, $servicio->only(array_keys($antes)));

        return response()->json(['data' => $this->resumen($servicio, true)]);
    }

    public function destroy(Servicio $servicio): JsonResponse
    {
        if ($servicio->citas()->exists()) {
            $servicio->update(['activo' => false]);

            return response()->json(['message' => 'El servicio tiene citas; se desactivó en lugar de eliminarlo.']);
        }
        $servicio->delete();

        return response()->json(['message' => 'Servicio eliminado correctamente.']);
    }

    public function registrarReceta(Request $request, Servicio $servicio): JsonResponse
    {
        $datos = $request->validate([
            'productos' => ['array'],
            'productos.*.producto_id' => ['required', 'exists:productos,id'],
            'productos.*.cantidad_sugerida' => ['required', 'numeric', 'min:0.001'],
        ]);

        $servicio->productos()->delete();
        foreach ($datos['productos'] ?? [] as $item) {
            $producto = Producto::find($item['producto_id']);
            ServicioProducto::create([
                'servicio_id' => $servicio->id,
                'producto_id' => $item['producto_id'],
                'cantidad_sugerida' => $item['cantidad_sugerida'],
                'costo_estimado' => Money::mul((string) ($producto?->costo_unitario ?? '0'), (string) $item['cantidad_sugerida']),
            ]);
        }

        return response()->json(['message' => 'Receta del servicio actualizada.']);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'categoria_servicio_id' => ['required', 'exists:categoria_servicios,id'],
            'nombre' => ['required', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string'],
            'precio' => ['required', 'numeric', 'min:0'],
            'costo_mano_obra' => ['nullable', 'numeric', 'min:0'],
        ]) + ['activo' => $request->boolean('activo', true)];
    }

    private function resumen(Servicio $s, bool $detalle = false): array
    {
        $base = [
            'id' => $s->id, 'nombre' => $s->nombre,
            'categoria_nombre' => $s->categoria?->nombre,
            'precio' => (string) $s->precio,
            'costo_estimado' => $s->costoTotalEstimado(),
            'margen' => $s->margenEstimado(),
            'activo' => (bool) $s->activo,
        ];
        if ($detalle) {
            $base += [
                'categoria_servicio_id' => $s->categoria_servicio_id,
                'descripcion' => $s->descripcion,
                'costo_mano_obra' => $s->costo_mano_obra ? (string) $s->costo_mano_obra : null,
                'receta' => $s->productos->map(fn ($p) => [
                    'producto_id' => $p->producto_id, 'nombre' => $p->producto?->nombre,
                    'cantidad_sugerida' => (string) $p->cantidad_sugerida,
                ])->values(),
            ];
        }

        return $base;
    }
}
