@extends('layouts.app')

@section('titulo', 'Editar gasto')

@section('contenido')
    <form method="POST" action="{{ route('gastos.update', $gasto) }}" class="card card-body max-w-3xl space-y-4">
        @csrf
        @method('PUT')
        @include('gastos._formulario', ['gasto' => $gasto])
        <div class="flex gap-3">
            <button class="btn-primary">Guardar cambios</button>
            <a href="{{ route('gastos.show', $gasto) }}" class="btn-ghost">Cancelar</a>
        </div>
    </form>
@endsection
