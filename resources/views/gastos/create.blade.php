@extends('layouts.app')

@section('titulo', 'Nuevo gasto')

@section('contenido')
    <form method="POST" action="{{ route('gastos.store') }}" class="card card-body max-w-3xl space-y-4">
        @csrf
        @include('gastos._formulario', ['gasto' => new \App\Models\Gasto(['fecha' => now(), 'estado' => 'pagado'])])
        <div class="flex gap-3">
            <button class="btn-primary">Registrar gasto</button>
            <a href="{{ route('gastos.index') }}" class="btn-ghost">Cancelar</a>
        </div>
    </form>
@endsection
