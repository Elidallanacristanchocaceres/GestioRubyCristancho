<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Auditoria extends Model
{
    protected $table = 'auditorias';

    protected $fillable = [
        'user_id',
        'accion',
        'modulo',
        'modelo',
        'modelo_id',
        'antes',
        'despues',
        'motivo',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'antes' => 'array',
            'despues' => 'array',
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function etiquetaAccion(): string
    {
        return match ($this->accion) {
            'crear' => 'Creó',
            'actualizar' => 'Actualizó',
            'eliminar' => 'Eliminó',
            'anular' => 'Anuló',
            'ajuste' => 'Ajustó',
            'apertura' => 'Abrió caja',
            'cierre' => 'Cerró caja',
            'consumo' => 'Consumió inventario',
            'pago' => 'Registró pago',
            'ver' => 'Consultó',
            default => ucfirst($this->accion),
        };
    }
}
