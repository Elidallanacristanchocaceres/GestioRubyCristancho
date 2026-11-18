<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoriaProductoResource;
use App\Http\Resources\ProductoResource;
use App\Models\CategoriaProducto;
use App\Models\InventarioLote;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\AuditoriaService;
use App\Services\InventarioService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->consultaIndex($request);
        $productos = $query->paginate(15);

        $stats = [
            'referencias' => Producto::where('activo', true)->count(),
            'stock_bajo' => Producto::where('activo', true)->where('stock', '>', 0)->whereColumn('stock', '<=', 'stock_minimo')->count(),
            'agotados' => Producto::where('activo', true)->where('stock', '<=', 0)->count(),
            'valor_costo' => (string) Producto::where('activo', true)
                ->selectRaw('COALESCE(SUM(stock * costo_unitario), 0) AS total')
                ->value('total'),
            'por_medir' => InventarioLote::where('medido', false)->count(),
            'por_revisar' => Producto::where('pendiente_confirmacion', true)->count(),
        ];

        $movimientosRecientes = InventarioMovimiento::with(['producto', 'usuario'])
            ->orderByDesc('id')->limit(10)->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'producto_id' => $m->producto_id,
                'producto_nombre' => $m->producto?->nombre,
                'tipo' => $m->tipo,
                'cantidad' => (string) $m->cantidad,
                'created_at' => $m->created_at?->toIso8601String(),
                'usuario_nombre' => $m->usuario?->name,
            ])->values();

        return response()->json([
            'data' => ProductoResource::collection($productos),
            'links' => [
                'first' => $productos->url(1),
                'last' => $productos->url($productos->lastPage()),
                'prev' => $productos->previousPageUrl(),
                'next' => $productos->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $productos->currentPage(),
                'last_page' => $productos->lastPage(),
                'per_page' => $productos->perPage(),
                'total' => $productos->total(),
            ],
            'stats' => $stats,
            'movimientos_recientes' => $movimientosRecientes,
            'categorias' => CategoriaProductoResource::collection(CategoriaProducto::orderBy('nombre')->get()),
            'proveedores' => Proveedor::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function show(Producto $producto): JsonResponse
    {
        $producto->load(['categoria', 'proveedor', 'lotes' => fn ($q) => $q->orderBy('fecha_ingreso')]);

        $movimientos = $producto->movimientos()->with('usuario')->orderByDesc('id')->paginate(20);

        return response()->json([
            'data' => new ProductoResource($producto),
            'lotes' => $producto->lotes->map(fn ($l) => [
                'id' => $l->id, 'envases' => (string) $l->envases,
                'estado_envase' => $l->estado_envase, 'estado' => $l->estado,
                'medido' => (bool) $l->medido,
                'cantidad_disponible' => (string) $l->cantidad_disponible,
                'costo_unitario' => (string) $l->costo_unitario,
                'notas' => $l->notas,
                'fecha_ingreso' => $l->fecha_ingreso?->toDateString(),
            ])->values(),
            'movimientos' => collect($movimientos->items())->map(fn ($m) => [
                'id' => $m->id,
                'tipo' => $m->tipo,
                'cantidad' => $m->cantidad !== null ? (string) $m->cantidad : null,
                'cantidad_anterior' => $m->cantidad_anterior !== null ? (string) $m->cantidad_anterior : null,
                'cantidad_nueva' => $m->cantidad_nueva !== null ? (string) $m->cantidad_nueva : null,
                'costo_unitario' => $m->costo_unitario !== null ? (string) $m->costo_unitario : null,
                'motivo' => $m->motivo,
                'modulo' => $m->modulo,
                'referencia' => $m->referencia,
                'created_at' => $m->created_at?->toIso8601String(),
                'usuario_nombre' => $m->usuario?->name,
            ])->values(),
            'movimientos_meta' => [
                'current_page' => $movimientos->currentPage(),
                'last_page' => $movimientos->lastPage(),
                'total' => $movimientos->total(),
            ],
        ]);
    }

    public function store(Request $request, InventarioService $inventario): JsonResponse
    {
        ['datos' => $datos, 'stock_inicial' => $stockInicial] = $this->validar($request);

        $producto = new Producto($datos);
        $producto->recalcularCostoUnitario();
        $producto->save();

        if (Money::mayorQue($stockInicial, '0', Money::ESCALA_CANTIDAD)) {
            $costoUnitario = $producto->costo_unitario
                ?: Money::div((string) ($producto->precio_costo ?? 0), '1', Money::ESCALA_COSTO_UNITARIO);
            $inventario->registrarEntrada($producto, $stockInicial, Money::mul($costoUnitario, $stockInicial), [
                'tipo' => InventarioService::TIPO_ENTRADA,
                'costo_unitario' => $costoUnitario,
                'motivo' => 'Stock inicial',
                'notas' => 'Lote de apertura del producto',
            ]);
            $producto->refresh();
        }

        AuditoriaService::registrar('crear', 'inventario', Producto::class, $producto->id, [], [
            'nombre' => $producto->nombre,
            'stock' => (string) $producto->stock,
        ]);

        $producto->load(['categoria', 'proveedor']);

        return response()->json(['data' => new ProductoResource($producto)], 201);
    }

    public function update(Request $request, Producto $producto): JsonResponse
    {
        ['datos' => $datos] = $this->validar($request, $producto);

        $antes = $producto->only(['nombre', 'marca', 'tono', 'precio_costo', 'precio_venta', 'precio_sugerido', 'stock_minimo', 'contenido_envase', 'costo_envase']);
        $producto->fill($datos);
        $producto->recalcularCostoUnitario();
        $producto->save();

        AuditoriaService::registrar('actualizar', 'inventario', Producto::class, $producto->id, $antes, $producto->only(array_keys($antes)));
        $producto->load(['categoria', 'proveedor']);

        return response()->json(['data' => new ProductoResource($producto)]);
    }

    public function destroy(Producto $producto): JsonResponse
    {
        if ($producto->movimientos()->exists() || $producto->consumos()->exists()) {
            $producto->update(['activo' => false]);

            return response()->json(['message' => 'El producto tiene movimientos históricos; se desactivó en lugar de eliminarlo.']);
        }

        $producto->delete();

        return response()->json(['message' => 'Producto eliminado correctamente.']);
    }

    /**
     * Reactiva un producto desactivado (por ejemplo, tras un borrado lógico
     * con movimientos históricos).
     */
    public function reactivar(Producto $producto): JsonResponse
    {
        $producto->update(['activo' => true]);
        $producto->load(['categoria', 'proveedor']);

        return response()->json([
            'message' => 'Producto reactivado.',
            'data' => new ProductoResource($producto),
        ]);
    }

    /**
     * Ajuste manual de inventario con motivo (queda auditado).
     */
    public function ajustar(Request $request, Producto $producto, InventarioService $inventario): JsonResponse
    {
        $datos = $request->validate([
            'nueva_cantidad' => ['required', 'numeric', 'min:0'],
            'motivo' => ['required', 'string', 'max:300'],
        ]);

        $inventario->ajustar($producto, (string) $datos['nueva_cantidad'], $datos['motivo']);
        $producto->refresh()->load(['categoria', 'proveedor']);

        return response()->json([
            'message' => 'Inventario ajustado correctamente.',
            'data' => new ProductoResource($producto),
        ]);
    }

    /**
     * Mide un envase pendiente: solo entonces su contenido suma al stock.
     */
    public function medirEnvase(Request $request, Producto $producto, InventarioService $inventario): JsonResponse
    {
        $datos = $request->validate([
            'lote_id' => [
                'required',
                Rule::exists('inventario_lotes', 'id')
                    ->where('producto_id', $producto->id)
                    ->where('medido', false),
            ],
            'cantidad' => ['required', 'numeric', 'min:0'],
        ]);

        $lote = InventarioLote::where('producto_id', $producto->id)->findOrFail($datos['lote_id']);
        $inventario->medirEnvase($lote, (string) $datos['cantidad']);
        $producto->refresh()->load(['categoria', 'proveedor']);

        return response()->json([
            'message' => "Envase medido: {$datos['cantidad']} {$producto->unidadAbrev()} agregados al stock de {$producto->nombre}.",
            'data' => new ProductoResource($producto),
        ]);
    }

    /**
     * Descuenta producto usado dejando constancia del motivo.
     */
    public function registrarConsumo(Request $request, Producto $producto, InventarioService $inventario): JsonResponse
    {
        $datos = $request->validate([
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'motivo' => ['required', 'string', 'max:300'],
        ]);

        $inventario->consumir($producto, (string) $datos['cantidad'], [
            'tipo' => InventarioService::TIPO_SALIDA,
            'motivo' => $datos['motivo'],
            'modulo' => 'inventario',
        ]);
        $producto->refresh()->load(['categoria', 'proveedor']);

        return response()->json([
            'message' => "Consumo registrado: -{$datos['cantidad']} {$producto->unidadAbrev()} de {$producto->nombre}.",
            'data' => new ProductoResource($producto),
        ]);
    }

    /**
     * Exporta el listado filtrado a CSV (compatible con Excel).
     */
    public function exportar(Request $request): StreamedResponse
    {
        $productos = $this->consultaIndex($request)->get();
        $nombre = 'inventario-'.now()->format('Y-m-d-Hi');

        return response()->streamDownload(function () use ($productos) {
            $fh = fopen('php://output', 'w');
            fwrite($fh, "\xEF\xBB\xBF"); // UTF-8 BOM para Excel
            fputcsv($fh, [
                'Categoría', 'Nombre', 'Marca', 'Tono', 'Presentación',
                'Unidad de control', 'Contenido por envase', 'Existencia actual', 'Stock mínimo',
                'Costo por envase', 'Costo por gramo/ml', 'Precio sugerido', 'Precio de venta',
                'Valor al costo', 'Proveedor', 'Última compra', 'Pendiente', 'Activo',
            ], ';');

            foreach ($productos as $p) {
                fputcsv($fh, [
                    $p->categoria?->nombre,
                    $p->nombre,
                    $p->marca,
                    $p->tono,
                    $p->contenido_envase !== null
                        ? Money::formatoDecimal($p->contenido_envase).' '.$p->unidadAbrev().'/envase'
                        : '',
                    $p->unidad_control,
                    $p->contenido_envase !== null ? Money::formatoDecimal($p->contenido_envase) : '',
                    Money::formatoDecimal($p->stock),
                    Money::formatoDecimal($p->stock_minimo),
                    Money::formato($p->costo_envase),
                    Money::formatoDecimal($p->costo_unitario, 6),
                    Money::formato($p->precio_sugerido),
                    Money::formato($p->precio_venta),
                    Money::formato(Money::mul((string) $p->costo_unitario, (string) $p->stock)),
                    $p->proveedor?->nombre,
                    optional($p->fecha_compra)->format('d/m/Y'),
                    $p->pendiente_confirmacion ? 'Sí' : 'No',
                    $p->activo ? 'Sí' : 'No',
                ], ';');
            }
            fclose($fh);
        }, "{$nombre}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Panel de revisión: productos cuya relación precio/costo no cuadra.
     * No corrige nada, solo los señala.
     */
    public function revisionPrecios(): JsonResponse
    {
        $productos = Producto::with(['categoria', 'proveedor'])
            ->whereNotNull('precio_venta')
            ->whereNotNull('costo_unitario')
            ->where('costo_unitario', '>', 0)
            ->get()
            ->filter(fn (Producto $p) => $this->tieneInconsistenciaPrecio($p))
            ->sortByDesc(fn (Producto $p) => $this->diferenciaPrecio($p))
            ->values();

        $pendientes = Producto::with(['categoria', 'proveedor'])
            ->where('pendiente_confirmacion', true)
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'data' => ProductoResource::collection($productos),
            'pendientes' => ProductoResource::collection($pendientes),
            'total' => $productos->count(),
        ]);
    }

    private function tieneInconsistenciaPrecio(Producto $producto): bool
    {
        return $this->diferenciaPrecio($producto) !== 0.0;
    }

    /**
     * Diferencia entre la fórmula (contenido × costo unitario) y el precio
     * de venta cargado. Devuelve 0 cuando no hay datos suficientes.
     */
    private function diferenciaPrecio(Producto $producto): float
    {
        if (! $producto->contenido_envase || ! $producto->costo_unitario || ! $producto->precio_venta) {
            return 0.0;
        }

        $esperado = (float) $producto->contenido_envase * (float) $producto->costo_unitario;
        $real = (float) $producto->precio_venta;

        if ($esperado <= 0) {
            return 0.0;
        }

        return round($real - $esperado, 2);
    }

    public function entrada(Request $request, InventarioService $inventario): JsonResponse
    {
        $datos = $request->validate([
            'producto_id' => ['required', 'exists:productos,id'],
            'cantidad_envases' => ['required', 'numeric', 'gt:0'],
            'contenido_envase' => ['nullable', 'numeric', 'gt:0'],
            'costo_total' => ['nullable', 'numeric', 'min:0'],
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'fecha_ingreso' => ['nullable', 'date'],
            'estado_envase' => ['nullable', Rule::in(['nuevo', 'empezado'])],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);

        $producto = Producto::findOrFail($datos['producto_id']);
        $estadoEnvase = $datos['estado_envase'] ?? 'nuevo';
        $medido = $estadoEnvase === 'nuevo';
        $contenido = $datos['contenido_envase'] ?? $producto->contenido_envase;

        if (! $medido) {
            $costoUnitario = $producto->costo_unitario !== null && (float) $producto->costo_unitario > 0
                ? Money::normal((string) $producto->costo_unitario, Money::ESCALA_COSTO_UNITARIO)
                : null;
            $inventario->registrarEntrada($producto, '0', '0', [
                'tipo' => InventarioService::TIPO_ENTRADA,
                'costo_unitario' => $costoUnitario,
                'proveedor_id' => $datos['proveedor_id'] ?? null,
                'fecha_ingreso' => $datos['fecha_ingreso'] ?? now()->toDateString(),
                'envases' => (int) $datos['cantidad_envases'],
                'estado_envase' => 'empezado',
                'medido' => false,
                'notas' => $datos['observaciones'] ?? null,
                'motivo' => 'Entrada de inventario (envase empezado por medir)',
                'modulo' => 'inventario',
            ]);

            return response()->json(['message' => 'Envase registrado como pendiente de medición.'], 201);
        }

        if ((float) $contenido <= 0) {
            return response()->json(['message' => 'Indica el contenido de cada envase (o completa el producto primero).'], 422);
        }

        $totalRecibido = Money::mul((string) $datos['cantidad_envases'], (string) $contenido, Money::ESCALA_CANTIDAD);
        $costoUnitario = null;
        if (isset($datos['costo_total']) && (float) $datos['costo_total'] > 0) {
            $costoUnitario = Money::div((string) $datos['costo_total'], $totalRecibido, Money::ESCALA_COSTO_UNITARIO);
        } elseif ($producto->costo_unitario !== null && (float) $producto->costo_unitario > 0) {
            $costoUnitario = Money::normal((string) $producto->costo_unitario, Money::ESCALA_COSTO_UNITARIO);
        }
        $costoTotalEntrada = $costoUnitario !== null ? Money::mul($costoUnitario, $totalRecibido) : '0';

        $inventario->registrarEntrada($producto, $totalRecibido, $costoTotalEntrada, [
            'tipo' => InventarioService::TIPO_ENTRADA,
            'costo_unitario' => $costoUnitario,
            'proveedor_id' => $datos['proveedor_id'] ?? null,
            'fecha_ingreso' => $datos['fecha_ingreso'] ?? now()->toDateString(),
            'envases' => (int) $datos['cantidad_envases'],
            'estado_envase' => 'nuevo',
            'medido' => true,
            'notas' => $datos['observaciones'] ?? null,
            'motivo' => 'Entrada de inventario (pedido recibido)',
            'modulo' => 'inventario',
        ]);

        return response()->json(['message' => "Entrada registrada: +{$totalRecibido} {$producto->unidadAbrev()} de {$producto->nombre}."], 201);
    }

    private function validar(Request $request, ?Producto $producto = null): array
    {
        $validado = $request->validate([
            'categoria_producto_id' => ['required', 'exists:categoria_productos,id'],
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'nombre' => ['required', 'string', 'max:200'],
            'marca' => ['nullable', 'string', 'max:120'],
            'tono' => ['nullable', 'string', 'max:60'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'unidad_control' => ['required', Rule::in([Producto::UNIDAD_UNIDAD, Producto::UNIDAD_GRAMO, Producto::UNIDAD_MILILITRO, Producto::UNIDAD_BOLSA])],
            'presentacion' => ['nullable', 'string', 'max:80'],
            'contenido_envase' => ['nullable', 'numeric', 'min:0'],
            'costo_envase' => ['nullable', 'numeric', 'min:0'],
            'costo_unitario' => ['nullable', 'numeric', 'min:0'],
            'precio_costo' => ['nullable', 'numeric', 'min:0'],
            'precio_venta' => ['nullable', 'numeric', 'min:0'],
            'precio_sugerido' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'fecha_compra' => ['nullable', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_compra'],
        ]);

        $stockInicial = $producto === null ? Money::normal($validado['stock'] ?? '0', Money::ESCALA_CANTIDAD) : '0';
        $validado['costo_unitario'] = $request->filled('costo_unitario')
            ? $request->input('costo_unitario')
            : $producto?->costo_unitario;
        $validado['stock'] = $producto?->stock ?? '0';
        $validado['activo'] = $request->boolean('activo', true);
        $validado['pendiente_confirmacion'] = $request->boolean('pendiente_confirmacion', $producto?->pendiente_confirmacion ?? false);

        return ['datos' => $validado, 'stock_inicial' => $stockInicial];
    }

    private function consultaIndex(Request $request): Builder
    {
        return Producto::with(['categoria', 'proveedor'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = $request->str('q');
                $q->where(function ($sub) use ($t) {
                    $sub->where('nombre', 'like', "%{$t}%")
                        ->orWhere('marca', 'like', "%{$t}%")
                        ->orWhere('tono', 'like', "%{$t}%");
                });
            })
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria_producto_id', $request->integer('categoria')))
            ->when($request->filled('grupo'), fn ($q) => $q->whereHas('categoria', fn ($sub) => $sub->where('grupo', $request->str('grupo'))))
            ->when($request->filled('unidad'), fn ($q) => $q->where('unidad_control', $request->str('unidad')))
            ->when($request->filled('estado'), fn ($q) => $q->where('activo', $request->str('estado') === 'activos'))
            ->when($request->boolean('agotados'), fn ($q) => $q->where('stock', '<=', 0))
            ->when($request->boolean('bajos'), fn ($q) => $q->whereColumn('stock', '<=', 'stock_minimo')->where('stock', '>', 0))
            ->when($request->filled('pendiente'), fn ($q) => $q->where('pendiente_confirmacion', true))
            ->when($request->boolean('por_medir'), fn ($q) => $q->whereHas('lotes', fn ($sub) => $sub->where('medido', false)))
            ->orderBy($this->columnaOrden($request), $this->direccionOrden($request));
    }

    private function columnaOrden(Request $request): string
    {
        $orden = $request->str('orden', 'nombre');

        return in_array($orden, ['nombre', 'stock', 'marca'], true) ? $orden : 'nombre';
    }

    private function direccionOrden(Request $request): string
    {
        $direccion = $request->str('direccion', 'asc');

        return in_array($direccion, ['asc', 'desc'], true) ? $direccion : 'asc';
    }
}
