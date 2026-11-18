<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = [
        'nombre',
        'email',
        'fecha_nacimiento',
        'celular',
        'whatsapp',
        'direccion',
        'observaciones',
        'preferencias_cabello',
        'formula_color',
        'saldo_pendiente',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'activo' => 'boolean',
            'saldo_pendiente' => 'decimal:2',
            'direccion' => 'encrypted',
            'observaciones' => 'encrypted',
            'preferencias_cabello' => 'encrypted',
            'formula_color' => 'encrypted',
        ];
    }

    public function getEdadAttribute(): ?int
    {
        return $this->fecha_nacimiento?->age;
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeBuscar($query, ?string $termino)
    {
        if (! $termino) {
            return $query;
        }

        return $query->where(function ($q) use ($termino) {
            $q->where('nombre', 'like', "%{$termino}%")
                ->orWhere('celular', 'like', "%{$termino}%")
                ->orWhere('email', 'like', "%{$termino}%");
        });
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'cliente_id');
    }

    public function consumos(): HasMany
    {
        return $this->hasMany(Consumo::class, 'cliente_id');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'cliente_id');
    }

    public function atenciones(): HasMany
    {
        return $this->hasMany(Atencion::class, 'cliente_id');
    }

    public function pagosVisitas(): int
    {
        return $this->citas()->where('estado', Cita::ESTADO_FINALIZADA)->count();
    }
}
