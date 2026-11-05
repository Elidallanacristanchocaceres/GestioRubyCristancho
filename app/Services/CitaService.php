<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\Servicio;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Agenda y citas. Evita cruces de horarios para el mismo profesional y
 * calcula el precio/anticipo/saldo de cada cita.
 */
class CitaService
{
    public function crear(array $datos): Cita
    {
        $servicio = Servicio::findOrFail($datos['servicio_id']);
        $duracion = $datos['duracion_minutos'] ?? $servicio->duracion_minutos;
        $precio = $datos['precio'] ?? $servicio->precio;
        $anticipo = Money::normal($datos['anticipo'] ?? '0');
        $fechaHora = $datos['fecha_hora'];

        $this->validarDisponibilidad((int) $datos['estilista_id'], $fechaHora, (int) $duracion);

        return Cita::create([
            'cliente_id' => $datos['cliente_id'],
            'estilista_id' => $datos['estilista_id'],
            'servicio_id' => $servicio->id,
            'fecha_hora' => $fechaHora,
            'duracion_minutos' => $duracion,
            'precio' => Money::normal($precio),
            'anticipo' => $anticipo,
            'saldo_pendiente' => Money::sub((string) $precio, $anticipo),
            'estado' => $datos['estado'] ?? Cita::ESTADO_PENDIENTE,
            'notas' => $datos['notas'] ?? null,
            'created_by' => Auth::id(),
        ]);
    }

    public function actualizar(Cita $cita, array $datos): Cita
    {
        $estilistaId = (int) ($datos['estilista_id'] ?? $cita->estilista_id);
        $fechaHora = $datos['fecha_hora'] ?? $cita->fecha_hora;
        $duracion = (int) ($datos['duracion_minutos'] ?? $cita->duracion_minutos);

        $this->validarDisponibilidad($estilistaId, $fechaHora, $duracion, $cita->id);

        $precio = $datos['precio'] ?? $cita->precio;
        $anticipo = $datos['anticipo'] ?? $cita->anticipo;

        $antes = $cita->only(['fecha_hora', 'estilista_id', 'estado', 'precio', 'anticipo']);

        $cita->update([
            'cliente_id' => $datos['cliente_id'] ?? $cita->cliente_id,
            'estilista_id' => $estilistaId,
            'servicio_id' => $datos['servicio_id'] ?? $cita->servicio_id,
            'fecha_hora' => $fechaHora,
            'duracion_minutos' => $duracion,
            'precio' => Money::normal($precio),
            'anticipo' => Money::normal($anticipo),
            'saldo_pendiente' => Money::sub((string) Money::normal($precio), (string) Money::normal($anticipo)),
            'estado' => $datos['estado'] ?? $cita->estado,
            'notas' => $datos['notas'] ?? $cita->notas,
        ]);

        AuditoriaService::registrar('actualizar', 'citas', Cita::class, $cita->id, $antes, $cita->only(['fecha_hora', 'estilista_id', 'estado']));

        return $cita->fresh();
    }

    /**
     * Verifica que el profesional no tenga otra cita que se cruce.
     */
    public function validarDisponibilidad(int $estilistaId, $fechaHora, int $duracion, ?int $ignorarCitaId = null): void
    {
        $inicio = \Illuminate\Support\Carbon::parse($fechaHora);
        $fin = $inicio->copy()->addMinutes($duracion);

        $citas = Cita::where('estilista_id', $estilistaId)
            ->whereDate('fecha_hora', $inicio->toDateString())
            ->whereNotIn('estado', [Cita::ESTADO_CANCELADA])
            ->when($ignorarCitaId, fn ($q) => $q->where('id', '!=', $ignorarCitaId))
            ->get();

        foreach ($citas as $existente) {
            $inicioExistente = $existente->fecha_hora;
            $finExistente = $inicioExistente->copy()->addMinutes((int) $existente->duracion_minutos);

            if ($inicio->lt($finExistente) && $fin->gt($inicioExistente)) {
                throw ValidationException::withMessages([
                    'fecha_hora' => 'El profesional ya tiene una cita en ese horario ('
                        .$inicioExistente->format('H:i').' - '.$finExistente->format('H:i').').',
                ]);
            }
        }
    }

    public function cancelar(Cita $cita, string $motivo): Cita
    {
        if ($cita->venta_id || $cita->consumos()->exists()) {
            throw ValidationException::withMessages([
                'cita' => 'Esta cita ya tiene venta o consumo registrado. Anule primero la venta para revertir inventario.',
            ]);
        }

        $antes = ['estado' => $cita->estado];
        $cita->update(['estado' => Cita::ESTADO_CANCELADA, 'notas' => trim(($cita->notas ?? '')."\nCancelada: ".$motivo)]);

        AuditoriaService::registrar('anular', 'citas', Cita::class, $cita->id, $antes, ['estado' => 'cancelada'], $motivo);

        return $cita->fresh();
    }

    /**
     * Franjas ocupadas de un estilista en una fecha (para pintar la agenda).
     */
    public function franjasOcupadas(int $estilistaId, string $fecha): array
    {
        return Cita::where('estilista_id', $estilistaId)
            ->whereDate('fecha_hora', $fecha)
            ->whereNotIn('estado', [Cita::ESTADO_CANCELADA])
            ->orderBy('fecha_hora')
            ->get()
            ->map(fn (Cita $c) => [
                'id' => $c->id,
                'inicio' => $c->fecha_hora->format('H:i'),
                'fin' => $c->fecha_hora->copy()->addMinutes((int) $c->duracion_minutos)->format('H:i'),
                'cliente' => $c->cliente?->nombre,
                'servicio' => $c->servicio?->nombre,
                'estado' => $c->estado,
            ])
            ->all();
    }
}
