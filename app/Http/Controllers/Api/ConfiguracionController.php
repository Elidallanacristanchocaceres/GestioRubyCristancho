<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CategoriaGasto;
use App\Models\CategoriaProducto;
use App\Models\CategoriaServicio;
use App\Models\Configuracion;
use App\Models\MetodoPago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfiguracionController extends Controller
{
    public function index(): JsonResponse
    {
        $configuraciones = Configuracion::orderBy('grupo')->orderBy('id')->get()->groupBy('grupo');

        return response()->json([
            'configuraciones' => $configuraciones->map(fn ($lista) => $lista->map(fn ($c) => [
                'id' => $c->id,
                'clave' => $c->clave,
                'valor' => $c->valor,
                'etiqueta' => $c->etiqueta,
                'grupo' => $c->grupo,
                'tipo' => $c->tipo,
            ])->values())->all(),
            'categorias_producto' => CategoriaProducto::orderBy('nombre')->get(['id', 'nombre', 'grupo'])->map(fn ($c) => [
                'id' => $c->id, 'nombre' => $c->nombre, 'grupo' => $c->grupo,
            ]),
            'categorias_servicio' => CategoriaServicio::orderBy('nombre')->get(['id', 'nombre'])->map(fn ($c) => [
                'id' => $c->id, 'nombre' => $c->nombre,
            ]),
            'categorias_gasto' => CategoriaGasto::orderBy('ambito')->orderBy('nombre')->get(['id', 'nombre', 'ambito'])->map(fn ($c) => [
                'id' => $c->id, 'nombre' => $c->nombre, 'ambito' => $c->ambito,
            ]),
            'metodos' => MetodoPago::orderBy('nombre')->get(['id', 'nombre', 'descripcion', 'es_efectivo'])->map(fn ($m) => [
                'id' => $m->id, 'nombre' => $m->nombre, 'descripcion' => $m->descripcion, 'es_efectivo' => (bool) $m->es_efectivo,
            ]),
        ]);
    }

    public function guardar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'config' => ['array'],
            'config.*' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($datos['config'] ?? [] as $clave => $valor) {
            Configuracion::updateOrCreate(['clave' => $clave], ['valor' => $valor]);
        }

        return response()->json(['message' => 'Configuración guardada correctamente.']);
    }

    public function guardarCategoria(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', 'in:producto,servicio,gasto'],
            'nombre' => ['required', 'string', 'max:100'],
            'ambito' => ['nullable', 'in:salon,casa'],
        ]);

        $modelo = match ($datos['tipo']) {
            'producto' => CategoriaProducto::class,
            'servicio' => CategoriaServicio::class,
            'gasto' => CategoriaGasto::class,
        };

        $atributos = $datos['tipo'] === 'gasto'
            ? ['activo' => true, 'ambito' => $datos['ambito'] ?? 'salon']
            : [];

        $consulta = in_array($datos['tipo'], ['producto', 'servicio'], true)
            ? $modelo::withTrashed()
            : $modelo::query();

        $categoria = $consulta->where('nombre', $datos['nombre'])->first();

        if ($categoria) {
            if (method_exists($categoria, 'trashed') && $categoria->trashed()) {
                $categoria->restore();
            }
            if ($atributos !== []) {
                $categoria->fill($atributos);
                $categoria->save();
            }
        } else {
            $categoria = $modelo::create(['nombre' => $datos['nombre']] + $atributos);
        }

        return response()->json(['message' => 'Categoría guardada correctamente.', 'data' => ['id' => $categoria->id, 'nombre' => $categoria->nombre]]);
    }

    public function guardarMetodo(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:300'],
            'es_efectivo' => ['boolean'],
        ]);

        $metodo = MetodoPago::updateOrCreate(
            ['nombre' => $datos['nombre']],
            ['descripcion' => $datos['descripcion'] ?? null, 'es_efectivo' => $request->boolean('es_efectivo')]
        );

        return response()->json(['message' => 'Medio de pago guardado correctamente.', 'data' => ['id' => $metodo->id, 'nombre' => $metodo->nombre]]);
    }
}
