@extends('layouts.app')

@section('titulo', 'Nuevo usuario')

@section('contenido')
    <form method="POST" action="{{ route('usuarios.store') }}" class="card card-body max-w-xl space-y-4">
        @csrf
        @include('usuarios._formulario', ['usuario' => $usuario, 'crear' => true])
        <div class="flex gap-3">
            <button class="btn-primary">Crear usuario</button>
            <a href="{{ route('usuarios.index') }}" class="btn-ghost">Cancelar</a>
        </div>
    </form>
@endsection