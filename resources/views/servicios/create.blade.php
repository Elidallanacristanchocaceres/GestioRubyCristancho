@extends('layouts.app')

@section('titulo', 'Nuevo servicio')

@section('contenido')
    <form method="POST" action="{{ route('servicios.store') }}" class="card card-body max-w-3xl space-y-4">
        @csrf
        @include('servicios._formulario', ['servicio' => new \App\Models\Servicio(['activo' => true, 'duracion_minutos' => 60])])
        <div class="flex gap-3">
            <button class="btn-primary">Crear servicio</button>
            <a href="{{ route('servicios.index') }}" class="btn-ghost">Cancelar</a>
        </div>
    </form>
@endsection
