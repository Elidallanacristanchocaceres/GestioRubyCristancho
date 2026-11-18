<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaGasto extends Model
{
    public const AMBITO_SALON = 'salon';

    public const AMBITO_CASA = 'casa';

    protected $table = 'categoria_gastos';

    protected $fillable = [
        'nombre',
        'ambito',
        'activo',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function gastos(): HasMany
    {
        return $this->hasMany(Gasto::class, 'categoria_gasto_id');
    }
}
