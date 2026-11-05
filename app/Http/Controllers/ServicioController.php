<?php

namespace App\Http\Controllers;

use App\Models\CategoriaServicio;
use App\Models\Producto;
use App\Models\Servicio;
use App\Models\ServicioProducto;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServicioController extends Controller
{
    public function index(Request $request): View
    {
        $servicios = Servicio::with('categoria')
            ->when($request->filled('q'), fn ($q) => $q->where('nombre', 'like', '%'.$request->str('q').'%'))
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria_servicio_id', $request->integer('categoria')))
            ->when($request->filled('estado'), fn ($q) => $q->where('activo', $request->str('estado') === 'activos'))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('servicios.index', [
            'servicios' => $servicios,
            'categorias' => CategoriaServicio::orderBy('nombre')->get(),
        ]);
    }

    public function create(): View
    {
        return view('servicios.create', $this->opciones() + ['servicio' => new Servicio(['activo' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $servicio = Servicio::create($this->validar($request));

        AuditoriaService::registrar('crear', 'servicios', Servicio::class, $servicio->id, [], ['nombre' => $servicio->nombre]);

        return redirect()->route('servicios.show', $servicio)->with('exito', 'Servicio creado correctamente.');
    }

    public function show(Servicio $servicio): View
    {
        $servicio->load(['categoria', 'productos.producto']);

        return view('servicios.show', [
            'servicio' => $servicio,
            'productos' => Producto::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function edit(Servicio $servicio): View
    {
        return view('servicios.edit', $this->opciones() + compact('servicio'));
    }

    public function update(Request $request, Servicio $servicio): RedirectResponse
    {
        $antes = $servicio->only(['nombre', 'precio', 'duracion_minutos', 'costo_mano_obra']);
        $servicio->update($this->validar($request));

        AuditoriaService::registrar('actualizar', 'servicios', Servicio::class, $servicio->id, $antes, $servicio->only(array_keys($antes)));

        return redirect()->route('servicios.show', $servicio)->with('exito', 'Servicio actualizado correctamente.');
    }

    public function destroy(Servicio $servicio): RedirectResponse
    {
        if ($servicio->citas()->exists()) {
            $servicio->update(['activo' => false]);

            return redirect()->route('servicios.index')->with('exito', 'El servicio tiene citas; se desactivó en lugar de eliminarlo.');
        }

        $servicio->delete();

        return redirect()->route('servicios.index')->with('exito', 'Servicio eliminado correctamente.');
    }

    /**
     * Guarda la receta técnica (productos y cantidades sugeridas del servicio).
     */
    public function guardarReceta(Request $request, Servicio $servicio): RedirectResponse
    {
        $datos = $request->validate([
            'productos' => ['array'],
            'productos.*.producto_id' => ['required', 'exists:productos,id'],
            'productos.*.cantidad_sugerida' => ['required', 'numeric', 'min:0.001'],
        ]);

        $servicio->productos()->delete();

        foreach ($datos['productos'] ?? [] as $item) {
            $producto = Producto::find($item['producto_id']);
            $costo = $producto?->costo_unitario ?? '0';

            ServicioProducto::create([
                'servicio_id' => $servicio->id,
                'producto_id' => $item['producto_id'],
                'cantidad_sugerida' => $item['cantidad_sugerida'],
                'costo_estimado' => \App\Support\Money::mul((string) $costo, (string) $item['cantidad_sugerida']),
            ]);
        }

        AuditoriaService::registrar('actualizar', 'servicios', Servicio::class, $servicio->id, [], ['receta' => true]);

        return back()->with('exito', 'Receta del servicio actualizada.');
    }

    private function opciones(): array
    {
        return ['categorias' => CategoriaServicio::orderBy('nombre')->get()];
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'categoria_servicio_id' => ['required', 'exists:categoria_servicios,id'],
            'nombre' => ['required', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string'],
            'duracion_minutos' => ['required', 'integer', 'min:5', 'max:600'],
            'precio' => ['required', 'numeric', 'min:0'],
            'costo_mano_obra' => ['nullable', 'numeric', 'min:0'],
        ], [], ['categoria_servicio_id' => 'categoría', 'duracion_minutos' => 'duración']) + [
            'activo' => $request->boolean('activo', true),
        ];
    }
}
