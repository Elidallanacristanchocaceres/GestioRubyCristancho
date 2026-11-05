<?php

namespace App\Http\Controllers;

use App\Models\CategoriaProducto;
use App\Models\InventarioLote;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\AuditoriaService;
use App\Services\InventarioService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductoController extends Controller
{
    public function index(Request $request): View
    {
        $productos = $this->consultaIndex($request)->paginate(15)->withQueryString();

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

        $movimientosRecientes = \App\Models\InventarioMovimiento::with(['producto', 'usuario'])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $resumenCategorias = CategoriaProducto::withCount(['productos as productos_activos' => fn ($q) => $q->where('activo', true)])
            ->orderBy('nombre')->get();

        $resumenGrupos = CategoriaProducto::selectRaw('grupo, COUNT(DISTINCT categoria_productos.id) AS categorias, COUNT(productos.id) AS productos')
            ->leftJoin('productos', 'productos.categoria_producto_id', '=', 'categoria_productos.id')
            ->whereNull('categoria_productos.deleted_at')
            ->groupBy('grupo')
            ->orderBy('grupo')
            ->get();

        return view('productos.index', [
            'productos' => $productos,
            'stats' => $stats,
            'movimientosRecientes' => $movimientosRecientes,
            'categorias' => CategoriaProducto::orderBy('nombre')->get(),
            'resumenCategorias' => $resumenCategorias,
            'resumenGrupos' => $resumenGrupos,
            'orden' => $this->columnaOrden($request),
            'direccion' => $this->direccionOrden($request),
        ]);
    }

    /**
     * Panel de revisión: lista los productos cuya relación matemática entre
     * precio de venta y costo por unidad no cuadra.
     *
     * No corrige nada: solo señala lo que hay que confirmar.
     */
    public function revisarPrecios(Request $request): View
    {
        $productos = Producto::with(['categoria'])
            ->whereNotNull('precio_venta')
            ->whereNotNull('costo_unitario')
            ->where('costo_unitario', '>', 0)
            ->get()
            ->filter(fn (Producto $p) => $this->tieneInconsistenciaPrecio($p))
            ->sortByDesc(fn (Producto $p) => $this->diferenciaPrecio($p))
            ->values();

        $pendientes = Producto::where('pendiente_confirmacion', true)
            ->with('categoria')
            ->orderBy('nombre')
            ->get();

        return view('productos.revision-precios', [
            'productos' => $productos,
            'pendientes' => $pendientes,
        ]);
    }

    private function tieneInconsistenciaPrecio(Producto $producto): bool
    {
        return $this->diferenciaPrecio($producto) !== 0;
    }

    /**
     * Diferencia entre lo que dice la fórmula (contenido × costo por unidad)
     * y el precio de venta cargado. Vuelve 0 cuando no hay datos suficientes.
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

    /**
     * Exporta el listado filtrado a CSV (compatible con Excel).
     */
    public function exportar(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $productos = $this->consultaIndex($request)->get();

        $nombre = 'inventario-' . now()->format('Y-m-d-Hi');

        return response()->streamDownload(function () use ($productos) {
            $fh = fopen('php://output', 'w');
            fwrite($fh, "\xEF\xBB\xBF"); // UTF-8 BOM para Excel
            fputcsv($fh, [
                'Categoría', 'Nombre', 'Línea', 'Marca', 'Código', 'Tono', 'Presentación',
                'Unidad de control', 'Contenido por envase', 'Existencia actual', 'Stock mínimo',
                'Costo por envase', 'Costo por gramo/ml', 'Precio sugerido', 'Precio de venta',
                'Valor al costo', 'Proveedor', 'Última compra', 'Pendiente', 'Activo',
            ], ';');

            foreach ($productos as $p) {
                fputcsv($fh, [
                    $p->categoria?->nombre,
                    $p->nombre,
                    $p->linea,
                    $p->marca,
                    $p->codigo,
                    $p->tono,
                    optional($p->contenido_envase)->format(null) ? '' : \App\Support\Money::formatoDecimal($p->contenido_envase) . ' ' . $p->unidadAbrev() . '/envase',
                    $p->unidad_control,
                    \App\Support\Money::formatoDecimal($p->contenido_envase),
                    \App\Support\Money::formatoDecimal($p->stock),
                    \App\Support\Money::formatoDecimal($p->stock_minimo),
                    \App\Support\Money::formato($p->costo_envase),
                    \App\Support\Money::formatoDecimal($p->costo_unitario, 6),
                    \App\Support\Money::formato($p->precio_sugerido),
                    \App\Support\Money::formato($p->precio_venta),
                    \App\Support\Money::formato(\App\Support\Money::mul((string) $p->costo_unitario, (string) $p->stock)),
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

    public function create(): View
    {
        return view('productos.create', $this->opciones());
    }

    public function store(Request $request, InventarioService $inventario): RedirectResponse
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

        return redirect()->route('productos.show', $producto)
            ->with('exito', 'Producto creado correctamente.');
    }

    public function show(Producto $producto): View
    {
        $producto->load(['categoria', 'proveedor', 'lotes' => fn ($q) => $q->orderBy('fecha_ingreso')]);

        $movimientos = $producto->movimientos()
            ->with('usuario')
            ->orderByDesc('id')
            ->paginate(20);

        return view('productos.show', compact('producto', 'movimientos'));
    }

    public function edit(Producto $producto): View
    {
        return view('productos.edit', array_merge($this->opciones(), ['producto' => $producto]));
    }

    public function update(Request $request, Producto $producto): RedirectResponse
    {
        ['datos' => $datos] = $this->validar($request, $producto);

        $antes = $producto->only(['nombre', 'codigo', 'marca', 'linea', 'tono', 'precio_costo', 'precio_venta', 'precio_sugerido', 'stock_minimo', 'contenido_envase', 'costo_envase']);
        $producto->fill($datos);
        $producto->recalcularCostoUnitario();
        $producto->save();

        AuditoriaService::registrar('actualizar', 'inventario', Producto::class, $producto->id, $antes, $producto->only(array_keys($antes)));

        return redirect()->route('productos.show', $producto)
            ->with('exito', 'Producto actualizado correctamente.');
    }

    /**
     * Desactiva un producto. No se elimina si tiene movimientos históricos.
     */
    public function destroy(Producto $producto): RedirectResponse
    {
        if ($producto->movimientos()->exists() || $producto->consumos()->exists()) {
            $producto->update(['activo' => false]);

            return redirect()->route('productos.index')
                ->with('exito', 'El producto tiene movimientos históricos; se desactivó en lugar de eliminarlo.');
        }

        $producto->delete();

        return redirect()->route('productos.index')
            ->with('exito', 'Producto eliminado correctamente.');
    }

    public function reactivar(Producto $producto): RedirectResponse
    {
        $producto->update(['activo' => true]);

        return back()->with('exito', 'Producto reactivado.');
    }

    /**
     * Ajuste manual de inventario con motivo (queda auditado).
     */
    public function ajustar(Request $request, Producto $producto, InventarioService $inventario): RedirectResponse
    {
        $datos = $request->validate([
            'nueva_cantidad' => ['required', 'numeric', 'min:0'],
            'motivo' => ['required', 'string', 'max:300'],
        ]);

        $inventario->ajustar($producto, (string) $datos['nueva_cantidad'], $datos['motivo']);

        return back()->with('exito', 'Inventario ajustado correctamente.');
    }

    /**
     * Formulario para registrar una entrada de inventario (pedido recibido).
     */
    public function entradaForm(): View
    {
        return view('productos.entrada', [
            'productos' => Producto::with('categoria')->where('activo', true)->orderBy('nombre')->get(),
            'proveedores' => Proveedor::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    /**
     * Registra la llegada de mercancía: envases x contenido = total, crea
     * lote (FIFO), actualiza stock y deja movimiento auditable.
     */
    public function registrarEntrada(Request $request, InventarioService $inventario): RedirectResponse
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
            // Envase ya empezado: se registra el envase pero NO se suma
            // stock, porque no se puede suponer cuánto queda dentro.
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

            return redirect()->route('productos.index')
                ->with('exito', "Envase registrado como pendiente de medición: {$datos['cantidad_envases']} de {$producto->nombre}. Pésalos para que sumen al stock.");
        }

        if ((float) $contenido <= 0) {
            return back()->withInput()->withErrors(['contenido_envase' => 'Indica el contenido de cada envase (o completa el producto primero).']);
        }

        $totalRecibido = \App\Support\Money::mul(
            (string) $datos['cantidad_envases'],
            (string) $contenido,
            \App\Support\Money::ESCALA_CANTIDAD
        );

        $costoUnitario = null;
        if (isset($datos['costo_total']) && (float) $datos['costo_total'] > 0) {
            $costoUnitario = \App\Support\Money::div(
                (string) $datos['costo_total'],
                $totalRecibido,
                \App\Support\Money::ESCALA_COSTO_UNITARIO
            );
        } elseif ($producto->costo_unitario !== null && (float) $producto->costo_unitario > 0) {
            // Sin costo indicado: se conserva el costo por unidad ya configurado
            // en la ficha para no crear un lote valorizado en cero.
            $costoUnitario = \App\Support\Money::normal((string) $producto->costo_unitario, \App\Support\Money::ESCALA_COSTO_UNITARIO);
        }

        $costoTotalEntrada = $costoUnitario !== null
            ? \App\Support\Money::mul($costoUnitario, $totalRecibido)
            : '0';

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

        return redirect()->route('productos.index')
            ->with('exito', "Entrada registrada: +{$totalRecibido} {$producto->unidadAbrev()} de {$producto->nombre} ({$datos['cantidad_envases']} envase(s)).");
    }

    /**
     * Mide un envase que estaba pendiente (empezado o sin contenido definido).
     *
     * Solo entonces la cantidad real entra a las existencias: hasta ese
     * momento no se puede suponer cuánto queda dentro.
     */
    public function medirEnvase(Request $request, Producto $producto, InventarioService $inventario): RedirectResponse
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

        return back()->with('exito', "Envase medido: {$datos['cantidad']} {$producto->unidadAbrev()} agregados al stock de {$producto->nombre}.");
    }

    /**
     * Descuenta producto usado (gramos, mililitros o unidades) dejando
     * constancia del motivo. No descuenta el envase completo.
     */
    public function registrarConsumo(Request $request, Producto $producto, InventarioService $inventario): RedirectResponse
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

        return back()->with('exito', "Consumo registrado: -{$datos['cantidad']} {$producto->unidadAbrev()} de {$producto->nombre}.");
    }

    private function opciones(): array
    {
        return [
            'categorias' => CategoriaProducto::orderBy('nombre')->get(),
            'proveedores' => Proveedor::where('activo', true)->orderBy('nombre')->get(),
            'unidades' => [
                Producto::UNIDAD_UNIDAD => 'Unidades',
                Producto::UNIDAD_GRAMO => 'Gramos',
                Producto::UNIDAD_MILILITRO => 'Mililitros',
                Producto::UNIDAD_BOLSA => 'Bolsas',
            ],
            'usos' => [
                Producto::USO_PROFESIONAL => 'Uso profesional (interno)',
                Producto::USO_VENTA => 'Venta al cliente',
                Producto::USO_AMBOS => 'Ambos',
            ],
        ];
    }

    /**
     * @return array{datos: array, stock_inicial: string, costo_total_inicial: string}
     */
    private function validar(Request $request, ?Producto $producto = null): array
    {
        $validado = $request->validate([
            'categoria_producto_id' => ['required', 'exists:categoria_productos,id'],
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'nombre' => ['required', 'string', 'max:200'],
            'codigo' => ['nullable', 'string', 'max:60'],
            'marca' => ['nullable', 'string', 'max:120'],
            'linea' => ['nullable', 'string', 'max:120'],
            'tono' => ['nullable', 'string', 'max:60'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'unidad_control' => ['required', Rule::in([Producto::UNIDAD_UNIDAD, Producto::UNIDAD_GRAMO, Producto::UNIDAD_MILILITRO, Producto::UNIDAD_BOLSA])],
            'tipo_uso' => ['required', Rule::in([Producto::USO_PROFESIONAL, Producto::USO_VENTA, Producto::USO_AMBOS])],
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
        ], [
            'categoria_producto_id.required' => 'Selecciona una categoría para el producto.',
            'categoria_producto_id.exists' => 'La categoría seleccionada no existe.',
            'nombre.required' => 'El nombre del producto es obligatorio.',
            'nombre.max' => 'El nombre no debe superar 200 caracteres.',
            'unidad_control.required' => 'Indica la unidad de medida (unidad, gramo, mililitro o bolsa).',
            'unidad_control.in' => 'La unidad de medida seleccionada no es válida.',
            'tipo_uso.required' => 'Indica el tipo de uso del producto.',
            'contenido_envase.numeric' => 'El contenido por envase debe ser un número.',
            'costo_envase.numeric' => 'El costo del envase debe ser un número.',
            'costo_unitario.numeric' => 'El costo por unidad debe ser un número.',
            'precio_venta.numeric' => 'El precio de venta debe ser un número.',
            'precio_sugerido.numeric' => 'El precio sugerido debe ser un número.',
            'stock.numeric' => 'El stock inicial debe ser un número.',
            'stock_minimo.numeric' => 'El stock mínimo debe ser un número.',
            'fecha_vencimiento.after_or_equal' => 'La fecha de vencimiento no puede ser anterior a la fecha de compra.',
        ], [
            'categoria_producto_id' => 'categoría',
            'nombre' => 'nombre',
        ]);

        $stockInicial = $producto === null ? Money::normal($validado['stock'] ?? '0', Money::ESCALA_CANTIDAD) : '0';
        $costoTotalInicial = '0';

        // El costo por gramo/ml es un valor propio y editable. Si viene vacío se
        // conserva el actual (edición) o se calcula desde el envase (alta).
        $validado['costo_unitario'] = $request->filled('costo_unitario')
            ? $request->input('costo_unitario')
            : $producto?->costo_unitario;

        if ($producto === null && Money::mayorQue($stockInicial, '0', Money::ESCALA_CANTIDAD)) {
            $costoEnvase = $validado['costo_envase'] ?? null;
            $contenido = $validado['contenido_envase'] ?? null;
            $precioCosto = $validado['precio_costo'] ?? null;

            if ($costoEnvase && $contenido && Money::mayorQue((string) $contenido, '0', Money::ESCALA_CANTIDAD)) {
                $costoTotalInicial = $stockInicial; // placeholder; se recalcula abajo
                $costoUnitario = Money::div((string) $costoEnvase, (string) $contenido);
                $costoTotalInicial = Money::mul($costoUnitario, $stockInicial);
            } elseif ($precioCosto) {
                $costoTotalInicial = Money::mul((string) $precioCosto, $stockInicial);
            }
        }

        // El stock nunca se edita directo desde el formulario: se ajusta por
        // movimientos auditados.
        $validado['stock'] = $producto?->stock ?? '0';
        $validado['activo'] = $request->boolean('activo', true);
        $validado['pendiente_confirmacion'] = $request->boolean('pendiente_confirmacion');
        $validado['fecha_compra'] = $validado['fecha_compra'] ?? null;

        return ['datos' => $validado, 'stock_inicial' => $stockInicial, 'costo_total_inicial' => $costoTotalInicial];
    }

    private function consultaIndex(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        return Producto::with(['categoria', 'proveedor'])
            ->addSelect(['ultima_entrada' => \App\Models\InventarioMovimiento::selectRaw('MAX(created_at)')
                ->whereColumn('inventario_movimientos.producto_id', 'productos.id')
                ->whereIn('tipo', ['entrada', 'compra', 'ajuste']),
            ])
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = $request->str('q');
                $q->where(function ($sub) use ($t) {
                    $sub->where('nombre', 'like', "%{$t}%")
                        ->orWhere('codigo', 'like', "%{$t}%")
                        ->orWhere('marca', 'like', "%{$t}%")
                        ->orWhere('linea', 'like', "%{$t}%")
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
