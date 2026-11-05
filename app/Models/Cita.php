<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cita extends Model
{
    use SoftDeletes;

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_CONFIRMADA = 'confirmada';

    public const ESTADO_EN_PROGRESO = 'en_progreso';

    public const ESTADO_FINALIZADA = 'completada';

    public const ESTADO_CANCELADA = 'cancelada';

    protected $fillable = [
        'cliente_id',
        'estilista_id',
        'servicio_id',
        'venta_id',
        'fecha_hora',
        'duracion_minutos',
        'precio',
        'anticipo',
        'saldo_pendiente',
        'estado',
        'notas',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            'duracion_minutos' => 'integer',
            'precio' => 'decimal:2',
            'anticipo' => 'decimal:2',
            'saldo_pendiente' => 'decimal:2',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function estilista(): BelongsTo
    {
        return $this->belongsTo(Estilista::class, 'estilista_id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'cita_id');
    }

    public function consumos(): HasMany
    {
        return $this->hasMany(ConsumoServicio::class, 'cita_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function horaFin(): \DateTimeInterface
    {
        return $this->fecha_hora->copy()->addMinutes((int) $this->duracion_minutos);
    }

    public function totalPagado(): string
    {
        $pagos = $this->pagos()->where('estado', 'completado')->sum('monto');

        return Money::normal($pagos);
    }

    public function estadoLabel(): string
    {
        return match ($this->estado) {
            self::ESTADO_PENDIENTE => 'Pendiente',
            self::ESTADO_CONFIRMADA => 'Confirmada',
            self::ESTADO_EN_PROGRESO => 'En proceso',
            self::ESTADO_FINALIZADA => 'Finalizada',
            self::ESTADO_CANCELADA => 'Cancelada',
            default => $this->estado,
        };
    }

    public function esEditable(): bool
    {
        return in_array($this->estado, [self::ESTADO_PENDIENTE, self::ESTADO_CONFIRMADA], true);
    }

    public function scopeDelDia($query, string $fecha)
    {
        return $query->whereDate('fecha_hora', $fecha);
    }
}
