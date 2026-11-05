@extends('layouts.app')

@section('titulo', 'Agenda y citas')
@section('acciones')
    <a href="{{ route('citas.create') }}" class="btn-primary">Nueva cita</a>
@endsection

@section('contenido')
    <form method="GET" class="card card-body mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="label">Vista</label>
            <select name="vista" class="field">
                <option value="dia" @selected($vista === 'dia')>Día</option>
                <option value="semana" @selected($vista === 'semana')>Semana</option>
                <option value="mes" @selected($vista === 'mes')>Mes</option>
                <option value="lista" @selected($vista === 'lista')>Lista</option>
            </select>
        </div>
        <div>
            <label class="label">Fecha</label>
            <input type="date" name="fecha" value="{{ $fecha->format('Y-m-d') }}" class="field">
        </div>
        <div>
            <label class="label">Profesional</label>
            <select name="estilista" class="field">
                <option value="">Todos</option>
                @foreach($estilistas as $e)
                    <option value="{{ $e->id }}" @selected((int) request('estilista') === $e->id)>{{ $e->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Estado</label>
            <select name="estado" class="field">
                <option value="">Todos</option>
                @foreach(['pendiente','confirmada','en_progreso','completada','cancelada'] as $est)
                    <option value="{{ $est }}" @selected(request('estado') === $est)>{{ ucfirst(str_replace('_', ' ', $est)) }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn-dark">Ver</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Fecha y hora</th><th>Cliente</th><th>Servicio</th><th>Profesional</th><th>Duración</th><th>Precio</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse($citas as $cita)
                    <tr>
                        <td class="whitespace-nowrap">{{ $cita->fecha_hora->format('d/m/Y H:i') }}</td>
                        <td class="font-medium">{{ $cita->cliente?->nombre ?? '—' }}</td>
                        <td>{{ $cita->servicio?->nombre ?? '—' }}</td>
                        <td>{{ $cita->estilista?->nombre ?? '—' }}</td>
                        <td>{{ $cita->duracion_minutos }} min</td>
                        <td>{{ \App\Support\Money::formato($cita->precio) }}</td>
                        <td>
                            @php($colores = ['pendiente' => 'bg-amber-100 text-amber-700', 'confirmada' => 'bg-blue-100 text-blue-700', 'en_progreso' => 'bg-indigo-100 text-indigo-700', 'completada' => 'bg-green-100 text-green-700', 'cancelada' => 'bg-red-100 text-red-700'])
                            <span class="badge {{ $colores[$cita->estado] ?? 'bg-cream-200 text-ink-500' }}">{{ $cita->estadoLabel() }}</span>
                        </td>
                        <td class="text-right"><a href="{{ route('citas.show', $cita) }}" class="text-blush-600 hover:underline">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-ink-500">No hay citas para el filtro seleccionado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.paginacion', ['paginable' => $citas])
@endsection
