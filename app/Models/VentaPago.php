<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VentaPago extends Model
{
    use SoftDeletes;

    protected $table = 'venta_pagos';

    protected $fillable = [
        'venta_id',
        'metodo_pago_id',
        'monto',
        'tipo',
        'fecha_hora',
        'referencia',
        'estado',
        'caja_movimiento_id',
        'notas',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_hora' => 'datetime',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class, 'metodo_pago_id');
    }

    public function cajaMovimiento(): BelongsTo
    {
        return $this->belongsTo(CajaMovimiento::class, 'caja_movimiento_id');
    }
}
