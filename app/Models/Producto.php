<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use SoftDeletes;

    public const UNIDAD_UNIDAD = 'unidad';

    public const UNIDAD_GRAMO = 'gramo';

    public const UNIDAD_MILILITRO = 'mililitro';

    public const UNIDAD_BOLSA = 'bolsa';

    protected $fillable = [
        'categoria_producto_id',
        'proveedor_id',
        'nombre',
        'marca',
        'tono',
        'descripcion',
        'precio_costo',
        'precio_iva',
        'precio_venta',
        'precio_sugerido',
        'stock',
        'stock_minimo',
        'unidad_control',
        'contenido_envase',
        'presentacion',
        'costo_envase',
        'costo_unitario',
        'fecha_compra',
        'fecha_vencimiento',
        'activo',
        'pendiente_confirmacion',
    ];

    protected function casts(): array
    {
        return [
            'precio_costo' => 'decimal:2',
            'precio_iva' => 'decimal:2',
            'precio_venta' => 'decimal:2',
            'precio_sugerido' => 'decimal:2',
            'costo_envase' => 'decimal:2',
            'stock' => 'decimal:3',
            'stock_minimo' => 'decimal:3',
            'contenido_envase' => 'decimal:3',
            'costo_unitario' => 'decimal:6',
            'fecha_compra' => 'date',
            'fecha_vencimiento' => 'date',
            'activo' => 'boolean',
            'pendiente_confirmacion' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaProducto::class, 'categoria_producto_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class, 'producto_id');
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(InventarioLote::class, 'producto_id');
    }

    public function compraDetalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class, 'producto_id');
    }

    public function serviciosReceta(): HasMany
    {
        return $this->hasMany(ServicioProducto::class, 'producto_id');
    }

    public function consumos(): HasMany
    {
        return $this->hasMany(ConsumoServicio::class, 'producto_id');
    }

    public function esPeso(): bool
    {
        return $this->unidad_control === self::UNIDAD_GRAMO;
    }

    public function esVolumen(): bool
    {
        return $this->unidad_control === self::UNIDAD_MILILITRO;
    }

    public function esUnidad(): bool
    {
        return $this->unidad_control === self::UNIDAD_UNIDAD;
    }

    public function esBolsa(): bool
    {
        return $this->unidad_control === self::UNIDAD_BOLSA;
    }

    /**
     * Texto de presentación del envase ("1000 ml", "12 x 10 ml"...).
     *
     * Si el producto no tiene presentación cargada, se deduce del contenido
     * y la unidad de control.
     */
    public function presentacionLabel(): string
    {
        if ($this->presentacion) {
            return $this->presentacion;
        }

        if ($this->contenido_envase === null) {
            return 'Sin presentación';
        }

        return Money::formatoDecimal($this->contenido_envase).' '.$this->unidadAbrev();
    }

    public function unidadLabel(): string
    {
        return match ($this->unidad_control) {
            self::UNIDAD_GRAMO => 'gramos',
            self::UNIDAD_MILILITRO => 'mililitros',
            self::UNIDAD_BOLSA => 'bolsas',
            default => 'unidades',
        };
    }

    public function unidadAbrev(): string
    {
        return match ($this->unidad_control) {
            self::UNIDAD_GRAMO => 'g',
            self::UNIDAD_MILILITRO => 'ml',
            self::UNIDAD_BOLSA => 'bolsa',
            default => 'und',
        };
    }

    public function tieneStockBajo(): bool
    {
        return Money::menorQue((string) $this->stock, (string) $this->stock_minimo, Money::ESCALA_CANTIDAD)
            && ! Money::igual((string) $this->stock, '0', Money::ESCALA_CANTIDAD);
    }

    public function estaAgotado(): bool
    {
        return Money::igual((string) $this->stock, '0', Money::ESCALA_CANTIDAD);
    }

    public function proximoAgotarse(int $dias = 14): bool
    {
        return ! $this->estaAgotado()
            && $this->tieneStockBajo()
            && $this->fecha_vencimiento !== null
            && $this->fecha_vencimiento->diffInDays(now()) <= $dias;
    }

    /**
     * Calcula el costo unitario SOLO cuando aún no hay uno configurado.
     *
     * El costo por gramo/ml es un valor propio del salón y puede no coincidir
     * con (costo del envase ÷ contenido). Si ya está definido, no se pisa.
     */
    public function recalcularCostoUnitario(): void
    {
        if ($this->costo_unitario !== null
            && ! Money::igual((string) $this->costo_unitario, '0', Money::ESCALA_COSTO_UNITARIO)) {
            return;
        }

        if ($this->contenido_envase && Money::mayorQue((string) $this->contenido_envase, '0', Money::ESCALA_CANTIDAD)
            && $this->costo_envase) {
            $this->costo_unitario = Money::div((string) $this->costo_envase, (string) $this->contenido_envase);
        }
    }
}
