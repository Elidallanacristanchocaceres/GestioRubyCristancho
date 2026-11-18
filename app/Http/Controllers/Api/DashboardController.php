<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReporteService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(ReporteService $reportes): JsonResponse
    {
        $datos = $reportes->dashboard();

        // Se serializa explícitamente lo que consume el frontend Angular,
        // reutilizando el cálculo de ReporteService sin pasar modelos crudos.
        return response()->json([
            'ingresos_hoy' => $datos['ingresos_hoy'],
            'ingresos_semana' => $datos['ingresos_semana'],
            'ingresos_mes' => $datos['ingresos_mes'],
            'ventas_hoy' => $datos['ventas_hoy'],
            'ventas_mes' => $datos['ventas_mes'],
            'gastos_hoy' => $datos['gastos_hoy'],
            'gastos_semana' => $datos['gastos_semana'],
            'gastos_mes' => $datos['gastos_mes'],
            'ganancia_mes' => $datos['ganancia_mes'],
            'utilidad_mes' => $datos['utilidad_mes'],
            'saldo_caja' => $datos['saldo_caja'],
            'caja_abierta' => $datos['caja'] !== null,
            'caja_fecha_apertura' => $datos['caja']?->fecha_apertura?->toIso8601String(),
            'citas_hoy' => $datos['citas_hoy'],
            'registros_hoy' => $datos['registros_hoy'],
            'registros_hoy_ingresos' => $datos['registros_hoy_ingresos'],
            'registros_hoy_costo' => $datos['registros_hoy_costo'],
            'registros_hoy_neto' => $datos['registros_hoy_neto'],
            'por_cobrar' => $datos['por_cobrar'],
            'por_pagar' => $datos['por_pagar'],
            'product_bajos' => $datos['productos_bajos']->map(fn ($p) => [
                'id' => $p->id, 'nombre' => $p->nombre, 'stock' => (string) $p->stock,
                'stock_minimo' => (string) $p->stock_minimo, 'unidad_abrev' => $p->unidadAbrev(),
            ])->values(),
            'productos_agotados' => $datos['productos_agotados']->map(fn ($p) => [
                'id' => $p->id, 'nombre' => $p->nombre, 'stock' => (string) $p->stock,
                'stock_minimo' => (string) $p->stock_minimo, 'unidad_abrev' => $p->unidadAbrev(),
            ])->values(),
            'productos_vencen' => $datos['productos_vencen']->map(fn ($p) => [
                'id' => $p->id, 'nombre' => $p->nombre,
                'fecha_vencimiento' => $p->fecha_vencimiento?->toDateString(),
            ])->values(),
            'registros_hoy_lista' => $datos['registros_hoy_lista']->map(fn ($r) => [
                'id' => $r->id,
                'descripcion' => $r->descripcion,
                'precio_cobrado' => (string) $r->precio_cobrado,
                'cliente_nombre' => $r->cliente?->nombre,
                'servicio_nombre' => $r->servicio?->nombre,
                'estilista_nombre' => $r->estilista?->nombre,
            ])->values(),
            'serie' => $datos['serie'],
            'estado_resultados_mes' => $datos['estado_resultados_mes'],
        ]);
    }
}
