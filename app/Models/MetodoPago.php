<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetodoPago extends Model
{
    protected $table = 'metodo_pagos';

    protected $fillable = [
        'nombre',
        'descripcion',
        'es_efectivo',
    ];

    protected function casts(): array
    {
        return [
            'es_efectivo' => 'boolean',
        ];
    }

    public function esEfectivo(): bool
    {
        if ($this->exists && $this->getAttribute('es_efectivo') !== null) {
            return (bool) $this->getAttribute('es_efectivo');
        }

        return in_array(mb_strtolower($this->nombre), ['efectivo', 'cash'], true);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'metodo_pago_id');
    }

    public function ventaPagos(): HasMany
    {
        return $this->hasMany(VentaPago::class, 'metodo_pago_id');
    }

    public function gastos(): HasMany
    {
        return $this->hasMany(Gasto::class, 'metodo_pago_id');
    }
}
