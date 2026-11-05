@extends('layouts.app')

@section('titulo', 'Editar servicio')
@section('subtitulo', $servicio->nombre)

@section('contenido')
    <form method="POST" action="{{ route('servicios.update', $servicio) }}" class="card card-body max-w-3xl space-y-4">
        @csrf
        @method('PUT')
        @include('servicios._formulario', ['servicio' => $servicio])
        <div class="flex gap-3">
            <button class="btn-primary">Guardar cambios</button>
            <a href="{{ route('servicios.show', $servicio) }}" class="btn-ghost">Cancelar</a>
        </div>
    </form>
@endsection
