@extends('layouts.app')

@section('titulo', 'Editar usuario')
@section('subtitulo', $usuario->name)

@section('contenido')
    <form method="POST" action="{{ route('usuarios.update', $usuario) }}" class="card card-body max-w-xl space-y-4">
        @csrf
        @method('PUT')
        @include('usuarios._formulario', ['usuario' => $usuario, 'crear' => false])
        <div class="flex gap-3">
            <button class="btn-primary">Guardar cambios</button>
            <a href="{{ route('usuarios.index') }}" class="btn-ghost">Cancelar</a>
        </div>
    </form>
@endsection