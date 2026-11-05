<?php

namespace App\Console\Commands;

use App\Models\Producto;
use App\Support\Money;
use Illuminate\Console\Command;

/**
 * Propaga el costo por gramo/ml que dio el salón a todos los tamaños de la
 * MISMA línea y tipo (champú con champú, mascarilla/acondicionador del
 * tratamiento con su tratamiento).
 *
 * Solo escribe `costo_unitario` (el valor que se usa para costear un consumo);
 * no toca el precio de compra del envase ni el precio de venta.
 *
 * Es idempotente. Los productos que el salón no cotizó (lociones, aceites,
 * toners, pre-shampoos, ceras, lacas…) se dejan intactos y se reportan.
 */
class PropagarCostosUnidad extends Command
{
    protected $signature = 'inventario:propagar-costos-unidad {--dry-run : Solo mostrar lo que se haría}';

    protected $description = 'Aplica el costo por gramo/ml a todos los tamaños de cada línea';

    /** id de producto => costo por gramo/ml */
    private const COSTOS = [
        // Champús
        27 => '145',  // Coconut Shampoo 500ml
        31 => '145',  // Milk Shampoo 500ml
        19 => '164',  // Energizing Shampoo 500ml
        37 => '164',  // Energizing Shampoo 950ml
        39 => '164',  // Energizing Shampoo 300ml
        85 => '200',  // Egg Shampoo 250ml
        44 => '186',  // Balancing Shampoo 300ml
        121 => '186', // Calming Shampoo 300ml
        45 => '186',  // Densifying Shampoo 300ml
        49 => '161',  // Hydraday Shampoo 300ml
        54 => '182',  // Silk Oil Shampoo 300ml
        61 => '186',  // Filler Shampoo 300ml
        67 => '167',  // Color Care Shampoo 300ml
        72 => '167',  // Silver Shampoo 300ml

        // Tratamientos (mascarillas y acondicionadores de la misma línea)
        26 => '237',  // Coconut Mask 500ml
        30 => '237',  // Milk Mask 500ml
        20 => '237',  // Rebalancing Cream 250ml
        48 => '186',  // Hydraday Conditioner 200ml
        55 => '207',  // Silk Oil Conditioner 300ml
        63 => '207',  // Filler Mask 300ml
        66 => '186',  // Color Care Conditioner 200ml
        68 => '186',  // Color Care Mask 950ml
        70 => '186',  // Color Care Mask 300ml
        73 => '207',  // Silver Conditioner 300ml

        // Coactivadores (mismo tipo que el Coactivador cotizado)
        91 => '50',   // Cream Coactivator 30 Vol
        92 => '50',   // Cream Coactivator 5 Vol
        93 => '50',   // Cream Coactivator 10 Vol
        94 => '50',   // Cream Coactivator 20 Vol
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $this->info($dry ? 'SIMULACIÓN (no se guarda nada)' : 'Propagando costo por unidad a los tamaños…');
        $this->newLine();

        $filas = [];

        foreach (self::COSTOS as $id => $costo) {
            $producto = Producto::find($id);
            if (! $producto) {
                continue;
            }

            $anterior = $producto->costo_unitario;

            if (! $dry) {
                $producto->costo_unitario = Money::normal($costo, Money::ESCALA_COSTO_UNITARIO);
                $producto->save();
            }

            $filas[] = [
                $producto->nombre,
                $anterior === null ? '—' : Money::formato($anterior),
                Money::formato($costo),
            ];
        }

        $this->table(['Producto', 'Antes', 'Nuevo costo/und'], $filas);

        $sinCosto = Producto::where('activo', true)
            ->where(function ($q) {
                $q->whereNull('costo_unitario')->orWhere('costo_unitario', '<=', 0);
            })
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'linea']);

        $this->newLine();
        $this->warn("Productos activos que siguen SIN costo por unidad: {$sinCosto->count()}");
        foreach ($sinCosto as $p) {
            $this->line(" • [{$p->id}] {$p->nombre}".($p->linea ? " ({$p->linea})" : ''));
        }

        $this->newLine();
        $this->info($dry ? 'Simulación terminada.' : 'Listo. Revisa la lista de arriba para completar los que faltan.');

        return self::SUCCESS;
    }
}
