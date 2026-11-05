@extends('layouts.app')

@section('titulo', 'Revisión de precios')
@section('subtitulo', 'Productos que necesitan confirmación antes de cobrar o costear')
@section('acciones')
    <a href="{{ route('productos.index') }}" class="btn-ghost">Volver al inventario</a>
@endsection

@section('contenido')
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <p class="font-semibold">Estas inconsistencias NO se corrigen solas.</p>
        <p class="mt-1">
            Se comparan el contenido del envase × el costo por unidad con el precio de venta.
            La diferencia puede ser un margen comercial legítimo o un dato mal cargado:
            revisa cada caso y corrige el que corresponda desde la ficha del producto.
        </p>
    </div>

    {{-- Inconsistencias matemáticas --}}
    <div class="card card-body mb-6">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-serif text-lg font-semibold">Diferencia entre costo y precio de venta</h2>
            <span class="text-xs text-ink-500">{{ $productos->count() }} producto(s)</span>
        </div>

        @if($productos->isEmpty())
            <p class="text-sm text-ink-500">No hay inconsistencias: todos los precios cuadran con su costo.</p>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th class="text-right">Contenido</th>
                            <th class="text-right">Costo/unidad</th>
                            <th class="text-right">Costo del envase</th>
                            <th class="text-right">Precio de venta</th>
                            <th class="text-right">Diferencia</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($productos as $producto)
                            @php
                                $esperado = (float) $producto->contenido_envase * (float) $producto->costo_unitario;
                                $diferencia = (float) $producto->precio_venta - $esperado;
                                $porcentaje = $esperado > 0 ? ($diferencia / $esperado) * 100 : 0;
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('productos.show', $producto) }}" class="font-medium text-ink-900 hover:text-blush-600">{{ $producto->nombre }}</a>
                                    <span class="block text-xs text-ink-500">{{ $producto->presentacionLabel() }}</span>
                                </td>
                                <td class="text-xs text-ink-600">{{ $producto->categoria?->nombre ?? '—' }}</td>
                                <td class="text-right text-xs">{{ \App\Support\Money::formatoDecimal($producto->contenido_envase) }} {{ $producto->unidadAbrev() }}</td>
                                <td class="text-right text-xs">{{ \App\Support\Money::formatoDecimal($producto->costo_unitario, 4) }}</td>
                                <td class="text-right text-xs">{{ \App\Support\Money::formato($esperado) }}</td>
                                <td class="text-right text-xs font-medium">{{ \App\Support\Money::formato($producto->precio_venta) }}</td>
                                <td class="text-right text-xs">
                                    <span class="font-semibold {{ $diferencia < 0 ? 'text-red-600' : 'text-amber-700' }}">
                                        {{ $diferencia > 0 ? '+' : '' }}{{ \App\Support\Money::formato($diferencia) }}
                                    </span>
                                    <span class="block text-ink-400">{{ number_format($porcentaje, 1) }}%</span>
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('productos.edit', $producto) }}" class="btn-link">Revisar</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Productos marcados para revisión --}}
    <div class="card card-body">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-serif text-lg font-semibold">Datos por confirmar</h2>
            <span class="text-xs text-ink-500">{{ $pendientes->count() }} producto(s)</span>
        </div>

        @if($pendientes->isEmpty())
            <p class="text-sm text-ink-500">No hay productos marcados para revisión.</p>
        @else
            <div class="space-y-3">
                @foreach($pendientes as $producto)
                    <div class="rounded border border-cream-200 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <a href="{{ route('productos.show', $producto) }}" class="font-medium text-ink-900 hover:text-blush-600">{{ $producto->nombre }}</a>
                                <p class="text-xs text-ink-500">
                                    {{ $producto->categoria?->nombre ?? 'Sin categoría' }} ·
                                    {{ $producto->presentacionLabel() }} ·
                                    Venta {{ $producto->precio_venta ? \App\Support\Money::formato($producto->precio_venta) : '—' }}
                                </p>
                            </div>
                            <a href="{{ route('productos.edit', $producto) }}" class="btn-link">Abrir ficha</a>
                        </div>
                        @if($producto->descripcion)
                            <p class="mt-2 whitespace-pre-line text-xs text-ink-600">{{ $producto->descripcion }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
