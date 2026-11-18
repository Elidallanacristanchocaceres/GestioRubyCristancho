<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\CajaMovimiento;
use App\Models\CategoriaGasto;
use App\Models\Gasto;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GastoService
{
    public function __construct(
        private readonly CajaService $caja,
        private readonly CierreDiarioService $cierres,
    ) {}

    public function registrar(array $datos): Gasto
    {
        return DB::transaction(function () use ($datos) {
            $monto = Money::normal($datos['monto'] ?? '0');

            if (! Money::mayorQue($monto, '0')) {
                throw ValidationException::withMessages(['monto' => 'El monto debe ser mayor que cero.']);
            }

            $estado = $datos['estado'] ?? 'pagado';
            $ambito = $this->ambito($datos);
            $fecha = $datos['fecha'] ?? now()->toDateString();
            $this->cierres->assertDiaAbierto($fecha);

            $gasto = Gasto::create([
                'monto' => $monto,
                'categoria_gasto_id' => $datos['categoria_gasto_id'] ?? null,
                'ambito' => $ambito,
                'estado' => $estado,
                'descripcion' => $datos['descripcion'],
                'fecha' => $fecha,
                'metodo_pago_id' => $datos['metodo_pago_id'] ?? null,
                'proveedor_id' => $datos['proveedor_id'] ?? null,
                'comprobante' => $datos['comprobante'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // Solo afecta la caja si el gasto está pagado y hay caja abierta.
            if ($estado === 'pagado' && ($datos['afectar_caja'] ?? true) && Caja::cajaAbierta()) {
                $movimiento = $this->caja->registrarMovimiento([
                    'gasto_id' => $gasto->id,
                    'tipo' => 'egreso',
                    'monto' => $monto,
                    'metodo_pago_id' => $gasto->metodo_pago_id,
                    'concepto' => 'Gasto: '.mb_substr($gasto->descripcion, 0, 80),
                    'descripcion' => $gasto->descripcion,
                    'referencia' => $gasto->comprobante,
                ]);
                $gasto->update(['caja_movimiento_id' => $movimiento->id]);
            }

            AuditoriaService::registrar('crear', 'gastos', Gasto::class, $gasto->id, [], [
                'monto' => $monto,
                'ambito' => $ambito,
                'descripcion' => $gasto->descripcion,
            ]);

            return $gasto;
        });
    }

    public function actualizar(Gasto $gasto, array $datos): Gasto
    {
        return DB::transaction(function () use ($gasto, $datos) {
            $antes = $gasto->only(['monto', 'descripcion', 'fecha', 'estado', 'categoria_gasto_id', 'ambito']);

            $this->cierres->assertDiaAbierto($gasto->fecha->toDateString());
            if (isset($datos['fecha'])) {
                $this->cierres->assertDiaAbierto($datos['fecha']);
            }

            $gasto->update([
                'monto' => Money::normal($datos['monto'] ?? $gasto->monto),
                'categoria_gasto_id' => $datos['categoria_gasto_id'] ?? $gasto->categoria_gasto_id,
                'ambito' => $this->ambito($datos, $gasto->ambito),
                'descripcion' => $datos['descripcion'] ?? $gasto->descripcion,
                'fecha' => $datos['fecha'] ?? $gasto->fecha,
                'metodo_pago_id' => $datos['metodo_pago_id'] ?? $gasto->metodo_pago_id,
                'proveedor_id' => $datos['proveedor_id'] ?? $gasto->proveedor_id,
                'comprobante' => $datos['comprobante'] ?? $gasto->comprobante,
                'estado' => $datos['estado'] ?? $gasto->estado,
            ]);

            AuditoriaService::registrar('actualizar', 'gastos', Gasto::class, $gasto->id, $antes, $gasto->only(['monto', 'descripcion', 'estado', 'ambito']));

            return $gasto->fresh();
        });
    }

    public function eliminar(Gasto $gasto, ?string $motivo = null): void
    {
        DB::transaction(function () use ($gasto, $motivo) {
            $antes = $gasto->only(['monto', 'descripcion']);

            $this->cierres->assertDiaAbierto($gasto->fecha->toDateString());

            if ($gasto->caja_movimiento_id) {
                $movimiento = CajaMovimiento::find($gasto->caja_movimiento_id);
                if ($movimiento) {
                    $movimiento->update([
                        'estado' => 'anulado',
                        'descripcion' => trim(($movimiento->descripcion ?? '').' [Anulado]'),
                    ]);
                }
            }

            $gasto->delete();

            AuditoriaService::registrar('eliminar', 'gastos', Gasto::class, $gasto->id, $antes, [], $motivo);
        });
    }

    private function ambito(array $datos, ?string $actual = null): string
    {
        $solicitado = $datos['ambito'] ?? null;

        if (in_array($solicitado, [Gasto::AMBITO_SALON, Gasto::AMBITO_CASA], true)) {
            return $solicitado;
        }

        if (! empty($datos['categoria_gasto_id'])) {
            $categoria = CategoriaGasto::find($datos['categoria_gasto_id']);
            if ($categoria && in_array($categoria->ambito, [Gasto::AMBITO_SALON, Gasto::AMBITO_CASA], true)) {
                return $categoria->ambito;
            }
        }

        return in_array($actual, [Gasto::AMBITO_SALON, Gasto::AMBITO_CASA], true)
            ? $actual
            : Gasto::AMBITO_SALON;
    }
}
