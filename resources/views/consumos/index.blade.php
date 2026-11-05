@extends('layouts.app')

@section('titulo', 'Registro de productos')
@section('subtitulo', 'Consumo de productos por gramos, organizado por categoría')
@section('acciones')
    <a href="{{ route('consumos.create') }}" class="btn-primary">Nuevo registro</a>
    <a href="{{ route('atenciones.index') }}" class="btn-ghost">Registro de clientes</a>
    <a href="{{ route('productos.index') }}" class="btn-ghost">Inventario</a>
@endsection

@section('contenido')
    <div class="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-6">
        <div class="stat-card"><p class="text-sm text-ink-500">Registros</p><p class="font-serif text-2xl font-semibold">{{ $totalRegistros }}</p><p class="text-xs text-ink-500">{{ \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y') }} a {{ \Illuminate\Support\Carbon::parse($hasta)->format('d/m/Y') }}</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Tintes (gramos)</p><p class="font-serif text-2xl font-semibold">{{ \App\Support\Money::formatoDecimal($resumenDiario['totales_por_categoria']['tintes'] ?? '0', 3) }} g</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Tratamientos (gramos)</p><p class="font-serif text-2xl font-semibold">{{ \App\Support\Money::formatoDecimal($resumenDiario['totales_por_categoria']['tratamientos'] ?? '0', 3) }} g</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Coactivadores de agua (gramos)</p><p class="font-serif text-2xl font-semibold">{{ \App\Support\Money::formatoDecimal($resumenDiario['totales_por_categoria']['coactivadores'] ?? '0', 3) }} g</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Decolorantes (gramos)</p><p class="font-serif text-2xl font-semibold">{{ \App\Support\Money::formatoDecimal($resumenDiario['totales_por_categoria']['decolorantes'] ?? '0', 3) }} g</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Costo total / Ingresos</p><p class="font-serif text-xl font-semibold">{{ \App\Support\Money::formato($resumenDiario['total_costo']) }}</p><p class="text-xs text-ink-500">{{ \App\Support\Money::formato($totalIngresos) }}</p></div>
    </div>

    <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
        @foreach($resumenDiario['grupos'] as $grupo)
            <div class="card card-body">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <h3 class="font-serif text-lg font-semibold">{{ $grupo['etiqueta'] }}</h3>
                        <p class="text-xs text-ink-500">{{ $grupo['resumen'] }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold">{{ \App\Support\Money::formatoDecimal($grupo['gramos'], 3) }} g</p>
                        <p class="text-sm text-ink-600">{{ \App\Support\Money::formato($grupo['costo']) }}</p>
                    </div>
                </div>
                @if(!empty($grupo['productos']))
                    <table class="table mt-2">
                        <tbody>
                            @foreach($grupo['productos'] as $prod)
                                <tr>
                                    <td class="truncate">{{ $prod['nombre'] }}</td>
                                    <td class="text-right whitespace-nowrap">{{ \App\Support\Money::formatoDecimal($prod['gramos'], 3) }} g</td>
                                    <td class="text-right whitespace-nowrap">{{ \App\Support\Money::formato($prod['costo']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-sm text-ink-500 mt-2">Sin registros en esta categoría</p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12 xl:col-span-8">
            <form method="GET" class="card card-body mb-4 flex flex-wrap items-end gap-2">
                <div>
                    <label class="label">Período</label>
                    <select name="periodo" class="field">
                        <option value="dia" @selected($periodo === 'dia')>Día</option>
                        <option value="semana" @selected($periodo === 'semana')>Semana</option>
                        <option value="mes" @selected($periodo === 'mes')>Mes</option>
                        <option value="rango" @selected($periodo === 'rango')>Rango</option>
                        <option value="todo" @selected($periodo === 'todo')>Todos los registros</option>
                    </select>
                </div>
                <div>
                    <label class="label">Desde</label>
                    <input type="date" name="desde" value="{{ request('desde', $desde) }}" class="field">
                </div>
                <div>
                    <label class="label">Hasta</label>
                    <input type="date" name="hasta" value="{{ request('hasta', $hasta) }}" class="field">
                </div>
                <button class="btn-primary">Filtrar</button>
                <a href="{{ route('consumos.index') }}" class="btn-ghost">Hoy</a>
            </form>

            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-cream-200 px-4 py-3">
                    <div>
                        <h2 class="font-serif text-lg font-semibold">Registros guardados</h2>
                        <p class="text-xs text-ink-500">
                            @if($periodo === 'todo')
                                Mostrando todos los registros del salón
                            @else
                                Del {{ \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($hasta)->format('d/m/Y') }}
                            @endif
                        </p>
                    </div>
                    @if($periodo !== 'todo' && $totalRegistros === 0 && $hayRegistrosTotales)
                        <a href="{{ route('consumos.index', ['periodo' => 'todo']) }}" class="btn-primary text-sm">Ver todos los registros guardados</a>
                    @endif
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Qué se hizo</th>
                            <th>Gramos</th>
                            <th class="text-right">Costo total</th>
                            <th class="text-right">Cobrado</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($consumos as $consumo)
                            <tr>
                                <td class="whitespace-nowrap">{{ $consumo->fecha->format('d/m/Y') }}</td>
                                <td>
                                    <span class="font-medium">{{ $consumo->descripcion ?? $consumo->servicio?->nombre ?? 'Sin detalle' }}</span>
                                    <span class="block text-xs text-ink-500">
                                        @if($consumo->cliente)
                                            {{ $consumo->cliente->nombre }}
                                        @endif
                                        @if($consumo->estilista)
                                            · {{ $consumo->estilista->nombre }}
                                        @endif
                                        @if(! $consumo->cliente && ! $consumo->estilista)
                                            Sin cliente asociado
                                        @endif
                                    </span>
                                </td>
                                <td>{{ \App\Support\Money::formatoDecimal($consumo->gramos_count, 3) }} g</td>
                                <td class="text-right">{{ \App\Support\Money::formato($consumo->costo_total) }}</td>
                                <td class="text-right font-medium">{{ \App\Support\Money::formato($consumo->precio_cobrado) }}</td>
                                <td>
                                    @if($consumo->esActivo())
                                        <span class="badge bg-green-100 text-green-700">Activo</span>
                                    @else
                                        <span class="badge bg-ink-100 text-ink-600">Anulado</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    <a href="{{ route('consumos.show', $consumo) }}" class="text-sm text-blush-600 hover:underline">Ver</a>
                                    @if($consumo->esActivo())
                                        <a href="{{ route('consumos.edit', $consumo) }}" class="ml-2 text-sm text-ink-600 hover:underline">Editar</a>
                                        <form method="POST" action="{{ route('consumos.destroy', $consumo) }}" class="inline" onsubmit="return confirm('¿Eliminar este registro? Se borrará y el inventario volverá a sus lotes.');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="ml-2 text-sm text-red-600 hover:underline">Eliminar</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-ink-500">
                                    <p>No hay registros guardados en este período.</p>
                                    <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
                                        @if($periodo !== 'todo' && $hayRegistrosTotales)
                                            <a href="{{ route('consumos.index', ['periodo' => 'todo']) }}" class="btn-ghost text-sm">Ver todos los registros guardados</a>
                                        @endif
                                        <a href="{{ route('consumos.create') }}" class="btn-primary text-sm">Registrar productos</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $consumos->links() }}</div>
        </div>

        <div class="col-span-12 space-y-4 xl:col-span-4">
            <div class="card card-body">
                <h2 class="mb-3 font-serif text-lg font-semibold">Por reponer</h2>
                @forelse($porReponer as $p)
                    <div class="flex items-center justify-between gap-2 border-b border-cream-100 py-2 text-sm last:border-0">
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ $p->nombre }}</p>
                            <p class="text-xs text-ink-500">mínimo {{ \App\Support\Money::formatoDecimal($p->stock_minimo) }}</p>
                        </div>
                        <span class="{{ $p->estaAgotado() ? 'font-semibold text-red-600' : 'font-semibold text-amber-600' }}">{{ \App\Support\Money::formatoDecimal($p->stock) }} {{ $p->unidadAbrev() }}</span>
                    </div>
                @empty
                    <p class="text-sm text-ink-500">Ningún producto por debajo del mínimo.</p>
                @endforelse
            </div>
            <p class="text-xs text-ink-500">Todo se registra en <strong>gramos</strong>. Los tintes usan $450/g (regla fija del salón). Tratamientos y coactivadores usan su costo ya configurado. El inventario se descuenta FIFO.</p>
        </div>
    </div>
@endsection
