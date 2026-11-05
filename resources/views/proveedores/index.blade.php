@extends('layouts.app')

@section('titulo', 'Proveedores')
@section('acciones')
    <a href="{{ route('proveedores.create') }}" class="btn-primary">Nuevo proveedor</a>
@endsection

@section('contenido')
    <form method="GET" class="card card-body mb-4 flex flex-wrap items-end gap-3">
        <div class="min-w-48 flex-1">
            <label class="label">Buscar</label>
            <input name="q" value="{{ request('q') }}" class="field" placeholder="Nombre o NIT">
        </div>
        <button class="btn-dark">Filtrar</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Nombre</th><th>NIT</th><th>Contacto</th><th>Teléfono</th><th>Compras</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse($proveedores as $proveedor)
                    <tr>
                        <td class="font-medium">{{ $proveedor->nombre }}</td>
                        <td>{{ $proveedor->nit ?? '—' }}</td>
                        <td>{{ $proveedor->contacto ?? '—' }}</td>
                        <td>{{ $proveedor->telefono ?? '—' }}</td>
                        <td>{{ $proveedor->compras_count }}</td>
                        <td><span class="badge {{ $proveedor->activo ? 'bg-green-100 text-green-700' : 'bg-cream-200 text-ink-500' }}">{{ $proveedor->activo ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="text-right"><a href="{{ route('proveedores.show', $proveedor) }}" class="text-blush-600 hover:underline">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-ink-500">No hay proveedores registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.paginacion', ['paginable' => $proveedores])
@endsection
