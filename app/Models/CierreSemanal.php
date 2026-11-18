<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cierre semanal del negocio.
 *
 * No es el cierre de caja (efectivo del día): es el resultado de la semana
 * con ingresos, costos, gastos, diezmo y neto.
 */
class CierreSemanal extends Model
{
    public const ESTADO_CERRADO = 'cerrado';

    protected $table = 'cierres_semanales';

    protected $fillable = [
        'fecha_inicio',
        'fecha_fin',
        'ingresos_servicios',
        'ingresos_productos',
        'costo_productos_consumidos',
        'costo_productos_vendidos',
        'gastos',
        'base_diezmo',
        'porcentaje_diezmo',
        'diezmo',
        'neto',
        'detalle',
        'notas',
        'estado',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'ingresos_servicios' => 'decimal:2',
            'ingresos_productos' => 'decimal:2',
            'costo_productos_consumidos' => 'decimal:2',
            'costo_productos_vendidos' => 'decimal:2',
            'gastos' => 'decimal:2',
            'base_diezmo' => 'decimal:2',
            'porcentaje_diezmo' => 'decimal:2',
            'diezmo' => 'decimal:2',
            'neto' => 'decimal:2',
            'detalle' => 'array',
        ];
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function etiqueta(): string
    {
        return $this->fecha_inicio->format('d/m/Y').' – '.$this->fecha_fin->format('d/m/Y');
    }

    public function estaCerrado(): bool
    {
        return $this->estado === self::ESTADO_CERRADO;
    }

    public function netoEsNegativo(): bool
    {
        return Money::esNegativo((string) $this->neto);
    }
}
