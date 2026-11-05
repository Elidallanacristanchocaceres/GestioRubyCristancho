<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Caja extends Model
{
    protected $table = 'caja';

    protected $fillable = [
        'fecha_apertura',
        'fecha_cierre',
        'saldo_inicial',
        'nota_apertura',
        'total_ingresos',
        'total_egresos',
        'saldo_final_esperado',
        'saldo_final_real',
        'diferencia',
        'estado',
        'notas_cierre',
        'created_by',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_apertura' => 'datetime',
            'fecha_cierre' => 'datetime',
            'saldo_inicial' => 'decimal:2',
            'total_ingresos' => 'decimal:2',
            'total_egresos' => 'decimal:2',
            'saldo_final_esperado' => 'decimal:2',
            'saldo_final_real' => 'decimal:2',
            'diferencia' => 'decimal:2',
        ];
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(CajaMovimiento::class, 'caja_id');
    }

    public function cierreDetalles(): HasMany
    {
        return $this->hasMany(CajaCierreDetalle::class, 'caja_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cerrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function estaAbierta(): bool
    {
        return $this->estado === 'abierta';
    }

    public static function cajaAbierta(): ?self
    {
        return static::where('estado', 'abierta')->orderByDesc('id')->first();
    }

    /**
     * Saldo esperado = saldo inicial + entradas efectivas - salidas efectivas.
     */
    public function saldoEsperado(): string
    {
        $ingresos = $this->movimientos()
            ->where('tipo', 'ingreso')
            ->where('estado', 'confirmado')
            ->sum('monto');

        $egresos = $this->movimientos()
            ->where('tipo', 'egreso')
            ->where('estado', 'confirmado')
            ->sum('monto');

        $saldo = Money::add((string) $this->saldo_inicial, (string) $ingresos);

        return Money::sub($saldo, (string) $egresos);
    }

    public function saldoEsperadoPorMetodo(int $metodoPagoId): string
    {
        $ingresos = $this->movimientos()
            ->where('tipo', 'ingreso')
            ->where('estado', 'confirmado')
            ->where('metodo_pago_id', $metodoPagoId)
            ->sum('monto');

        $egresos = $this->movimientos()
            ->where('tipo', 'egreso')
            ->where('estado', 'confirmado')
            ->where('metodo_pago_id', $metodoPagoId)
            ->sum('monto');

        $esEfectivo = MetodoPago::find($metodoPagoId)?->esEfectivo();

        if ($esEfectivo) {
            $saldo = Money::add((string) $this->saldo_inicial, (string) $ingresos);

            return Money::sub($saldo, (string) $egresos);
        }

        return Money::sub((string) $ingresos, (string) $egresos);
    }

    public function saldoActual(): string
    {
        return $this->estaAbierta() ? $this->saldoEsperado() : (string) $this->saldo_final_real;
    }
}
