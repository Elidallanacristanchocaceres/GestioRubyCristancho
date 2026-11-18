<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Compra;
use App\Models\Venta;
use App\Services\ReporteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReporteController extends Controller
{
    public function __construct(private readonly ReporteService $reportes) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'reportes' => [
                ['clave' => 'resultados', 'nombre' => 'Estado de resultados', 'descripcion' => 'Ventas, consumo de inventario, gastos y utilidad por período.', 'ruta' => '/reportes/resultados'],
                ['clave' => 'inventario', 'nombre' => 'Inventario valorizado', 'descripcion' => 'Valor del inventario actual a costo unitario.', 'ruta' => '/reportes/inventario'],
                ['clave' => 'consumo', 'nombre' => 'Consumo y servicios', 'descripcion' => 'Productos más usados y servicios más vendidos.', 'ruta' => '/reportes/consumo'],
                ['clave' => 'cartera', 'nombre' => 'Cartera', 'descripcion' => 'Cuentas por cobrar de clientes y por pagar a proveedores.', 'ruta' => '/reportes/cartera'],
                ['clave' => 'cierre', 'nombre' => 'Cierre semanal', 'descripcion' => 'Ingresos, costos, gastos, diezmo del 10 % y neto de la semana.', 'ruta' => '/reportes/cierres'],
                ['clave' => 'cierre-diario', 'nombre' => 'Cierre diario', 'descripcion' => 'Servicios, productos utilizados, productos vendidos, gastos del salón y de la casa.', 'ruta' => '/cierre-diario'],
                ['clave' => 'historico-cierres-diarios', 'nombre' => 'Histórico de cierres diarios', 'descripcion' => 'Consulta por fecha y totales diarios, semanales y mensuales.', 'ruta' => '/reportes/cierres-diarios'],
            ],
        ]);
    }

    public function resultados(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->rango($request);

        $resultado = $this->reportes->estadoResultados($desde, $hasta);
        $serie = $this->reportes->serieDiaria(30);

        // Money ya devuelve cadenas; se pasa tal cual sin floats.
        return response()->json([
            'desde' => $desde,
            'hasta' => $hasta,
            'resultado' => [
                'desde' => $resultado['desde'],
                'hasta' => $resultado['hasta'],
                'ventas' => (string) $resultado['ventas'],
                'cobros' => (string) $resultado['cobros'],
                'compras' => (string) $resultado['compras'],
                'gastos' => (string) $resultado['gastos'],
                'consumo_inventario' => (string) $resultado['consumo_inventario'],
                'costo_productos_vendidos' => (string) $resultado['costo_productos_vendidos'],
                'ganancia_bruta' => (string) $resultado['ganancia_bruta'],
                'utilidad_neta' => (string) $resultado['utilidad_neta'],
                'margen' => (string) $resultado['margen'],
            ],
            'serie' => $serie,
        ]);
    }

    public function inventario(): JsonResponse
    {
        $inventario = $this->reportes->inventarioValorizado();

        $items = $inventario['items']->map(fn ($item) => [
            'producto_id' => $item['producto']->id,
            'producto_nombre' => $item['producto']->nombre,
            'categoria_nombre' => $item['producto']->categoria?->nombre,
            'stock' => (string) $item['stock'],
            'unidad_abrev' => $item['producto']->unidadAbrev(),
            'costo_unitario' => (string) $item['costo_unitario'],
            'valor' => (string) $item['valor'],
        ])->values();

        return response()->json([
            'items' => $items,
            'total' => (string) $inventario['total'],
        ]);
    }

    public function consumo(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->rango($request);

        $productos = $this->reportes->productosMasUtilizados($desde, $hasta, 20)->map(fn ($fila) => [
            'producto_id' => $fila->producto_id,
            'producto_nombre' => $fila->producto?->nombre,
            'total_cantidad' => (string) $fila->total_cantidad,
            'total_costo' => (string) $fila->total_costo,
        ])->values();

        $servicios = $this->reportes->serviciosMasVendidos($desde, $hasta, 20)->map(fn ($fila) => [
            'servicio_id' => $fila->servicio_id,
            'servicio_nombre' => $fila->servicio?->nombre,
            'veces' => (int) $fila->veces,
            'total' => (string) $fila->total,
        ])->values();

        return response()->json([
            'desde' => $desde,
            'hasta' => $hasta,
            'productos' => $productos,
            'servicios' => $servicios,
        ]);
    }

    public function cartera(): JsonResponse
    {
        $porCobrar = Venta::with('cliente')->where('saldo_pendiente', '>', 0)->whereNotIn('estado', ['anulada'])->orderByDesc('saldo_pendiente')->get()
            ->map(fn ($v) => [
                'venta_id' => $v->id,
                'numero' => $v->numero,
                'cliente_nombre' => $v->cliente?->nombre,
                'total' => (string) $v->total,
                'saldo_pendiente' => (string) $v->saldo_pendiente,
            ])->values();

        $porPagar = Compra::with('proveedor')->where('saldo_pendiente', '>', 0)->orderByDesc('saldo_pendiente')->get()
            ->map(fn ($c) => [
                'compra_id' => $c->id,
                'numero_factura' => $c->numero_factura ?? ('#'.$c->id),
                'proveedor_nombre' => $c->proveedor?->nombre,
                'total' => (string) $c->total,
                'saldo_pendiente' => (string) $c->saldo_pendiente,
            ])->values();

        return response()->json([
            'por_cobrar' => $porCobrar,
            'por_pagar' => $porPagar,
            'total_cobrar' => (string) $this->reportes->cuentasPorCobrar(),
            'total_pagar' => (string) $this->reportes->cuentasPorPagar(),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function rango(Request $request): array
    {
        $desde = $request->filled('desde') ? Carbon::parse($request->date('desde'))->toDateString() : Carbon::today()->startOfMonth()->toDateString();
        $hasta = $request->filled('hasta') ? Carbon::parse($request->date('hasta'))->toDateString() : Carbon::today()->toDateString();

        if (Carbon::parse($desde)->gt(Carbon::parse($hasta))) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [$desde, $hasta];
    }
}
