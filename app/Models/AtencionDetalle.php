<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de una atención: un servicio (o texto libre) con su precio y subtotal.
 */
class AtencionDetalle extends Model
{
    protected $table = 'atencion_detalles';

    protected $fillable = [
        'atencion_id',
        'servicio_id',
        'nombre',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'subtotal',
        'estilista_id',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'precio_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function atencion(): BelongsTo
    {
        return $this->belongsTo(Atencion::class, 'atencion_id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    public function estilista(): BelongsTo
    {
        return $this->belongsTo(Estilista::class, 'estilista_id');
    }

    public function etiqueta(): string
    {
        return $this->servicio?->nombre
            ?? $this->nombre
            ?? $this->descripcion
            ?? 'Servicio';
    }
}
