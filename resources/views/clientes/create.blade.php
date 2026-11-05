@extends('layouts.app')

@section('titulo', $cliente->exists ? 'Editar cliente' : 'Nuevo cliente')

@section('contenido')
    <form method="POST" action="{{ $cliente->exists ? route('clientes.update', $cliente) : route('clientes.store') }}" class="card card-body max-w-3xl space-y-4">
        @csrf
        @if($cliente->exists) @method('PUT') @endif

        @include('clientes._formulario', ['cliente' => $cliente])

        <div class="flex gap-3">
            <button class="btn-primary">{{ $cliente->exists ? 'Guardar cambios' : 'Registrar cliente' }}</button>
            <a href="{{ route('clientes.index') }}" class="btn-ghost">Cancelar</a>
        </div>
    </form>
@endsection
