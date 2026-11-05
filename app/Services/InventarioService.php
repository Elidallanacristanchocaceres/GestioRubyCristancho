<?php

namespace App\Services;

use App\Models\InventarioLote;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Inventario por gramos / mililitros / unidades.
 *
 * Reglas clave:
 *  - El stock del producto se lleva en el lote (FIFO). Al consumir gramos se
 *    descuenta exactamente lo usado, nunca un envase completo.
 *  - Cada salida genera un movimiento con costo unitario y costo total reales.
 *  - Todo se ejecuta dentro de transacciones; el llamador abre la transacción
 *    (para poder agrupar varias operaciones) o este servicio la abre sola.
 */
class InventarioService
{
    public const TIPO_ENTRADA = 'entrada';

    public const TIPO_SALIDA = 'salida';

    public const TIPO_AJUSTE = 'ajuste';

    public const TIPO_COMPRA = 'compra';

    public const TIPO_VENTA = 'venta';

    public const TIPO_MERMA = 'merma';

    public const TIPO_DEVOLUCION = 'devolucion';

    /**
     * Registra una entrada de inventario creando un lote con su costo unitario.
     * Devuelve el lote creado.
     */
    public function registrarEntrada(
        Producto $producto,
        string $cantidad,
        string $costoTotal,
        array $opciones = []
    ): InventarioLote {
        $cantidad = Money::normal($cantidad, Money::ESCALA_CANTIDAD);

        // Un lote "por medir" (envase empezado o de contenido desconocido) se
        // registra con cantidad cero: queda anotado, pero sin sumar stock.
        $medido = (bool) ($opciones['medido'] ?? true);

        if (! $medido) {
            if (Money::menorQue($cantidad, '0', Money::ESCALA_CANTIDAD)) {
                throw ValidationException::withMessages(['cantidad' => 'La cantidad no puede ser negativa.']);
            }
        } else {
            if (Money::menorQue($cantidad, '0.001', Money::ESCALA_CANTIDAD)) {
                throw ValidationException::withMessages(['cantidad' => 'La cantidad debe ser mayor que cero.']);
            }

            if (Money::igual($cantidad, '0', Money::ESCALA_CANTIDAD)) {
                throw ValidationException::withMessages(['cantidad' => 'La cantidad debe ser mayor que cero.']);
            }
        }

        return DB::transaction(function () use ($producto, $cantidad, $costoTotal, $opciones, $medido) {
            $producto = Producto::whereKey($producto->id)->lockForUpdate()->firstOrFail();

            $costoUnitario = $opciones['costo_unitario']
                ?? Money::div($costoTotal, $cantidad, Money::ESCALA_COSTO_UNITARIO);

            $costoTotalNormalizado = Money::mul($costoUnitario, $cantidad, Money::ESCALA_DINERO);

            // Un envase empezado cuyo contenido no se ha medido se REGISTRA
            // (queda en el kardex y se ve en la ficha) pero NO suma existencias:
            // no se puede suponer cuánto queda dentro.
            $estadoEnvase = $opciones['estado_envase'] ?? ($medido ? 'nuevo' : 'empezado');

            $cantidadLote = $medido
                ? $cantidad
                : Money::cero();

            $lote = InventarioLote::create([
                'producto_id' => $producto->id,
                'proveedor_id' => $opciones['proveedor_id'] ?? $producto->proveedor_id,
                'compra_id' => $opciones['compra_id'] ?? null,
                'cantidad_inicial' => $cantidadLote,
                'cantidad_disponible' => $cantidadLote,
                'costo_unitario' => $costoUnitario,
                'costo_total' => $costoTotalNormalizado,
                'fecha_ingreso' => $opciones['fecha_ingreso'] ?? now()->toDateString(),
                'fecha_vencimiento' => $opciones['fecha_vencimiento'] ?? $producto->fecha_vencimiento,
                'estado' => $medido ? 'activo' : 'pendiente_medicion',
                'estado_envase' => $estadoEnvase,
                'medido' => $medido,
                'envases' => $opciones['envases'] ?? 0,
                'notas' => $opciones['notas'] ?? null,
            ]);

            $stockAnterior = (string) $producto->stock;
            $stockNuevo = Money::add($stockAnterior, $cantidadLote, Money::ESCALA_CANTIDAD);

            $producto->stock = $stockNuevo;
            // El costo por gramo es un valor propio del salón: NO se sobrescribe
            // automáticamente al recibir mercancía. Solo se actualiza si se pide
            // explícitamente con 'actualizar_costo' => true.
            if ($opciones['actualizar_costo'] ?? false) {
                $producto->costo_unitario = $costoUnitario;
            }
            $producto->save();

            $this->registrarMovimiento([
                'producto_id' => $producto->id,
                'lote_id' => $lote->id,
                'tipo' => $opciones['tipo'] ?? self::TIPO_COMPRA,
                'unidad' => $producto->unidad_control,
                'cantidad' => $cantidadLote,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo' => $stockNuevo,
                'precio_unitario' => $costoUnitario,
                'costo_unitario' => $costoUnitario,
                'costo_total' => $costoTotalNormalizado,
                'referencia' => $opciones['referencia'] ?? null,
                'motivo' => $opciones['motivo'] ?? 'Entrada de inventario',
                'modulo' => $opciones['modulo'] ?? null,
                'modulo_id' => $opciones['modulo_id'] ?? null,
            ]);

            return $lote;
        });
    }

    /**
     * Consume cantidad de un producto usando FIFO entre sus lotes.
     * Devuelve [cantidad_consumida, costo_total, detalle_lotes].
     *
     * @param  bool  $permitirNegativo  requiere autorización explícita y queda auditado
     */
    public function consumir(
        Producto $producto,
        string $cantidad,
        array $opciones = []
    ): array {
        $cantidad = Money::normal($cantidad, Money::ESCALA_CANTIDAD);

        if (Money::menorQue($cantidad, '0.001', Money::ESCALA_CANTIDAD)) {
            throw ValidationException::withMessages(['cantidad' => 'La cantidad a consumir debe ser mayor que cero.']);
        }

        return DB::transaction(function () use ($producto, $cantidad, $opciones) {
            $producto = Producto::whereKey($producto->id)->lockForUpdate()->firstOrFail();
            $permitirNegativo = (bool) ($opciones['permitir_negativo'] ?? false);

            $disponible = (string) $producto->stock;

            if (Money::menorQue($disponible, $cantidad, Money::ESCALA_CANTIDAD) && ! $permitirNegativo) {
                throw ValidationException::withMessages([
                    'cantidad' => "Inventario insuficiente de {$producto->nombre}. Disponible: {$disponible} {$producto->unidadAbrev()}.",
                ]);
            }

            $restante = $cantidad;
            $costoTotal = Money::cero();
            $detalles = [];

            $lotes = InventarioLote::where('producto_id', $producto->id)
                ->where('estado', 'activo')
                ->where('cantidad_disponible', '>', 0)
                ->orderBy('fecha_ingreso')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($lotes as $lote) {
                if (Money::igual($restante, '0', Money::ESCALA_CANTIDAD)) {
                    break;
                }

                $disponibleLote = (string) $lote->cantidad_disponible;
                $aTomar = Money::menorQue($disponibleLote, $restante, Money::ESCALA_CANTIDAD)
                    ? $disponibleLote
                    : $restante;

                if (Money::igual($aTomar, '0', Money::ESCALA_CANTIDAD)) {
                    continue;
                }

                $costoLote = Money::mul((string) $lote->costo_unitario, $aTomar);

                $lote->cantidad_disponible = Money::sub($disponibleLote, $aTomar, Money::ESCALA_CANTIDAD);
                if (Money::igual((string) $lote->cantidad_disponible, '0', Money::ESCALA_CANTIDAD)) {
                    $lote->estado = 'agotado';
                }
                $lote->save();

                $costoTotal = Money::add($costoTotal, $costoLote);

                $this->registrarMovimiento([
                    'producto_id' => $producto->id,
                    'lote_id' => $lote->id,
                    'tipo' => $opciones['tipo'] ?? self::TIPO_SALIDA,
                    'unidad' => $producto->unidad_control,
                    'cantidad' => Money::mul($aTomar, '-1', Money::ESCALA_CANTIDAD),
                    'stock_anterior' => (string) $producto->stock,
                    'stock_nuevo' => Money::sub((string) $producto->stock, $aTomar, Money::ESCALA_CANTIDAD),
                    'precio_unitario' => (string) $lote->costo_unitario,
                    'costo_unitario' => (string) $lote->costo_unitario,
                    'costo_total' => $costoLote,
                    'referencia' => $opciones['referencia'] ?? null,
                    'motivo' => $opciones['motivo'] ?? 'Consumo de inventario',
                    'modulo' => $opciones['modulo'] ?? null,
                    'modulo_id' => $opciones['modulo_id'] ?? null,
                    'autorizado_por' => $opciones['autorizado_por'] ?? null,
                ]);

                $producto->stock = Money::sub((string) $producto->stock, $aTomar, Money::ESCALA_CANTIDAD);

                $detalles[] = ['lote_id' => $lote->id, 'cantidad' => $aTomar, 'costo' => $costoLote];

                $restante = Money::sub($restante, $aTomar, Money::ESCALA_CANTIDAD);
            }

            // Si se autorizó consumo sin stock, se registra el faltante dejando
            // constancia en auditoría (no hay lote del cual descontar).
            if (Money::mayorQue($restante, '0', Money::ESCALA_CANTIDAD)) {
                if (! $permitirNegativo) {
                    throw ValidationException::withMessages([
                        'cantidad' => "No hay lotes suficientes de {$producto->nombre}.",
                    ]);
                }

                $costoFaltante = Money::mul((string) ($producto->costo_unitario ?? 0), $restante);
                $costoTotal = Money::add($costoTotal, $costoFaltante);

                $this->registrarMovimiento([
                    'producto_id' => $producto->id,
                    'lote_id' => null,
                    'tipo' => self::TIPO_AJUSTE,
                    'unidad' => $producto->unidad_control,
                    'cantidad' => Money::mul($restante, '-1', Money::ESCALA_CANTIDAD),
                    'stock_anterior' => (string) $producto->stock,
                    'stock_nuevo' => Money::sub((string) $producto->stock, $restante, Money::ESCALA_CANTIDAD),
                    'costo_unitario' => (string) ($producto->costo_unitario ?? 0),
                    'costo_total' => $costoFaltante,
                    'motivo' => 'Consumo sin stock autorizado',
                    'modulo' => $opciones['modulo'] ?? null,
                    'modulo_id' => $opciones['modulo_id'] ?? null,
                    'autorizado_por' => $opciones['autorizado_por'] ?? Auth::id(),
                ]);

                $producto->stock = Money::sub((string) $producto->stock, $restante, Money::ESCALA_CANTIDAD);
                $detalles[] = ['lote_id' => null, 'cantidad' => $restante, 'costo' => $costoFaltante];
            }

            $producto->save();

            return [
                'cantidad' => $cantidad,
                'costo_total' => $costoTotal,
                'detalles' => $detalles,
            ];
        });
    }

    /**
     * Devuelve inventario a los lotes desde donde se había consumido.
     * Se usa para revertir consumos de servicios anulados.
     *
     * @param  array  $detalles  [['lote_id' => x, 'cantidad' => y, 'costo' => z], ...]
     */
    public function devolver(Producto $producto, array $detalles, array $opciones = []): void
    {
        DB::transaction(function () use ($producto, $detalles, $opciones) {
            $producto = Producto::whereKey($producto->id)->lockForUpdate()->firstOrFail();

            foreach ($detalles as $detalle) {
                $cantidad = Money::normal($detalle['cantidad'], Money::ESCALA_CANTIDAD);

                if ($detalle['lote_id']) {
                    $lote = InventarioLote::whereKey($detalle['lote_id'])->lockForUpdate()->first();
                    if ($lote) {
                        $lote->cantidad_disponible = Money::add((string) $lote->cantidad_disponible, $cantidad, Money::ESCALA_CANTIDAD);
                        if ($lote->estado === 'agotado' && Money::mayorQue((string) $lote->cantidad_disponible, '0', Money::ESCALA_CANTIDAD)) {
                            $lote->estado = 'activo';
                        }
                        $lote->save();
                    }
                }

                $stockAnterior = (string) $producto->stock;
                $producto->stock = Money::add($stockAnterior, $cantidad, Money::ESCALA_CANTIDAD);

                $this->registrarMovimiento([
                    'producto_id' => $producto->id,
                    'lote_id' => $detalle['lote_id'] ?? null,
                    'tipo' => $opciones['tipo'] ?? self::TIPO_DEVOLUCION,
                    'unidad' => $producto->unidad_control,
                    'cantidad' => $cantidad,
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo' => (string) $producto->stock,
                    'costo_unitario' => isset($detalle['costo'], $detalle['cantidad']) && Money::mayorQue((string) $detalle['cantidad'], '0', Money::ESCALA_CANTIDAD)
                        ? Money::div((string) $detalle['costo'], (string) $detalle['cantidad'])
                        : ($producto->costo_unitario ?? '0'),
                    'costo_total' => $detalle['costo'] ?? null,
                    'referencia' => $opciones['referencia'] ?? null,
                    'motivo' => $opciones['motivo'] ?? 'Devolución a inventario',
                    'modulo' => $opciones['modulo'] ?? null,
                    'modulo_id' => $opciones['modulo_id'] ?? null,
                ]);
            }

            $producto->save();
        });
    }

    /**
     * Ajuste manual de inventario (positivo o negativo) con motivo obligatorio.
     */
    public function ajustar(Producto $producto, string $nuevaCantidad, string $motivo): void
    {
        $nuevaCantidad = Money::normal($nuevaCantidad, Money::ESCALA_CANTIDAD);

        if (trim($motivo) === '') {
            throw ValidationException::withMessages(['motivo' => 'El motivo del ajuste es obligatorio.']);
        }

        $anterior = (string) $producto->fresh()->stock;
        $diferencia = Money::sub($nuevaCantidad, $anterior, Money::ESCALA_CANTIDAD);

        if (Money::igual($diferencia, '0', Money::ESCALA_CANTIDAD)) {
            return;
        }

        if (Money::mayorQue($diferencia, '0', Money::ESCALA_CANTIDAD)) {
            $this->registrarEntrada($producto, $diferencia, Money::mul($diferencia, (string) ($producto->costo_unitario ?? 0)), [
                'tipo' => self::TIPO_AJUSTE,
                'motivo' => $motivo,
                'notas' => $motivo,
            ]);

            return;
        }

        $this->consumir($producto, Money::mul($diferencia, '-1', Money::ESCALA_CANTIDAD), [
            'tipo' => self::TIPO_AJUSTE,
            'motivo' => $motivo,
            'permitir_negativo' => true,
            'autorizado_por' => Auth::id(),
        ]);
    }

    /**
     * Registra una merma (desperdicio) descontando inventario.
     */
    public function registrarMerma(Producto $producto, string $cantidad, string $motivo): array
    {
        return $this->consumir($producto, $cantidad, [
            'tipo' => self::TIPO_MERMA,
            'motivo' => $motivo ?: 'Merma / desperdicio',
        ]);
    }

    /**
     * Mide un envase que estaba pendiente (empezado y sin pesar).
     *
     * La cantidad medida es la que realmente queda dentro; recién entonces
     * suma a las existencias y queda disponible para consumo FIFO.
     */
    public function medirEnvase(InventarioLote $lote, string $cantidad): InventarioLote
    {
        $cantidad = Money::normal($cantidad, Money::ESCALA_CANTIDAD);

        if (Money::menorQue($cantidad, '0', Money::ESCALA_CANTIDAD)) {
            throw ValidationException::withMessages(['cantidad' => 'La cantidad medida no puede ser negativa.']);
        }

        return DB::transaction(function () use ($lote, $cantidad) {
            $lote = InventarioLote::whereKey($lote->id)->lockForUpdate()->firstOrFail();
            $producto = Producto::whereKey($lote->producto_id)->lockForUpdate()->firstOrFail();

            $disponible = (string) $lote->cantidad_disponible;
            $stockAnterior = (string) $producto->stock;
            $stockNuevo = Money::add($stockAnterior, Money::sub($cantidad, $disponible, Money::ESCALA_CANTIDAD), Money::ESCALA_CANTIDAD);

            $lote->cantidad_inicial = $cantidad;
            $lote->cantidad_disponible = $cantidad;
            $lote->medido = true;
            $lote->estado = Money::mayorQue($cantidad, '0', Money::ESCALA_CANTIDAD) ? 'activo' : 'agotado';
            $lote->save();

            $producto->stock = $stockNuevo;
            $producto->save();

            $this->registrarMovimiento([
                'producto_id' => $producto->id,
                'lote_id' => $lote->id,
                'tipo' => self::TIPO_AJUSTE,
                'unidad' => $producto->unidad_control,
                'cantidad' => Money::sub($cantidad, $disponible, Money::ESCALA_CANTIDAD),
                'stock_anterior' => $stockAnterior,
                'stock_nuevo' => $stockNuevo,
                'precio_unitario' => (string) $lote->costo_unitario,
                'costo_unitario' => (string) $lote->costo_unitario,
                'costo_total' => Money::mul((string) $lote->costo_unitario, $cantidad),
                'motivo' => 'Envase pesado: contenido medido',
                'modulo' => 'inventario',
                'modulo_id' => $lote->id,
            ]);

            return $lote->refresh();
        });
    }

    public function stock(Producto $producto): string
    {
        return (string) Producto::whereKey($producto->id)->value('stock');
    }

    public function lotesDisponibles(Producto $producto): Collection
    {
        return InventarioLote::where('producto_id', $producto->id)
            ->where('cantidad_disponible', '>', 0)
            ->orderBy('fecha_ingreso')
            ->get();
    }

    private function registrarMovimiento(array $datos): InventarioMovimiento
    {
        $datos['created_by'] = $datos['created_by'] ?? Auth::id();

        return InventarioMovimiento::create($datos);
    }
}
