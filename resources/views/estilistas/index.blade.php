@extends('layouts.app')

@section('titulo', 'Profesionales')
@section('subtitulo', 'Estilistas y personal del salón')
@section('acciones')
    <a href="{{ route('estilistas.create') }}" class="btn-primary">Nuevo profesional</a>
@endsection

@section('contenido')
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Nombre</th><th>Especialidad</th><th>Teléfono</th><th>Contratación</th><th>Registros</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse($estilistas as $estilista)
                    <tr>
                        <td class="font-medium">{{ $estilista->nombre }}</td>
                        <td>{{ $estilista->especialidad ?? '—' }}</td>
                        <td>{{ $estilista->telefono ?? '—' }}</td>
                        <td>{{ optional($estilista->fecha_contratacion)->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $estilista->consumos_count }}</td>
                        <td><span class="badge {{ $estilista->activo ? 'bg-green-100 text-green-700' : 'bg-cream-200 text-ink-500' }}">{{ $estilista->activo ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="text-right"><a href="{{ route('estilistas.show', $estilista) }}" class="text-blush-600 hover:underline">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-ink-500">No hay profesionales registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.paginacion', ['paginable' => $estilistas])
@endsection
