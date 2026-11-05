@extends('layouts.app')

@section('titulo', 'Clientes')
@section('subtitulo', 'Registro de clientes del salón')
@section('acciones')
    <a href="{{ route('clientes.create') }}" class="btn-primary">Nuevo cliente</a>
@endsection

@section('contenido')
    <form method="GET" class="card card-body mb-4 flex flex-wrap items-end gap-3">
        <div class="min-w-48 flex-1">
            <label class="label">Buscar</label>
            <input name="q" value="{{ request('q') }}" class="field" placeholder="Nombre, celular o correo">
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
            <thead>
                <tr>
                    <th>Nombre</th><th>Celular</th><th>Correo</th><th>Visitas</th><th>Saldo</th><th>Estado</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($clientes as $cliente)
                    <tr>
                        <td class="font-medium">{{ $cliente->nombre }}</td>
                        <td>{{ $cliente->celular }}</td>
                        <td>{{ $cliente->email ?? '—' }}</td>
                        <td>{{ $cliente->pagosVisitas() }}</td>
                        <td>{{ \App\Support\Money::formato($cliente->saldo_pendiente) }}</td>
                        <td>
                            <span class="badge {{ $cliente->activo ? 'bg-green-100 text-green-700' : 'bg-cream-200 text-ink-500' }}">
                                {{ $cliente->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="text-right">
                            <a href="{{ route('clientes.show', $cliente) }}" class="text-blush-600 hover:underline">Ver</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-ink-500">No hay clientes registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.paginacion', ['paginable' => $clientes])
@endsection
