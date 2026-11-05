@extends('layouts.app')

@section('titulo', 'Editar proveedor')
@section('subtitulo', $proveedor->nombre)

@section('contenido')
    <form method="POST" action="{{ route('proveedores.update', $proveedor) }}" class="card card-body max-w-3xl space-y-4">
        @csrf
        @method('PUT')
        @include('proveedores._formulario', ['proveedor' => $proveedor])
        <div class="flex gap-3">
            <button class="btn-primary">Guardar cambios</button>
            <a href="{{ route('proveedores.show', $proveedor) }}" class="btn-ghost">Cancelar</a>
        </div>
    </form>
@endsection
