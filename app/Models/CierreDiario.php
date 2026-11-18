<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cierre diario del salón.
 *
 * Guarda una foto (JSON) de los movimientos y los totales del día. Un cierre
 * reabierto conserva su versión y se registra un nuevo cierre cuando el día
 * vuelve a cerrarse; así nunca se pierde el historial.
 */
class CierreDiario extends Model
{
    public const ESTADO_CERRADO = 'cerrado';

    public const ESTADO_REABIERTO = 'reabierto';

    protected $table = 'cierres_diarios';

    protected $fillable = [
        'fecha',
        'estado',
        'total_servicios',
        'total_productos_vendidos',
        'total_productos_utilizados',
        'total_gastos_salon',
        'total_gastos_casa',
        'total_ingresos',
        'total_descuentos',
        'resultado',
        'costo_productos_vendidos',
        'detalle',
        'notas',
        'cerrado_por',
        'cerrado_en',
        'reabierto_por',
        'reabierto_en',
        'motivo_reapertura',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'total_servicios' => 'decimal:2',
            'total_productos_vendidos' => 'decimal:2',
            'total_productos_utilizados' => 'decimal:2',
            'total_gastos_salon' => 'decimal:2',
            'total_gastos_casa' => 'decimal:2',
            'total_ingresos' => 'decimal:2',
            'total_descuentos' => 'decimal:2',
            'resultado' => 'decimal:2',
            'costo_productos_vendidos' => 'decimal:2',
            'detalle' => 'array',
            'cerrado_en' => 'datetime',
            'reabierto_en' => 'datetime',
        ];
    }

    public function cerrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    public function reabridor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reabierto_por');
    }

    public function esCerrado(): bool
    {
        return $this->estado === self::ESTADO_CERRADO;
    }
}
