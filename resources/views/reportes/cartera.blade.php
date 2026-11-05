@extends('layouts.app')

@section('titulo', 'Cartera')
@section('subtitulo', 'Cuentas por cobrar y por pagar')

@section('contenido')
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-6">
        <div class="stat-card"><p class="text-sm text-ink-500">Total por cobrar</p><p class="font-serif text-2xl font-semibold text-amber-700">{{ \App\Support\Money::formato($totalCobrar) }}</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Total por pagar</p><p class="font-serif text-2xl font-semibold text-red-600">{{ \App\Support\Money::formato($totalPagar) }}</p></div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="card card-body">
            <h2 class="mb-3 font-serif text-lg font-semibold">Por cobrar (ventas)</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Venta</th><th>Cliente</th><th>Total</th><th>Saldo</th><th></th></tr></thead>
                    <tbody>
                        @forelse($porCobrar as $venta)
                            <tr>
                                <td class="font-mono text-xs">{{ $venta->numero }}</td>
                                <td>{{ $venta->cliente?->nombre ?? 'Consumidor final' }}</td>
                                <td>{{ \App\Support\Money::formato($venta->total) }}</td>
                                <td class="font-medium text-amber-700">{{ \App\Support\Money::formato($venta->saldo_pendiente) }}</td>
                                <td class="text-right"><a href="{{ route('ventas.show', $venta) }}" class="text-blush-600 hover:underline">Ver</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-ink-500">Sin saldos pendientes.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-body">
            <h2 class="mb-3 font-serif text-lg font-semibold">Por pagar (compras)</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Compra</th><th>Proveedor</th><th>Total</th><th>Saldo</th><th></th></tr></thead>
                    <tbody>
                        @forelse($porPagar as $compra)
                            <tr>
                                <td class="font-mono text-xs">{{ $compra->numero_factura ?? ('#' . $compra->id) }}</td>
                                <td>{{ $compra->proveedor?->nombre ?? '—' }}</td>
                                <td>{{ \App\Support\Money::formato($compra->total) }}</td>
                                <td class="font-medium text-red-600">{{ \App\Support\Money::formato($compra->saldo_pendiente) }}</td>
                                <td class="text-right"><a href="{{ route('compras.show', $compra) }}" class="text-blush-600 hover:underline">Ver</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-ink-500">Sin saldos pendientes.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
