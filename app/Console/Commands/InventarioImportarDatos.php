<?php

namespace App\Console\Commands;

use App\Models\CategoriaProducto;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Support\Money;
use Illuminate\Console\Command;

/**
 * Carga única de datos base del inventario a partir de:
 *  - Catálogo "LP SUGERIDO 2026 ALTER EGO" (líneas y precios sugeridos).
 *  - Factura de compra ALTER EGO SAS del 28/08/2026 (códigos y costos
 *    unitarios legibles).
 *
 * Reglas respetadas: los costos de compra provienen SOLO de la factura, los
 * precios sugeridos SOLO del catálogo, y los datos no confirmados se marcan
 * como pendientes sin inventar cantidades, precios ni presentaciones.
 */
class InventarioImportarDatos extends Command
{
    protected $signature = 'inventario:importar-datos';

    protected $description = 'Completa línea, precio sugerido, códigos y costos de compra (catálogo ALTER EGO + factura)';

    /** Fecha de la factura legible: 28/08/2026. */
    private const FECHA_FACTURA = '2026-08-28';

    public function handle(): int
    {
        $this->info('== Proveedor ==');
        $proveedorId = $this->asegurarProveedor();

        $this->info('== Categorías ==');
        $this->renombrarYCrearCategorias();

        $this->info('== Líneas y precio sugerido del catálogo ==');
        $actualizados = $this->aplicarCatalogo();

        $this->info('== Factura (códigos y costos legibles) ==');
        [$coincididos, $creados] = $this->aplicarFactura($proveedorId);

        $this->info('== Faltantes del catálogo de tratamientos ==');
        $this->asegurarFaltantesCatalogo();

        $this->newLine();
        $this->info("Resumen: {$actualizados} productos catalogados · {$coincididos} items de factura emparejados con productos existentes · {$creados} productos nuevos creados.");
        $this->line('Revisar con: SELECT id, codigo, nombre, linea, tono, costo_envase, precio_sugerido, pendiente_confirmacion FROM productos ORDER BY id;');

        return self::SUCCESS;
    }

    private function asegurarProveedor(): int
    {
        $prove = Proveedor::where('nombre', 'ALTER EGO SAS')->first();
        if (! $prove) {
            $prove = Proveedor::create([
                'nombre' => 'ALTER EGO SAS',
                'contacto' => 'Juan Carlos Valderrama Arciniega',
                'observaciones' => 'Proveedor registrado desde factura del 28/08/2026.',
            ]);
            $this->line("  + Proveedor creado: {$prove->nombre}");
        }

        return $prove->id;
    }

    private function renombrarYCrearCategorias(): void
    {
        $renombres = [
            'Tintes' => 'Tintes y coloración',
            'Decolorantes' => 'Decolorantes y aclarantes',
            'Shampoo' => 'Shampoos',
        ];

        foreach (CategoriaProducto::withTrashed()->get() as $cat) {
            $nuevo = $renombres[$cat->nombre] ?? null;
            if ($nuevo !== null && $cat->nombre !== $nuevo) {
                $cat->update(['nombre' => $nuevo]);
                $this->line("  ~ Categoría renombrada: {$nuevo}");
            }
        }

        foreach (['Productos de protección', 'Accesorios y otros productos'] as $nombre) {
            if (! CategoriaProducto::where('nombre', $nombre)->exists()) {
                CategoriaProducto::create(['nombre' => $nombre, 'descripcion' => 'Categoría creada para el inventario.']);
                $this->line("  + Categoría creada: {$nombre}");
            }
        }
    }

    private function aplicarCatalogo(): int
    {
        $contador = 0;

        Producto::withTrashed()->each(function (Producto $p) use (&$contador) {
            $cambios = [];

            $linea = $this->lineaDelNombre($p->nombre);
            if ($linea && $p->linea !== $linea) {
                $cambios['linea'] = $linea;
            }

            if (! $p->precio_sugerido && $p->precio_venta && Money::mayorQue((string) $p->precio_venta, '0')) {
                $cambios['precio_sugerido'] = $p->precio_venta;
            }

            if ($cambios) {
                $p->update($cambios);
                $contador++;
            }
        });

        return $contador;
    }

    /**
     * Aplica el catálogo de precios sugeridos y la factura (códigos + costos).
     *
     * @return array{0: int, 1: int}
     */
    private function aplicarFactura(int $proveedorId): array
    {
        $coincididos = 0;
        $creados = 0;

        foreach (self::FACTURA as $item) {
            if (isset($item['producto_id'])) {
                $producto = Producto::find($item['producto_id']);
                if (! $producto) {
                    $this->warn("  ! No se encontró el producto id {$item['producto_id']} (código {$item['codigo']})");
                    continue;
                }

                $cambios = [
                    'codigo' => $producto->codigo ?: $item['codigo'],
                    'proveedor_id' => $proveedorId,
                    'fecha_compra' => $producto->fecha_compra ?: self::FECHA_FACTURA,
                ];

                if (array_key_exists('costo', $item) && $item['costo'] !== null
                    && ! $producto->costo_envase) {
                    $cambios['costo_envase'] = $item['costo'];
                    $cambios['precio_costo'] = $item['costo'];
                }

                if (array_key_exists('linea', $item)) {
                    $cambios['linea'] = $item['linea'];
                }

                if (array_key_exists('categoria', $item)) {
                    $cambios['categoria_producto_id'] = CategoriaProducto::where('nombre', $item['categoria'])->value('id');
                }

                if (! empty($item['pendiente'])) {
                    $cambios['pendiente_confirmacion'] = true;
                }

                $producto->fill($cambios);
                $producto->recalcularCostoUnitario();
                $producto->save();

                $this->line("  ~ [{$item['codigo']}] {$producto->nombre}".($item['pendiente'] ?? false ? '  ·PENDIENTE' : ''));
                $coincididos++;
                continue;
            }

            $nombre = $item['nombre'];

            $producto = Producto::firstOrNew(['nombre' => $nombre]);
            $esNuevo = ! $producto->exists;

            $producto->fill([
                'codigo' => $producto->codigo ?: $item['codigo'],
                'marca' => $producto->marca ?: 'ALTER EGO',
                'linea' => $item['linea'] ?? ($producto->linea ?: $this->lineaDelNombre($nombre)),
                'tono' => $item['tono'] ?? $producto->tono,
                'categoria_producto_id' => CategoriaProducto::where('nombre', $item['categoria'])->value('id'),
                'proveedor_id' => $producto->proveedor_id ?: $proveedorId,
                'unidad_control' => $item['unidad'] ?? ($producto->unidad_control ?: 'mililitro'),
                'contenido_envase' => $item['contenido'] ?? $producto->contenido_envase,
                'costo_envase' => $producto->costo_envase ?: ($item['costo'] ?? null),
                'precio_costo' => $producto->precio_costo ?: ($item['costo'] ?? null),
                'fecha_compra' => $producto->fecha_compra ?: self::FECHA_FACTURA,
                'pendiente_confirmacion' => ! empty($item['pendiente']),
                'tipo_uso' => $producto->tipo_uso ?: 'ambos',
                'descripcion' => $item['nota'] ?? $producto->descripcion,
                'activo' => true,
            ]);
            $producto->recalcularCostoUnitario();
            $producto->save();

            $this->line(($esNuevo ? '  + Nuevo: ' : '  ~ Actualizado: ').$nombre.(! empty($item['pendiente']) ? '  ·PENDIENTE' : ''));
            $creados += $esNuevo ? 1 : 0;
        }

        // Limpieza de datos de prueba previos sobre el tinte genérico.
        Producto::find(1)?->update(['costo_unitario' => null, 'costo_envase' => null, 'precio_costo' => null]);

        return [$coincididos, $creados];
    }

    /**
     * Completa el catálogo de tratamientos: separa el pre-shampoo genérico en
     * su línea correcta y crea los productos documentados que faltaban.
     */
    private function asegurarFaltantesCatalogo(): void
    {
        $pre = Producto::find(46);
        if ($pre && $pre->nombre !== 'Balancing Pre-Shampoo 150ml') {
            $pre->update([
                'nombre' => 'Balancing Pre-Shampoo 150ml',
                'linea' => 'Balancing',
                'pendiente_confirmacion' => true,
            ]);
            $this->line('  ~ id 46 renombrado a Balancing Pre-Shampoo 150ml (presentación/costo por confirmar).');
        }

        $nuevos = [
            ['nombre' => 'Calming Shampoo 300ml', 'linea' => 'Calming', 'categoria' => 'Shampoos', 'contenido' => '300', 'precio' => '89500'],
            ['nombre' => 'Calming Pre-Shampoo 150ml', 'linea' => 'Calming', 'categoria' => 'Tratamientos', 'contenido' => '150', 'precio' => '148000'],
        ];

        foreach ($nuevos as $item) {
            $producto = Producto::firstOrNew(['nombre' => $item['nombre']]);
            if ($producto->exists) {
                continue;
            }

            $producto->fill([
                'marca' => 'ALTER EGO',
                'linea' => $item['linea'],
                'categoria_producto_id' => CategoriaProducto::where('nombre', $item['categoria'])->value('id'),
                'unidad_control' => 'mililitro',
                'contenido_envase' => $item['contenido'],
                'precio_sugerido' => $item['precio'],
                'precio_venta' => $item['precio'],
                'stock_minimo' => 2,
                'tipo_uso' => 'ambos',
                'activo' => true,
                'pendiente_confirmacion' => false,
                'descripcion' => 'Producto del catálogo ALTER EGO (LP SUGERIDO 2026); presentación '.$item['contenido'].' ml.',
            ]);
            $producto->save();
            $this->line('  + Nuevo (catálogo): '.$item['nombre']);
        }
    }

    private function lineaDelNombre(string $nombre): ?string
    {
        $u = mb_strtoupper($nombre);

        $grupos = [
            'ENERGIZING' => 'Energizing',
            'ENERGY LOTION' => 'Energizing',
            'DENSIFYING' => 'Densifying',
            'CALMING' => 'Calming',
            'REBALANCING' => 'Rebalancing',
            'BALANCING' => 'Balancing',
            'HYDRADAY' => 'Hydraday',
            'WHIPPED CREAM' => 'Hydraday',
            'SILK' => 'Silk Oil',
            'FILLER' => 'Filler',
            'COLOR CARE' => 'Color Care',
            'SILVER' => 'Silver Maintain',
            'GARLIC' => 'Garlic Protection',
            'COCONUT' => 'Coconut Restore',
            'LEAVE CONDITIONER' => 'Coconut Restore',
            'MILK' => 'Milk & Collagen',
            'LINSEED' => 'Linseed Light',
            'LACA' => 'Stylege',
            'VOLUME CLOUD' => 'Stylege',
            'CLASSIC POMADE' => 'Stylege',
            'ELASTIC SHAPE' => 'Stylege',
            'HI-T' => 'Stylege',
            'TECHNO FRUIT' => 'Hair Color Techno',
            'TECHNOFRUIT' => 'Hair Color Techno',
            'ARGANWAY' => 'Arganway & Egg',
            'EGG' => 'Arganway & Egg',
            'INTENSIVE MASK' => 'Arganway & Egg',
            'INSTANT REPAIR' => 'Arganway & Egg',
            'PROTECTIVE SHINE' => 'Arganway & Egg',
            'BLEACHING' => 'Hair Deco Blond',
            'LIGHTENER' => 'Hair Deco Blond',
            'PURE LIGHT' => 'Hair Deco Blond',
            'GENTLE PROTECTIVE' => 'Hair Deco Blond',
            'COACTIVADOR' => 'Hair Deco Blond',
            'DEVELOPER' => 'Hair Deco Blond',
            'TRANSFORMER' => 'Hair Deco Blond',
            'TONER' => 'Hair Deco Blond',
            'BOOSTER' => 'Hair Deco Blond',
            'PREPARE' => 'Hair Deco Blond',
        ];

        foreach ($grupos as $clave => $linea) {
            if (str_contains($u, $clave)) {
                return $linea;
            }
        }

        return null;
    }

    /**
     * Items de la factura. Cada item sin producto_id indica un producto nuevo
     * a crear. Las cantidades/presentaciones marcadas en la fuente como "no
     * confirmadas" se cargan con pendiente_confirmacion = true.
     *
     * @var array<int, array<string, mixed>>
     */
    private const FACTURA = [
        // Productos existentes emparejados con códigos/costos legibles.
        ['producto_id' => 39, 'codigo' => '32746', 'costo' => '50000'],
        ['producto_id' => 38, 'codigo' => '32749', 'costo' => '94000', 'pendiente' => true],
        ['producto_id' => 46, 'codigo' => '32755', 'costo' => '83000', 'linea' => 'Balancing', 'pendiente' => true],
        ['producto_id' => 42, 'codigo' => '32757', 'costo' => '104000'],
        ['producto_id' => 47, 'codigo' => '32760', 'costo' => '90000'],
        ['producto_id' => 53, 'codigo' => '32765', 'costo' => '101000'],
        ['producto_id' => 55, 'codigo' => '32766', 'costo' => '62000', 'pendiente' => true],
        ['producto_id' => 56, 'codigo' => '32767', 'costo' => '116000'],
        ['producto_id' => 58, 'codigo' => '32768', 'costo' => '94000'],
        ['producto_id' => 61, 'codigo' => '32847', 'costo' => '50000'],
        ['producto_id' => 60, 'codigo' => '32848', 'costo' => '104000'],
        ['producto_id' => 63, 'codigo' => '32849', 'costo' => '62000'],
        ['producto_id' => 62, 'codigo' => '32850', 'costo' => '116000'],
        ['producto_id' => 67, 'codigo' => '32776', 'costo' => null, 'pendiente' => true],
        ['producto_id' => 70, 'codigo' => '32778', 'costo' => '62000'],
        ['producto_id' => 10, 'codigo' => '32037', 'costo' => '106000'],
        ['producto_id' => 25, 'codigo' => '46071', 'costo' => '133000'],
        ['producto_id' => 28, 'codigo' => '46314', 'costo' => '81000'],
        ['producto_id' => 29, 'codigo' => '46316', 'costo' => '133000'],
        ['producto_id' => 20, 'codigo' => '46309', 'costo' => '45000'],
        ['producto_id' => 17, 'codigo' => '46061', 'costo' => '81000'],
        ['producto_id' => 18, 'codigo' => '46063', 'costo' => '133000'],
        ['producto_id' => 79, 'codigo' => '46398', 'costo' => '62000', 'linea' => 'Stylege', 'categoria' => 'Productos de protección'],
        ['producto_id' => 7, 'codigo' => '8817'],

        // Productos nuevos: técnicos de color de la factura.
        ['nombre' => 'Cream Coactivator 30 Vol', 'codigo' => '3833', 'costo' => '26000', 'contenido' => '1000', 'categoria' => 'Oxidantes y activadores', 'unidad' => 'mililitro', 'linea' => 'Hair Deco Blond'],
        ['nombre' => 'Cream Coactivator 5 Vol', 'codigo' => '3834', 'contenido' => '1000', 'categoria' => 'Oxidantes y activadores', 'unidad' => 'mililitro', 'linea' => 'Hair Deco Blond', 'pendiente' => true, 'nota' => 'Presentación 1.000 ml visible en factura; costo por confirmar.'],
        ['nombre' => 'Cream Coactivator 10 Vol', 'codigo' => '3831', 'categoria' => 'Oxidantes y activadores', 'unidad' => 'mililitro', 'linea' => 'Hair Deco Blond', 'pendiente' => true, 'nota' => 'Presentación y costo por confirmar en factura.'],
        ['nombre' => 'Cream Coactivator 20 Vol', 'codigo' => '3832', 'categoria' => 'Oxidantes y activadores', 'unidad' => 'mililitro', 'linea' => 'Hair Deco Blond', 'pendiente' => true, 'nota' => 'Presentación y costo por confirmar en factura.'],
        ['nombre' => 'Protein and Minerals Shampoo', 'codigo' => 'MAX722', 'costo' => '106000', 'contenido' => '1000', 'categoria' => 'Shampoos', 'unidad' => 'mililitro', 'linea' => 'Protein and Minerals'],
        ['nombre' => 'Protein and Minerals Mask', 'codigo' => 'MAX723', 'costo' => '86000', 'contenido' => '350', 'categoria' => 'Mascarillas', 'unidad' => 'mililitro', 'linea' => 'Protein and Minerals'],
        ['nombre' => 'Protein and Minerals Ampollas A+B', 'codigo' => 'MAX719', 'costo' => '155000', 'categoria' => 'Lociones Tónicos y Aceites', 'unidad' => 'unidad', 'linea' => 'Protein and Minerals', 'pendiente' => true, 'nota' => 'Presentación por confirmar en factura.'],
        ['nombre' => 'Capas desechables', 'codigo' => null, 'costo' => '12000', 'categoria' => 'Accesorios y otros productos', 'unidad' => 'unidad', 'linea' => 'Accesorios', 'nota' => 'Unidad; costo legible en factura.'],

        // Tintes TechnoFruit (presentación 100 ml; costos/precios por confirmar).
        ['nombre' => 'TechnoFruit 6-0 Rubio Oscuro', 'codigo' => null, 'tono' => '6-0', 'contenido' => '100', 'categoria' => 'Tintes y coloración', 'unidad' => 'mililitro', 'pendiente' => true, 'nota' => 'Tono visible en factura; costo/precio por confirmar.'],
        ['nombre' => 'TechnoFruit 7-0 Rubio', 'codigo' => null, 'tono' => '7-0', 'contenido' => '100', 'categoria' => 'Tintes y coloración', 'unidad' => 'mililitro', 'pendiente' => true],
        ['nombre' => 'TechnoFruit 8-0 Rubio Claro', 'codigo' => null, 'tono' => '8-0', 'contenido' => '100', 'categoria' => 'Tintes y coloración', 'unidad' => 'mililitro', 'pendiente' => true],
        ['nombre' => 'TechnoFruit 9-0 Rubio Clarísimo', 'codigo' => null, 'tono' => '9-0', 'contenido' => '100', 'categoria' => 'Tintes y coloración', 'unidad' => 'mililitro', 'pendiente' => true],
        ['nombre' => 'TechnoFruit 10-0 Rubio Platino', 'codigo' => null, 'tono' => '10-0', 'contenido' => '100', 'categoria' => 'Tintes y coloración', 'unidad' => 'mililitro', 'pendiente' => true],
        ['nombre' => 'TechnoFruit 6-00 Rubio Oscuro Profundo', 'codigo' => null, 'tono' => '6-00', 'contenido' => '100', 'categoria' => 'Tintes y coloración', 'unidad' => 'mililitro', 'pendiente' => true],
        ['nombre' => 'TechnoFruit 7-00 Rubio Profundo', 'codigo' => null, 'tono' => '7-00', 'contenido' => '100', 'categoria' => 'Tintes y coloración', 'unidad' => 'mililitro', 'pendiente' => true],
        ['nombre' => 'TechnoFruit 8-00 Rubio Claro Profundo', 'codigo' => null, 'tono' => '8-00', 'contenido' => '100', 'categoria' => 'Tintes y coloración', 'unidad' => 'mililitro', 'pendiente' => true],
        ['nombre' => 'TechnoFruit 9-00 Rubio Clarísimo Profundo', 'codigo' => null, 'tono' => '9-00', 'contenido' => '100', 'categoria' => 'Tintes y coloración', 'unidad' => 'mililitro', 'pendiente' => true],
        ['nombre' => 'TechnoFruit 8-1 Rubio Claro Ceniza', 'codigo' => null, 'tono' => '8-1', 'contenido' => '100', 'categoria' => 'Tintes y coloración', 'unidad' => 'mililitro', 'pendiente' => true],
        ['nombre' => 'TechnoFruit 7-7 Rubio Oscuro Marrón', 'codigo' => null, 'tono' => '7-7', 'contenido' => '100', 'categoria' => 'Tintes y coloración', 'unidad' => 'mililitro', 'pendiente' => true],
        ['nombre' => 'TechnoFruit 8-7 Rubio Nuez', 'codigo' => null, 'tono' => '8-7', 'contenido' => '100', 'categoria' => 'Tintes y coloración', 'unidad' => 'mililitro', 'pendiente' => true],
        ['nombre' => 'TechnoFruit 8-17 Rubio Claro Avellana', 'codigo' => null, 'tono' => '8-17', 'contenido' => '100', 'categoria' => 'Tintes y coloración', 'unidad' => 'mililitro', 'pendiente' => true, 'nota' => 'Aparece también como "Rubio Claro Ceniza Marrón" en la factura; revisar duplicidad de referencia.'],
        ['nombre' => 'TechnoFruit 7-71 Biondo Marrone Cenere', 'codigo' => null, 'tono' => '7-71', 'contenido' => '100', 'categoria' => 'Tintes y coloración', 'unidad' => 'mililitro', 'pendiente' => true],
        ['nombre' => 'TechnoFruit 11-0 Superaclarante Natural', 'codigo' => null, 'tono' => '11-0', 'contenido' => '100', 'categoria' => 'Tintes y coloración', 'unidad' => 'mililitro', 'pendiente' => true],
        ['nombre' => 'TechnoFruit 8-3 Rubio Dorado', 'codigo' => null, 'tono' => '8-3', 'contenido' => '100', 'categoria' => 'Tintes y coloración', 'unidad' => 'mililitro', 'pendiente' => true],
        ['nombre' => 'TechnoFruit 9-3 Rubio Claro Dorado', 'codigo' => null, 'tono' => '9-3', 'contenido' => '100', 'categoria' => 'Tintes y coloración', 'unidad' => 'mililitro', 'pendiente' => true],
        ['nombre' => 'TechnoFruit 10-3 Rubio Platino Dorado', 'codigo' => null, 'tono' => '10-3', 'contenido' => '100', 'categoria' => 'Tintes y coloración', 'unidad' => 'mililitro', 'pendiente' => true],
        ['nombre' => 'TechnoFruit 7-34 Rubio Dorado Cobre', 'codigo' => null, 'tono' => '7-34', 'contenido' => '100', 'categoria' => 'Tintes y coloración', 'unidad' => 'mililitro', 'pendiente' => true],
        ['nombre' => 'TechnoFruit 8-34 Rubio Claro Dorado Cobre', 'codigo' => null, 'tono' => '8-34', 'contenido' => '100', 'categoria' => 'Tintes y coloración', 'unidad' => 'mililitro', 'pendiente' => true],
        ['nombre' => 'TechnoFruit Castaño Claro', 'codigo' => null, 'tono' => null, 'contenido' => '100', 'categoria' => 'Tintes y coloración', 'unidad' => 'mililitro', 'pendiente' => true, 'nota' => 'Tono/referencia por confirmar en factura.'],
    ];
}