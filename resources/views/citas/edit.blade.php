@extends('layouts.app')

@section('titulo', 'Editar cita')
@section('subtitulo', $cita->cliente?->nombre)

@section('contenido')
    <form method="POST" action="{{ route('citas.update', $cita) }}" class="card card-body max-w-3xl space-y-4">
        @csrf
        @method('PUT')
        @include('citas._formulario', ['cita' => $cita])
        <div class="flex gap-3">
            <button class="btn-primary">Guardar cambios</button>
            <a href="{{ route('citas.show', $cita) }}" class="btn-ghost">Cancelar</a>
        </div>
    </form>
@endsection
