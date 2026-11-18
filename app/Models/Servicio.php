<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Servicio extends Model
{
    protected $table = 'servicios';

    protected $fillable = [
        'categoria_servicio_id',
        'nombre',
        'descripcion',
        'precio',
        'costo_mano_obra',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'precio' => 'decimal:2',
            'costo_mano_obra' => 'decimal:2',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaServicio::class, 'categoria_servicio_id');
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'servicio_id');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(ServicioProducto::class, 'servicio_id');
    }

    /**
     * Costo estimado de productos según la receta técnica.
     */
    public function costoProductosEstimado(): string
    {
        $total = $this->productos()->with('producto')->get()->sum(function ($rel) {
            if (! $rel->producto) {
                return 0;
            }

            $costo = $rel->producto->costo_unitario
                ?? Money::div((string) $rel->producto->precio_costo, '1');

            return (float) Money::mul((string) $costo, (string) $rel->cantidad_sugerida);
        });

        return Money::normal($total);
    }

    public function costoTotalEstimado(): string
    {
        $productos = $this->costoProductosEstimado();

        return Money::add($productos, (string) ($this->costo_mano_obra ?? 0));
    }

    public function margenEstimado(): string
    {
        return Money::sub((string) $this->precio, $this->costoTotalEstimado());
    }
}
