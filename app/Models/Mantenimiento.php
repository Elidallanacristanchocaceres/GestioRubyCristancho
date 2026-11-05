<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mantenimiento extends Model
{
    use SoftDeletes;

    protected $table = 'mantenimientos';

    protected $fillable = [
        'equipo',
        'descripcion',
        'fecha_solicitud',
        'fecha_realizado',
        'costo',
        'estado',
        'observaciones',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_solicitud' => 'date',
            'fecha_realizado' => 'date',
            'costo' => 'decimal:2',
        ];
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
