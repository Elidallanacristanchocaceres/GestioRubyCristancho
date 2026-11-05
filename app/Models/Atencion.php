<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Registro diario de clientes: una atención del día.
 *
 * Independiente del consumo de productos. Guarda a quién se atendió, qué
 * servicios se le realizaron y el total. No descuenta inventario.
 */
class Atencion extends Model
{
    public const ESTADO_ACTIVO = 'activo';

    public const ESTADO_ANULADO = 'anulado';

    protected $table = 'atenciones';

    protected $fillable = [
        'token',
        'cliente_id',
        'cita_id',
        'venta_id',
        'estilista_id',
        'fecha',
        'descripcion',
        'observaciones',
        'total',
        'pagado',
        'metodo_pago',
        'estado',
        'reversado_por',
        'reversado_en',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'total' => 'decimal:2',
            'pagado' => 'decimal:2',
            'reversado_en' => 'datetime',
        ];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(AtencionDetalle::class, 'atencion_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function estilista(): BelongsTo
    {
        return $this->belongsTo(Estilista::class, 'estilista_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function esActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function estadoLabel(): string
    {
        return $this->esActivo() ? 'Activo' : 'Anulado';
    }

    public function saldo(): string
    {
        return Money::sub($this->total ?? 0, $this->pagado ?? 0);
    }

    public function tieneSaldo(): bool
    {
        return Money::mayorQue($this->saldo(), '0');
    }
}
