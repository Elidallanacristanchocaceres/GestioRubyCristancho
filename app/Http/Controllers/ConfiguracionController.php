<?php

namespace App\Http\Controllers;

use App\Models\CategoriaGasto;
use App\Models\CategoriaProducto;
use App\Models\CategoriaServicio;
use App\Models\Configuracion;
use App\Models\MetodoPago;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConfiguracionController extends Controller
{
    public function index(): View
    {
        return view('configuracion.index', [
            'categoriasProducto' => CategoriaProducto::orderBy('nombre')->get(),
            'categoriasServicio' => CategoriaServicio::orderBy('nombre')->get(),
            'categoriasGasto' => CategoriaGasto::orderBy('nombre')->get(),
            'metodos' => MetodoPago::orderBy('nombre')->get(),
            'configuraciones' => Configuracion::orderBy('grupo')->orderBy('id')->get()->groupBy('grupo'),
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'config' => ['array'],
            'config.*' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($datos['config'] ?? [] as $clave => $valor) {
            Configuracion::updateOrCreate(['clave' => $clave], ['valor' => $valor]);
        }

        return back()->with('exito', 'Configuración guardada correctamente.');
    }

    public function guardarCategoria(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', 'in:producto,servicio,gasto'],
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:300'],
        ]);

        $modelo = match ($datos['tipo']) {
            'producto' => CategoriaProducto::class,
            'servicio' => CategoriaServicio::class,
            'gasto' => CategoriaGasto::class,
        };

        $modelo::updateOrCreate(
            ['nombre' => $datos['nombre']],
            ['descripcion' => $datos['descripcion'] ?? null] + ($datos['tipo'] === 'gasto' ? ['activo' => true] : [])
        );

        return back()->with('exito', 'Categoría guardada correctamente.');
    }

    public function guardarMetodo(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:300'],
            'es_efectivo' => ['boolean'],
        ]);

        MetodoPago::updateOrCreate(
            ['nombre' => $datos['nombre']],
            ['descripcion' => $datos['descripcion'] ?? null, 'es_efectivo' => $request->boolean('es_efectivo')]
        );

        return back()->with('exito', 'Medio de pago guardado correctamente.');
    }
}
