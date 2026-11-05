<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgendaEstilista extends Model
{
    protected $table = 'agenda_estilistas';

    protected $fillable = [
        'estilista_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
        'disponible',
    ];

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
            'disponible' => 'boolean',
        ];
    }

    public function estilista(): BelongsTo
    {
        return $this->belongsTo(Estilista::class, 'estilista_id');
    }

    public function diaLabel(): string
    {
        return ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'][$this->dia_semana] ?? '';
    }
}
