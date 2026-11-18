<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\Cita;
use App\Models\ConsumoServicio;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\Servicio;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ventas de servicios y productos, con pagos divididos y registro de consumo.
 *
 * Una venta distingue el total vendido de lo efectivamente cobrado:
 *  - total           = suma de detalles - descuento
 *  - total_pagado    = suma de pagos completados
 *  - saldo_pendiente = total - total_pagado
 */
class VentaService
{
    public function __construct(
        private readonly InventarioService $inventario,
        private readonly CajaService $caja,
        private readonly CierreDiarioService $cierres,
    ) {}

    /**
     * Crea una venta con sus detalles. Los detalles de tipo "servicio" pueden
     * incluir consumos ([producto_id, cantidad]) que se descuentan del inventario.
     *
     * @param  array  $datos  ['cliente_id','cita_id','estilista_id','descuento','notas','detalles'=>[]]
     */
    public function crear(array $datos): Venta
    {
        return DB::transaction(function () use ($datos) {
            $detalles = $datos['detalles'] ?? [];

            if (count($detalles) === 0) {
                throw ValidationException::withMessages(['detalles' => 'La venta debe tener al menos un concepto.']);
            }

            $fecha = $datos['fecha'] ?? now()->toDateString();
            $this->cierres->assertDiaAbierto($fecha);

            $venta = Venta::create([
                'numero' => $this->siguienteNumero(),
                'cliente_id' => $datos['cliente_id'] ?? null,
                'cita_id' => $datos['cita_id'] ?? null,
                'estilista_id' => $datos['estilista_id'] ?? null,
                'fecha' => $fecha,
                'fecha_hora' => $datos['fecha_hora'] ?? now(),
                'descuento' => Money::normal($datos['descuento'] ?? '0'),
                'estado' => 'abierta',
                'tipo' => $datos['tipo'] ?? 'servicio',
                'notas' => $datos['notas'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $subtotal = Money::cero();

            foreach ($detalles as $detalle) {
                $subtotal = Money::add($subtotal, $this->crearDetalle($venta, $detalle));
            }

            $total = Money::sub($subtotal, (string) $venta->descuento);

            if (Money::menorQue($total, '0')) {
                throw ValidationException::withMessages(['descuento' => 'El descuento no puede ser mayor que el subtotal.']);
            }

            $venta->update([
                'subtotal' => $subtotal,
                'total' => $total,
                'saldo_pendiente' => $total,
                'total_pagado' => '0.00',
            ]);

            if (! empty($datos['cita_id'])) {
                Cita::whereKey($datos['cita_id'])->update([
                    'venta_id' => $venta->id,
                    'precio' => $total,
                ]);
            }

            AuditoriaService::registrar('crear', 'ventas', Venta::class, $venta->id, [], [
                'numero' => $venta->numero,
                'total' => $total,
            ]);

            return $venta->fresh(['detalles', 'pagos']);
        });
    }

    private function crearDetalle(Venta $venta, array $detalle): string
    {
        $tipo = $detalle['tipo'] ?? 'servicio';
        $cantidad = Money::normal($detalle['cantidad'] ?? '1', Money::ESCALA_CANTIDAD);
        $precio = Money::normal($detalle['precio_unitario'] ?? '0');
        $descuento = Money::normal($detalle['descuento'] ?? '0');
        $subtotal = Money::sub(Money::mul($precio, $cantidad, Money::ESCALA_DINERO), $descuento);

        $costoUnitario = null;
        $costoTotal = null;
        $descripcion = $detalle['descripcion'] ?? '';
        $unidad = $detalle['unidad'] ?? 'unidad';
        $servicioId = null;
        $productoId = null;

        if ($tipo === 'servicio') {
            $servicio = Servicio::findOrFail($detalle['servicio_id']);
            $servicioId = $servicio->id;
            $descripcion = $descripcion ?: $servicio->nombre;

            $detalleVenta = VentaDetalle::create([
                'venta_id' => $venta->id,
                'tipo' => 'servicio',
                'servicio_id' => $servicioId,
                'descripcion' => $descripcion,
                'cantidad' => $cantidad,
                'unidad' => 'unidad',
                'precio_unitario' => $precio,
                'descuento' => $descuento,
                'subtotal' => $subtotal,
            ]);

            $costoConsumo = $this->registrarConsumosServicio($venta, $detalleVenta, $detalle['consumos'] ?? [], $detalle['estilista_id'] ?? $venta->estilista_id);

            if ($costoConsumo !== null) {
                $detalleVenta->update(['costo_total' => $costoConsumo]);
            }

            return $subtotal;
        }

        // Producto de venta directa
        $producto = Producto::findOrFail($detalle['producto_id']);
        $productoId = $producto->id;
        $descripcion = $descripcion ?: $producto->nombre;
        $unidad = $producto->unidad_control;
        $costoUnitario = $producto->costo_unitario;

        $consumo = $this->inventario->consumir($producto, $cantidad, [
            'tipo' => InventarioService::TIPO_VENTA,
            'motivo' => 'Venta de producto',
            'modulo' => 'ventas',
            'modulo_id' => $venta->id,
        ]);

        $costoTotal = $consumo['costo_total'];
        $costoUnitario = Money::div($costoTotal, $cantidad);

        VentaDetalle::create([
            'venta_id' => $venta->id,
            'tipo' => 'producto',
            'producto_id' => $productoId,
            'descripcion' => $descripcion,
            'cantidad' => $cantidad,
            'unidad' => $unidad,
            'presentacion' => $producto->presentacionLabel(),
            'contenido_envase' => $producto->contenido_envase,
            'precio_unitario' => $precio,
            'descuento' => $descuento,
            'subtotal' => $subtotal,
            'costo_unitario' => $costoUnitario,
            'costo_total' => $costoTotal,
        ]);

        return $subtotal;
    }

    /**
     * Descuenta del inventario los productos usados en un servicio y guarda el
     * consumo real. Devuelve el costo total del consumo o null si no hubo.
     */
    private function registrarConsumosServicio(Venta $venta, VentaDetalle $detalleVenta, array $consumos, ?int $estilistaId): ?string
    {
        if (empty($consumos)) {
            return null;
        }

        $costoTotal = Money::cero();

        foreach ($consumos as $consumo) {
            $cantidad = Money::normal($consumo['cantidad'] ?? '0', Money::ESCALA_CANTIDAD);

            if (! Money::mayorQue($cantidad, '0', Money::ESCALA_CANTIDAD)) {
                continue;
            }

            $producto = Producto::findOrFail($consumo['producto_id']);

            $resultado = $this->inventario->consumir($producto, $cantidad, [
                'tipo' => InventarioService::TIPO_SALIDA,
                'motivo' => "Consumo en servicio #{$venta->numero}",
                'modulo' => 'ventas',
                'modulo_id' => $venta->id,
            ]);

            ConsumoServicio::create([
                'cita_id' => $venta->cita_id,
                'venta_id' => $venta->id,
                'producto_id' => $producto->id,
                'estilista_id' => $estilistaId,
                'cantidad' => $resultado['cantidad'],
                'unidad' => $producto->unidad_control,
                'costo_unitario' => Money::div($resultado['costo_total'], $resultado['cantidad']),
                'costo_total' => $resultado['costo_total'],
                'estado' => 'activo',
                'notas' => $consumo['notas'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $costoTotal = Money::add($costoTotal, $resultado['costo_total']);
        }

        return $costoTotal;
    }

    /**
     * Registra un pago (completo, abono o dividido en varios medios) de una venta.
     *
     * @param  array  $pagos  [['metodo_pago_id'=>x,'monto'=>y,'referencia'=>z], ...]
     */
    public function registrarPagos(Venta $venta, array $pagos, bool $registrarEnCaja = true): Venta
    {
        if (count($pagos) === 0) {
            throw ValidationException::withMessages(['pagos' => 'Debe indicar al menos un pago.']);
        }

        return DB::transaction(function () use ($venta, $pagos, $registrarEnCaja) {
            $venta = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();

            $this->cierres->assertDiaAbierto($venta->fecha->toDateString());

            $totalNuevo = Money::normal($venta->total_pagado);

            foreach ($pagos as $pago) {
                $monto = Money::normal($pago['monto'] ?? '0');

                if (! Money::mayorQue($monto, '0')) {
                    continue;
                }

                if (Money::mayorQue(Money::add($totalNuevo, $monto), (string) $venta->total)) {
                    throw ValidationException::withMessages([
                        'pagos' => 'Los pagos superan el total de la venta. Total: '.Money::formato($venta->total).'.',
                    ]);
                }

                $ventaPago = VentaPago::create([
                    'venta_id' => $venta->id,
                    'metodo_pago_id' => $pago['metodo_pago_id'],
                    'monto' => $monto,
                    'tipo' => $pago['tipo'] ?? 'pago',
                    'fecha_hora' => now(),
                    'referencia' => $pago['referencia'] ?? null,
                    'estado' => 'completado',
                    'notas' => $pago['notas'] ?? null,
                    'created_by' => Auth::id(),
                ]);

                $totalNuevo = Money::add($totalNuevo, $monto);

                if ($registrarEnCaja) {
                    $movimiento = $this->caja->registrarMovimiento([
                        'venta_id' => $venta->id,
                        'tipo' => 'ingreso',
                        'monto' => $monto,
                        'metodo_pago_id' => $ventaPago->metodo_pago_id,
                        'concepto' => 'Pago venta '.$venta->numero,
                        'descripcion' => $pago['notas'] ?? null,
                        'referencia' => $venta->numero,
                    ]);

                    $ventaPago->update(['caja_movimiento_id' => $movimiento->id]);
                }
            }

            $saldo = Money::sub((string) $venta->total, $totalNuevo);

            $venta->update([
                'total_pagado' => $totalNuevo,
                'saldo_pendiente' => $saldo,
                'estado' => Money::igual($saldo, '0') ? 'pagada' : 'abonada',
            ]);

            if ($venta->cita_id) {
                Cita::whereKey($venta->cita_id)->update(['saldo_pendiente' => $saldo]);
            }

            AuditoriaService::registrar('pago', 'ventas', Venta::class, $venta->id, [], [
                'total_pagado' => $totalNuevo,
                'saldo_pendiente' => $saldo,
            ]);

            return $venta->fresh(['detalles', 'pagos']);
        });
    }

    /**
     * Anula una venta: revierte consumos de inventario y registra un egreso de
     * caja por los pagos completados que ya habían entrado.
     */
    public function anular(Venta $venta, string $motivo): Venta
    {
        if (trim($motivo) === '') {
            throw ValidationException::withMessages(['motivo' => 'El motivo de anulación es obligatorio.']);
        }

        return DB::transaction(function () use ($venta, $motivo) {
            $venta = Venta::with(['detalles', 'pagos', 'consumos'])->whereKey($venta->id)->lockForUpdate()->firstOrFail();

            $this->cierres->assertDiaAbierto($venta->fecha->toDateString());

            if ($venta->estado === 'anulada') {
                throw ValidationException::withMessages(['venta' => 'La venta ya está anulada.']);
            }

            // Revertir consumos de servicio agrupando por producto y lote.
            foreach ($venta->consumos()->where('estado', 'activo')->get() as $consumo) {
                $movimientos = InventarioMovimiento::where('modulo', 'ventas')
                    ->where('modulo_id', $venta->id)
                    ->where('producto_id', $consumo->producto_id)
                    ->where('tipo', InventarioService::TIPO_SALIDA)
                    ->get();

                $detalles = $movimientos->map(fn ($m) => [
                    'lote_id' => $m->lote_id,
                    'cantidad' => Money::mul((string) $m->cantidad, '-1', Money::ESCALA_CANTIDAD),
                    'costo' => Money::mul((string) $m->costo_total, '-1'),
                ])->all();

                $producto = Producto::find($consumo->producto_id);
                if ($producto && $detalles) {
                    $this->inventario->devolver($producto, $detalles, [
                        'tipo' => InventarioService::TIPO_DEVOLUCION,
                        'motivo' => "Anulación venta {$venta->numero}",
                        'modulo' => 'ventas',
                        'modulo_id' => $venta->id,
                    ]);
                }

                $consumo->update(['estado' => 'reversado', 'reversado_por' => Auth::id()]);
            }

            // Revertir productos de venta directa.
            foreach ($venta->detalles()->where('tipo', 'producto')->get() as $detalle) {
                $movimientos = InventarioMovimiento::where('modulo', 'ventas')
                    ->where('modulo_id', $venta->id)
                    ->where('producto_id', $detalle->producto_id)
                    ->get();

                // Evitar doble reversión: solo si el último movimiento no fue devolución.
                $yaDevuelto = InventarioMovimiento::where('modulo', 'ventas')
                    ->where('modulo_id', $venta->id)
                    ->where('producto_id', $detalle->producto_id)
                    ->where('tipo', InventarioService::TIPO_DEVOLUCION)
                    ->exists();

                if ($yaDevuelto) {
                    continue;
                }

                $detalles = $movimientos->filter(fn ($m) => $m->tipo === InventarioService::TIPO_VENTA)
                    ->map(fn ($m) => [
                        'lote_id' => $m->lote_id,
                        'cantidad' => Money::mul((string) $m->cantidad, '-1', Money::ESCALA_CANTIDAD),
                        'costo' => Money::mul((string) $m->costo_total, '-1'),
                    ])->all();

                $producto = Producto::find($detalle->producto_id);
                if ($producto && $detalles) {
                    $this->inventario->devolver($producto, $detalles, [
                        'tipo' => InventarioService::TIPO_DEVOLUCION,
                        'motivo' => "Anulación venta {$venta->numero}",
                        'modulo' => 'ventas',
                        'modulo_id' => $venta->id,
                    ]);
                }
            }

            // Revertir pagos en caja mediante egresos referenciados.
            foreach ($venta->pagos()->where('estado', 'completado')->get() as $pago) {
                if ($this->cajaActiva()) {
                    $this->caja->registrarMovimiento([
                        'venta_id' => $venta->id,
                        'tipo' => 'egreso',
                        'monto' => (string) $pago->monto,
                        'metodo_pago_id' => $pago->metodo_pago_id,
                        'concepto' => 'Anulación venta '.$venta->numero,
                        'descripcion' => $motivo,
                        'referencia' => $venta->numero,
                    ]);
                }
                $pago->update(['estado' => 'reembolsado']);
            }

            $antes = ['estado' => $venta->estado];
            $venta->update([
                'estado' => 'anulada',
                'total_pagado' => '0.00',
                'saldo_pendiente' => '0.00',
            ]);

            if ($venta->cita_id) {
                Cita::whereKey($venta->cita_id)->update([
                    'venta_id' => null,
                    'saldo_pendiente' => null,
                ]);
            }

            AuditoriaService::registrar('anular', 'ventas', Venta::class, $venta->id, $antes, ['estado' => 'anulada'], $motivo);

            return $venta->fresh();
        });
    }

    private function cajaActiva(): bool
    {
        return (bool) Caja::cajaAbierta();
    }

    public function siguienteNumero(): string
    {
        $prefijo = 'V-'.now()->format('Ymd').'-';

        $ultimo = Venta::withTrashed()
            ->where('numero', 'like', $prefijo.'%')
            ->orderByDesc('id')
            ->value('numero');

        $consecutivo = 1;
        if ($ultimo) {
            $consecutivo = (int) substr($ultimo, strlen($prefijo)) + 1;
        }

        return $prefijo.str_pad((string) $consecutivo, 4, '0', STR_PAD_LEFT);
    }
}
