<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CajaCierreDetalle extends Model
{
    protected $table = 'caja_cierre_detalles';

    protected $fillable = [
        'caja_id',
        'metodo_pago_id',
        'saldo_esperado',
        'valor_contado',
        'diferencia',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'saldo_esperado' => 'decimal:2',
            'valor_contado' => 'decimal:2',
            'diferencia' => 'decimal:2',
        ];
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class, 'metodo_pago_id');
    }
}
