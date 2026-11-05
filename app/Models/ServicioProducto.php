<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicioProducto extends Model
{
    protected $table = 'servicio_productos';

    protected $fillable = [
        'servicio_id',
        'producto_id',
        'cantidad_sugerida',
        'costo_estimado',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_sugerida' => 'decimal:3',
            'costo_estimado' => 'decimal:2',
        ];
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
