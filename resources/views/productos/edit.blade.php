@extends('layouts.app')

@section('titulo', 'Editar producto')
@section('subtitulo', $producto->nombre)

@section('contenido')
    <form method="POST" action="{{ route('productos.update', $producto) }}" class="card card-body max-w-4xl space-y-4">
        @csrf
        @method('PUT')
        @include('productos._formulario', ['producto' => $producto, 'esEdicion' => true])
        <div class="flex gap-3">
            <button class="btn-primary">Guardar cambios</button>
            <a href="{{ route('productos.show', $producto) }}" class="btn-ghost">Cancelar</a>
        </div>
    </form>
@endsection
