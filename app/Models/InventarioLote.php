<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioLote extends Model
{
    protected $table = 'inventario_lotes';

    protected $fillable = [
        'producto_id',
        'proveedor_id',
        'compra_id',
        'cantidad_inicial',
        'cantidad_disponible',
        'costo_unitario',
        'costo_total',
        'fecha_ingreso',
        'fecha_vencimiento',
        'estado',
        'estado_envase',
        'medido',
        'envases',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_inicial' => 'decimal:3',
            'cantidad_disponible' => 'decimal:3',
            'costo_unitario' => 'decimal:6',
            'costo_total' => 'decimal:2',
            'envases' => 'decimal:3',
            'fecha_ingreso' => 'date',
            'fecha_vencimiento' => 'date',
            'medido' => 'boolean',
        ];
    }

    public function esEnvaseEmpezado(): bool
    {
        return $this->estado_envase === 'empezado';
    }

    public function estaPendienteDeMedicion(): bool
    {
        return ! $this->medido;
    }

    public function estadoEnvaseLabel(): string
    {
        if (! $this->medido) {
            return 'Empezado (sin medir)';
        }

        return $this->esEnvaseEmpezado() ? 'Empezado' : 'Nuevo';
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }
}
