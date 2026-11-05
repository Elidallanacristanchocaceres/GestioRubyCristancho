<?php

namespace App\Console\Commands;

use App\Models\Producto;
use App\Support\Money;
use Illuminate\Console\Command;

/**
 * Carga inicial de los costos por gramo/ml/unidad que entregó el salón.
 *
 * Reglas:
 *  - El costo por gramo es un valor propio: NO se deriva del precio del envase.
 *  - Los valores dudosos quedan marcados como "pendiente de confirmación".
 *  - Es idempotente: volver a ejecutarlo deja los mismos valores.
 *  - Los productos de la lista que no se pueden mapear con seguridad NO se
 *    inventan: se reportan para que se definan manualmente.
 */
class ConfigurarCostosConsumo extends Command
{
    protected $signature = 'inventario:configurar-costos {--dry-run : Solo mostrar lo que se haría, sin guardar}';

    protected $description = 'Configura el precio de envase y el costo por gramo según la lista del salón';

    /**
     * nombre => [ids[], costo_envase, costo_unitario, pendiente, nota]
     */
    private const MAPA = [
        // Coloración y decoloración
        'Tinte (Techno Fruit Color)' => [[1, 99, 100, 101, 102, 103, 104, 105, 106, 107, 108, 109, 110, 111, 112, 113, 114, 115, 116, 117, 118, 119], '44000', '450', false, null],
        'Coactivador' => [[3], '47000', '50', false, null],
        'Decolorante 9 tonos (Ultra 9)' => [[10], '190000', '390', false, null],
        'Decolorante Cream (Pure Light)' => [[11], '211000', '430', false, null],
        'Decolorante Gentle' => [[13], '252000', '500', false, null],
        'Decolorante White' => [[14], '245000', '250', false, null],

        // Champús
        'Shampoo Energizing' => [[15], '164000', '164', false, null],
        'Shampoo Coco' => [[24], '145000', '145', false, null],
        'Shampoo Leche' => [[28], '145000', '145', false, null],
        'Shampoo Lino' => [[34], '122000', '122', false, null],
        'Shampoo Balancing' => [[41], '186000', '186', false, null],
        'Shampoo Calming' => [[42], '186000', '186', false, null],
        'Shampoo Densifying' => [[43], '186000', '186', false, null],
        'Shampoo Hydraday' => [[47], '161000', '161', false, null],
        'Shampoo Silk Oil' => [[53], '186000', '182', true, 'Valor anotado $182/g; el salón pidió confirmarlo'],
        'Shampoo Filler' => [[60], '186000', '186', false, null],
        'Shampoo Color Care' => [[65], '161000', '167', true, 'Valor anotado $167/g (envase $161.000); confirmar'],
        'Shampoo Silver' => [[71], '161000', '167', true, 'Valor anotado $167/g (envase $161.000); confirmar'],

        // Tratamientos
        'Tratamiento Rebalancing' => [[16], '237000', '237', false, null],
        'Tratamiento Coco' => [[25], '237000', '237', false, null],
        'Tratamiento Leche' => [[29], '237000', '237', false, null],
        'Tratamiento Hydraday' => [[50], '186000', '186', false, null],
        'Tratamiento Silk Oil' => [[56], '207000', '207', false, null],
        'Tratamiento Filler' => [[62], '207000', '207', false, null],
        'Tratamiento Color Care' => [[69], '186000', '186', false, null],
        'Tratamiento Silver' => [[74], '207000', '207', false, null],

        // Adicionales
        'Shampoo Arganway' => [[81], '116000', '232', false, null],
        'Shampoo Proteins Huevo' => [[83], '190000', '200', false, null],
        'Tratamiento Arganway' => [[87], '129000', '258', false, null],
        'Tratamiento Huevo' => [[89], '154000', '550', true, 'Valor anotado $550/g; confirmar'],
    ];

    /**
     * Items de la lista que no se mapean con seguridad (no se inventan).
     */
    private const NO_MAPEADOS = [
        'Shampoo Ovo ($145.000 / $145)' => 'No hay un shampoo "Ovo" claro en el catálogo (Egg Shampoo 1000ml figura a $190.000). Definir manualmente.',
        'Shampoo Energizing Scalpego ($186.000 / $186)' => 'No existe un shampoo Scalpego en el catálogo. Definir o crear manualmente.',
        'Tratamiento Ovo ($237.000 / $237)' => 'El catálogo tiene Egg Mask 350ml ($154.000), no de 1000ml. Definir manualmente.',
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $this->info($dry ? 'SIMULACIÓN (no se guarda nada)' : 'Configurando costos por gramo…');
        $this->newLine();

        $filas = [];

        foreach (self::MAPA as $nombre => [$ids, $costoEnvase, $costoUnitario, $pendiente, $nota]) {
            $aplicados = 0;

            foreach ($ids as $id) {
                $producto = Producto::find($id);
                if (! $producto) {
                    continue;
                }

                if (! $dry) {
                    $producto->costo_envase = Money::normal($costoEnvase);
                    $producto->precio_costo = Money::normal($costoEnvase);
                    $producto->costo_unitario = Money::normal($costoUnitario, Money::ESCALA_COSTO_UNITARIO);
                    if ($pendiente) {
                        $producto->pendiente_confirmacion = true;
                    }
                    $producto->save();
                }

                $aplicados++;
            }

            $filas[] = [
                $nombre,
                $aplicados,
                Money::formato($costoEnvase),
                Money::formato($costoUnitario),
                $pendiente ? 'Sí' : '',
            ];
        }

        $this->table(['Producto', 'Filas', 'Envase', 'Costo/gramo', 'Confirmar'], $filas);

        if (self::NO_MAPEADOS) {
            $this->newLine();
            $this->warn('Productos de la lista sin mapeo seguro (revisar a mano):');
            foreach (self::NO_MAPEADOS as $nombre => $detalle) {
                $this->line(" • {$nombre}: {$detalle}");
            }
        }

        $this->newLine();
        if ($dry) {
            $this->info('Simulación terminada. Ejecuta sin --dry-run para guardar.');
        } else {
            $this->info('Listo. Los valores dudosos quedaron marcados como "pendiente de confirmación".');
        }

        return self::SUCCESS;
    }
}
