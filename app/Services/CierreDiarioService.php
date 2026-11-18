<?php

namespace App\Services;

use App\Models\Atencion;
use App\Models\CierreDiario;
use App\Models\Consumo;
use App\Models\ConsumoServicio;
use App\Models\Gasto;
use App\Models\VentaDetalle;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cierre financiero de un día.
 *
 * Fórmula del salón:
 *
 *   Servicios realizados
 *   + Productos vendidos
 *   - Productos utilizados
 *   - Gastos del salón
 *   - Gastos de la casa
 *   = Saldo después de los movimientos registrados
 *
 * El costo de adquisición de los productos vendidos se conserva como dato
 * informativo de rentabilidad, pero NO se resta otra vez en esta fórmula.
 *
 * Las fuentes son los módulos existentes: atenciones (servicios), consumos
 * (productos usados), ventas (productos vendidos) y gastos (salón/casa).
 */
class CierreDiarioService
{
    public const RESULTADO_LABEL = 'Saldo después de los movimientos registrados';

    /**
     * Indica si el día tiene un cierre vigente. Un día reabierto vuelve a
     * aceptar movimientos para que el administrador pueda corregirlo.
     */
    public function estaCerrado(string $fecha): bool
    {
        return CierreDiario::whereDate('fecha', Carbon::parse($fecha)->toDateString())
            ->where('estado', CierreDiario::ESTADO_CERRADO)
            ->exists();
    }

    /**
     * Evita modificaciones accidentales después del cierre. Para corregir se
     * debe reabrir el día desde la pantalla de cierre diario.
     */
    public function assertDiaAbierto(string $fecha, string $campo = 'fecha'): void
    {
        if ($this->estaCerrado($fecha)) {
            throw ValidationException::withMessages([
                $campo => 'El día '.Carbon::parse($fecha)->toDateString().' está cerrado. Reábrelo antes de modificarlo.',
            ]);
        }
    }

    /**
     * Resumen vivo de un día. No guarda nada; sirve para la vista previa y
     * para tomar la foto cuando se cierra.
     *
     * @return array<string, mixed>
     */
    public function resumen(string $fecha): array
    {
        $fecha = Carbon::parse($fecha)->toDateString();

        $servicios = $this->servicios($fecha);
        $utilizados = $this->productosUtilizados($fecha);
        $vendidos = $this->productosVendidos($fecha);
        $gastos = $this->gastos($fecha);

        $ingresos = Money::add($servicios['total'], $vendidos['total']);
        $descuentos = Money::add(
            Money::add($utilizados['total'], $gastos['total_salon']),
            $gastos['total_casa']
        );

        return [
            'fecha' => $fecha,
            'label' => Carbon::parse($fecha)->translatedFormat('l d \d\e F \d\e Y'),
            'fuente_servicios' => $servicios['fuente'],
            'servicios' => $servicios['items'],
            'total_servicios' => $servicios['total'],
            'productos_utilizados' => $utilizados['items'],
            'total_productos_utilizados' => $utilizados['total'],
            'productos_vendidos' => $vendidos['items'],
            'total_productos_vendidos' => $vendidos['total'],
            'costo_productos_vendidos' => $vendidos['costo'],
            'gastos_salon' => $gastos['salon'],
            'total_gastos_salon' => $gastos['total_salon'],
            'gastos_casa' => $gastos['casa'],
            'total_gastos_casa' => $gastos['total_casa'],
            'subtotal_gastos' => $gastos['total'],
            'total_ingresos' => $ingresos,
            'total_descuentos' => $descuentos,
            'resultado' => Money::sub($ingresos, $descuentos),
            'resultado_label' => self::RESULTADO_LABEL,
            'formula' => [
                'servicios' => $servicios['total'],
                'productos_vendidos' => $vendidos['total'],
                'productos_utilizados' => $utilizados['total'],
                'gastos_salon' => $gastos['total_salon'],
                'gastos_casa' => $gastos['total_casa'],
                'resultado' => Money::sub($ingresos, $descuentos),
            ],
            'generado_en' => now()->toIso8601String(),
        ];
    }

    /**
     * Cierra el día y guarda una foto de todos los movimientos.
     */
    public function cerrar(string $fecha, ?string $notas = null): CierreDiario
    {
        $fecha = Carbon::parse($fecha)->toDateString();

        return DB::transaction(function () use ($fecha, $notas) {
            $yaCerrado = CierreDiario::whereDate('fecha', $fecha)
                ->where('estado', CierreDiario::ESTADO_CERRADO)
                ->lockForUpdate()
                ->first();

            if ($yaCerrado) {
                throw ValidationException::withMessages([
                    'fecha' => 'El día '.$fecha.' ya está cerrado. Reábrelo antes de volver a cerrarlo.',
                ]);
            }

            $resumen = $this->resumen($fecha);

            $cierre = CierreDiario::create([
                'fecha' => $fecha,
                'estado' => CierreDiario::ESTADO_CERRADO,
                'total_servicios' => $resumen['total_servicios'],
                'total_productos_vendidos' => $resumen['total_productos_vendidos'],
                'total_productos_utilizados' => $resumen['total_productos_utilizados'],
                'total_gastos_salon' => $resumen['total_gastos_salon'],
                'total_gastos_casa' => $resumen['total_gastos_casa'],
                'total_ingresos' => $resumen['total_ingresos'],
                'total_descuentos' => $resumen['total_descuentos'],
                'resultado' => $resumen['resultado'],
                'costo_productos_vendidos' => $resumen['costo_productos_vendidos'],
                'detalle' => $resumen,
                'notas' => $notas,
                'cerrado_por' => Auth::id(),
                'cerrado_en' => now(),
                'created_by' => Auth::id(),
            ]);

            AuditoriaService::registrar('cerrar', 'cierres_diarios', CierreDiario::class, $cierre->id, [], [
                'fecha' => $fecha,
                'resultado' => $resumen['resultado'],
            ], $notas);

            return $cierre->fresh(['cerrador']);
        });
    }

    /**
     * Reabre un cierre conservando el registro original y su motivo.
     */
    public function reabrir(CierreDiario $cierre, string $motivo): CierreDiario
    {
        if (trim($motivo) === '') {
            throw ValidationException::withMessages(['motivo' => 'Debes indicar el motivo de la reapertura.']);
        }

        return DB::transaction(function () use ($cierre, $motivo) {
            $cierre = CierreDiario::whereKey($cierre->id)->lockForUpdate()->firstOrFail();

            if ($cierre->estado !== CierreDiario::ESTADO_CERRADO) {
                throw ValidationException::withMessages(['cierre' => 'Este cierre ya fue reabierto.']);
            }

            $antes = ['estado' => $cierre->estado];
            $cierre->update([
                'estado' => CierreDiario::ESTADO_REABIERTO,
                'reabierto_por' => Auth::id(),
                'reabierto_en' => now(),
                'motivo_reapertura' => $motivo,
            ]);

            AuditoriaService::registrar('reabrir', 'cierres_diarios', CierreDiario::class, $cierre->id, $antes, [
                'estado' => CierreDiario::ESTADO_REABIERTO,
            ], $motivo);

            return $cierre->fresh(['cerrador', 'reabridor']);
        });
    }

    /**
     * Historial con totales diarios, semanales y mensuales.
     *
     * @return array<string, mixed>
     */
    public function historial(string $desde, string $hasta): array
    {
        $desde = Carbon::parse($desde)->toDateString();
        $hasta = Carbon::parse($hasta)->toDateString();

        $cierres = CierreDiario::with(['cerrador', 'reabridor'])
            ->whereBetween('fecha', [$desde, $hasta])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        $cerrados = $cierres->where('estado', CierreDiario::ESTADO_CERRADO)->values();

        $totales = [
            'total_servicios' => $this->sumar($cerrados, 'total_servicios'),
            'total_productos_vendidos' => $this->sumar($cerrados, 'total_productos_vendidos'),
            'total_productos_utilizados' => $this->sumar($cerrados, 'total_productos_utilizados'),
            'total_gastos_salon' => $this->sumar($cerrados, 'total_gastos_salon'),
            'total_gastos_casa' => $this->sumar($cerrados, 'total_gastos_casa'),
            'total_ingresos' => $this->sumar($cerrados, 'total_ingresos'),
            'total_descuentos' => $this->sumar($cerrados, 'total_descuentos'),
            'resultado' => $this->sumar($cerrados, 'resultado'),
            'costo_productos_vendidos' => $this->sumar($cerrados, 'costo_productos_vendidos'),
        ];

        $porDia = $cerrados
            ->groupBy(fn (CierreDiario $c) => $c->fecha->toDateString())
            ->map(fn ($grupo, $fecha) => [
                'fecha' => $fecha,
                'label' => Carbon::parse($fecha)->translatedFormat('d M Y'),
                'total_servicios' => $this->sumar($grupo, 'total_servicios'),
                'total_productos_vendidos' => $this->sumar($grupo, 'total_productos_vendidos'),
                'total_productos_utilizados' => $this->sumar($grupo, 'total_productos_utilizados'),
                'total_gastos_salon' => $this->sumar($grupo, 'total_gastos_salon'),
                'total_gastos_casa' => $this->sumar($grupo, 'total_gastos_casa'),
                'resultado' => $this->sumar($grupo, 'resultado'),
                'cierres' => $grupo->count(),
            ])
            ->sortByDesc('fecha')
            ->values();

        $porSemana = $cerrados
            ->groupBy(fn (CierreDiario $c) => $c->fecha->copy()->startOfWeek()->toDateString())
            ->map(fn ($grupo, $inicio) => [
                'inicio' => $inicio,
                'fin' => Carbon::parse($inicio)->endOfWeek()->toDateString(),
                'label' => Carbon::parse($inicio)->translatedFormat('d M').' - '.Carbon::parse($inicio)->endOfWeek()->translatedFormat('d M Y'),
                'resultado' => $this->sumar($grupo, 'resultado'),
                'total_ingresos' => $this->sumar($grupo, 'total_ingresos'),
                'total_descuentos' => $this->sumar($grupo, 'total_descuentos'),
                'dias' => $grupo->count(),
            ])
            ->sortByDesc('inicio')
            ->values();

        $porMes = $cerrados
            ->groupBy(fn (CierreDiario $c) => $c->fecha->format('Y-m'))
            ->map(fn ($grupo, $mes) => [
                'mes' => $mes,
                'label' => Carbon::parse($mes.'-01')->translatedFormat('F Y'),
                'resultado' => $this->sumar($grupo, 'resultado'),
                'total_ingresos' => $this->sumar($grupo, 'total_ingresos'),
                'total_descuentos' => $this->sumar($grupo, 'total_descuentos'),
                'dias' => $grupo->count(),
            ])
            ->sortByDesc('mes')
            ->values();

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'data' => $cierres->map(fn (CierreDiario $c) => $this->serializar($c))->values(),
            'totales' => $totales,
            'por_dia' => $porDia,
            'por_semana' => $porSemana,
            'por_mes' => $porMes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializar(CierreDiario $cierre): array
    {
        return [
            'id' => $cierre->id,
            'fecha' => $cierre->fecha?->toDateString(),
            'estado' => $cierre->estado,
            'estado_label' => $cierre->esCerrado() ? 'Cerrado' : 'Reabierto',
            'total_servicios' => (string) $cierre->total_servicios,
            'total_productos_vendidos' => (string) $cierre->total_productos_vendidos,
            'total_productos_utilizados' => (string) $cierre->total_productos_utilizados,
            'total_gastos_salon' => (string) $cierre->total_gastos_salon,
            'total_gastos_casa' => (string) $cierre->total_gastos_casa,
            'total_ingresos' => (string) $cierre->total_ingresos,
            'total_descuentos' => (string) $cierre->total_descuentos,
            'resultado' => (string) $cierre->resultado,
            'costo_productos_vendidos' => (string) $cierre->costo_productos_vendidos,
            'notas' => $cierre->notas,
            'detalle' => $cierre->detalle,
            'cerrado_por_nombre' => $cierre->cerrador?->name,
            'cerrado_en' => $cierre->cerrado_en?->toIso8601String(),
            'reabierto_por_nombre' => $cierre->reabridor?->name,
            'reabierto_en' => $cierre->reabierto_en?->toIso8601String(),
            'motivo_reapertura' => $cierre->motivo_reapertura,
            'created_at' => $cierre->created_at?->toIso8601String(),
        ];
    }

    /**
     * Servicios del día. Se usa el módulo de atenciones como fuente principal.
     * Si un día antiguo no tiene atenciones, se conserva el registro diario
     * histórico (consumos con precio cobrado) para no perder información.
     *
     * @return array{fuente: string, items: array<int, array<string, mixed>>, total: string}
     */
    private function servicios(string $fecha): array
    {
        $atenciones = Atencion::with(['cliente', 'estilista', 'detalles.servicio'])
            ->whereDate('fecha', $fecha)
            ->where('estado', Atencion::ESTADO_ACTIVO)
            ->orderBy('id')
            ->get();

        if ($atenciones->isNotEmpty()) {
            $items = $atenciones->map(fn (Atencion $a) => [
                'id' => $a->id,
                'origen' => 'atencion',
                'cliente_nombre' => $a->cliente?->nombre,
                'estilista_nombre' => $a->estilista?->nombre,
                'descripcion' => $a->descripcion,
                'detalle' => $a->detalles->map(fn ($d) => [
                    'nombre' => $d->etiqueta(),
                    'precio' => (string) $d->subtotal,
                ])->values(),
                'servicios_label' => $a->detalles->map(fn ($d) => $d->etiqueta())->filter()->implode(' · '),
                'total' => (string) $a->total,
                'pagado' => (string) $a->pagado,
                'saldo' => $a->saldo(),
                'metodo_pago' => $a->metodo_pago,
            ])->values()->all();

            return [
                'fuente' => 'atenciones',
                'items' => $items,
                'total' => $this->sumar(collect($items), 'total'),
            ];
        }

        $consumos = Consumo::with(['cliente', 'estilista', 'servicio'])
            ->whereDate('fecha', $fecha)
            ->where('estado', Consumo::ESTADO_ACTIVO)
            ->where('precio_cobrado', '>', 0)
            ->orderBy('id')
            ->get();

        $items = $consumos->map(fn (Consumo $c) => [
            'id' => $c->id,
            'origen' => 'registro_diario',
            'cliente_nombre' => $c->cliente?->nombre,
            'estilista_nombre' => $c->estilista?->nombre,
            'descripcion' => $c->descripcion,
            'detalle' => [],
            'servicios_label' => $c->servicio?->nombre ?? $c->descripcion ?? 'Servicio',
            'total' => (string) $c->precio_cobrado,
            'pagado' => (string) $c->precio_cobrado,
            'saldo' => Money::cero(),
            'metodo_pago' => null,
        ])->values()->all();

        return [
            'fuente' => $consumos->isNotEmpty() ? 'registro_diario' : 'sin_registros',
            'items' => $items,
            'total' => $this->sumar(collect($items), 'total'),
        ];
    }

    /**
     * Productos utilizados en el día, agrupados por producto. El costo y el
     * valor por gramo salen de la línea histórica congelada, no de la ficha
     * actual del producto.
     *
     * @return array{items: array<int, array<string, mixed>>, total: string}
     */
    private function productosUtilizados(string $fecha): array
    {
        $lineas = ConsumoServicio::with(['producto.categoria', 'consumo', 'venta'])
            ->where('estado', Consumo::ESTADO_ACTIVO)
            ->where(function ($query) use ($fecha) {
                $query->whereHas('consumo', fn ($q) => $q
                    ->where('estado', Consumo::ESTADO_ACTIVO)
                    ->whereDate('fecha', $fecha))
                    // Compatibilidad con consumos creados desde una venta de
                    // servicio: no tienen cabecera Consumo, pero sí venta.
                    ->orWhere(function ($q) use ($fecha) {
                        $q->whereNull('consumo_id')
                            ->whereHas('venta', fn ($v) => $v
                                ->whereDate('fecha', $fecha)
                                ->whereNotIn('estado', ['anulada']));
                    });
            })
            ->orderBy('id')
            ->get();

        $agrupados = [];

        foreach ($lineas as $linea) {
            $clave = (string) ($linea->producto_id ?? 'sin-producto');
            $cantidad = Money::normal((string) $linea->cantidad, Money::ESCALA_CANTIDAD);
            $costo = Money::normal((string) $linea->costo_total);

            if (! isset($agrupados[$clave])) {
                $agrupados[$clave] = [
                    'producto_id' => $linea->producto_id,
                    'producto_nombre' => $linea->producto?->nombre ?? 'Producto #'.$linea->producto_id,
                    'categoria_nombre' => $linea->producto?->categoria?->nombre,
                    'grupo' => $linea->producto
                        ? RegistroDiarioService::grupoDe($linea->producto)
                        : null,
                    'cantidad' => Money::cero(Money::ESCALA_CANTIDAD),
                    'costo' => Money::cero(),
                    'lineas' => [],
                ];
            }

            $agrupados[$clave]['cantidad'] = Money::add(
                $agrupados[$clave]['cantidad'],
                $cantidad,
                Money::ESCALA_CANTIDAD
            );
            $agrupados[$clave]['costo'] = Money::add($agrupados[$clave]['costo'], $costo);
            $agrupados[$clave]['lineas'][] = [
                'consumo_id' => $linea->consumo_id,
                'cantidad' => $cantidad,
                'costo_unitario' => (string) $linea->costo_unitario,
                'costo_total' => $costo,
                'notas' => $linea->notas,
            ];
        }

        $items = [];

        foreach ($agrupados as $fila) {
            $fila['costo_promedio_gramo'] = Money::mayorQue($fila['cantidad'], '0', Money::ESCALA_CANTIDAD)
                ? Money::div($fila['costo'], $fila['cantidad'], Money::ESCALA_COSTO_UNITARIO)
                : '0.000000';
            $items[] = $fila;
        }

        usort($items, fn ($a, $b) => strcmp((string) $a['producto_nombre'], (string) $b['producto_nombre']));

        return [
            'items' => $items,
            'total' => $this->sumar(collect($items), 'costo'),
        ];
    }

    /**
     * Productos vendidos en el día. El descuento general de una venta mixta se
     * reparte proporcionalmente entre sus líneas para que el ingreso del día
     * sea el realmente cobrado.
     *
     * @return array{items: array<int, array<string, mixed>>, total: string, costo: string}
     */
    private function productosVendidos(string $fecha): array
    {
        $detalles = VentaDetalle::with(['venta.cliente', 'producto'])
            ->where('tipo', 'producto')
            ->whereHas('venta', fn ($q) => $q
                ->whereDate('fecha', $fecha)
                ->whereNotIn('estado', ['anulada']))
            ->orderBy('id')
            ->get();

        $items = [];

        foreach ($detalles as $detalle) {
            $venta = $detalle->venta;
            $subtotal = Money::normal((string) $detalle->subtotal);
            $descuentoAsignado = Money::cero();

            if ($venta && Money::mayorQue((string) $venta->descuento, '0')
                && Money::mayorQue((string) $venta->subtotal, '0')) {
                $descuentoAsignado = Money::div(
                    Money::mul((string) $venta->descuento, $subtotal),
                    (string) $venta->subtotal
                );
            }

            $neto = Money::sub($subtotal, $descuentoAsignado);

            $items[] = [
                'detalle_id' => $detalle->id,
                'venta_id' => $detalle->venta_id,
                'numero' => $venta?->numero,
                'fecha' => $venta?->fecha?->toDateString(),
                'cliente_nombre' => $venta?->cliente?->nombre,
                'producto_id' => $detalle->producto_id,
                'producto_nombre' => $detalle->producto?->nombre ?? $detalle->descripcion,
                'presentacion' => $detalle->presentacion ?? $detalle->producto?->presentacionLabel(),
                'contenido' => $detalle->contenido_envase !== null
                    ? (string) $detalle->contenido_envase
                    : ($detalle->producto?->contenido_envase !== null
                        ? (string) $detalle->producto->contenido_envase
                        : null),
                'unidad' => $detalle->unidad,
                'cantidad' => (string) $detalle->cantidad,
                'precio_unitario' => (string) $detalle->precio_unitario,
                'descuento' => (string) $detalle->descuento,
                'descuento_asignado' => $descuentoAsignado,
                'subtotal' => $subtotal,
                'total' => $neto,
                'costo_unitario' => (string) ($detalle->costo_unitario ?? '0'),
                'costo_total' => (string) ($detalle->costo_total ?? '0'),
            ];
        }

        return [
            'items' => $items,
            'total' => $this->sumar(collect($items), 'total'),
            'costo' => $this->sumar(collect($items), 'costo_total'),
        ];
    }

    /**
     * Gastos del día separados por ámbito. Se incluyen los gastos registrados
     * (pagados y pendientes) porque representan compromisos del día.
     *
     * @return array{ salon: array<int, array<string, mixed>>, casa: array<int, array<string, mixed>>, total_salon: string, total_casa: string, total: string }
     */
    private function gastos(string $fecha): array
    {
        $gastos = Gasto::with(['categoria', 'metodoPago'])
            ->whereDate('fecha', $fecha)
            ->orderBy('id')
            ->get();

        $salon = [];
        $casa = [];

        foreach ($gastos as $gasto) {
            $fila = [
                'id' => $gasto->id,
                'ambito' => $gasto->ambito ?: Gasto::AMBITO_SALON,
                'ambito_label' => $gasto->ambitoLabel(),
                'categoria_nombre' => $gasto->categoria?->nombre,
                'descripcion' => $gasto->descripcion,
                'monto' => (string) $gasto->monto,
                'fecha' => $gasto->fecha?->toDateString(),
                'estado' => $gasto->estado,
                'metodo_pago' => $gasto->metodoPago?->nombre,
            ];

            if ($fila['ambito'] === Gasto::AMBITO_CASA) {
                $casa[] = $fila;
            } else {
                $salon[] = $fila;
            }
        }

        $totalSalon = $this->sumar(collect($salon), 'monto');
        $totalCasa = $this->sumar(collect($casa), 'monto');

        return [
            'salon' => $salon,
            'casa' => $casa,
            'total_salon' => $totalSalon,
            'total_casa' => $totalCasa,
            'total' => Money::add($totalSalon, $totalCasa),
        ];
    }

    /**
     * Suma con BCMath, evitando el redondeo flotante de sum().
     */
    private function sumar(iterable $items, string $campo): string
    {
        $total = Money::cero();

        foreach ($items as $item) {
            $valor = is_array($item) ? ($item[$campo] ?? null) : ($item->{$campo} ?? null);
            if ($valor === null || $valor === '') {
                continue;
            }
            $total = Money::add($total, Money::normal((string) $valor));
        }

        return $total;
    }
}
