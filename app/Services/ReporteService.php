<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Consumo;
use App\Models\ConsumoServicio;
use App\Models\Gasto;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\VentaDetalle;
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

    /**
     * Servicios cobrados en el registro diario (Consumos).
     */
    public function serviciosCobradosEntre(string $desde, string $hasta): string
    {
        return Money::normal(Consumo::where('estado', Consumo::ESTADO_ACTIVO)
            ->whereBetween('fecha', [$desde, $hasta])
            ->sum('precio_cobrado'));
    }

    /**
     * Ventas de productos (potes, etc.) por fecha de la venta.
     */
    public function ventasProductosEntre(string $desde, string $hasta): string
    {
        return Money::normal(VentaDetalle::where('tipo', 'producto')
            ->whereHas('venta', fn ($q) => $q
                ->whereBetween('fecha', [$desde, $hasta])
                ->whereNotIn('estado', ['anulada']))
            ->sum('subtotal'));
    }

    /**
     * Ventas de servicios registradas por el módulo de ventas. Se usan solo
     * cuando no hay registro diario en el período, para no duplicar.
     */
    public function ventasServiciosEntre(string $desde, string $hasta): string
    {
        return Money::normal(VentaDetalle::where('tipo', 'servicio')
            ->whereHas('venta', fn ($q) => $q
                ->whereBetween('fecha', [$desde, $hasta])
                ->whereNotIn('estado', ['anulada']))
            ->sum('subtotal'));
    }

    public function hayConsumosEntre(string $desde, string $hasta): bool
    {
        return Consumo::where('estado', Consumo::ESTADO_ACTIVO)
            ->whereBetween('fecha', [$desde, $hasta])
            ->exists();
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
        return Money::add(
            $this->consumoInventarioConsumosEntre($desde, $hasta),
            $this->consumoInventarioVentasEntre($desde, $hasta)
        );
    }

    public function consumoInventarioConsumosEntre(string $desde, string $hasta): string
    {
        return Money::normal(ConsumoServicio::where('estado', 'activo')
            ->whereHas('consumo', fn ($c) => $c
                ->where('estado', Consumo::ESTADO_ACTIVO)
                ->whereBetween('fecha', [$desde, $hasta]))
            ->sum('costo_total'));
    }

    public function consumoInventarioVentasEntre(string $desde, string $hasta): string
    {
        return Money::normal(ConsumoServicio::where('estado', 'activo')
            ->whereHas('venta', fn ($v) => $v
                ->whereBetween('fecha', [$desde, $hasta])
                ->whereNotIn('estado', ['anulada']))
            ->sum('costo_total'));
    }

    /**
     * Costo de los productos que se vendieron directamente (potes, etc.).
     * El costo vive en la línea de venta, no en consumos_servicio.
     */
    public function costoProductosVendidosEntre(string $desde, string $hasta): string
    {
        return Money::normal(VentaDetalle::where('tipo', 'producto')
            ->whereHas('venta', fn ($q) => $q
                ->whereBetween('fecha', [$desde, $hasta])
                ->whereNotIn('estado', ['anulada']))
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
     * Ganancia bruta = ingresos - costo de consumo de inventario - costo de
     * productos vendidos. La fuente de los servicios es el registro diario;
     * si no hay ninguno en el período, se usan las ventas de servicios.
     */
    public function estadoResultados(string $desde, string $hasta): array
    {
        $hayConsumos = $this->hayConsumosEntre($desde, $hasta);
        $serviciosDiarios = $this->serviciosCobradosEntre($desde, $hasta);
        $serviciosVentas = $this->ventasServiciosEntre($desde, $hasta);
        $usarVentas = ! $hayConsumos && Money::mayorQue($serviciosVentas, '0');

        $servicios = $usarVentas ? $serviciosVentas : $serviciosDiarios;
        $productos = $this->ventasProductosEntre($desde, $hasta);
        $ventas = Money::add($servicios, $productos);

        $consumo = $usarVentas
            ? $this->consumoInventarioVentasEntre($desde, $hasta)
            : $this->consumoInventarioConsumosEntre($desde, $hasta);

        $costoVendido = $this->costoProductosVendidosEntre($desde, $hasta);
        $gastos = $this->gastosEntre($desde, $hasta);

        $gananciaBruta = Money::sub(Money::sub($ventas, $consumo), $costoVendido);
        $utilidad = Money::sub($gananciaBruta, $gastos);

        // En el flujo del registro diario, lo cobrado en el salón se suma a
        // los pagos recibidos por ventas de productos.
        $cobros = $usarVentas
            ? $this->cobrosEntre($desde, $hasta)
            : Money::add($serviciosDiarios, $this->cobrosEntre($desde, $hasta));

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'ventas' => $ventas,
            'ingresos_servicios' => $servicios,
            'ingresos_productos' => $productos,
            'fuente_servicios' => $usarVentas ? 'ventas' : 'registro_diario',
            'servicios_otra_fuente' => $usarVentas ? Money::cero() : $serviciosVentas,
            'cobros' => $cobros,
            'compras' => $this->comprasEntre($desde, $hasta),
            'gastos' => $gastos,
            'consumo_inventario' => $consumo,
            'costo_productos_vendidos' => $costoVendido,
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
            ->where(function ($q) use ($desde, $hasta) {
                $q->whereHas('consumo', fn ($c) => $c
                    ->where('estado', Consumo::ESTADO_ACTIVO)
                    ->whereBetween('fecha', [$desde, $hasta]))
                    ->orWhereHas('venta', fn ($v) => $v
                        ->whereBetween('fecha', [$desde, $hasta])
                        ->whereNotIn('estado', ['anulada']));
            })
            ->groupBy('producto_id')
            ->orderByDesc('total_cantidad')
            ->limit($limite)
            ->with('producto')
            ->get();
    }

    public function serviciosMasVendidos(string $desde, string $hasta, int $limite = 10)
    {
        return VentaDetalle::selectRaw('servicio_id, COUNT(*) as veces, SUM(subtotal) as total')
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

        $registrosHoyIngresos = Money::normal(
            Consumo::where('estado', Consumo::ESTADO_ACTIVO)->whereDate('fecha', $hoy)->sum('precio_cobrado')
        );
        $registrosHoyCosto = Money::normal(
            Consumo::where('estado', Consumo::ESTADO_ACTIVO)->whereDate('fecha', $hoy)->sum('costo_total')
        );
        $gastosHoy = $this->gastosEntre($hoy, $hoy);
        $registrosHoyNeto = Money::sub(Money::sub($registrosHoyIngresos, $registrosHoyCosto), $gastosHoy);

        // El dashboard usa la misma fuente que el reporte y el cierre semanal:
        // registro diario para servicios y ventas para productos.
        $resultadoHoy = $this->estadoResultados($hoy, $hoy);
        $resultadoSemana = $this->estadoResultados($inicioSemana, $hoy);
        $resultadoMes = $this->estadoResultados($inicioMes, $hoy);
        $resultadoMesCompleto = $this->estadoResultados($inicioMes, $finMes);

        return [
            'ingresos_hoy' => $resultadoHoy['cobros'],
            'ingresos_semana' => $resultadoSemana['cobros'],
            'ingresos_mes' => $resultadoMes['cobros'],
            'ventas_hoy' => $resultadoHoy['ventas'],
            'ventas_mes' => $resultadoMes['ventas'],
            'gastos_hoy' => $gastosHoy,
            'gastos_semana' => $this->gastosEntre($inicioSemana, $hoy),
            'gastos_mes' => $this->gastosEntre($inicioMes, $hoy),
            'ganancia_mes' => $resultadoMes['ganancia_bruta'],
            'utilidad_mes' => $resultadoMes['utilidad_neta'],
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
            'registros_hoy_ingresos' => $registrosHoyIngresos,
            'registros_hoy_costo' => $registrosHoyCosto,
            'registros_hoy_neto' => $registrosHoyNeto,
            'productos_bajos' => $productosBajos,
            'productos_agotados' => $productosAgotados,
            'productos_vencen' => $productosVencen,
            'por_cobrar' => $this->cuentasPorCobrar(),
            'por_pagar' => $this->cuentasPorPagar(),
            'clientes_activos' => Cliente::activos()->count(),
            'serie' => $this->serieDiaria(14),
            'estado_resultados_mes' => $resultadoMesCompleto,
        ];
    }
}
