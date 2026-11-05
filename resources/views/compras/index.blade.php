@extends('layouts.app')

@section('titulo', 'Compras')
@section('subtitulo', 'Ingreso de productos al inventario por lotes')
@section('acciones')
    <a href="{{ route('compras.create') }}" class="btn-primary">Nueva compra</a>
@endsection

@section('contenido')
    <form method="GET" class="card card-body mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="label">Proveedor</label>
            <select name="proveedor" class="field">
                <option value="">Todos</option>
                @foreach($proveedores as $prov)
                    <option value="{{ $prov->id }}" @selected((int) request('proveedor') === $prov->id)>{{ $prov->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="label">Desde</label><input type="date" name="desde" value="{{ request('desde') }}" class="field"></div>
        <div><label class="label">Hasta</label><input type="date" name="hasta" value="{{ request('hasta') }}" class="field"></div>
        <button class="btn-dark">Filtrar</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Factura</th><th>Proveedor</th><th>Fecha</th><th>Productos</th><th>Total</th><th>Pagado</th><th>Saldo</th><th></th></tr></thead>
            <tbody>
                @forelse($compras as $compra)
                    <tr>
                        <td class="font-mono text-xs">{{ $compra->numero_factura ?? ('#' . $compra->id) }}</td>
                        <td>{{ $compra->proveedor?->nombre ?? '—' }}</td>
                        <td>{{ optional($compra->fecha_compra)->format('d/m/Y') }}</td>
                        <td>{{ $compra->detalles_count }}</td>
                        <td>{{ \App\Support\Money::formato($compra->total) }}</td>
                        <td>{{ \App\Support\Money::formato($compra->total_pagado) }}</td>
                        <td>{{ \App\Support\Money::formato($compra->saldo_pendiente) }}</td>
                        <td class="text-right"><a href="{{ route('compras.show', $compra) }}" class="text-blush-600 hover:underline">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-ink-500">No hay compras registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.paginacion', ['paginable' => $compras])
@endsection
