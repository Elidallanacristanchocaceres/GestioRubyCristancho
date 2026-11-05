@extends('layouts.app')

@section('titulo', 'Permisos por rol')
@section('subtitulo', 'Qué módulos pueden usar los empleados e invitados')
@section('acciones')
    <a href="{{ route('usuarios.index') }}" class="btn-ghost">Usuarios</a>
@endsection

@section('contenido')
    <form method="POST" action="{{ route('usuarios.permisos.guardar') }}" class="card card-body">
        @csrf
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Permiso</th>
                        @foreach($roles as $role)
                            <th class="text-center">{{ ucfirst($role) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($permisos as $grupo => $lista)
                        <tr><td colspan="3" class="bg-cream-100 font-semibold text-ink-700">{{ ucfirst($grupo) }}</td></tr>
                        @foreach($lista as $permiso)
                            <tr>
                                <td>
                                    <span class="font-medium">{{ $permiso->nombre }}</span>
                                    <span class="block text-xs text-ink-500">{{ $permiso->descripcion }}</span>
                                </td>
                                @foreach($roles as $role)
                                    @php($permitido = $matriz->has($role) && (bool) $matriz[$role]->firstWhere('permiso_id', $permiso->id)?->permitido)
                                    <td class="text-center">
                                        <input type="checkbox"
                                               name="permisos[{{ $role }}][]"
                                               value="{{ $permiso->id }}"
                                               class="rounded border-cream-300"
                                               @checked($permitido)>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-sm text-ink-500">El rol administrador siempre tiene acceso completo. Los permisos marcados se suman por defecto para cada rol.</p>
        <button class="btn-primary mt-3">Guardar permisos</button>
    </form>
@endsection