<?php

namespace App\Console\Commands;

use App\Models\CategoriaProducto;
use App\Models\InventarioLote;
use App\Models\Producto;
use App\Services\InventarioService;
use App\Support\Money;
use Illuminate\Console\Command;

/**
 * Sincroniza el catálogo de productos y carga el inventario inicial a partir
 * de database/data/inventario_inicial.php.
 *
 * Es idempotente: se puede ejecutar varias veces sin duplicar productos ni
 * repetir la carga de existencias (cada lote lleva una marca de origen).
 *
 *   php artisan inventario:importar --dry-run
 *   php artisan inventario:importar
 *   php artisan inventario:importar --pendientes
 */
class ImportarInventarioCommand extends Command
{
    protected $signature = 'inventario:importar
        {--dry-run : Muestra los cambios sin escribir en la base de datos}
        {--sin-stock : Solo sincroniza el catálogo, no carga existencias}
        {--pendientes : Lista los productos marcados para revisión}';

    protected $description = 'Sincroniza el catálogo de productos y carga el inventario inicial de forma idempotente';

    private const MARCA_ORIGEN = '[import:catalogo-inicial]';

    public function handle(InventarioService $inventario): int
    {
        $items = require database_path('data/inventario_inicial.php');

        if ($this->option('pendientes')) {
            return $this->mostrarPendientes();
        }

        $dryRun = (bool) $this->option('dry-run');
        $cargarStock = ! $this->option('sin-stock');

        $creados = 0;
        $actualizados = 0;
        $sinCambio = 0;
        $lotesNuevos = 0;
        $pendientesMedicion = 0;
        $cambios = [];

        $categorias = $this->cacheCategorias();

        foreach ($items as $item) {
            $producto = $this->buscarProducto($item);
            $nuevo = ! $producto->exists;

            if ($nuevo) {
                $producto = new Producto();
            }

            $categoria = $this->resolverCategoria($item, $categorias, $producto, $dryRun);

            if (! $categoria) {
                $this->warn('  ! Categoría desconocida: ' . ($item['categoria'] ?? 'sin categoría')
                    . ' (para ' . ($item['nombre'] ?? $item['alias'] ?? 'producto') . ')');
                continue;
            }

            $datos = $this->datosProducto($item, $categoria, $producto);
            $cambiosProducto = [];

            foreach ($datos as $campo => $valor) {
                $anterior = $producto->getOriginal($campo);

                if ($producto->exists && ! $this->cambia($campo, $anterior, $valor)) {
                    continue;
                }

                // Rellenar una columna nueva no es un "cambio" a revisar:
                // solo se reporta cuando ya había un valor distinto.
                if ($producto->exists && $anterior === null && in_array($campo, ['presentacion', 'contenido_envase'], true)) {
                    $producto->{$campo} = $valor;
                    continue;
                }

                if ($producto->exists) {
                    $cambiosProducto[] = $this->describirCambio($campo, $anterior, $valor);
                }

                $producto->{$campo} = $valor;
            }

            $descripcion = $this->componerDescripcion($producto->descripcion, $item, $cambiosProducto);
            if ($descripcion !== $producto->descripcion) {
                $producto->descripcion = $descripcion;
            }

            if (! $dryRun) {
                $producto->recalcularCostoUnitario();
                $producto->save();
            }

            if ($nuevo) {
                $creados++;
                $this->line("  + NUEVO   {$producto->nombre} ({$categoria->nombre})");
            } elseif ($cambiosProducto !== []) {
                $actualizados++;
                $this->line("  ~ ACTUALIZADO  {$producto->nombre}");
                foreach ($cambiosProducto as $cambio) {
                    $this->line("      · {$cambio}");
                }
                $cambios[] = [$producto->nombre, $cambiosProducto];
            } else {
                $sinCambio++;
            }

            if (! $cargarStock) {
                continue;
            }

            $nuevos = (int) ($item['stock_envases'] ?? 0);
            $empezados = (int) ($item['envases_empezados'] ?? 0);

            if (! $dryRun) {
                $lotesNuevos += $this->cargarStock($inventario, $producto, $nuevos, $empezados, $item);
            } else {
                $lotesNuevos += $this->simularStock($producto, $nuevos, $empezados);
            }

            $pendientesMedicion += $empezados + $this->envasesSinContenido($producto, $nuevos);
        }

        $this->newLine();
        $this->info('Resumen de la importación:');
        $this->table(
            ['Concepto', 'Cantidad'],
            [
                ['Productos nuevos', $creados],
                ['Productos actualizados', $actualizados],
                ['Productos sin cambios', $sinCambio],
                ['Lotes de existencias creados', $lotesNuevos],
                ['Envases pendientes de medición', $pendientesMedicion],
            ]
        );

        if ($cambios !== []) {
            $this->newLine();
            $this->warn('Precios y datos ajustados (revisar y confirmar):');
            foreach ($cambios as [$nombre, $detalle]) {
                $this->line("  · {$nombre}: " . implode('; ', $detalle));
            }
        }

        if ($dryRun) {
            $this->comment('Modo simulación: no se escribió nada en la base de datos.');
        }

        return self::SUCCESS;
    }

    /**
     * Compara el valor actual con el nuevo según el tipo de campo.
     */
    private function cambia(string $campo, $anterior, $nuevo): bool
    {
        if ($anterior === null) {
            return true;
        }

        if (in_array($campo, ['precio_venta', 'precio_costo', 'precio_sugerido', 'costo_envase'], true)) {
            return ! Money::igual((string) $anterior, (string) $nuevo, Money::ESCALA_DINERO);
        }

        if ($campo === 'costo_unitario') {
            return ! Money::igual((string) $anterior, (string) $nuevo, Money::ESCALA_COSTO_UNITARIO);
        }

        if (in_array($campo, ['contenido_envase', 'stock_minimo'], true)) {
            return ! Money::igual((string) $anterior, (string) $nuevo, Money::ESCALA_CANTIDAD);
        }

        return (string) $anterior !== (string) $nuevo;
    }

    private function describirCambio(string $campo, $anterior, $nuevo): string
    {
        $etiquetas = [
            'precio_venta' => 'precio de venta',
            'precio_costo' => 'precio de costo',
            'precio_sugerido' => 'precio sugerido',
            'costo_unitario' => 'costo por unidad',
            'costo_envase' => 'costo del envase',
            'contenido_envase' => 'contenido por envase',
            'presentacion' => 'presentación',
            'unidad_control' => 'unidad de control',
            'categoria_producto_id' => 'categoría',
            'pendiente_confirmacion' => 'marcado para revisión',
        ];

        $etiqueta = $etiquetas[$campo] ?? $campo;

        $formato = in_array($campo, ['precio_venta', 'precio_costo', 'precio_sugerido', 'costo_envase'], true)
            ? fn ($v) => Money::formato($v)
            : (fn ($v) => $v === null ? 'sin valor' : (string) $v);

        if ($campo === 'pendiente_confirmacion') {
            return 'marcado para revisión';
        }

        return "{$etiqueta}: {$formato($anterior)} → {$formato($nuevo)}";
    }

    /**
     * Carga las existencias iniciales. No repite si el lote ya existe.
     *
     * La marca de origen se revisa POR TIPO DE LOTE (nuevo medido, nuevo sin
     * contenido conocido y empezado): así, si una corrida anterior falló a
     * mitad de camino, el lote faltante se crea en la siguiente.
     */
    private function cargarStock(InventarioService $inventario, Producto $producto, int $envases, int $empezados, array $item): int
    {
        $creados = 0;

        if ($envases > 0) {
            $contenido = (string) ($producto->contenido_envase ?? 0);

            if (Money::mayorQue($contenido, '0', Money::ESCALA_CANTIDAD)) {
                if ($this->loteYaCargado($producto->id, 'nuevo', true)) {
                    $this->line("      = existencias ya cargadas para {$producto->nombre}");
                } else {
                    $cantidad = Money::mul((string) $envases, $contenido, Money::ESCALA_CANTIDAD);
                    $costoUnitario = Money::normal((string) ($producto->costo_unitario ?? 0), Money::ESCALA_COSTO_UNITARIO);

                    $inventario->registrarEntrada($producto, $cantidad, Money::mul($costoUnitario, $cantidad), [
                        'tipo' => InventarioService::TIPO_ENTRADA,
                        'costo_unitario' => $costoUnitario,
                        'envases' => $envases,
                        'estado_envase' => 'nuevo',
                        'medido' => true,
                        'motivo' => 'Carga de inventario inicial',
                        'notas' => trim(self::MARCA_ORIGEN . ' ' . ($item['notas'] ?? 'Envases nuevos completos.')),
                        'modulo' => 'inventario',
                    ]);

                    $creados++;
                    $this->line("      + {$envases} envase(s) nuevo(s) = {$cantidad} {$producto->unidadAbrev()} de {$producto->nombre}");
                }
            } elseif ($this->loteYaCargado($producto->id, 'nuevo', false)) {
                $this->line("      = envases por medir ya cargados para {$producto->nombre}");
            } else {
                // No se conoce el tamaño del envase: se registra igual, pero
                // pendiente de medición. Perder las existencias sería peor.
                $inventario->registrarEntrada($producto, '0', '0', [
                    'tipo' => InventarioService::TIPO_ENTRADA,
                    'costo_unitario' => Money::normal((string) ($producto->costo_unitario ?? 0), Money::ESCALA_COSTO_UNITARIO),
                    'envases' => $envases,
                    'estado_envase' => 'nuevo',
                    'medido' => false,
                    'motivo' => 'Carga de inventario inicial (envases por medir)',
                    'notas' => trim(self::MARCA_ORIGEN . ' ' . ($item['notas'] ?? 'Envases nuevos sin contenido definido: medir para actualizar el stock.')),
                    'modulo' => 'inventario',
                ]);

                $creados++;
                $this->line("      ? {$envases} envase(s) nuevo(s) por medir (sin contenido definido) de {$producto->nombre}");
            }
        }

        if ($empezados > 0) {
            if ($this->loteYaCargado($producto->id, 'empezado', false)) {
                $this->line("      = envases empezados ya cargados para {$producto->nombre}");

                return $creados;
            }

            // Envases empezados: se REGISTRAN pero no suman existencias porque
            // no se puede suponer cuánto queda dentro.
            $inventario->registrarEntrada($producto, '0', '0', [
                'tipo' => InventarioService::TIPO_ENTRADA,
                'costo_unitario' => Money::normal((string) ($producto->costo_unitario ?? 0), Money::ESCALA_COSTO_UNITARIO),
                'envases' => $empezados,
                'estado_envase' => 'empezado',
                'medido' => false,
                'motivo' => 'Carga de inventario inicial (envases por medir)',
                'notas' => trim(self::MARCA_ORIGEN . ' ' . ($item['notas'] ?? 'Envase empezado sin medir.')),
                'modulo' => 'inventario',
            ]);

            $creados++;
            $this->line("      ? {$empezados} envase(s) por medir (no suman stock) de {$producto->nombre}");
        }

        return $creados;
    }

    /**
     * Envases cuyo contenido se desconoce: quedan pendientes de medición
     * aunque sean cerrados (no se puede calcular cuánto traen).
     */
    private function envasesSinContenido(Producto $producto, int $envases): int
    {
        if ($envases <= 0) {
            return 0;
        }

        $contenido = (string) ($producto->contenido_envase ?? 0);

        return Money::mayorQue($contenido, '0', Money::ESCALA_CANTIDAD) ? 0 : $envases;
    }

    /**
     * ¿Ya existe un lote de esta carga con ese estado de envase?
     */
    private function loteYaCargado(int $productoId, string $estadoEnvase, bool $medido): bool
    {
        return InventarioLote::where('producto_id', $productoId)
            ->where('notas', 'like', '%' . self::MARCA_ORIGEN . '%')
            ->where('estado_envase', $estadoEnvase)
            ->where('medido', $medido)
            ->exists();
    }

    private function simularStock(Producto $producto, int $envases, int $empezados): int
    {
        if ($envases === 0 && $empezados === 0) {
            return 0;
        }

        $yaCargado = InventarioLote::where('producto_id', $producto->id)
            ->where('notas', 'like', '%' . self::MARCA_ORIGEN . '%')
            ->exists();

        if ($yaCargado) {
            $this->line("      = existencias ya cargadas para {$producto->nombre}");

            return 0;
        }

        $this->line("      ~ {$envases} envase(s) nuevo(s) y {$empezados} por medir de {$producto->nombre}");

        return ($envases > 0 ? 1 : 0) + ($empezados > 0 ? 1 : 0);
    }

    /**
     * Busca el producto existente por id; si no viene id o el id no existe
     * (base vacía o ficha dada de baja), por nombre o por alias de la lista.
     */
    private function buscarProducto(array $item): Producto
    {
        if (! empty($item['producto_id'])) {
            $existente = Producto::withTrashed()->find($item['producto_id']);

            if ($existente) {
                return $existente;
            }
        }

        $nombre = trim($item['nombre'] ?? $item['alias'] ?? '');
        $candidatos = Producto::withTrashed()
            ->whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower($nombre)])
            ->get();

        if ($candidatos->isEmpty()) {
            return new Producto();
        }

        if ($candidatos->count() === 1) {
            return $candidatos->first();
        }

        if (isset($item['contenido_envase'])) {
            $contenido = (float) $item['contenido_envase'];
            $exacto = $candidatos->firstWhere(
                fn (Producto $p) => (float) $p->contenido_envase === $contenido
                    && $p->unidad_control === ($item['unidad'] ?? $p->unidad_control)
            );

            if ($exacto) {
                return $exacto;
            }
        }

        return $candidatos->first();
    }

    private function datosProducto(array $item, CategoriaProducto $categoria, Producto $producto): array
    {
        $datos = [
            'categoria_producto_id' => $categoria->id,
            'unidad_control' => $item['unidad'] ?? $producto->unidad_control ?? Producto::UNIDAD_UNIDAD,
            'tipo_uso' => $item['tipo_uso'] ?? $producto->tipo_uso ?? Producto::USO_PROFESIONAL,
            'activo' => true,
        ];

        if (! empty($item['nombre'])) {
            $datos['nombre'] = trim($item['nombre']);
        } elseif (! $producto->exists && ! empty($item['alias'])) {
            // Producto que aún no existe (base vacía): se usa el nombre de la
            // lista de precios para poder crearlo.
            $datos['nombre'] = trim($item['alias']);
        }

        foreach (['codigo', 'marca', 'tono', 'linea', 'precio_costo', 'precio_venta', 'precio_sugerido', 'stock_minimo'] as $campo) {
            if (isset($item[$campo])) {
                $datos[$campo] = $item[$campo];
            }
        }

        if (isset($item['presentacion'])) {
            $datos['presentacion'] = $item['presentacion'];
        }

        if (isset($item['contenido_envase'])) {
            $datos['contenido_envase'] = $item['contenido_envase'];
        }

        if (isset($item['costo_envase'])) {
            $datos['costo_envase'] = $item['costo_envase'];
        }

        if (isset($item['costo_unitario'])) {
            $datos['costo_unitario'] = $item['costo_unitario'];
        }

        if (! empty($item['pendiente'])) {
            $datos['pendiente_confirmacion'] = true;
        }

        return $datos;
    }

    /**
     * Compone la descripción sin pisar lo que ya estuviera escrito.
     */
    private function componerDescripcion(?string $actual, array $item, array $cambiosProducto): ?string
    {
        $partes = [];

        if ($actual) {
            $partes[] = trim($actual);
        }

        $texto = (string) $actual;

        if (! empty($item['alias']) && ! str_contains(mb_strtolower($texto), mb_strtolower($item['alias']))) {
            $partes[] = 'Nombre en la lista de precios: ' . $item['alias'];
        }

        if (! empty($item['presentacion']) && ! str_contains(mb_strtolower($texto), 'presentación:')) {
            $partes[] = 'Presentación: ' . $item['presentacion'];
        }

        if (! empty($item['notas']) && ! str_contains(mb_strtolower($texto), mb_strtolower($item['notas']))) {
            $partes[] = trim($item['notas']);
        }

        if ($cambiosProducto !== []) {
            $resumen = 'Ajustado desde la lista de precios: ' . implode('; ', array_filter(
                $cambiosProducto,
                fn ($c) => ! str_contains($c, 'queda marcado para revisión')
            ));

            if ($resumen !== 'Ajustado desde la lista de precios: ' && ! str_contains(mb_strtolower($texto), mb_strtolower($resumen))) {
                $partes[] = $resumen;
            }
        }

        $resultado = trim(implode("\n", array_filter($partes)));

        return $resultado !== '' ? $resultado : null;
    }

    /**
     * @return array<string, CategoriaProducto>
     */
    private function cacheCategorias(): array
    {
        return CategoriaProducto::orderBy('id')->get()->keyBy('nombre')->all();
    }

    private function resolverCategoria(array $item, array &$categorias, Producto $producto, bool $dryRun): ?CategoriaProducto
    {
        $nombre = trim($item['categoria'] ?? '');

        // Producto ya existente: se conserva su categoría.
        if ($nombre === '' && $producto->exists && $producto->categoria_producto_id) {
            return $producto->categoria()->first() ?? CategoriaProducto::find($producto->categoria_producto_id);
        }

        if ($nombre === '') {
            return null;
        }

        if (isset($categorias[$nombre])) {
            return $categorias[$nombre];
        }

        if ($dryRun) {
            $this->warn("  ! Categoría a crear: {$nombre}");

            return null;
        }

        $grupo = $item['grupo'] ?? $this->inferirGrupo($nombre);

        $categoria = CategoriaProducto::create([
            'nombre' => $nombre,
            'grupo' => $grupo,
            'descripcion' => 'Categoría creada durante la importación del catálogo.',
        ]);

        $categorias[$nombre] = $categoria;

        return $categoria;
    }

    private function inferirGrupo(string $nombre): string
    {
        $nombre = mb_strtolower($nombre);

        return match (true) {
            str_contains($nombre, 'tint') => CategoriaProducto::GRUPO_TINTES,
            str_contains($nombre, 'activador'), str_contains($nombre, 'oxidante') => CategoriaProducto::GRUPO_ACTIVADORES,
            str_contains($nombre, 'decolor'), str_contains($nombre, 'aclarant'), str_contains($nombre, 'deco') => CategoriaProducto::GRUPO_DECOLORANTES,
            str_contains($nombre, 'shampoo') => CategoriaProducto::GRUPO_CHAMPUOS,
            str_contains($nombre, 'ampolla'), str_contains($nombre, 'monodos') => CategoriaProducto::GRUPO_AMPOLLAS,
            str_contains($nombre, 'tratamiento'), str_contains($nombre, 'mascarilla'), str_contains($nombre, 'acondicionador') => CategoriaProducto::GRUPO_TRATAMIENTOS,
            default => CategoriaProducto::GRUPO_OTROS,
        };
    }

    private function mostrarPendientes(): int
    {
        $pendientes = Producto::where('pendiente_confirmacion', true)
            ->with('categoria')
            ->orderBy('nombre')
            ->get();

        if ($pendientes->isEmpty()) {
            $this->info('No hay productos marcados para revisión.');

            return self::SUCCESS;
        }

        $this->info("Productos marcados para revisión ({$pendientes->count()}):");

        $this->table(
            ['Producto', 'Categoría', 'Precio venta', 'Costo unit.', 'Observación'],
            $pendientes->map(fn (Producto $p) => [
                $p->nombre,
                $p->categoria?->nombre ?? '-',
                Money::formato($p->precio_venta),
                Money::formatoDecimal($p->costo_unitario, 6),
                mb_substr(str_replace("\n", ' ', (string) $p->descripcion), 0, 90),
            ])->all()
        );

        return self::SUCCESS;
    }
}
