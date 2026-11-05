<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venta extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'numero',
        'cliente_id',
        'cita_id',
        'estilista_id',
        'fecha',
        'fecha_hora',
        'subtotal',
        'descuento',
        'total',
        'total_pagado',
        'saldo_pendiente',
        'estado',
        'tipo',
        'notas',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_hora' => 'datetime',
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'total' => 'decimal:2',
            'total_pagado' => 'decimal:2',
            'saldo_pendiente' => 'decimal:2',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function estilista(): BelongsTo
    {
        return $this->belongsTo(Estilista::class, 'estilista_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class, 'venta_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(VentaPago::class, 'venta_id');
    }

    public function consumos(): HasMany
    {
        return $this->hasMany(ConsumoServicio::class, 'venta_id');
    }

    public function estaPagada(): bool
    {
        return Money::igual((string) $this->total_pagado, (string) $this->total);
    }

    public function tieneSaldo(): bool
    {
        return Money::mayorQue((string) $this->saldo_pendiente, '0');
    }
}
