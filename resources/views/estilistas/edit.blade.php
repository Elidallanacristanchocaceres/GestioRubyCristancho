@extends('layouts.app')

@section('titulo', 'Editar profesional')
@section('subtitulo', $estilista->nombre)

@section('contenido')
    <form method="POST" action="{{ route('estilistas.update', $estilista) }}" class="card card-body max-w-3xl space-y-4">
        @csrf
        @method('PUT')
        @include('estilistas._formulario', ['estilista' => $estilista])
        <div class="flex gap-3">
            <button class="btn-primary">Guardar cambios</button>
            <a href="{{ route('estilistas.show', $estilista) }}" class="btn-ghost">Cancelar</a>
        </div>
    </form>
@endsection
