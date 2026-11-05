<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compra extends Model
{
    protected $table = 'compras';

    protected $fillable = [
        'proveedor_id',
        'numero_factura',
        'fecha_compra',
        'fecha_recepcion',
        'total',
        'subtotal',
        'descuento',
        'total_pagado',
        'saldo_pendiente',
        'estado',
        'notas',
        'gasto_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_compra' => 'date',
            'fecha_recepcion' => 'date',
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'total' => 'decimal:2',
            'total_pagado' => 'decimal:2',
            'saldo_pendiente' => 'decimal:2',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class, 'compra_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(CompraPago::class, 'compra_id');
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(InventarioLote::class, 'compra_id');
    }

    public function gasto(): BelongsTo
    {
        return $this->belongsTo(Gasto::class, 'gasto_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function saldo(): string
    {
        return Money::sub((string) $this->total, (string) $this->total_pagado);
    }
}
