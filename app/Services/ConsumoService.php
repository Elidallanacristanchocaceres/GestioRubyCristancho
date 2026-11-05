<?php

namespace App\Services;

use App\Models\Consumo;
use App\Models\ConsumoServicio;
use App\Models\Producto;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Registro diario de productos: cabecera por día con el precio cobrado al
 * cliente y detalle de los gramos gastados por producto.
 *
 * Reglas clave:
 *  - Todo se mide y se guarda en GRAMOS (RegistroDiarioService::UNIDAD).
 *  - Los tintes cuestan COSTO_GRAMO_TINTE ($450) por gramo. Tratamientos y
 *    coactivadores usan el precio que ya tienen configurado en su ficha.
 *  - El costo unitario queda congelado en la línea (histórico inmutable).
 *  - El descuento de inventario SÍ usa FIFO y se registra en el kardex.
 *  - El token hace idempotente el guardado: repetir el envío no descuenta dos veces.
 *  - Anular, editar y eliminar devuelven el stock exactamente a los lotes de los que salió.
 */
class ConsumoService
{
    public function __construct(private readonly InventarioService $inventario) {}

    /**
     * @param  array  $datos  cabecera + 'detalles' => [producto_id, cantidad, costo_unitario?, notas?]
     */
    public function registrar(array $datos): Consumo
    {
        $detalles = $this->detallesValidos($datos);

        $token = ($datos['token'] ?? null) ?: (string) Str::uuid();

        // Reenvío duplicado (doble clic / recarga del POST): no se vuelve a descontar.
        $existente = Consumo::where('token', $token)->first();
        if ($existente) {
            return $existente->load(['detalles.producto', 'cliente', 'servicio', 'estilista']);
        }

        return DB::transaction(function () use ($datos, $detalles, $token) {
            $consumo = Consumo::create([
                'token' => $token,
                'cliente_id' => $datos['cliente_id'] ?? null,
                'cita_id' => $datos['cita_id'] ?? null,
                'venta_id' => $datos['venta_id'] ?? null,
                'servicio_id' => $datos['servicio_id'] ?? null,
                'estilista_id' => $datos['estilista_id'] ?? null,
                'fecha' => $datos['fecha'] ?? now()->toDateString(),
                'observaciones' => $datos['observaciones'] ?? null,
                'descripcion' => $datos['descripcion'] ?? $datos['observaciones'] ?? null,
                'precio_cobrado' => $this->precio($datos['precio_cobrado'] ?? null),
                'costo_total' => '0',
                'estado' => Consumo::ESTADO_ACTIVO,
                'created_by' => Auth::id(),
            ]);

            $total = $this->aplicarDetalles($consumo, $detalles);

            $consumo->update(['costo_total' => $total]);

            AuditoriaService::registrar('crear', 'consumos', Consumo::class, $consumo->id, [], [
                'costo_total' => $total,
                'precio_cobrado' => $consumo->precio_cobrado,
                'cliente_id' => $consumo->cliente_id,
                'productos' => count($detalles),
            ]);

            return $consumo->fresh(['detalles.producto', 'cliente', 'servicio', 'estilista']);
        });
    }

    /**
     * Guarda un único producto (línea) sobre la cabecera de la jornada.
     *
     * Si la cabecera identificada por el token todavía no existe, se crea;
     * si ya existe, se reutiliza. Descuenta el inventario de la línea y
     * devuelve el consumo con todas sus líneas.
     *
     * @param  array  $cabecera  token, fecha, cliente_id, servicio_id, estilista_id, precio_cobrado, descripcion, observaciones
     * @param  array  $linea      producto_id, cantidad, costo_unitario?, notas?, detalle_id? (para actualizar una ya guardada)
     */
    public function guardarLinea(array $cabecera, array $linea): Consumo
    {
        $token = ($cabecera['token'] ?? null) ?: (string) Str::uuid();
        $detalleId = $linea['detalle_id'] ?? null;
        unset($linea['detalle_id']);

        return DB::transaction(function () use ($cabecera, $linea, $token, $detalleId) {
            $consumo = Consumo::where('token', $token)->lockForUpdate()->first();

            if ($consumo && ! $consumo->esActivo()) {
                throw ValidationException::withMessages([
                    'consumo' => 'No se puede agregar productos a un registro anulado.',
                ]);
            }

            if (! $consumo) {
                $consumo = Consumo::create([
                    'token' => $token,
                    'cliente_id' => $cabecera['cliente_id'] ?? null,
                    'servicio_id' => $cabecera['servicio_id'] ?? null,
                    'estilista_id' => $cabecera['estilista_id'] ?? null,
                    'fecha' => $cabecera['fecha'] ?? now()->toDateString(),
                    'observaciones' => $cabecera['observaciones'] ?? null,
                    'descripcion' => $cabecera['descripcion'] ?? null,
                    'precio_cobrado' => $this->precio($cabecera['precio_cobrado'] ?? null),
                    'costo_total' => '0',
                    'estado' => Consumo::ESTADO_ACTIVO,
                    'created_by' => Auth::id(),
                ]);
            } else {
                $consumo->update([
                    'cliente_id' => $cabecera['cliente_id'] ?? $consumo->cliente_id,
                    'servicio_id' => $cabecera['servicio_id'] ?? $consumo->servicio_id,
                    'estilista_id' => $cabecera['estilista_id'] ?? $consumo->estilista_id,
                    'fecha' => $cabecera['fecha'] ?? $consumo->fecha?->toDateString(),
                    'observaciones' => array_key_exists('observaciones', $cabecera)
                        ? $cabecera['observaciones']
                        : $consumo->observaciones,
                    'descripcion' => array_key_exists('descripcion', $cabecera)
                        ? $cabecera['descripcion']
                        : $consumo->descripcion,
                    'precio_cobrado' => array_key_exists('precio_cobrado', $cabecera)
                        ? $this->precio($cabecera['precio_cobrado'])
                        : $consumo->precio_cobrado,
                ]);
            }

            // Si es una línea ya guardada, se devuelve su stock y se reemplaza.
            if ($detalleId) {
                $lineaPrevia = ConsumoServicio::whereKey($detalleId)
                    ->where('consumo_id', $consumo->id)
                    ->lockForUpdate()
                    ->first();

                if ($lineaPrevia) {
                    if ($lineaPrevia->estado === Consumo::ESTADO_ACTIVO && $lineaPrevia->producto) {
                        $this->inventario->devolver($lineaPrevia->producto, $lineaPrevia->lotes_consumidos ?? [], [
                            'tipo' => InventarioService::TIPO_DEVOLUCION,
                            'motivo' => "Línea reemplazada del consumo #{$consumo->id}",
                            'modulo' => 'consumos',
                            'modulo_id' => $consumo->id,
                            'referencia' => (string) $consumo->id,
                        ]);
                    }
                    $consumo->update([
                        'costo_total' => Money::sub((string) $consumo->costo_total, (string) $lineaPrevia->costo_total),
                    ]);
                    $lineaPrevia->delete();
                }
            }

            $agregado = $this->aplicarDetalles($consumo, [$linea]);
            $consumo->update([
                'costo_total' => Money::add((string) $consumo->costo_total, $agregado),
            ]);

            AuditoriaService::registrar($detalleId ? 'actualizar' : 'crear', 'consumos', Consumo::class, $consumo->id, [], [
                'linea_producto_id' => $linea['producto_id'] ?? null,
                'cantidad' => $linea['cantidad'] ?? null,
                'costo_total' => $consumo->costo_total,
            ], 'Producto guardado en el registro diario');

            return $consumo->fresh(['detalles.producto', 'cliente', 'servicio', 'estilista']);
        });
    }

    /**
     * Actualiza solo los datos de la cabecera (qué se hizo, cuánto se cobró),
     * sin tocar las líneas ya registradas.
     */
    public function actualizarCabecera(Consumo $consumo, array $datos): Consumo
    {
        return DB::transaction(function () use ($consumo, $datos) {
            $consumo = Consumo::whereKey($consumo->id)->lockForUpdate()->firstOrFail();

            if (! $consumo->esActivo()) {
                throw ValidationException::withMessages([
                    'consumo' => 'No se puede modificar un registro anulado.',
                ]);
            }

            $consumo->update([
                'fecha' => $datos['fecha'] ?? $consumo->fecha->toDateString(),
                'descripcion' => array_key_exists('descripcion', $datos)
                    ? $datos['descripcion']
                    : $consumo->descripcion,
                'precio_cobrado' => array_key_exists('precio_cobrado', $datos)
                    ? $this->precio($datos['precio_cobrado'])
                    : $consumo->precio_cobrado,
                'estilista_id' => array_key_exists('estilista_id', $datos)
                    ? $datos['estilista_id']
                    : $consumo->estilista_id,
                'observaciones' => array_key_exists('observaciones', $datos)
                    ? $datos['observaciones']
                    : $consumo->observaciones,
            ]);

            AuditoriaService::registrar('actualizar', 'consumos', Consumo::class, $consumo->id, [], [
                'descripcion' => $consumo->descripcion,
                'precio_cobrado' => $consumo->precio_cobrado,
            ], 'Datos del registro diario actualizados');

            return $consumo->fresh(['detalles.producto', 'cliente', 'servicio', 'estilista']);
        });
    }

    /**
     * Quita una línea del registro devolviendo su stock a los lotes originales.
     */
    public function quitarLinea(Consumo $consumo, int $detalleId): Consumo
    {
        return DB::transaction(function () use ($consumo, $detalleId) {
            $consumo = Consumo::whereKey($consumo->id)->lockForUpdate()->firstOrFail();

            if (! $consumo->esActivo()) {
                throw ValidationException::withMessages([
                    'consumo' => 'No se puede modificar un registro anulado.',
                ]);
            }

            $linea = ConsumoServicio::whereKey($detalleId)
                ->where('consumo_id', $consumo->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($linea->estado === Consumo::ESTADO_ACTIVO && $linea->producto) {
                $this->inventario->devolver($linea->producto, $linea->lotes_consumidos ?? [], [
                    'tipo' => InventarioService::TIPO_DEVOLUCION,
                    'motivo' => "Línea eliminada del consumo #{$consumo->id}",
                    'modulo' => 'consumos',
                    'modulo_id' => $consumo->id,
                    'referencia' => (string) $consumo->id,
                ]);
            }

            $consumo->update([
                'costo_total' => Money::sub((string) $consumo->costo_total, (string) $linea->costo_total),
            ]);

            $linea->delete();

            AuditoriaService::registrar('actualizar', 'consumos', Consumo::class, $consumo->id,
                ['linea_producto_id' => $linea->producto_id, 'cantidad' => $linea->cantidad], [],
                'Producto quitado del registro diario');

            return $consumo->fresh(['detalles.producto', 'cliente', 'servicio', 'estilista']);
        });
    }

    /**
     * Edita un registro activo: devuelve el stock de las líneas anteriores,
     * actualiza la cabecera y vuelve a descontar con los nuevos detalles.
     */
    public function actualizar(Consumo $consumo, array $datos): Consumo
    {
        $detalles = $this->detallesValidos($datos);

        return DB::transaction(function () use ($consumo, $datos, $detalles) {
            $consumo = Consumo::whereKey($consumo->id)->lockForUpdate()->firstOrFail();

            if (! $consumo->esActivo()) {
                throw ValidationException::withMessages([
                    'consumo' => 'No se puede editar un registro anulado.',
                ]);
            }

            // Devuelve el inventario de las líneas actuales y las descarta.
            $this->devolverStock($consumo);
            $consumo->detalles()->delete();

            $consumo->update([
                // Cliente y servicio ya no se piden en el registro de productos:
                // si no vienen, se conservan los que ya tuvieran.
                'cliente_id' => $datos['cliente_id'] ?? $consumo->cliente_id,
                'servicio_id' => $datos['servicio_id'] ?? $consumo->servicio_id,
                'estilista_id' => $datos['estilista_id'] ?? $consumo->estilista_id,
                'fecha' => $datos['fecha'] ?? $consumo->fecha->toDateString(),
                'observaciones' => $datos['observaciones'] ?? null,
                'descripcion' => $datos['descripcion'] ?? null,
                'precio_cobrado' => $this->precio($datos['precio_cobrado'] ?? null),
                'costo_total' => '0',
            ]);

            $total = $this->aplicarDetalles($consumo, $detalles);
            $consumo->update(['costo_total' => $total]);

            AuditoriaService::registrar('actualizar', 'consumos', Consumo::class, $consumo->id, [], [
                'costo_total' => $total,
                'precio_cobrado' => $consumo->precio_cobrado,
                'productos' => count($detalles),
            ], 'Edición del registro diario');

            return $consumo->fresh(['detalles.producto', 'cliente', 'servicio', 'estilista']);
        });
    }

    /**
     * Anula el registro: devuelve el stock a sus lotes y conserva las líneas
     * como historial (marcadas como reversadas).
     */
    public function anular(Consumo $consumo): Consumo
    {
        return DB::transaction(function () use ($consumo) {
            $consumo = Consumo::whereKey($consumo->id)->lockForUpdate()->firstOrFail();

            if (! $consumo->esActivo()) {
                throw ValidationException::withMessages(['consumo' => 'Este registro ya está anulado.']);
            }

            $this->devolverStock($consumo);

            $consumo->update([
                'estado' => Consumo::ESTADO_REVERSADO,
                'reversado_por' => Auth::id(),
            ]);

            AuditoriaService::registrar('anular', 'consumos', Consumo::class, $consumo->id, [
                'estado' => Consumo::ESTADO_ACTIVO,
            ], [
                'estado' => Consumo::ESTADO_REVERSADO,
            ], 'Anulación del registro diario');

            return $consumo->fresh(['detalles.producto']);
        });
    }

    /**
     * Elimina definitivamente el registro, devolviendo antes el stock usado.
     */
    public function eliminar(Consumo $consumo): void
    {
        DB::transaction(function () use ($consumo) {
            $consumo = Consumo::whereKey($consumo->id)->lockForUpdate()->firstOrFail();

            if ($consumo->esActivo()) {
                $this->devolverStock($consumo);
            }

            $antes = [
                'fecha' => $consumo->fecha?->toDateString(),
                'costo_total' => $consumo->costo_total,
                'precio_cobrado' => $consumo->precio_cobrado,
                'productos' => $consumo->detalles()->count(),
            ];

            $consumo->detalles()->delete();
            $consumo->delete();

            AuditoriaService::registrar('eliminar', 'consumos', Consumo::class, $consumo->id, $antes, [], 'Eliminación del registro diario');
        });
    }

    /**
     * Devuelve el stock de las líneas activas a sus lotes y las marca reversadas.
     */
    private function devolverStock(Consumo $consumo): void
    {
        foreach ($consumo->detalles as $linea) {
            if ($linea->estado !== Consumo::ESTADO_ACTIVO || ! $linea->producto) {
                continue;
            }

            $this->inventario->devolver($linea->producto, $linea->lotes_consumidos ?? [], [
                'tipo' => InventarioService::TIPO_DEVOLUCION,
                'motivo' => "Reversión de consumo #{$consumo->id}",
                'modulo' => 'consumos',
                'modulo_id' => $consumo->id,
                'referencia' => (string) $consumo->id,
            ]);

            $linea->update([
                'estado' => Consumo::ESTADO_REVERSADO,
                'reversado_por' => Auth::id(),
            ]);
        }
    }

    /**
     * Descuenta inventario, congela costos y crea las líneas. Devuelve el total.
     */
    private function aplicarDetalles(Consumo $consumo, array $detalles): string
    {
        $total = Money::cero();

        foreach ($detalles as $linea) {
            $producto = Producto::whereKey($linea['producto_id'])->lockForUpdate()->firstOrFail();

            $cantidad = Money::normal($linea['cantidad'] ?? '0', Money::ESCALA_CANTIDAD);
            if (! Money::mayorQue($cantidad, '0', Money::ESCALA_CANTIDAD)) {
                throw ValidationException::withMessages([
                    'detalles' => "La cantidad de {$producto->nombre} debe ser mayor que cero.",
                ]);
            }

            $costoUnitario = $this->costoPorUnidad($producto, $linea['costo_unitario'] ?? null);
            $costoLinea = Money::mul($cantidad, $costoUnitario, Money::ESCALA_DINERO);

            // Descuenta inventario (FIFO). El registro diario SIEMPRE guarda el
            // consumo real del salón: si el stock aún no se cargó, el faltante
            // queda negativo y auditado (no se bloquea el trabajo del día).
            $resultado = $this->inventario->consumir($producto, $cantidad, [
                'tipo' => InventarioService::TIPO_SALIDA,
                'motivo' => "Consumo en servicio (registro #{$consumo->id})",
                'modulo' => 'consumos',
                'modulo_id' => $consumo->id,
                'referencia' => (string) $consumo->id,
                'permitir_negativo' => true,
            ]);

            ConsumoServicio::create([
                'consumo_id' => $consumo->id,
                'cita_id' => $consumo->cita_id,
                'venta_id' => $consumo->venta_id,
                'producto_id' => $producto->id,
                'estilista_id' => $consumo->estilista_id,
                'cantidad' => $cantidad,
                'unidad' => RegistroDiarioService::UNIDAD,
                'costo_unitario' => $costoUnitario,
                'costo_total' => $costoLinea,
                'lotes_consumidos' => $resultado['detalles'],
                'estado' => Consumo::ESTADO_ACTIVO,
                'notas' => $linea['notas'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $total = Money::add($total, $costoLinea);
        }

        return $total;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detallesValidos(array $datos): array
    {
        $detalles = $datos['detalles'] ?? [];

        if (count($detalles) === 0) {
            throw ValidationException::withMessages(['detalles' => 'Debe registrar al menos un producto consumido.']);
        }

        return $detalles;
    }

    private function precio(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return Money::normal($valor, Money::ESCALA_DINERO);
    }

    /**
     * Valor por gramo a usar en la línea (siempre se pesa en gramos).
     *
     *  - Tintes: regla fija del salón, $450 por gramo.
     *  - Tratamientos y coactivadores: el costo por unidad ya configurado
     *    en la ficha del producto. No se sustituye por el de los tintes.
     *  - Sin precio configurado: se usa el escrito en la línea (opcional).
     */
    private function costoPorUnidad(Producto $producto, mixed $solicitado): string
    {
        $configurado = RegistroDiarioService::costoPorGramo($producto);

        if ($configurado !== null) {
            return $configurado;
        }

        if ($solicitado !== null && $solicitado !== '') {
            $valor = Money::normal($solicitado, Money::ESCALA_COSTO_UNITARIO);
            if (Money::mayorQue($valor, '0', Money::ESCALA_COSTO_UNITARIO)) {
                return $valor;
            }
        }

        throw ValidationException::withMessages([
            'detalles' => 'El producto "'.$producto->nombre.'" no tiene precio por gramo configurado. '
                .'Completa su ficha (costo por unidad) o indícalo en la línea.',
        ]);
    }
}
