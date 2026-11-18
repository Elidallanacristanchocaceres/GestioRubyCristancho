<?php

namespace App\Services;

use App\Models\CierreSemanal;
use App\Models\Configuracion;
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
 * Cierre semanal del negocio.
 *
 * Regla del salón:
 *   Ingresos = servicios cobrados (registro diario) + ventas de productos.
 *   Costos   = productos usados en servicios + costo de los productos vendidos.
 *   Base     = ingresos - costos - gastos pagados.
 *   Diezmo   = 10 % de la base (solo si la base es positiva).
 *   Neto     = base - diezmo.
 *
 * La semana va de lunes a domingo. El porcentaje del diezmo se puede cambiar
 * con la configuración "diezmo_porcentaje"; si no existe, se usa 10 %.
 */
class CierreSemanalService
{
    public const PORCENTAJE_DIEZMO_DEFECTO = '10';

    /**
     * Semana (lunes a domingo) que contiene la fecha indicada.
     *
     * @return array{inicio:string, fin:string, label:string, es_actual:bool, anterior:string, siguiente:string}
     */
    public function semana(?string $fecha = null): array
    {
        $base = $fecha ? Carbon::parse($fecha) : Carbon::today();
        $inicio = $base->copy()->startOfWeek();
        $fin = $inicio->copy()->addDays(6);
        $actual = Carbon::today()->startOfWeek();

        return [
            'inicio' => $inicio->toDateString(),
            'fin' => $fin->toDateString(),
            'label' => $inicio->translatedFormat('d M').' – '.$fin->translatedFormat('d M Y'),
            'es_actual' => $inicio->toDateString() === $actual->toDateString(),
            'anterior' => $inicio->copy()->subWeek()->toDateString(),
            'siguiente' => $inicio->copy()->addWeek()->toDateString(),
        ];
    }

    private function hayConsumosEntre(string $desde, string $hasta): bool
    {
        return Consumo::where('estado', Consumo::ESTADO_ACTIVO)
            ->whereBetween('fecha', [$desde, $hasta])
            ->exists();
    }

    private function ventasServiciosEntre(string $desde, string $hasta): string
    {
        return Money::normal(VentaDetalle::where('tipo', 'servicio')
            ->whereHas('venta', fn ($q) => $q
                ->whereBetween('fecha', [$desde, $hasta])
                ->whereNotIn('estado', ['anulada']))
            ->sum('subtotal'));
    }

    /**
     * Totales y detalle diario del período. No guarda nada.
     *
     * @return array<string, mixed>
     */
    public function resumen(string $desde, string $hasta): array
    {
        $inicio = Carbon::parse($desde)->startOfDay();
        $fin = Carbon::parse($hasta)->startOfDay();

        if ($inicio->gt($fin)) {
            [$inicio, $fin] = [$fin, $inicio];
        }

        $desdeFecha = $inicio->toDateString();
        $hastaFecha = $fin->toDateString();

        // Fuente de los servicios: si hay registros diarios, mandan ellos;
        // si no hay ninguno, se usan las ventas de servicios. Nunca se suman
        // las dos fuentes para no duplicar la misma atención.
        $hayConsumos = $this->hayConsumosEntre($desdeFecha, $hastaFecha);
        $serviciosVentas = $this->ventasServiciosEntre($desdeFecha, $hastaFecha);
        $usarVentas = ! $hayConsumos && Money::mayorQue($serviciosVentas, '0');

        $porcentaje = $this->porcentajeDiezmo();

        $totales = [
            'ingresos_servicios' => Money::cero(),
            'ingresos_productos' => Money::cero(),
            'costo_productos_consumidos' => Money::cero(),
            'costo_productos_vendidos' => Money::cero(),
            'gastos' => Money::cero(),
            'base_diezmo' => Money::cero(),
            'diezmo' => Money::cero(),
            'neto' => Money::cero(),
        ];

        $dias = [];

        for ($dia = $inicio->copy(); $dia->lte($fin); $dia->addDay()) {
            $fecha = $dia->toDateString();

            $servicios = $usarVentas
                ? Money::normal(
                    VentaDetalle::where('tipo', 'servicio')
                        ->whereHas('venta', fn ($q) => $q
                            ->whereDate('fecha', $fecha)
                            ->whereNotIn('estado', ['anulada']))
                        ->sum('subtotal')
                )
                : Money::normal(
                    Consumo::where('estado', Consumo::ESTADO_ACTIVO)
                        ->whereDate('fecha', $fecha)
                        ->sum('precio_cobrado')
                );

            $costoConsumido = $usarVentas
                ? Money::normal(
                    ConsumoServicio::where('estado', 'activo')
                        ->whereHas('venta', fn ($q) => $q
                            ->whereDate('fecha', $fecha)
                            ->whereNotIn('estado', ['anulada']))
                        ->sum('costo_total')
                )
                : Money::normal(
                    Consumo::where('estado', Consumo::ESTADO_ACTIVO)
                        ->whereDate('fecha', $fecha)
                        ->sum('costo_total')
                );

            $ventasProductos = Money::normal(
                VentaDetalle::where('tipo', 'producto')
                    ->whereHas('venta', fn ($q) => $q
                        ->whereDate('fecha', $fecha)
                        ->whereNotIn('estado', ['anulada']))
                    ->sum('subtotal')
            );

            $costoVendido = Money::normal(
                VentaDetalle::where('tipo', 'producto')
                    ->whereHas('venta', fn ($q) => $q
                        ->whereDate('fecha', $fecha)
                        ->whereNotIn('estado', ['anulada']))
                    ->sum('costo_total')
            );

            $gastos = Money::normal(
                Gasto::whereDate('fecha', $fecha)
                    ->where('estado', 'pagado')
                    ->sum('monto')
            );

            $base = Money::sub(
                Money::add($servicios, $ventasProductos),
                Money::add(Money::add($costoConsumido, $costoVendido), $gastos)
            );

            $dias[] = [
                'fecha' => $fecha,
                'label' => $dia->translatedFormat('D d/m'),
                'ingresos_servicios' => $servicios,
                'ingresos_productos' => $ventasProductos,
                'costo_productos_consumidos' => $costoConsumido,
                'costo_productos_vendidos' => $costoVendido,
                'gastos' => $gastos,
                'base_diezmo' => $base,
                // El diezmo no se calcula por día: se calcula sobre la base
                // total de la semana para que un día negativo compense a uno
                // positivo. Se dejan en cero para el detalle diario.
                'diezmo' => Money::cero(),
                'neto' => $base,
            ];

            foreach (['ingresos_servicios', 'ingresos_productos', 'costo_productos_consumidos', 'costo_productos_vendidos', 'gastos', 'base_diezmo'] as $clave) {
                $totales[$clave] = Money::add($totales[$clave], $dias[array_key_last($dias)][$clave]);
            }
        }

        // El diezmo es 10 % de la base semanal (no la suma de diezmos diarios).
        $totales['diezmo'] = Money::mayorQue($totales['base_diezmo'], '0')
            ? Money::div(Money::mul($totales['base_diezmo'], $porcentaje), '100', Money::ESCALA_DINERO)
            : Money::cero();
        $totales['neto'] = Money::sub($totales['base_diezmo'], $totales['diezmo']);

        return [
            'desde' => $inicio->toDateString(),
            'hasta' => $fin->toDateString(),
            'porcentaje_diezmo' => $porcentaje,
            'ingresos_servicios' => $totales['ingresos_servicios'],
            'ingresos_productos' => $totales['ingresos_productos'],
            'costo_productos_consumidos' => $totales['costo_productos_consumidos'],
            'costo_productos_vendidos' => $totales['costo_productos_vendidos'],
            'gastos' => $totales['gastos'],
            'base_diezmo' => $totales['base_diezmo'],
            'diezmo' => $totales['diezmo'],
            'neto' => $totales['neto'],
            'fuente_servicios' => $usarVentas ? 'ventas' : 'registro_diario',
            'servicios_otra_fuente' => $usarVentas ? Money::cero() : $serviciosVentas,
            'dias' => $dias,
        ];
    }

    /**
     * Guarda el cierre de la semana. No permite duplicados.
     */
    public function cerrar(array $datos): CierreSemanal
    {
        $semana = $this->semana($datos['semana'] ?? null);

        $existente = CierreSemanal::where('fecha_inicio', $semana['inicio'])
            ->where('fecha_fin', $semana['fin'])
            ->first();

        if ($existente) {
            throw ValidationException::withMessages([
                'semana' => 'Esta semana ya tiene un cierre guardado. Reábrelo si necesitas corregirlo.',
            ]);
        }

        $resumen = $this->resumen($semana['inicio'], $semana['fin']);

        return DB::transaction(function () use ($resumen, $datos) {
            $cierre = CierreSemanal::create([
                'fecha_inicio' => $resumen['desde'],
                'fecha_fin' => $resumen['hasta'],
                'ingresos_servicios' => $resumen['ingresos_servicios'],
                'ingresos_productos' => $resumen['ingresos_productos'],
                'costo_productos_consumidos' => $resumen['costo_productos_consumidos'],
                'costo_productos_vendidos' => $resumen['costo_productos_vendidos'],
                'gastos' => $resumen['gastos'],
                'base_diezmo' => $resumen['base_diezmo'],
                'porcentaje_diezmo' => $resumen['porcentaje_diezmo'],
                'diezmo' => $resumen['diezmo'],
                'neto' => $resumen['neto'],
                'detalle' => $resumen['dias'],
                'notas' => $datos['notas'] ?? null,
                'estado' => CierreSemanal::ESTADO_CERRADO,
                'created_by' => Auth::id(),
            ]);

            AuditoriaService::registrar('crear', 'cierres', CierreSemanal::class, $cierre->id, [], [
                'fecha_inicio' => $cierre->fecha_inicio->toDateString(),
                'fecha_fin' => $cierre->fecha_fin->toDateString(),
                'base_diezmo' => (string) $cierre->base_diezmo,
                'diezmo' => (string) $cierre->diezmo,
                'neto' => (string) $cierre->neto,
            ]);

            return $cierre;
        });
    }

    /**
     * Reabre una semana eliminando la foto guardada (queda registro en auditoría).
     */
    public function eliminar(CierreSemanal $cierre): void
    {
        DB::transaction(function () use ($cierre) {
            AuditoriaService::registrar('eliminar', 'cierres', CierreSemanal::class, $cierre->id, $cierre->toArray(), []);
            $cierre->delete();
        });
    }

    /**
     * Últimas semanas con su estado de cierre.
     *
     * @return array<int, array{inicio:string, fin:string, label:string, es_actual:bool, cerrado:bool, cierre_id:int|null}>
     */
    public function semanas(int $limite = 12): array
    {
        $actual = Carbon::today()->startOfWeek();
        $desde = $actual->copy()->subWeeks($limite - 1)->toDateString();

        $cierres = CierreSemanal::where('fecha_inicio', '>=', $desde)
            ->get()
            ->keyBy(fn (CierreSemanal $c) => $c->fecha_inicio->toDateString());

        $semanas = [];

        for ($i = 0; $i < $limite; $i++) {
            $inicio = $actual->copy()->subWeeks($i);
            $fin = $inicio->copy()->addDays(6);
            $cierre = $cierres->get($inicio->toDateString());

            $semanas[] = [
                'inicio' => $inicio->toDateString(),
                'fin' => $fin->toDateString(),
                'label' => $inicio->translatedFormat('d M').' – '.$fin->translatedFormat('d M Y'),
                'es_actual' => $i === 0,
                'cerrado' => $cierre !== null,
                'cierre_id' => $cierre?->id,
            ];
        }

        return $semanas;
    }

    public function porcentajeDiezmo(): string
    {
        $valor = Money::normal((string) Configuracion::valor('diezmo_porcentaje', self::PORCENTAJE_DIEZMO_DEFECTO), 2);

        if (Money::menorQue($valor, '0', 2) || Money::mayorQue($valor, '100', 2)) {
            return Money::normal(self::PORCENTAJE_DIEZMO_DEFECTO, 2);
        }

        return $valor;
    }
}
