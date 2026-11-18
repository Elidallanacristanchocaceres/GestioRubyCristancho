<?php

namespace Database\Seeders;

use App\Models\CategoriaGasto;
use Illuminate\Database\Seeder;

/**
 * Categorías de gasto separadas por ámbito.
 *
 * Las categorías históricas se conservan como gastos del salón. Las de la
 * casa son nuevas y se identifican con nombres propios para no mezclarlas.
 */
class CategoriaGastoSeparacionSeeder extends Seeder
{
    public function run(): void
    {
        CategoriaGasto::whereNull('ambito')
            ->orWhere('ambito', '')
            ->update(['ambito' => CategoriaGasto::AMBITO_SALON]);

        $categoriasCasa = [
            'Arriendo casa',
            'Servicios casa',
            'Mercado',
            'Transporte casa',
            'Salud',
            'Educación',
            'Otros casa',
        ];

        foreach ($categoriasCasa as $nombre) {
            CategoriaGasto::updateOrCreate(
                ['nombre' => $nombre],
                ['ambito' => CategoriaGasto::AMBITO_CASA, 'activo' => true]
            );
        }

        // Las categorías del salón que ya existían quedan explícitamente
        // marcadas como salón (sin eliminar ni renombrar ninguna).
        CategoriaGasto::whereNotIn('nombre', $categoriasCasa)
            ->update(['ambito' => CategoriaGasto::AMBITO_SALON]);
    }
}
