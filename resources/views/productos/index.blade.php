@extends('layouts.app')

@section('titulo', 'Inventario')
@section('subtitulo', 'Productos, stock y control por gramos / mililitros')
@section('acciones')
    <a href="{{ route('productos.entrada') }}" class="btn-primary">Registrar entrada</a>
    <a href="{{ route('productos.revision-precios') }}" class="btn-ghost">Revisar precios</a>
    <a href="{{ route('productos.exportar') }}" class="btn-ghost">Exportar</a>
    <a href="{{ route('categoria-productos.index') }}" class="btn-ghost">Categorías</a>
    <a href="{{ route('productos.create') }}" class="btn-ghost">Agregar producto</a>
@endsection

@section('contenido')
    {{-- Indicadores generales --}}
    <div class="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
        <div class="stat-card">
            <p class="text-sm text-ink-500">Referencias activas</p>
            <p class="font-serif text-2xl font-semibold">{{ $stats['referencias'] }}</p>
            <p class="text-xs text-ink-500">{{ $productos->total() }} en la lista actual</p>
        </div>
        <div class="stat-card">
            <p class="text-sm text-ink-500">Stock bajo</p>
            <p class="font-serif text-2xl font-semibold text-amber-600">{{ $stats['stock_bajo'] }}</p>
            <p class="text-xs text-ink-500">por debajo del mínimo</p>
        </div>
        <div class="stat-card">
            <p class="text-sm text-ink-500">Agotados</p>
            <p class="font-serif text-2xl font-semibold text-red-600">{{ $stats['agotados'] }}</p>
            <p class="text-xs text-ink-500">sin existencias</p>
        </div>
        <a href="{{ route('productos.index', ['por_medir' => 1]) }}" class="stat-card block">
            <p class="text-sm text-ink-500">Por medir</p>
            <p class="font-serif text-2xl font-semibold text-sky-700">{{ $stats['por_medir'] }}</p>
            <p class="text-xs text-ink-500">envases sin pesar</p>
        </a>
        <a href="{{ route('productos.index', ['pendiente' => 1]) }}" class="stat-card block">
            <p class="text-sm text-ink-500">Por revisar</p>
            <p class="font-serif text-2xl font-semibold text-amber-700">{{ $stats['por_revisar'] }}</p>
            <p class="text-xs text-ink-500">datos por confirmar</p>
        </a>
        <div class="stat-card">
            <p class="text-sm text-ink-500">Valor al costo</p>
            <p class="font-serif text-2xl font-semibold">{{ \App\Support\Money::formato($stats['valor_costo']) }}</p>
            <p class="text-xs text-ink-500">stock × costo unitario</p>
        </div>
    </div>

    {{-- Filtros por grupo de producto --}}
    @if($resumenGrupos->count())
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-ink-500">Grupos</span>
            <a href="{{ route('productos.index') }}"
               class="rounded-full border px-3 py-1 text-xs transition {{ request('grupo') ? 'border-cream-200 bg-white text-ink-700 hover:border-blush-300 hover:bg-blush-50' : 'border-blush-300 bg-blush-50 text-blush-700' }}">
                Todos
            </a>
            @foreach($resumenGrupos as $grupo)
                <a href="{{ route('productos.index', ['grupo' => $grupo->grupo]) }}"
                   class="rounded-full border px-3 py-1 text-xs transition {{ request('grupo') === $grupo->grupo ? 'border-blush-300 bg-blush-50 text-blush-700' : 'border-cream-200 bg-white text-ink-700 hover:border-blush-300 hover:bg-blush-50' }}">
                    {{ \App\Models\CategoriaProducto::GRUPOS[$grupo->grupo] ?? 'Otros productos capilares' }}
                    <span class="ml-1 font-semibold text-blush-600">{{ $grupo->productos }}</span>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Filtros por categoría --}}
    @if($resumenCategorias->count())
        <div class="mb-4 flex flex-wrap gap-2">
            <a href="{{ route('productos.index') }}"
               class="rounded-full border px-3 py-1 text-xs transition {{ request('categoria') ? 'border-cream-200 bg-white text-ink-700 hover:border-blush-300 hover:bg-blush-50' : 'border-blush-300 bg-blush-50 text-blush-700' }}">
                Todas <span class="ml-1 font-semibold">{{ $stats['referencias'] }}</span>
            </a>
            @foreach($resumenCategorias as $cat)
                <a href="{{ route('productos.index', ['categoria' => $cat->id]) }}"
                   class="rounded-full border px-3 py-1 text-xs transition {{ (int) request('categoria') === $cat->id ? 'border-blush-300 bg-blush-50 text-blush-700' : 'border-cream-200 bg-white text-ink-700 hover:border-blush-300 hover:bg-blush-50' }}">
                    {{ $cat->nombre }}
                    <span class="ml-1 font-semibold text-blush-600">{{ $cat->productos_activos }}</span>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Buscador y filtros --}}
    <form method="GET" class="card card-body mb-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-12">
            <div class="sm:col-span-2 lg:col-span-4">
                <label class="label">Buscar</label>
                <input name="q" value="{{ request('q') }}" class="field" placeholder="Nombre, marca, línea, código o tono">
            </div>
            <div class="lg:col-span-2">
                <label class="label">Grupo</label>
                <select name="grupo" class="field">
                    <option value="">Todos</option>
                    @foreach(\App\Models\CategoriaProducto::GRUPOS as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(request('grupo') === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div class="lg:col-span-2">
                <label class="label">Categoría</label>
                <select name="categoria" class="field">
                    <option value="">Todas</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id }}" @selected((int) request('categoria') === $cat->id)>{{ $cat->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="lg:col-span-2">
                <label class="label">Unidad</label>
                <select name="unidad" class="field">
                    <option value="">Todas</option>
                    <option value="unidad" @selected(request('unidad') === 'unidad')>Unidades</option>
                    <option value="gramo" @selected(request('unidad') === 'gramo')>Gramos</option>
                    <option value="mililitro" @selected(request('unidad') === 'mililitro')>Mililitros</option>
                    <option value="bolsa" @selected(request('unidad') === 'bolsa')>Bolsas</option>
                </select>
            </div>
            <div class="lg:col-span-2">
                <label class="label">Ordenar</label>
                <select name="orden" class="field">
                    <option value="nombre" @selected(request('orden', 'nombre') === 'nombre' && request('direccion', 'asc') === 'asc')>Nombre (A→Z)</option>
                    <option value="nombre" data-dir="desc" @selected(request('orden') === 'nombre' && request('direccion') === 'desc')>Nombre (Z→A)</option>
                    <option value="stock" data-dir="desc" @selected(request('orden') === 'stock')>Stock (mayor primero)</option>
                    <option value="marca" @selected(request('orden') === 'marca')>Marca</option>
                </select>
                <input type="hidden" name="direccion" id="direccion" value="{{ request('direccion', 'asc') }}">
            </div>
            <div class="lg:col-span-2 flex items-end">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 pb-2">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="bajos" value="1" class="rounded border-cream-300" @checked(request()->boolean('bajos'))>
                        Stock bajo
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="agotados" value="1" class="rounded border-cream-300" @checked(request()->boolean('agotados'))>
                        Agotados
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="por_medir" value="1" class="rounded border-cream-300" @checked(request()->boolean('por_medir'))>
                        Por medir
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="pendiente" value="1" class="rounded border-cream-300" @checked(request()->boolean('pendiente'))>
                        Por revisar
                    </label>
                </div>
            </div>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <button class="btn-dark">Filtrar</button>
            <a href="{{ route('productos.index') }}" class="btn-ghost">Limpiar</a>
            @if(request()->hasAny(['q', 'categoria', 'grupo', 'unidad', 'bajos', 'agotados', 'pendiente', 'por_medir', 'orden', 'direccion']))
                <span class="text-xs text-ink-500">Mostrando {{ $productos->total() }} resultado(s)</span>
            @endif
        </div>
    </form>

    {{-- Listado: tabla en escritorio --}}
    <div class="hidden overflow-hidden lg:block">
        <div class="table-wrap">
            <table class="table">
                <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Presentación</th>
                            <th class="text-right">Precios</th>
                            <th class="text-right">Stock disponible</th>
                            <th>Estado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                </thead>
                <tbody>
                    @forelse($productos as $producto)
                        @php
                            $agotado = $producto->estaAgotado();
                            $bajo = $producto->tieneStockBajo();
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('productos.show', $producto) }}" class="font-medium text-ink-900 hover:text-blush-600">{{ $producto->nombre }}</a>
                                @if($producto->marca)<span class="block text-xs text-ink-500">{{ $producto->marca }}</span>@endif
                                @if($producto->codigo || $producto->tono)
                                    <span class="block text-xs text-ink-400">
                                        @if($producto->codigo)Ref. {{ $producto->codigo }}@endif
                                        @if($producto->tono)· Tono {{ $producto->tono }}@endif
                                    </span>
                                @endif
                                @if($producto->pendiente_confirmacion)
                                    <span class="mt-1 inline-block rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">Pendiente</span>
                                @endif
                            </td>
                            <td class="text-xs text-ink-600">{{ $producto->categoria?->nombre ?? '—' }}</td>
                            <td class="text-xs">
                                <span class="block font-medium text-ink-700">{{ $producto->presentacionLabel() }}</span>
                                <span class="block text-ink-500">{{ $producto->unidadLabel() }}</span>
                                @if($producto->contenido_envase)
                                    <span class="block text-ink-400">{{ \App\Support\Money::formatoDecimal($producto->contenido_envase) }} {{ $producto->unidadAbrev() }}/envase</span>
                                @endif
                            </td>
                            <td class="text-right text-xs">
                                <span class="block font-medium text-ink-700">Venta: {{ $producto->precio_venta ? \App\Support\Money::formato($producto->precio_venta) : '—' }}</span>
                                <span class="block text-ink-500">Costo/{{ $producto->unidadAbrev() }}: {{ $producto->costo_unitario ? \App\Support\Money::formatoDecimal($producto->costo_unitario, 4) : '—' }}</span>
                            </td>
                            <td class="text-right">
                                <span class="font-semibold {{ $agotado || $bajo ? ($agotado ? 'text-red-600' : 'text-amber-600') : 'text-ink-800' }}">
                                    {{ \App\Support\Money::formatoDecimal($producto->stock) }} {{ $producto->unidadAbrev() }}
                                </span>
                                @if($producto->stock_minimo)
                                    <span class="block text-xs text-ink-400">mín. {{ \App\Support\Money::formatoDecimal($producto->stock_minimo) }}</span>
                                @endif
                            </td>
                            <td>
                                @if(! $producto->activo)
                                    <span class="badge bg-ink-100 text-ink-600">Inactivo</span>
                                @elseif($agotado)
                                    <span class="badge bg-red-100 text-red-600">Agotado</span>
                                @elseif($bajo)
                                    <span class="badge bg-amber-100 text-amber-700">Stock bajo</span>
                                @else
                                    <span class="badge bg-green-100 text-green-700">Disponible</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-1 text-xs">
                                    <a href="{{ route('productos.entrada', ['producto_id' => $producto->id]) }}" class="rounded px-2 py-1 font-medium text-blush-600 hover:bg-blush-50">Entrada</a>
                                    <a href="{{ route('productos.show', $producto) }}" class="rounded px-2 py-1 text-ink-600 hover:bg-cream-100">Detalle</a>
                                    <a href="{{ route('productos.edit', $producto) }}" class="rounded px-2 py-1 text-ink-600 hover:bg-cream-100">Editar</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-ink-500">No hay productos que coincidan con el filtro.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Listado: tarjetas en móvil --}}
    <div class="space-y-3 lg:hidden">
        @forelse($productos as $producto)
            @php
                $agotado = $producto->estaAgotado();
                $bajo = $producto->tieneStockBajo();
            @endphp
            <div class="card card-body">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <a href="{{ route('productos.show', $producto) }}" class="font-medium text-ink-900">{{ $producto->nombre }}</a>
                        <p class="text-xs text-ink-500">{{ $producto->categoria?->nombre ?? 'Sin categoría' }}@if($producto->marca) · {{ $producto->marca }}@endif</p>
                        <p class="text-xs text-ink-500">{{ $producto->presentacionLabel() }} · {{ $producto->unidadLabel() }}</p>
                    </div>
                    @if(! $producto->activo)
                        <span class="badge bg-ink-100 text-ink-600">Inactivo</span>
                    @elseif($agotado)
                        <span class="badge bg-red-100 text-red-600">Agotado</span>
                    @elseif($bajo)
                        <span class="badge bg-amber-100 text-amber-700">Stock bajo</span>
                    @else
                        <span class="badge bg-green-100 text-green-700">Disponible</span>
                    @endif
                </div>

                <div class="mt-3 grid grid-cols-3 gap-2 text-sm">
                    <div>
                        <p class="text-xs text-ink-500">Stock</p>
                        <p class="font-semibold {{ $agotado ? 'text-red-600' : ($bajo ? 'text-amber-600' : '') }}">{{ \App\Support\Money::formatoDecimal($producto->stock) }} {{ $producto->unidadAbrev() }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-ink-500">Venta</p>
                        <p class="font-semibold">{{ $producto->precio_venta ? \App\Support\Money::formato($producto->precio_venta) : '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-ink-500">Costo/{{ $producto->unidadAbrev() }}</p>
                        <p class="font-semibold">{{ $producto->costo_unitario ? \App\Support\Money::formatoDecimal($producto->costo_unitario, 3) : '—' }}</p>
                    </div>
                </div>

                <div class="mt-3 flex items-center gap-2 border-t border-cream-100 pt-3 text-sm">
                    <a href="{{ route('productos.entrada', ['producto_id' => $producto->id]) }}" class="btn-primary px-3 py-1.5 text-xs">Entrada</a>
                    <a href="{{ route('productos.edit', $producto) }}" class="btn-ghost px-3 py-1.5 text-xs">Editar</a>
                    <a href="{{ route('productos.show', $producto) }}" class="text-xs text-blush-600 hover:underline">Detalle</a>
                </div>
            </div>
        @empty
            <div class="card card-body text-center text-ink-500">No hay productos que coincidan con el filtro.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $productos->links() }}</div>

    {{-- Movimientos recientes --}}
    <div class="mt-6 card card-body">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-serif text-lg font-semibold">Movimientos recientes</h2>
            <span class="text-xs text-ink-500">Entradas, salidas y ajustes auditados</span>
        </div>
        @forelse($movimientosRecientes as $mov)
            <div class="flex items-center justify-between gap-3 border-b border-cream-100 py-2 text-sm last:border-0">
                <div class="min-w-0">
                    <p class="truncate font-medium">{{ $mov->producto?->nombre ?? 'Producto #' . $mov->producto_id }}</p>
                    <p class="text-xs text-ink-500">{{ ucfirst($mov->tipo) }} · {{ $mov->created_at->format('d/m/Y H:i') }} · {{ $mov->usuario?->name ?? '—' }}</p>
                </div>
                <span class="whitespace-nowrap font-semibold {{ \App\Support\Money::menorQue((string) $mov->cantidad, '0', \App\Support\Money::ESCALA_CANTIDAD) ? 'text-red-600' : 'text-green-700' }}">
                    {{ $mov->cantidad > 0 ? '+' : '' }}{{ \App\Support\Money::formatoDecimal($mov->cantidad) }} {{ $mov->producto?->unidadAbrev() }}
                </span>
            </div>
        @empty
            <p class="text-sm text-ink-500">Sin movimientos aún. Registra una entrada para comenzar.</p>
        @endforelse
        <p class="mt-3 text-xs text-ink-500">Las existencias nunca se editan a mano: se registran entradas, consumos y ajustes que quedan auditados en el kardex de cada producto.</p>
    </div>

    @push('scripts')
    <script>
        (function () {
            const orden = document.querySelector('select[name="orden"]');
            const direccion = document.getElementById('direccion');
            if (!orden || !direccion) return;
            orden.addEventListener('change', function () {
                const dir = orden.options[orden.selectedIndex].dataset.dir;
                direccion.value = dir || (orden.value === 'stock' ? 'desc' : 'asc');
            });
        })();
    </script>
    @endpush
@endsection
