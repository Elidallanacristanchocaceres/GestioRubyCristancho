@extends('layouts.app')

@section('titulo', 'Usuarios')
@section('acciones')
    <a href="{{ route('usuarios.permisos') }}" class="btn-ghost">Permisos por rol</a>
    <a href="{{ route('usuarios.create') }}" class="btn-primary">Nuevo usuario</a>
@endsection

@section('contenido')
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Nombre</th><th>Cédula</th><th>Correo</th><th>Rol</th><th></th></tr></thead>
            <tbody>
                @forelse($usuarios as $usuario)
                    <tr>
                        <td class="font-medium">{{ $usuario->name }}</td>
                        <td>{{ $usuario->cedula }}</td>
                        <td>{{ $usuario->email ?? '—' }}</td>
                        <td><span class="badge {{ $usuario->esAdmin() ? 'bg-gold-100 text-gold-700' : 'bg-cream-200 text-ink-700' }}">{{ $usuario->rolLabel() }}</span></td>
                        <td class="text-right">
                            <div class="flex justify-end gap-3">
                                <a href="{{ route('usuarios.edit', $usuario) }}" class="text-blush-600 hover:underline">Editar</a>
                                @if(auth()->id() !== $usuario->id)
                                    <form method="POST" action="{{ route('usuarios.destroy', $usuario) }}" onsubmit="return confirm('¿Eliminar usuario?')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-600 hover:underline">Eliminar</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-ink-500">No hay usuarios.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.paginacion', ['paginable' => $usuarios])
@endsection