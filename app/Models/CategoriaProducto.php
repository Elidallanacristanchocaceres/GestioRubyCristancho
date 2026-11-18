<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Categoría de producto.
 *
 * El campo "grupo" agrupa las categorías por tipo de producto (tintes,
 * activadores, decolorantes, shampoos, tratamientos, ampollas, otros) para
 * poder filtrar el inventario sin mezclar líneas ni tonos.
 */
class CategoriaProducto extends Model
{
    use SoftDeletes;

    public const GRUPO_TINTES = 'tintes';

    public const GRUPO_ACTIVADORES = 'activadores';

    public const GRUPO_DECOLORANTES = 'decolorantes';

    public const GRUPO_CHAMPUOS = 'champus';

    public const GRUPO_TRATAMIENTOS = 'tratamientos';

    public const GRUPO_AMPOLLAS = 'ampollas';

    public const GRUPO_OTROS = 'otros';

    public const GRUPOS = [
        self::GRUPO_TINTES => 'Tintes',
        self::GRUPO_ACTIVADORES => 'Activadores y oxidantes',
        self::GRUPO_DECOLORANTES => 'Decolorantes y aclarantes',
        self::GRUPO_CHAMPUOS => 'Champús',
        self::GRUPO_TRATAMIENTOS => 'Tratamientos',
        self::GRUPO_AMPOLLAS => 'Ampollas',
        self::GRUPO_OTROS => 'Otros productos capilares',
    ];

    protected $table = 'categoria_productos';

    protected $fillable = [
        'nombre',
        'grupo',
    ];

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'categoria_producto_id');
    }

    public function grupoLabel(): string
    {
        return self::GRUPOS[$this->grupo] ?? 'Otros productos capilares';
    }
}
