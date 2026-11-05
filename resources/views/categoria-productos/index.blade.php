@extends('layouts.app')

@section('titulo', 'Categorías de productos')

@section('contenido')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="card card-body">
            <h2 class="mb-3 font-serif text-lg font-semibold">Nueva categoría</h2>
            <form method="POST" action="{{ route('categoria-productos.store') }}" class="space-y-3">
                @csrf
                <div><label class="label">Nombre</label><input name="nombre" class="field" required></div>
                <div><label class="label">Descripción</label><input name="descripcion" class="field"></div>
                <button class="btn-primary">Guardar</button>
            </form>
        </div>

        <div class="card card-body">
            <h2 class="mb-3 font-serif text-lg font-semibold">Existentes</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Nombre</th><th>Productos</th><th></th></tr></thead>
                    <tbody>
                        @forelse($categorias as $cat)
                            <tr>
                                <td>
                                    <form method="POST" action="{{ route('categoria-productos.update', $cat) }}" class="flex gap-2">
                                        @csrf @method('PUT')
                                        <input name="nombre" value="{{ $cat->nombre }}" class="field py-1">
                                        <button class="btn-ghost py-1">Guardar</button>
                                    </form>
                                </td>
                                <td>{{ $cat->productos_count }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('categoria-productos.destroy', $cat) }}" onsubmit="return confirm('¿Eliminar categoría?')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-600 hover:underline">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-ink-500">Sin categorías.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('partials.paginacion', ['paginable' => $categorias])
        </div>
    </div>
@endsection
