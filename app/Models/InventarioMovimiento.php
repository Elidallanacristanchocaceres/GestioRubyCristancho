<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventarioMovimiento extends Model
{
    use SoftDeletes;

    protected $table = 'inventario_movimientos';

    protected $fillable = [
        'producto_id',
        'lote_id',
        'tipo',
        'unidad',
        'cantidad',
        'stock_anterior',
        'stock_nuevo',
        'precio_unitario',
        'costo_unitario',
        'costo_total',
        'referencia',
        'motivo',
        'modulo',
        'modulo_id',
        'autorizado_por',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'stock_anterior' => 'decimal:3',
            'stock_nuevo' => 'decimal:3',
            'precio_unitario' => 'decimal:2',
            'costo_unitario' => 'decimal:6',
            'costo_total' => 'decimal:2',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(InventarioLote::class, 'lote_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function autorizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por');
    }
}
