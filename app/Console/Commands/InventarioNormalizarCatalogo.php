<?php

namespace App\Console\Commands;

use App\Models\CategoriaProducto;
use App\Models\Producto;
use Illuminate\Console\Command;

/**
 * Completa el catálogo ALTER EGO ya cargado en `productos` con los datos que
 * la documentación permite deducir (marca, unidad de control y contenido del
 * envase) y lo organiza en las categorías funcionales del inventario.
 *
 * No crea productos ni elimina ni sobrescribe existencias: solo completa
 * metadatos derivados del nombre/presentación.
 */
class InventarioNormalizarCatalogo extends Command
{
    protected $signature = 'inventario:normalizar-catalogo {--solo-reporte : mostrar tabla sin guardar}';

    protected $description = 'Completa marca/unidad/contenido y reclasifica el catálogo ALTER EGO';

    public function handle(): int
    {
        $soloReporte = (bool) $this->option('solo-reporte');

        $categorias = [
            'Tintes' => 'Coloración y tinturas',
            'Oxidantes y Activadores' => 'Coactivadores, developers y activadores',
            'Decolorantes' => 'Polvos y cremas decolorantes (se controlan en gramos)',
            'Tratamientos' => 'Mascarillas, ampollas, lociones y tratamientos reparadores',
            'Shampoo' => 'Shampoos por líneas',
            'Acondicionadores' => 'Acondicionadores y acondicionadores sin enjuague',
            'Mascarillas' => 'Mascarillas y cremas',
            'Lociones Tónicos y Aceites' => 'Tónicos, lociones y óleos',
            'Estilizado' => 'Stylege: pomada, lacas y productos de peinado',
        ];

        $map = [];
        foreach ($categorias as $nombre => $descripcion) {
            $map[$nombre] = CategoriaProducto::firstOrCreate(['nombre' => $nombre], ['descripcion' => $descripcion]);
        }

        $productos = Producto::orderBy('id')->get();

        $filas = [];
        $guardadas = 0;

        foreach ($productos as $p) {
            $nombre = $p->nombre;

            [$unidad, $contenido] = $this->parsePresentacion($nombre);
            $categoria = $this->clasificar($nombre, $p->categoria?->nombre);

            $antes = [
                'marca' => $p->marca,
                'unidad_control' => $p->unidad_control,
                'contenido_envase' => $p->contenido_envase,
                'categoria_producto_id' => $p->categoria_producto_id,
            ];

            $filas[] = [
                'id' => $p->id,
                'producto' => $nombre,
                'categoria' => $categoria,
                'unidad' => $unidad,
                'contenido' => $contenido === null ? '—' : (string) $contenido,
            ];

            if ($soloReporte) {
                continue;
            }

            $nuevo = [
                'marca' => $p->marca ?: 'ALTER EGO',
                'unidad_control' => $unidad,
                'contenido_envase' => $contenido !== null ? $contenido : $p->contenido_envase,
                'categoria_producto_id' => $map[$categoria]->id,
            ];

            if ($nuevo !== $antes) {
                $p->fill($nuevo);
                $p->save();
                $guardadas++;
            }
        }

        $this->table(['ID', 'Producto', 'Categoría', 'Unidad', 'Contenido'], $filas);
        $this->info(($soloReporte ? 'Reporte' : 'Actualización')." completada: {$guardadas} productos tocados de {$productos->count()}.");

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: float|null}
     */
    private function parsePresentacion(string $nombre): array
    {
        // Kits y ampollas tipo "12x10ml": se controlan por unidad (la caja).
        if (preg_match('/\d+\s*x\s*\d+/i', $nombre)) {
            return ['unidad', null];
        }

        // "Egg Living 125ml", "Techno Fruit Color 100ml", "Bleaching Blue Powder 1000gr"
        if (preg_match('/\b(\d+(?:[.,]\d+)?)\s*(ml|gr|g)\b/i', $nombre, $m)) {
            $unidad = strtolower($m[2]) === 'ml' ? 'mililitro' : 'gramo';

            return [$unidad, (float) str_replace(',', '.', $m[1])];
        }

        return ['unidad', null];
    }

    private function clasificar(string $nombre, ?string $linea): string
    {
        $n = mb_strtolower($nombre);

        if (str_contains($n, 'coactivador') || str_contains($n, 'developer')) {
            return 'Oxidantes y Activadores';
        }
        if (str_contains($n, 'toner') || str_contains($n, 'techno fruit')) {
            return 'Tintes';
        }
        if (str_contains($n, 'bleaching') || str_contains($n, 'lightener') || str_contains($n, 'pure light')
            || str_contains($n, 'ultra 9') || str_contains($n, 'blond')) {
            return 'Decolorantes';
        }
        if (str_contains($n, 'pre-shampoo') || str_contains($n, 'prepare treatment')
            || str_contains($n, 'transformer') || str_contains($n, 'booster')
            || str_contains($n, 'rebala')) {
            return 'Tratamientos';
        }
        if (str_contains($n, 'shampoo')) {
            return 'Shampoo';
        }
        if (str_contains($n, 'conditioner') || str_contains($n, 'condicionador') || str_contains($n, 'leave cond')) {
            return 'Acondicionadores';
        }
        if (str_contains($n, 'mask') || str_contains($n, 'mascarilla') || str_contains($n, 'whipped')) {
            return 'Mascarillas';
        }
        if (str_contains($n, 'tonic') || str_contains($n, 'lotion') || str_contains($n, 'oil')
            || str_contains($n, 'ampolla') || str_contains($n, 'kit')) {
            return 'Lociones Tónicos y Aceites';
        }
        if (str_contains($n, 'pomade') || str_contains($n, 'laca') || str_contains($n, 'hi-t')
            || str_contains($n, 'elastic') || str_contains($n, 'cloud') || str_contains($n, 'volume')) {
            return 'Estilizado';
        }
        if (str_contains($n, 'living') || str_contains($n, 'repair') || str_contains($n, 'shine')) {
            return 'Tratamientos';
        }

        return $linea ?: 'Tratamientos';
    }
}