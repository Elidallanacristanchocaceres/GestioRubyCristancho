@extends('layouts.app')

@section('titulo', 'Nueva cita')

@section('contenido')
    <form method="POST" action="{{ route('citas.store') }}" class="card card-body max-w-3xl space-y-4">
        @csrf
        @include('citas._formulario', ['cita' => $cita])
        <div class="flex gap-3">
            <button class="btn-primary">Agendar cita</button>
            <a href="{{ route('citas.index') }}" class="btn-ghost">Cancelar</a>
        </div>
    </form>
@endsection
