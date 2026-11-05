@extends('layouts.app')

@section('titulo', 'Servicios')
@section('subtitulo', 'Catálogo de servicios con receta técnica de productos')
@section('acciones')
    <a href="{{ route('categoria-servicios.index') }}" class="btn-ghost">Categorías</a>
    <a href="{{ route('servicios.create') }}" class="btn-primary">Nuevo servicio</a>
@endsection

@section('contenido')
    <form method="GET" class="card card-body mb-4 flex flex-wrap items-end gap-3">
        <div class="min-w-48 flex-1">
            <label class="label">Buscar</label>
            <input name="q" value="{{ request('q') }}" class="field">
        </div>
        <div>
            <label class="label">Categoría</label>
            <select name="categoria" class="field">
                <option value="">Todas</option>
                @foreach($categorias as $cat)
                    <option value="{{ $cat->id }}" @selected((int) request('categoria') === $cat->id)>{{ $cat->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Estado</label>
            <select name="estado" class="field">
                <option value="">Todos</option>
                <option value="activos" @selected(request('estado') === 'activos')>Activos</option>
                <option value="inactivos" @selected(request('estado') === 'inactivos')>Inactivos</option>
            </select>
        </div>
        <button class="btn-dark">Filtrar</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Servicio</th><th>Categoría</th><th>Duración</th><th>Precio</th><th>Costo estimado</th><th>Margen</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse($servicios as $servicio)
                    <tr>
                        <td class="font-medium">{{ $servicio->nombre }}</td>
                        <td>{{ $servicio->categoria?->nombre ?? '—' }}</td>
                        <td>{{ $servicio->duracion_minutos }} min</td>
                        <td>{{ \App\Support\Money::formato($servicio->precio) }}</td>
                        <td>{{ \App\Support\Money::formato($servicio->costoTotalEstimado()) }}</td>
                        <td>{{ \App\Support\Money::formato($servicio->margenEstimado()) }}</td>
                        <td><span class="badge {{ $servicio->activo ? 'bg-green-100 text-green-700' : 'bg-cream-200 text-ink-500' }}">{{ $servicio->activo ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="text-right"><a href="{{ route('servicios.show', $servicio) }}" class="text-blush-600 hover:underline">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-ink-500">No hay servicios registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.paginacion', ['paginable' => $servicios])
@endsection
