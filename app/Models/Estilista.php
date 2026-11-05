<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Estilista extends Model
{
    use SoftDeletes;

    protected $table = 'estilistas';

    protected $fillable = [
        'nombre',
        'especialidad',
        'telefono',
        'fecha_contratacion',
        'salario_base',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_contratacion' => 'date',
            'salario_base' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'estilista_id');
    }

    public function consumos(): HasMany
    {
        return $this->hasMany(Consumo::class, 'estilista_id');
    }

    public function agenda(): HasMany
    {
        return $this->hasMany(AgendaEstilista::class, 'estilista_id');
    }
}
