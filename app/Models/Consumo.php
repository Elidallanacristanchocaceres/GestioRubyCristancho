<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cabecera de un consumo de productos por servicio.
 *
 * Una visita puede usar varios productos: cada uno es una fila en
 * consumos_servicio (l��neas), que guarda ademǭs el costo por gramo que
 * estaba vigente al momento de usarlo (hist��rico inmutable).
 */
class Consumo extends Model
{
    public const ESTADO_ACTIVO = 'activo';

    public const ESTADO_REVERSADO = 'reversado';

    protected $table = 'consumos';

    protected $fillable = [
        'token',
        'cliente_id',
        'cita_id',
        'venta_id',
        'servicio_id',
        'estilista_id',
        'fecha',
        'precio_cobrado',
        'observaciones',
        'descripcion',
        'costo_total',
        'estado',
        'reversado_por',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'costo_total' => 'decimal:2',
            'precio_cobrado' => 'decimal:2',
        ];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(ConsumoServicio::class, 'consumo_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    public function estilista(): BelongsTo
    {
        return $this->belongsTo(Estilista::class, 'estilista_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversado_por');
    }

    public function esActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function estadoLabel(): string
    {
        return $this->estado === self::ESTADO_ACTIVO ? 'Activo' : 'Anulado';
    }
}
