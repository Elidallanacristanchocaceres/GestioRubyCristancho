@extends('layouts.app')

@section('titulo', 'Consumo y servicios')
@section('subtitulo', 'Del ' . \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y') . ' al ' . \Illuminate\Support\Carbon::parse($hasta)->format('d/m/Y'))

@section('contenido')
    <form method="GET" class="card card-body mb-4 flex flex-wrap items-end gap-3">
        <div><label class="label">Desde</label><input type="date" name="desde" value="{{ $desde }}" class="field"></div>
        <div><label class="label">Hasta</label><input type="date" name="hasta" value="{{ $hasta }}" class="field"></div>
        <button class="btn-dark">Consultar</button>
    </form>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="card card-body">
            <h2 class="mb-3 font-serif text-lg font-semibold">Productos más utilizados</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Producto</th><th>Cantidad</th><th class="text-right">Costo</th></tr></thead>
                    <tbody>
                        @forelse($productos as $fila)
                            <tr>
                                <td>{{ $fila->producto?->nombre ?? '—' }}</td>
                                <td>{{ \App\Support\Money::formatoDecimal($fila->total_cantidad) }}</td>
                                <td class="text-right">{{ \App\Support\Money::formato($fila->total_costo) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-ink-500">Sin datos en el período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-body">
            <h2 class="mb-3 font-serif text-lg font-semibold">Servicios más vendidos</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Servicio</th><th>Veces</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                        @forelse($servicios as $fila)
                            <tr>
                                <td>{{ $fila->servicio?->nombre ?? '—' }}</td>
                                <td>{{ (int) $fila->veces }}</td>
                                <td class="text-right">{{ \App\Support\Money::formato($fila->total) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-ink-500">Sin datos en el período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
