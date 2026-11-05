@extends('layouts.app')

@section('titulo', 'Editar cliente')

@section('contenido')
    <form method="POST" action="{{ route('clientes.update', $cliente) }}" class="card card-body max-w-3xl space-y-4">
        @csrf
        @method('PUT')
        @include('clientes._formulario', ['cliente' => $cliente])
        <div class="flex gap-3">
            <button class="btn-primary">Guardar cambios</button>
            <a href="{{ route('clientes.show', $cliente) }}" class="btn-ghost">Cancelar</a>
        </div>
    </form>
@endsection
