<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumoServicio extends Model
{
    protected $table = 'consumos_servicio';

    protected $fillable = [
        'consumo_id',
        'cita_id',
        'venta_id',
        'producto_id',
        'estilista_id',
        'cantidad',
        'unidad',
        'costo_unitario',
        'costo_total',
        'lotes_consumidos',
        'estado',
        'reversado_por',
        'notas',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'costo_unitario' => 'decimal:6',
            'costo_total' => 'decimal:2',
            'lotes_consumidos' => 'array',
        ];
    }

    public function consumo(): BelongsTo
    {
        return $this->belongsTo(Consumo::class, 'consumo_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
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
}
