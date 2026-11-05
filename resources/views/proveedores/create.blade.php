@extends('layouts.app')

@section('titulo', 'Nuevo proveedor')

@section('contenido')
    <form method="POST" action="{{ route('proveedores.store') }}" class="card card-body max-w-3xl space-y-4">
        @csrf
        @include('proveedores._formulario', ['proveedor' => new \App\Models\Proveedor(['activo' => true])])
        <div class="flex gap-3">
            <button class="btn-primary">Registrar</button>
            <a href="{{ route('proveedores.index') }}" class="btn-ghost">Cancelar</a>
        </div>
    </form>
@endsection
