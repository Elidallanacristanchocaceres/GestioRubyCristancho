<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\CompraPago;
use App\Models\Consumo;
use App\Models\ConsumoServicio;
use App\Models\Gasto;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Reportes de gestión. Distingue con claridad:
 *  - Ventas realizadas (devengado)
 *  - Cobros recibidos (efectivo/medios)
 *  - Compras (aumentan inventario, NO son costo directo)
 *  - Gastos
 *  - Consumo de inventario (costo reconocido al usarse)
 */
class ReporteService
{
    public function ventasEntre(string $desde, string $hasta): string
    {
        return Money::normal(Venta::whereBetween('fecha', [$desde, $hasta])
            ->whereNotIn('estado', ['anulada'])
            ->sum('total'));
    }

    public function cobrosEntre(string $desde, string $hasta): string
    {
        return Money::normal(VentaPago::whereBetween('fecha_hora', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->where('estado', 'completado')
            ->sum('monto'));
    }

    public function gastosEntre(string $desde, string $hasta, bool $soloPagados = true): string
    {
        return Money::normal(Gasto::whereBetween('fecha', [$desde, $hasta])
            ->when($soloPagados, fn ($q) => $q->where('estado', 'pagado'))
            ->sum('monto'));
    }

    public function comprasEntre(string $desde, string $hasta): string
    {
        return Money::normal(Compra::whereBetween('fecha_compra', [$desde, $hasta])
            ->where('estado', '!=', 'cancelado')
            ->sum('total'));
    }

    public function consumoInventarioEntre(string $desde, string $hasta): string
    {
        return Money::normal(ConsumoServicio::where('estado', 'activo')
            ->whereBetween('created_at', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->sum('costo_total'));
    }

    public function cuentasPorCobrar(): string
    {
        return Money::normal(Venta::where('saldo_pendiente', '>', 0)
            ->whereNotIn('estado', ['anulada'])
            ->sum('saldo_pendiente'));
    }

    public function cuentasPorPagar(): string
    {
        return Money::normal(Compra::where('saldo_pendiente', '>', 0)->sum('saldo_pendiente'));
    }

    /**
     * Ganancia bruta = ventas - costo de consumo de inventario.
     * Utilidad estimada = ganancia bruta - gastos pagados.
     */
    public function estadoResultados(string $desde, string $hasta): array
    {
        $ventas = $this->ventasEntre($desde, $hasta);
        $consumo = $this->consumoInventarioEntre($desde, $hasta);
        $gastos = $this->gastosEntre($desde, $hasta);

        $gananciaBruta = Money::sub($ventas, $consumo);
        $utilidad = Money::sub($gananciaBruta, $gastos);

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'ventas' => $ventas,
            'cobros' => $this->cobrosEntre($desde, $hasta),
            'compras' => $this->comprasEntre($desde, $hasta),
            'gastos' => $gastos,
            'consumo_inventario' => $consumo,
            'ganancia_bruta' => $gananciaBruta,
            'utilidad_neta' => $utilidad,
            'margen' => Money::mayorQue($ventas, '0') ? Money::div(Money::mul($utilidad, '100'), $ventas) : '0',
        ];
    }

    public function inventarioValorizado(): array
    {
        $productos = Producto::with(['categoria', 'lotes' => fn ($q) => $q->where('cantidad_disponible', '>', 0)])
            ->where('activo', true)
            ->get();

        $total = Money::cero();
        $items = $productos->map(function (Producto $p) use (&$total) {
            // Se valora con el costo real de los lotes FIFO disponibles. Si el
            // producto aún no tiene lotes (datos históricos), se usa el costo
            // unitario configurado como aproximación.
            $valorLotes = Money::cero();
            foreach ($p->lotes as $lote) {
                $valorLotes = Money::add($valorLotes, Money::mul((string) $lote->costo_unitario, (string) $lote->cantidad_disponible));
            }

            $costoUnitario = (string) ($p->costo_unitario ?? 0);
            $valor = $p->lotes->isNotEmpty() ? $valorLotes : Money::mul($costoUnitario, (string) $p->stock);

            $total = Money::add($total, $valor);

            return [
                'producto' => $p,
                'costo_unitario' => $costoUnitario,
                'stock' => (string) $p->stock,
                'valor' => $valor,
            ];
        });

        return ['items' => $items, 'total' => $total];
    }

    public function productosMasUtilizados(string $desde, string $hasta, int $limite = 10)
    {
        return ConsumoServicio::selectRaw('producto_id, SUM(cantidad) as total_cantidad, SUM(costo_total) as total_costo')
            ->where('estado', 'activo')
            ->whereBetween('created_at', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->groupBy('producto_id')
            ->orderByDesc('total_cantidad')
            ->limit($limite)
            ->with('producto')
            ->get();
    }

    public function serviciosMasVendidos(string $desde, string $hasta, int $limite = 10)
    {
        return \App\Models\VentaDetalle::selectRaw('servicio_id, COUNT(*) as veces, SUM(subtotal) as total')
            ->where('tipo', 'servicio')
            ->whereNotNull('servicio_id')
            ->whereHas('venta', fn ($q) => $q->whereBetween('fecha', [$desde, $hasta])->whereNotIn('estado', ['anulada']))
            ->groupBy('servicio_id')
            ->orderByDesc('veces')
            ->limit($limite)
            ->with('servicio')
            ->get();
    }

    public function rentabilidadPorServicio(string $desde, string $hasta)
    {
        return Venta::with(['detalles.consumos' => fn ($q) => $q])->get();
    }

    /**
     * Resumen diario de los últimos N días para el dashboard.
     *
     * @return array<int, array{fecha:string, ventas:string, cobros:string, gastos:string, consumo:string}>
     */
    public function serieDiaria(int $dias = 14): array
    {
        $serie = [];

        for ($i = $dias - 1; $i >= 0; $i--) {
            $fecha = Carbon::today()->subDays($i)->toDateString();
            $serie[] = [
                'fecha' => $fecha,
                'label' => Carbon::parse($fecha)->translatedFormat('d M'),
                'ventas' => $this->ventasEntre($fecha, $fecha),
                'cobros' => $this->cobrosEntre($fecha, $fecha),
                'gastos' => $this->gastosEntre($fecha, $fecha),
                'consumo' => $this->consumoInventarioEntre($fecha, $fecha),
            ];
        }

        return $serie;
    }

    public function dashboard(): array
    {
        $hoy = Carbon::today()->toDateString();
        $inicioSemana = Carbon::today()->startOfWeek()->toDateString();
        $inicioMes = Carbon::today()->startOfMonth()->toDateString();
        $finMes = Carbon::today()->endOfMonth()->toDateString();

        $productosBajos = Producto::where('activo', true)
            ->whereColumn('stock', '<', 'stock_minimo')
            ->where('stock', '>', 0)
            ->orderBy('stock')
            ->limit(10)
            ->get();

        $productosAgotados = Producto::where('activo', true)
            ->where('stock', '<=', 0)
            ->orderBy('nombre')
            ->limit(10)
            ->get();

        $productosVencen = Producto::where('activo', true)
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '>=', $hoy)
            ->whereDate('fecha_vencimiento', '<=', Carbon::today()->addDays(30)->toDateString())
            ->orderBy('fecha_vencimiento')
            ->limit(10)
            ->get();

        $caja = Caja::cajaAbierta();

        return [
            'ingresos_hoy' => $this->cobrosEntre($hoy, $hoy),
            'ingresos_semana' => $this->cobrosEntre($inicioSemana, $hoy),
            'ingresos_mes' => $this->cobrosEntre($inicioMes, $hoy),
            'ventas_hoy' => $this->ventasEntre($hoy, $hoy),
            'ventas_mes' => $this->ventasEntre($inicioMes, $hoy),
            'gastos_hoy' => $this->gastosEntre($hoy, $hoy),
            'gastos_semana' => $this->gastosEntre($inicioSemana, $hoy),
            'gastos_mes' => $this->gastosEntre($inicioMes, $hoy),
            'ganancia_mes' => $this->estadoResultados($inicioMes, $hoy)['ganancia_bruta'],
            'utilidad_mes' => $this->estadoResultados($inicioMes, $hoy)['utilidad_neta'],
            'saldo_caja' => $caja ? $caja->saldoActual() : '0',
            'caja' => $caja,
            'citas_hoy' => Cita::delDia($hoy)->whereNotIn('estado', [Cita::ESTADO_CANCELADA])->count(),
            'citas_hoy_lista' => Cita::with(['cliente', 'estilista', 'servicio'])
                ->delDia($hoy)
                ->orderBy('fecha_hora')
                ->get(),
            'registros_hoy' => Consumo::where('estado', Consumo::ESTADO_ACTIVO)->whereDate('fecha', $hoy)->count(),
            'registros_hoy_lista' => Consumo::with(['cliente', 'servicio', 'estilista'])
                ->where('estado', Consumo::ESTADO_ACTIVO)
                ->whereDate('fecha', $hoy)
                ->orderByDesc('id')
                ->limit(8)
                ->get(),
            'registros_hoy_ingresos' => Money::normal(
                Consumo::where('estado', Consumo::ESTADO_ACTIVO)->whereDate('fecha', $hoy)->sum('precio_cobrado')
            ),
            'registros_hoy_costo' => Money::normal(
                Consumo::where('estado', Consumo::ESTADO_ACTIVO)->whereDate('fecha', $hoy)->sum('costo_total')
            ),
            'productos_bajos' => $productosBajos,
            'productos_agotados' => $productosAgotados,
            'productos_vencen' => $productosVencen,
            'por_cobrar' => $this->cuentasPorCobrar(),
            'por_pagar' => $this->cuentasPorPagar(),
            'clientes_activos' => Cliente::activos()->count(),
            'serie' => $this->serieDiaria(14),
            'estado_resultados_mes' => $this->estadoResultados($inicioMes, $finMes),
        ];
    }
}
