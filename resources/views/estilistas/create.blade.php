@extends('layouts.app')

@section('titulo', 'Nuevo profesional')

@section('contenido')
    <form method="POST" action="{{ route('estilistas.store') }}" class="card card-body max-w-3xl space-y-4">
        @csrf
        @include('estilistas._formulario', ['estilista' => new \App\Models\Estilista(['activo' => true, 'fecha_contratacion' => now()])])
        <div class="flex gap-3">
            <button class="btn-primary">Registrar</button>
            <a href="{{ route('estilistas.index') }}" class="btn-ghost">Cancelar</a>
        </div>
    </form>
@endsection
