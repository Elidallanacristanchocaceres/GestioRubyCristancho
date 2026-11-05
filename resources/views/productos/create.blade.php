@extends('layouts.app')

@section('titulo', 'Nuevo producto')
@section('subtitulo', 'Registra un producto de inventario; el stock se maneja por movimientos')

@section('contenido')
    <form method="POST" action="{{ route('productos.store') }}" class="card card-body max-w-4xl space-y-4">
        @csrf
        @include('productos._formulario', ['producto' => new \App\Models\Producto(['activo' => true, 'unidad_control' => 'unidad', 'tipo_uso' => 'profesional']), 'esEdicion' => false])
        <div class="flex gap-3">
            <button class="btn-primary">Crear producto</button>
            <a href="{{ route('productos.index') }}" class="btn-ghost">Cancelar</a>
        </div>
    </form>
@endsection
