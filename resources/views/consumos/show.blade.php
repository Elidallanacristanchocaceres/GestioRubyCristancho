@extends('layouts.app')

@section('titulo', 'Registro #' . $consumo->id)
@section('subtitulo', $consumo->fecha->format('d/m/Y') . ' · ' . ($consumo->descripcion ?? 'Sin detalle'))
@section('acciones')
    <a href="{{ route('consumos.index') }}" class="btn-ghost">Volver</a>
    @if($consumo->esActivo())
        <a href="{{ route('consumos.edit', $consumo) }}" class="btn-primary">Editar</a>
        <form method="POST" action="{{ route('consumos.anular', $consumo) }}" onsubmit="return confirm('¿Anular este registro? El inventario se devolverá a sus lotes.');">
            @csrf
            <button class="btn-ghost text-amber-600">Anular</button>
        </form>
    @endif
    <form method="POST" action="{{ route('consumos.destroy', $consumo) }}" onsubmit="return confirm('¿Eliminar definitivamente este registro? Se devolverá el inventario.');">
        @csrf
        @method('DELETE')
        <button class="btn-ghost text-red-600">Eliminar</button>
    </form>
@endsection

@section('contenido')
    @php
        $lineas = $consumo->detalles->load('producto.categoria');
        $resumenDiario = \App\Services\RegistroDiarioService::resumen(
            $consumo->fecha->toDateString(),
            $consumo->fecha->toDateString()
        );
        $ingresos = \App\Support\Money::normal($consumo->precio_cobrado ?? 0);
        $costo = \App\Support\Money::normal($consumo->costo_total);
        $diferencia = \App\Support\Money::sub($consumo->precio_cobrado ?? 0, $consumo->costo_total);
    @endphp

    <div class="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-6">
        <div class="stat-card"><p class="text-sm text-ink-500">Fecha</p><p class="font-serif text-2xl font-semibold">{{ $consumo->fecha->format('d/m/Y') }}</p><p class="text-xs text-ink-500">{{ $consumo->usuario?->name ?? 'Registrado por usuario' }}</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Tintes (gramos)</p><p class="font-serif text-2xl font-semibold">{{ \App\Support\Money::formatoDecimal($resumenDiario['totales_por_categoria']['tintes'] ?? '0', 3) }} g</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Tratamientos (gramos)</p><p class="font-serif text-2xl font-semibold">{{ \App\Support\Money::formatoDecimal($resumenDiario['totales_por_categoria']['tratamientos'] ?? '0', 3) }} g</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Coactivadores de agua (gramos)</p><p class="font-serif text-2xl font-semibold">{{ \App\Support\Money::formatoDecimal($resumenDiario['totales_por_categoria']['coactivadores'] ?? '0', 3) }} g</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Decolorantes (gramos)</p><p class="font-serif text-2xl font-semibold">{{ \App\Support\Money::formatoDecimal($resumenDiario['totales_por_categoria']['decolorantes'] ?? '0', 3) }} g</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Ingresos − costo</p><p class="font-serif text-2xl font-semibold {{ \App\Support\Money::esNegativo($diferencia) ? 'text-red-600' : 'text-green-700' }}">{{ \App\Support\Money::formato($diferencia) }}</p><p class="text-xs text-ink-500">Costo: {{ \App\Support\Money::formato($costo) }} · Cobrado: {{ \App\Support\Money::formato($ingresos) }}</p></div>
    </div>

    <div class="card card-body mb-4">
        <h2 class="mb-3 font-serif text-lg font-semibold">Datos del registro</h2>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2"><p class="text-xs uppercase tracking-wide text-ink-500">Qué se hizo</p><p class="font-medium">{{ $consumo->descripcion ?? '—' }}</p></div>
            <div><p class="text-xs uppercase tracking-wide text-ink-500">Cobrado</p><p class="font-medium">{{ \App\Support\Money::formato($consumo->precio_cobrado) }}</p></div>
            <div><p class="text-xs uppercase tracking-wide text-ink-500">Estado</p><p class="font-medium">{{ $consumo->estadoLabel() }}</p></div>
            @if($consumo->cliente || $consumo->servicio || $consumo->estilista)
                <div class="sm:col-span-4">
                    <p class="text-xs uppercase tracking-wide text-ink-500">Asociado en su momento</p>
                    <p class="font-medium text-sm">
                        @if($consumo->cliente)
                            Cliente: {{ $consumo->cliente->nombre }}
                        @endif
                        @if($consumo->servicio)
                            · Servicio: {{ $consumo->servicio->nombre }}
                        @endif
                        @if($consumo->estilista)
                            · Profesional: {{ $consumo->estilista->nombre }}
                        @endif
                    </p>
                </div>
            @endif
            @if($consumo->observaciones)
                <div class="sm:col-span-4"><p class="text-xs uppercase tracking-wide text-ink-500">Observaciones</p><p class="font-medium">{{ $consumo->observaciones }}</p></div>
            @endif
        </div>
    </div>

    <div class="card card-body mb-4">
        <h2 class="mb-3 font-serif text-lg font-semibold">Resumen por categoría</h2>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            @foreach($resumenDiario['grupos'] as $grupo)
                <div class="stat-card">
                    <p class="text-sm text-ink-500">{{ $grupo['etiqueta'] }}</p>
                    <p class="font-serif text-xl font-semibold">{{ \App\Support\Money::formatoDecimal($grupo['gramos'], 3) }} g</p>
                    <p class="text-xs text-ink-500">{{ \App\Support\Money::formato($grupo['costo']) }}</p>
                    @if(!empty($grupo['productos']))
                        <ul class="mt-2 space-y-1 text-xs text-ink-600">
                            @foreach($grupo['productos'] as $prod)
                                <li class="flex justify-between gap-2">
                                    <span class="truncate">{{ $prod['nombre'] }}</span>
                                    <span>{{ \App\Support\Money::formatoDecimal($prod['gramos'], 3) }} g</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="card card-body">
        <h2 class="mb-3 font-serif text-lg font-semibold">Productos utilizados por gramos</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Categoría</th>
                        <th>Producto</th>
                        <th>Gramos usados</th>
                        <th>Costo por gramo</th>
                        <th class="text-right">Costo línea</th>
                        <th>Notas</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lineas as $linea)
                        @php $p = $linea->producto; $grupo = \App\Services\RegistroDiarioService::grupoDe($p); @endphp
                        <tr>
                            <td>{{ \App\Services\RegistroDiarioService::etiqueta($grupo) }}</td>
                            <td class="font-medium">{{ $p?->nombre ?? 'Producto #' . $linea->producto_id }}</td>
                            <td>{{ \App\Support\Money::formatoDecimal($linea->cantidad, 3) }} g</td>
                            <td>{{ \App\Support\Money::formatoDecimal($linea->costo_unitario, 6) }} / g</td>
                            <td class="text-right font-semibold">{{ \App\Support\Money::formato($linea->costo_total) }}</td>
                            <td>{{ $linea->notas ?? '—' }}</td>
                            <td>
                                @if($linea->estado === 'activo')
                                    <span class="badge bg-green-100 text-green-700">Activo</span>
                                @else
                                    <span class="badge bg-ink-100 text-ink-600">Reversado</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-ink-500">Sin líneas.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-right font-semibold">Costo total de productos</td>
                        <td colspan="3" class="text-right font-serif text-lg font-semibold">{{ \App\Support\Money::formato($costo) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
