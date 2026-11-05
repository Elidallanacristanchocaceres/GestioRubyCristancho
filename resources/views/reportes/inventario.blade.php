@extends('layouts.app')

@section('titulo', 'Inventario valorizado')
@section('subtitulo', 'Valor total: ' . \App\Support\Money::formato($inventario['total']))

@section('contenido')
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Producto</th><th>Categoría</th><th>Stock</th><th>Costo unitario</th><th class="text-right">Valor</th></tr></thead>
            <tbody>
                @forelse($inventario['items'] as $item)
                    <tr>
                        <td class="font-medium">{{ $item['producto']->nombre }}</td>
                        <td>{{ $item['producto']->categoria?->nombre ?? '—' }}</td>
                        <td>{{ \App\Support\Money::formatoDecimal($item['stock']) }} {{ $item['producto']->unidadAbrev() }}</td>
                        <td>{{ \App\Support\Money::formatoDecimal($item['costo_unitario'], 4) }}</td>
                        <td class="text-right font-medium">{{ \App\Support\Money::formato($item['valor']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-ink-500">Sin productos.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr><td colspan="4" class="text-right font-semibold">Total</td><td class="text-right font-semibold">{{ \App\Support\Money::formato($inventario['total']) }}</td></tr>
            </tfoot>
        </table>
    </div>
@endsection
