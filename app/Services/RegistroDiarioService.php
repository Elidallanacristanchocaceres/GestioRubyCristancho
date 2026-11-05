<?php

namespace App\Services;

use App\Models\CategoriaProducto;
use App\Models\Consumo;
use App\Models\ConsumoServicio;
use App\Models\Producto;
use App\Support\Money;

/**
 * Reglas del registro diario de productos usados en la peluquería.
 *
 * El registro clasifica los productos en tres categorías y todo se mide
 * en GRAMOS:
 *
 *   Tintes              -> gramos × COSTO_GRAMO_TINTE ($450)
 *   Tratamientos        -> gramos × costo por unidad del producto
 *   Coactivadores agua  -> gramos × costo por unidad del producto
 *
 * Los precios de tratamientos y coactivadores salen SIEMPRE de la ficha
 * del producto (no se reemplazan ni se inventan valores nuevos).
 */
class RegistroDiarioService
{
    /** Valor por gramo de tinte, regla fija del salón. */
    public const COSTO_GRAMO_TINTE = '450';

    /** Unidad en la que se pesa y se registra todo. */
    public const UNIDAD = 'gramo';

    /**
     * Categorías del registro diario, en el orden en que se muestran.
     *
     * @var array<string, array{etiqueta: string, resumen: string}>
     */
    public const GRUPOS = [
        'tintes' => ['etiqueta' => 'Tintes', 'resumen' => 'Coloración aplicada el día'],
        'tratamientos' => ['etiqueta' => 'Tratamientos', 'resumen' => 'Reparación y nutrición'],
        'coactivadores' => ['etiqueta' => 'Coactivadores de agua', 'resumen' => 'Oxidantes / activadores'],
        'decolorantes' => ['etiqueta' => 'Decolorantes', 'resumen' => 'Aclarantes y polvos decolorantes'],
    ];

    /**
     * Identifica a qué grupo del registro diario pertenece un producto,
     * según la categoría configurada en su ficha.
     */
    public static function grupoDe(?Producto $producto): ?string
    {
        $categoria = mb_strtolower(trim($producto?->categoria?->nombre ?? ''));

        if ($categoria === '') {
            return null;
        }

        if (str_contains($categoria, 'tinte') || str_contains($categoria, 'coloraci')) {
            return 'tintes';
        }

        if (str_contains($categoria, 'decolor') || str_contains($categoria, 'aclarante')
            || str_contains($categoria, 'blond') || str_contains($categoria, 'lightener')) {
            return 'decolorantes';
        }

        if (str_contains($categoria, 'oxidante')
            || str_contains($categoria, 'activador')
            || str_contains($categoria, 'coactivador')) {
            return 'coactivadores';
        }

        if (str_contains($categoria, 'tratamiento')) {
            return 'tratamientos';
        }

        return null;
    }

    public static function etiqueta(?string $grupo): string
    {
        return self::GRUPOS[$grupo]['etiqueta'] ?? 'Otros productos';
    }

    public static function esTinte(?Producto $producto): bool
    {
        return self::grupoDe($producto) === 'tintes';
    }

    /**
     * Valor por gramo que se aplica al producto.
     *
     * Los tintes usan siempre la regla fija del salón. Los demás usan el
     * costo ya configurado en la ficha del producto. Devuelve null cuando
     * no existe precio y hay que pedirlo en el formulario.
     */
    public static function costoPorGramo(?Producto $producto): ?string
    {
        if (self::esTinte($producto)) {
            return self::COSTO_GRAMO_TINTE;
        }

        $configurado = $producto?->costo_unitario;

        if ($configurado === null || Money::igual((string) $configurado, '0', Money::ESCALA_COSTO_UNITARIO)) {
            return null;
        }

        return Money::normal((string) $configurado, Money::ESCALA_COSTO_UNITARIO);
    }

    /**
     * Productos activos agrupados por categoría, listos para el formulario.
     *
     * @return array<string, array<int, array{id: int, nombre: string, stock: string, costo: string|null, tiene_costo: bool}>>
     */
    public static function productosPorGrupo(): array
    {
        $porGrupo = ['tintes' => [], 'tratamientos' => [], 'coactivadores' => [], 'decolorantes' => []];

        $productos = Producto::with('categoria')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        foreach ($productos as $producto) {
            $grupo = self::grupoDe($producto);

            if ($grupo === null || ! isset($porGrupo[$grupo])) {
                continue;
            }

            $costo = self::costoPorGramo($producto);

            $porGrupo[$grupo][] = [
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'stock' => (string) $producto->stock,
                'costo' => $costo,
                'tiene_costo' => $costo !== null,
            ];
        }

        return $porGrupo;
    }

    /**
     * Líneas activas de un período, con las relaciones necesarias para
     * armar el resumen por categoría.
     */
    public static function lineasPeriodo(string $desde, string $hasta, array $filtros = []): \Illuminate\Database\Eloquent\Builder
    {
        return ConsumoServicio::query()
            ->where('estado', Consumo::ESTADO_ACTIVO)
            ->whereHas('consumo', function ($q) use ($desde, $hasta) {
                $q->where('estado', Consumo::ESTADO_ACTIVO)->whereBetween('fecha', [$desde, $hasta]);
            })
            ->when($filtros['cliente'] ?? null, fn ($q) => $q->whereHas('consumo', fn ($c) => $c->where('cliente_id', $filtros['cliente'])))
            ->when($filtros['servicio'] ?? null, fn ($q) => $q->whereHas('consumo', fn ($c) => $c->where('servicio_id', $filtros['servicio'])))
            ->when($filtros['estilista'] ?? null, fn ($q) => $q->whereHas('consumo', fn ($c) => $c->where('estilista_id', $filtros['estilista'])))
            ->when($filtros['producto'] ?? null, fn ($q) => $q->where('producto_id', $filtros['producto']))
            ->with(['producto.categoria', 'consumo.cliente']);
    }

    /**
     * Resumen del período organizado por categoría: gramos y costo.
     *
     * @return array{
     *   grupos: array<int, array{clave: string, etiqueta: string, resumen: string, gramos: string, costo: string, productos: array<int, array{nombre: string, gramos: string, costo: string, notas: string|null}>}>,
     *   total_gramos: string,
     *   total_costo: string,
     *   total_lineas: int,
     *   totales_por_categoria: array<string, string>
     * }
     */
    public static function resumen(string $desde, string $hasta, array $filtros = []): array
    {
        $acumulados = [];

        foreach (array_keys(self::GRUPOS) as $clave) {
            $acumulados[$clave] = ['gramos' => Money::cero(Money::ESCALA_CANTIDAD), 'costo' => Money::cero(), 'productos' => []];
        }

        $acumulados['otros'] = ['gramos' => Money::cero(Money::ESCALA_CANTIDAD), 'costo' => Money::cero(), 'productos' => []];

        foreach (self::lineasPeriodo($desde, $hasta, $filtros)->get() as $linea) {
            $producto = $linea->producto;
            $clave = self::grupoDe($producto) ?? 'otros';
            $gramos = Money::normal($linea->cantidad, Money::ESCALA_CANTIDAD);
            $costo = Money::normal($linea->costo_total);

            $acumulados[$clave]['gramos'] = Money::add($acumulados[$clave]['gramos'], $gramos, Money::ESCALA_CANTIDAD);
            $acumulados[$clave]['costo'] = Money::add($acumulados[$clave]['costo'], $costo);

            $nombre = $producto->nombre ?? 'Producto #'.$linea->producto_id;
            $indice = $nombre.'|'.$clave;

            if (! isset($acumulados[$clave]['productos'][$indice])) {
                $acumulados[$clave]['productos'][$indice] = [
                    'nombre' => $nombre,
                    'gramos' => Money::cero(Money::ESCALA_CANTIDAD),
                    'costo' => Money::cero(),
                    'notas' => null,
                    'orden' => 0,
                ];
            }

            $acumulados[$clave]['productos'][$indice]['gramos'] = Money::add(
                $acumulados[$clave]['productos'][$indice]['gramos'],
                $gramos,
                Money::ESCALA_CANTIDAD
            );
            $acumulados[$clave]['productos'][$indice]['costo'] = Money::add(
                $acumulados[$clave]['productos'][$indice]['costo'],
                $costo
            );
            $acumulados[$clave]['productos'][$indice]['orden']++;
            $acumulados[$clave]['productos'][$indice]['notas'] = $acumulados[$clave]['productos'][$indice]['notas'] ?? $linea->notas;
        }

        $grupos = [];
        $totalGramos = Money::cero(Money::ESCALA_CANTIDAD);
        $totalCosto = Money::cero();
        $totalLineas = 0;
        $totalesPorCategoria = [
            'tintes' => Money::cero(Money::ESCALA_CANTIDAD),
            'tratamientos' => Money::cero(Money::ESCALA_CANTIDAD),
            'coactivadores' => Money::cero(Money::ESCALA_CANTIDAD),
            'decolorantes' => Money::cero(Money::ESCALA_CANTIDAD),
            'otros' => Money::cero(Money::ESCALA_CANTIDAD),
        ];

        foreach (array_keys(self::GRUPOS) as $clave) {
            $datos = $acumulados[$clave];
            $totalLineas += count($datos['productos']);

            if (self::estaVacio($datos)) {
                $grupos[] = self::grupoVacio($clave);

                continue;
            }

            $productos = $datos['productos'];
            uasort($productos, fn ($a, $b) => [$b['orden'], $b['gramos']] <=> [$a['orden'], $a['gramos']]);

            $grupos[] = [
                'clave' => $clave,
                'etiqueta' => self::GRUPOS[$clave]['etiqueta'],
                'resumen' => self::GRUPOS[$clave]['resumen'],
                'gramos' => $datos['gramos'],
                'costo' => $datos['costo'],
                'productos' => array_values($productos),
            ];

            $totalGramos = Money::add($totalGramos, $datos['gramos'], Money::ESCALA_CANTIDAD);
            $totalCosto = Money::add($totalCosto, $datos['costo']);
            if (isset($totalesPorCategoria[$clave])) {
                $totalesPorCategoria[$clave] = Money::add($totalesPorCategoria[$clave], $datos['gramos'], Money::ESCALA_CANTIDAD);
            }
        }

        // Productos fuera de las tres categorías: solo aparecen si existen.
        if (! self::estaVacio($acumulados['otros'])) {
            $productos = $acumulados['otros']['productos'];
            uasort($productos, fn ($a, $b) => [$b['orden'], $b['gramos']] <=> [$a['orden'], $a['gramos']]);

            $grupos[] = [
                'clave' => 'otros',
                'etiqueta' => self::etiqueta(null),
                'resumen' => 'Fuera de las tres categorías',
                'gramos' => $acumulados['otros']['gramos'],
                'costo' => $acumulados['otros']['costo'],
                'productos' => array_values($productos),
            ];

            $totalGramos = Money::add($totalGramos, $acumulados['otros']['gramos'], Money::ESCALA_CANTIDAD);
            $totalCosto = Money::add($totalCosto, $acumulados['otros']['costo']);
            $totalesPorCategoria['otros'] = Money::add($totalesPorCategoria['otros'], $acumulados['otros']['gramos'], Money::ESCALA_CANTIDAD);
            $totalLineas += count($acumulados['otros']['productos']);
        }

        return [
            'grupos' => $grupos,
            'total_gramos' => $totalGramos,
            'total_costo' => $totalCosto,
            'total_lineas' => $totalLineas,
            'totales_por_categoria' => [
                'tintes' => $totalesPorCategoria['tintes'],
                'tratamientos' => $totalesPorCategoria['tratamientos'],
                'coactivadores' => $totalesPorCategoria['coactivadores'],
                'decolorantes' => $totalesPorCategoria['decolorantes'],
                'otros' => $totalesPorCategoria['otros'],
            ],
        ];
    }

    /**
     * @param  array{gramos: string, costo: string, productos: array}  $datos
     */
    private static function estaVacio(array $datos): bool
    {
        return Money::igual($datos['gramos'], '0', Money::ESCALA_CANTIDAD)
            && Money::igual($datos['costo'], '0');
    }

    /**
     * @return array{clave: string, etiqueta: string, resumen: string, gramos: string, costo: string, productos: array}
     */
    private static function grupoVacio(string $clave): array
    {
        return [
            'clave' => $clave,
            'etiqueta' => self::GRUPOS[$clave]['etiqueta'],
            'resumen' => self::GRUPOS[$clave]['resumen'],
            'gramos' => Money::cero(Money::ESCALA_CANTIDAD),
            'costo' => Money::cero(),
            'productos' => [],
        ];
    }
}
