@extends('layouts.app')

@section('titulo', 'Reportes')
@section('subtitulo', 'Indicadores de gestión del salón')

@section('contenido')
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <a href="{{ route('reportes.resultados') }}" class="card card-body transition hover:shadow-md">
            <h2 class="font-serif text-lg font-semibold">Estado de resultados</h2>
            <p class="mt-1 text-sm text-ink-500">Ventas, consumo de inventario, gastos y utilidad por período.</p>
        </a>
        <a href="{{ route('reportes.inventario') }}" class="card card-body transition hover:shadow-md">
            <h2 class="font-serif text-lg font-semibold">Inventario valorizado</h2>
            <p class="mt-1 text-sm text-ink-500">Valor del inventario actual a costo unitario.</p>
        </a>
        <a href="{{ route('reportes.consumo') }}" class="card card-body transition hover:shadow-md">
            <h2 class="font-serif text-lg font-semibold">Consumo y servicios</h2>
            <p class="mt-1 text-sm text-ink-500">Productos más usados y servicios más vendidos.</p>
        </a>
        <a href="{{ route('reportes.cartera') }}" class="card card-body transition hover:shadow-md">
            <h2 class="font-serif text-lg font-semibold">Cartera</h2>
            <p class="mt-1 text-sm text-ink-500">Cuentas por cobrar de clientes y por pagar a proveedores.</p>
        </a>
    </div>
@endsection
