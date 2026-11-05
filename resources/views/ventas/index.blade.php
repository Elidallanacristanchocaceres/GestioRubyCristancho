@extends('layouts.app')

@section('titulo', 'Ventas')
@section('acciones')
    <a href="{{ route('ventas.create') }}" class="btn-primary">Nueva venta</a>
@endsection

@section('contenido')
    <form method="GET" class="card card-body mb-4 flex flex-wrap items-end gap-3">
        <div class="min-w-40 flex-1">
            <label class="label">Número</label>
            <input name="q" value="{{ request('q') }}" class="field" placeholder="V-...">
        </div>
        <div><label class="label">Desde</label><input type="date" name="desde" value="{{ request('desde') }}" class="field"></div>
        <div><label class="label">Hasta</label><input type="date" name="hasta" value="{{ request('hasta') }}" class="field"></div>
        <div>
            <label class="label">Estado</label>
            <select name="estado" class="field">
                <option value="">Todos</option>
                @foreach(['abierta','abonada','pagada','anulada'] as $est)
                    <option value="{{ $est }}" @selected(request('estado') === $est)>{{ ucfirst($est) }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn-dark">Filtrar</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Número</th><th>Fecha</th><th>Cliente</th><th>Total</th><th>Pagado</th><th>Saldo</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse($ventas as $venta)
                    <tr>
                        <td class="font-mono text-xs">{{ $venta->numero }}</td>
                        <td>{{ $venta->fecha_hora->format('d/m/Y H:i') }}</td>
                        <td>{{ $venta->cliente?->nombre ?? 'Consumidor final' }}</td>
                        <td>{{ \App\Support\Money::formato($venta->total) }}</td>
                        <td>{{ \App\Support\Money::formato($venta->total_pagado) }}</td>
                        <td>{{ \App\Support\Money::formato($venta->saldo_pendiente) }}</td>
                        <td>
                            @php($colores = ['pagada' => 'bg-green-100 text-green-700', 'abonada' => 'bg-amber-100 text-amber-700', 'abierta' => 'bg-blue-100 text-blue-700', 'anulada' => 'bg-red-100 text-red-700'])
                            <span class="badge {{ $colores[$venta->estado] ?? 'bg-cream-200 text-ink-500' }}">{{ ucfirst($venta->estado) }}</span>
                        </td>
                        <td class="text-right"><a href="{{ route('ventas.show', $venta) }}" class="text-blush-600 hover:underline">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-ink-500">No hay ventas registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.paginacion', ['paginable' => $ventas])
@endsection
