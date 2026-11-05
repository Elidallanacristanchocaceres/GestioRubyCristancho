<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\CompraPago;
use App\Models\Gasto;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Compras a proveedor: aumentan inventario creando lotes y, opcionalmente,
 * generan UN gasto asociado (para no registrar dos veces el mismo costo).
 */
class CompraService
{
    public function __construct(private readonly InventarioService $inventario) {}

    /**
     * @param  array  $datos  ['proveedor_id','numero_factura','fecha_compra','fecha_recepcion','descuento','notas','detalles'=>[], 'registrar_gasto'=>bool, 'tipo_pago','metodo_pago_id','monto_pagado']
     */
    public function registrar(array $datos): Compra
    {
        $detalles = $datos['detalles'] ?? [];

        if (count($detalles) === 0) {
            throw ValidationException::withMessages(['detalles' => 'La compra debe tener al menos un producto.']);
        }

        return DB::transaction(function () use ($datos, $detalles) {
            $compra = Compra::create([
                'proveedor_id' => $datos['proveedor_id'] ?? null,
                'numero_factura' => $datos['numero_factura'] ?? null,
                'fecha_compra' => $datos['fecha_compra'] ?? now()->toDateString(),
                'fecha_recepcion' => $datos['fecha_recepcion'] ?? now()->toDateString(),
                'subtotal' => '0',
                'descuento' => Money::normal($datos['descuento'] ?? '0'),
                'total' => '0',
                'total_pagado' => '0',
                'saldo_pendiente' => '0',
                'estado' => 'recibido',
                'notas' => $datos['notas'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $subtotal = Money::cero();

            foreach ($detalles as $detalle) {
                $subtotal = Money::add($subtotal, $this->registrarDetalle($compra, $detalle, $datos));
            }

            $total = Money::sub($subtotal, (string) $compra->descuento);
            if (Money::menorQue($total, '0')) {
                throw ValidationException::withMessages(['descuento' => 'El descuento no puede superar el subtotal.']);
            }

            $compra->update(['subtotal' => $subtotal, 'total' => $total, 'saldo_pendiente' => $total]);

            // Gasto asociado (evita duplicar el costo): se registra una sola vez.
            if ($datos['registrar_gasto'] ?? true) {
                $gasto = Gasto::create([
                    'monto' => $total,
                    'categoria_gasto_id' => $datos['categoria_gasto_id'] ?? $this->categoriaProductosId(),
                    'estado' => 'pagado',
                    'descripcion' => 'Compra a proveedor'.($compra->numero_factura ? " factura {$compra->numero_factura}" : '')." (#{$compra->id})",
                    'fecha' => $compra->fecha_compra,
                    'metodo_pago_id' => $datos['metodo_pago_id'] ?? null,
                    'proveedor_id' => $compra->proveedor_id,
                    'compra_id' => $compra->id,
                    'created_by' => Auth::id(),
                ]);

                $compra->update(['gasto_id' => $gasto->id]);
            }

            // Pago inicial (opcional): efectivo/tarjeta y movimiento de caja.
            $montoPagado = Money::normal($datos['monto_pagado'] ?? '0');
            if (Money::mayorQue($montoPagado, '0')) {
                $this->registrarPago($compra, [
                    'metodo_pago_id' => $datos['metodo_pago_id'],
                    'monto' => $montoPagado,
                    'referencia' => $datos['numero_factura'] ?? null,
                ], $datos['afectar_caja'] ?? true);
            }

            AuditoriaService::registrar('crear', 'compras', Compra::class, $compra->id, [], [
                'total' => $total,
                'proveedor_id' => $compra->proveedor_id,
            ]);

            return $compra->fresh(['detalles.producto', 'pagos']);
        });
    }

    private function registrarDetalle(Compra $compra, array $detalle, array $datos): string
    {
        $producto = Producto::findOrFail($detalle['producto_id']);

        $unidad = $producto->unidad_control;
        $cantidadEnvases = Money::normal($detalle['cantidad_envases'] ?? '1', Money::ESCALA_CANTIDAD);
        $contenido = Money::normal(
            $detalle['contenido_envase'] ?? ($producto->contenido_envase ?: '1'),
            Money::ESCALA_CANTIDAD
        );

        // En productos por gramos/ml la cantidad total = envases * contenido.
        $cantidad = $unidad === Producto::UNIDAD_UNIDAD
            ? Money::normal($detalle['cantidad'] ?? $cantidadEnvases, Money::ESCALA_CANTIDAD)
            : Money::mul($cantidadEnvases, $contenido, Money::ESCALA_CANTIDAD);

        $precioUnitario = Money::normal($detalle['precio_unitario'] ?? '0');
        $descuento = Money::normal($detalle['descuento'] ?? '0');

        // El precio unitario de la compra suele ser por envase; el costo por
        // unidad de inventario se reparte según el contenido.
        $subtotal = Money::sub(Money::mul($precioUnitario, $cantidadEnvases, Money::ESCALA_DINERO), $descuento);

        $compraDetalle = CompraDetalle::create([
            'compra_id' => $compra->id,
            'producto_id' => $producto->id,
            'unidad' => $unidad,
            'cantidad' => $cantidad,
            'cantidad_envases' => $cantidadEnvases,
            'contenido_envase' => $contenido,
            'precio_unitario' => $precioUnitario,
            'descuento' => $descuento,
            'subtotal' => $subtotal,
        ]);

        // Crea el lote con el costo unitario real (subtotal / cantidad total).
        $this->inventario->registrarEntrada($producto, $cantidad, $subtotal, [
            'tipo' => InventarioService::TIPO_COMPRA,
            'proveedor_id' => $compra->proveedor_id,
            'compra_id' => $compra->id,
            'fecha_ingreso' => $compra->fecha_recepcion ?? now()->toDateString(),
            'fecha_vencimiento' => $detalle['fecha_vencimiento'] ?? $producto->fecha_vencimiento,
            'referencia' => $compra->numero_factura,
            'motivo' => 'Compra #'.$compra->id,
            'modulo' => 'compras',
            'modulo_id' => $compra->id,
            'notas' => 'Lote de compra #'.$compra->id,
        ]);

        // Actualiza costo de referencia del producto.
        $producto->update([
            'precio_costo' => $precioUnitario,
            'costo_envase' => $precioUnitario,
            'contenido_envase' => $contenido,
            'fecha_compra' => $compra->fecha_compra,
            'proveedor_id' => $compra->proveedor_id ?? $producto->proveedor_id,
        ]);
        $producto->recalcularCostoUnitario();
        $producto->save();

        return $subtotal;
    }

    /**
     * Registra un pago/abono a la compra. No duplica el gasto: solo mueve caja.
     */
    public function registrarPago(Compra $compra, array $pago, bool $afectarCaja = true): Compra
    {
        return DB::transaction(function () use ($compra, $pago, $afectarCaja) {
            $compra = Compra::whereKey($compra->id)->lockForUpdate()->firstOrFail();
            $monto = Money::normal($pago['monto'] ?? '0');

            if (! Money::mayorQue($monto, '0')) {
                throw ValidationException::withMessages(['monto' => 'El monto del pago debe ser mayor que cero.']);
            }

            if (Money::mayorQue(Money::add((string) $compra->total_pagado, $monto), (string) $compra->total)) {
                throw ValidationException::withMessages(['monto' => 'El pago supera el saldo pendiente de la compra.']);
            }

            $compraPago = CompraPago::create([
                'compra_id' => $compra->id,
                'metodo_pago_id' => $pago['metodo_pago_id'],
                'monto' => $monto,
                'tipo' => $pago['tipo'] ?? 'pago',
                'fecha_hora' => now(),
                'referencia' => $pago['referencia'] ?? null,
                'notas' => $pago['notas'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $totalPagado = Money::add((string) $compra->total_pagado, $monto);
            $saldo = Money::sub((string) $compra->total, $totalPagado);

            $compra->update([
                'total_pagado' => $totalPagado,
                'saldo_pendiente' => $saldo,
            ]);

            if ($afectarCaja && \App\Models\Caja::cajaAbierta()) {
                $movimiento = app(CajaService::class)->registrarMovimiento([
                    'tipo' => 'egreso',
                    'monto' => $monto,
                    'metodo_pago_id' => $compraPago->metodo_pago_id,
                    'concepto' => 'Pago compra #'.$compra->id,
                    'descripcion' => $pago['notas'] ?? null,
                    'referencia' => $compra->numero_factura,
                ]);
                $compraPago->update(['caja_movimiento_id' => $movimiento->id]);
            }

            return $compra->fresh(['pagos']);
        });
    }

    private function categoriaProductosId(): ?int
    {
        return \App\Models\CategoriaGasto::where('nombre', 'Productos')->value('id');
    }
}
