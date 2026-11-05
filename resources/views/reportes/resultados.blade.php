@extends('layouts.app')

@section('titulo', 'Estado de resultados')
@section('subtitulo', 'Del ' . \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y') . ' al ' . \Illuminate\Support\Carbon::parse($hasta)->format('d/m/Y'))

@section('contenido')
    <form method="GET" class="card card-body mb-4 flex flex-wrap items-end gap-3">
        <div><label class="label">Desde</label><input type="date" name="desde" value="{{ $desde }}" class="field"></div>
        <div><label class="label">Hasta</label><input type="date" name="hasta" value="{{ $hasta }}" class="field"></div>
        <button class="btn-dark">Consultar</button>
    </form>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat-card"><p class="text-sm text-ink-500">Ventas realizadas</p><p class="font-serif text-2xl font-semibold">{{ \App\Support\Money::formato($resultado['ventas']) }}</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Cobros recibidos</p><p class="font-serif text-2xl font-semibold">{{ \App\Support\Money::formato($resultado['cobros']) }}</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Compras del período</p><p class="font-serif text-2xl font-semibold">{{ \App\Support\Money::formato($resultado['compras']) }}</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Utilidad neta</p><p class="font-serif text-2xl font-semibold {{ \App\Support\Money::mayorQue($resultado['utilidad_neta'], '0') ? 'text-green-700' : 'text-red-600' }}">{{ \App\Support\Money::formato($resultado['utilidad_neta']) }}</p></div>
    </div>

    <div class="mt-6 card card-body max-w-2xl">
        <h2 class="mb-3 font-serif text-lg font-semibold">Detalle</h2>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-ink-500">Ventas realizadas</dt><dd>{{ \App\Support\Money::formato($resultado['ventas']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-ink-500">(-) Consumo de inventario</dt><dd>-{{ \App\Support\Money::formato($resultado['consumo_inventario']) }}</dd></div>
            <div class="flex justify-between border-t border-cream-200 pt-2 font-semibold"><dt>Ganancia bruta</dt><dd>{{ \App\Support\Money::formato($resultado['ganancia_bruta']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-ink-500">(-) Gastos pagados</dt><dd>-{{ \App\Support\Money::formato($resultado['gastos']) }}</dd></div>
            <div class="flex justify-between border-t border-cream-200 pt-2 text-base font-semibold"><dt>Utilidad neta</dt><dd class="{{ \App\Support\Money::mayorQue($resultado['utilidad_neta'], '0') ? 'text-green-700' : 'text-red-600' }}">{{ \App\Support\Money::formato($resultado['utilidad_neta']) }}</dd></div>
            <div class="flex justify-between text-xs text-ink-500"><dt>Margen sobre ventas</dt><dd>{{ \App\Support\Money::formatoDecimal($resultado['margen'], 1) }}%</dd></div>
        </dl>
        <p class="mt-4 text-xs text-ink-500">Nota: las compras aumentan el inventario y no se restan directamente; el costo se reconoce cuando el producto se consume (ventas o servicios).</p>
    </div>
@endsection
