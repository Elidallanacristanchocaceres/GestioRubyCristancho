@extends('layouts.app')

@section('titulo', $proveedor->nombre)
@section('subtitulo', 'NIT: ' . ($proveedor->nit ?? '—'))
@section('acciones')
    <a href="{{ route('proveedores.edit', $proveedor) }}" class="btn-ghost">Editar</a>
    <a href="{{ route('compras.create') }}" class="btn-primary">Registrar compra</a>
@endsection

@section('contenido')
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="stat-card"><p class="text-sm text-ink-500">Total comprado</p><p class="font-serif text-2xl font-semibold">{{ \App\Support\Money::formato($totalComprado) }}</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Saldo por pagar</p><p class="font-serif text-2xl font-semibold {{ \App\Support\Money::mayorQue($saldoPendiente, '0') ? 'text-red-600' : '' }}">{{ \App\Support\Money::formato($saldoPendiente) }}</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Contacto</p><p class="font-serif text-lg font-semibold">{{ $proveedor->contacto ?? '—' }}</p><p class="text-xs text-ink-500">{{ $proveedor->telefono }}</p></div>
    </div>

    <div class="mt-6 card card-body">
        <h2 class="mb-3 font-serif text-lg font-semibold">Compras</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Factura</th><th>Fecha</th><th>Total</th><th>Pagado</th><th>Saldo</th><th></th></tr></thead>
                <tbody>
                    @forelse($compras as $compra)
                        <tr>
                            <td>{{ $compra->numero_factura ?? ('#' . $compra->id) }}</td>
                            <td>{{ optional($compra->fecha_compra)->format('d/m/Y') }}</td>
                            <td>{{ \App\Support\Money::formato($compra->total) }}</td>
                            <td>{{ \App\Support\Money::formato($compra->total_pagado) }}</td>
                            <td>{{ \App\Support\Money::formato($compra->saldo_pendiente) }}</td>
                            <td class="text-right"><a href="{{ route('compras.show', $compra) }}" class="text-blush-600 hover:underline">Ver</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-ink-500">Sin compras registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.paginacion', ['paginable' => $compras])
    </div>
@endsection
