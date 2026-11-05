<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\CajaCierreDetalle;
use App\Models\CajaMovimiento;
use App\Models\MetodoPago;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Caja diaria.
 *
 * Saldo esperado = saldo inicial + entradas efectivas - salidas efectivas.
 */
class CajaService
{
    public function abrir(string $saldoInicial, ?string $nota = null): Caja
    {
        if (Caja::cajaAbierta()) {
            throw ValidationException::withMessages(['caja' => 'Ya existe una caja abierta. Debe cerrarla antes de abrir otra.']);
        }

        return DB::transaction(function () use ($saldoInicial, $nota) {
            $caja = Caja::create([
                'fecha_apertura' => now(),
                'saldo_inicial' => Money::normal($saldoInicial),
                'nota_apertura' => $nota,
                'estado' => 'abierta',
                'created_by' => Auth::id(),
            ]);

            AuditoriaService::registrar('apertura', 'caja', Caja::class, $caja->id, [], [
                'saldo_inicial' => (string) $caja->saldo_inicial,
            ], $nota);

            return $caja;
        });
    }

    /**
     * Registra un movimiento de caja. Se usa dentro de transacciones de ventas,
     * gastos y compras. Si la caja está cerrada y el movimiento es un ingreso/egreso
     * obligatorio, se lanza excepción.
     */
    public function registrarMovimiento(array $datos): CajaMovimiento
    {
        $caja = Caja::cajaAbierta();

        if (! $caja) {
            throw ValidationException::withMessages(['caja' => 'No hay una caja abierta. Abra la caja para registrar movimientos.']);
        }

        return CajaMovimiento::create([
            'caja_id' => $caja->id,
            'venta_id' => $datos['venta_id'] ?? null,
            'pago_id' => $datos['pago_id'] ?? null,
            'gasto_id' => $datos['gasto_id'] ?? null,
            'tipo' => $datos['tipo'],
            'monto' => Money::normal($datos['monto']),
            'metodo_pago_id' => $datos['metodo_pago_id'] ?? null,
            'concepto' => $datos['concepto'],
            'descripcion' => $datos['descripcion'] ?? null,
            'referencia' => $datos['referencia'] ?? null,
            'estado' => $datos['estado'] ?? 'confirmado',
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Movimiento manual de caja (ingreso/egreso justificado).
     */
    public function movimientoManual(string $tipo, string $monto, ?int $metodoPagoId, string $concepto, ?string $descripcion = null): CajaMovimiento
    {
        if (trim($concepto) === '') {
            throw ValidationException::withMessages(['concepto' => 'El concepto es obligatorio.']);
        }

        if (Money::menorQue($monto, '0.01')) {
            throw ValidationException::withMessages(['monto' => 'El monto debe ser mayor que cero.']);
        }

        if (! in_array($tipo, ['ingreso', 'egreso'], true)) {
            throw ValidationException::withMessages(['tipo' => 'Tipo de movimiento inválido.']);
        }

        return DB::transaction(function () use ($tipo, $monto, $metodoPagoId, $concepto, $descripcion) {
            $movimiento = $this->registrarMovimiento([
                'tipo' => $tipo,
                'monto' => $monto,
                'metodo_pago_id' => $metodoPagoId,
                'concepto' => $concepto,
                'descripcion' => $descripcion,
                'referencia' => 'MANUAL',
            ]);

            AuditoriaService::registrar(
                $tipo === 'ingreso' ? 'crear' : 'crear',
                'caja',
                CajaMovimiento::class,
                $movimiento->id,
                [],
                ['tipo' => $tipo, 'monto' => (string) $movimiento->monto, 'concepto' => $concepto],
                $descripcion
            );

            return $movimiento;
        });
    }

    /**
     * Cierra la caja registrando el conteo por medio de pago.
     *
     * @param  array  $conteos  [metodo_pago_id => valor_contado]
     */
    public function cerrar(Caja $caja, array $conteos, ?string $notas = null, array $observaciones = []): Caja
    {
        if (! $caja->estaAbierta()) {
            throw ValidationException::withMessages(['caja' => 'La caja ya está cerrada.']);
        }

        return DB::transaction(function () use ($caja, $conteos, $notas, $observaciones) {
            $caja = Caja::whereKey($caja->id)->lockForUpdate()->firstOrFail();
            $metodos = MetodoPago::orderBy('id')->get();

            $totalEsperado = Money::cero();
            $totalContado = Money::cero();
            $totalIngresos = Money::cero();
            $totalEgresos = Money::cero();

            foreach ($metodos as $metodo) {
                $esperado = $caja->saldoEsperadoPorMetodo($metodo->id);
                $contado = Money::normal($conteos[$metodo->id] ?? '0');
                $diferencia = Money::sub($contado, $esperado);

                CajaCierreDetalle::updateOrCreate(
                    ['caja_id' => $caja->id, 'metodo_pago_id' => $metodo->id],
                    [
                        'saldo_esperado' => $esperado,
                        'valor_contado' => $contado,
                        'diferencia' => $diferencia,
                        'observaciones' => $observaciones[$metodo->id] ?? null,
                    ]
                );

                $ingresos = $caja->movimientos()->where('tipo', 'ingreso')->where('estado', 'confirmado')->where('metodo_pago_id', $metodo->id)->sum('monto');
                $egresos = $caja->movimientos()->where('tipo', 'egreso')->where('estado', 'confirmado')->where('metodo_pago_id', $metodo->id)->sum('monto');
                $totalIngresos = Money::add($totalIngresos, (string) $ingresos);
                $totalEgresos = Money::add($totalEgresos, (string) $egresos);

                $totalEsperado = Money::add($totalEsperado, $esperado);
                $totalContado = Money::add($totalContado, $contado);
            }

            $antes = [
                'estado' => $caja->estado,
                'saldo_final_esperado' => (string) $caja->saldo_final_esperado,
            ];

            $caja->update([
                'fecha_cierre' => now(),
                'total_ingresos' => $totalIngresos,
                'total_egresos' => $totalEgresos,
                'saldo_final_esperado' => $totalEsperado,
                'saldo_final_real' => $totalContado,
                'diferencia' => Money::sub($totalContado, $totalEsperado),
                'estado' => 'cerrada',
                'notas_cierre' => $notas,
                'closed_by' => Auth::id(),
            ]);

            AuditoriaService::registrar('cierre', 'caja', Caja::class, $caja->id, $antes, [
                'saldo_final_esperado' => (string) $caja->saldo_final_esperado,
                'saldo_final_real' => (string) $caja->saldo_final_real,
                'diferencia' => (string) $caja->diferencia,
            ], $notas);

            return $caja->fresh();
        });
    }

    public function reabrir(Caja $caja, string $motivo): Caja
    {
        if (trim($motivo) === '') {
            throw ValidationException::withMessages(['motivo' => 'El motivo de reapertura es obligatorio.']);
        }

        return DB::transaction(function () use ($caja, $motivo) {
            $antes = ['estado' => $caja->estado, 'saldo_final_real' => (string) $caja->saldo_final_real];

            $caja->update([
                'fecha_cierre' => null,
                'saldo_final_esperado' => null,
                'saldo_final_real' => null,
                'diferencia' => null,
                'estado' => 'abierta',
                'notas_cierre' => null,
                'closed_by' => null,
            ]);

            AuditoriaService::registrar('actualizar', 'caja', Caja::class, $caja->id, $antes, ['estado' => 'abierta'], $motivo);

            return $caja->fresh();
        });
    }
}
