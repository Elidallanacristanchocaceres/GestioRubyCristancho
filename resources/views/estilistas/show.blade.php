@extends('layouts.app')

@section('titulo', $estilista->nombre)
@section('subtitulo', ($estilista->especialidad ?? 'Sin especialidad') . ' · ' . ($estilista->telefono ?? 'sin teléfono'))
@section('acciones')
    <a href="{{ route('estilistas.edit', $estilista) }}" class="btn-ghost">Editar</a>
@endsection

@section('contenido')
    <div class="card card-body">
        <h2 class="mb-3 font-serif text-lg font-semibold">Servicios registrados</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Fecha</th><th>Cliente</th><th>Detalle</th><th class="text-right">Cobrado</th><th class="text-right">Costo</th></tr></thead>
                <tbody>
                    @forelse($registros as $registro)
                        <tr>
                            <td class="whitespace-nowrap">{{ $registro->fecha->format('d/m/Y') }}</td>
                            <td>{{ $registro->cliente?->nombre ?? 'Sin cliente' }}</td>
                            <td>{{ $registro->descripcion ?? $registro->servicio?->nombre ?? '—' }}</td>
                            <td class="text-right">{{ \App\Support\Money::formato($registro->precio_cobrado) }}</td>
                            <td class="text-right">{{ \App\Support\Money::formato($registro->costo_total) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-ink-500">Sin registros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.paginacion', ['paginable' => $registros])
    </div>
@endsection
