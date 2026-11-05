@extends('layouts.app')

@section('titulo', 'Configuración')
@section('subtitulo', 'Parámetros, categorías, medios de pago y datos del negocio')

@section('contenido')
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="card card-body">
            <h2 class="mb-3 font-serif text-lg font-semibold">Datos del negocio</h2>
            <form method="POST" action="{{ route('configuracion.guardar') }}" class="space-y-3">
                @csrf
                @foreach($configuraciones['general'] ?? collect() as $config)
                    <div>
                        <label class="label">{{ $config->etiqueta ?? ucfirst($config->clave) }}</label>
                        <input name="config[{{ $config->clave }}]" value="{{ old('config.' . $config->clave, $config->valor) }}" class="field">
                    </div>
                @endforeach
                <button class="btn-primary">Guardar</button>
            </form>
        </div>

        <div class="card card-body">
            <h2 class="mb-3 font-serif text-lg font-semibold">Medios de pago</h2>
            <form method="POST" action="{{ route('configuracion.metodo') }}" class="mb-4 space-y-3 rounded-xl border border-cream-200 p-3">
                @csrf
                <div><label class="label">Nombre</label><input name="nombre" class="field" placeholder="Ej: Efectivo, Tarjeta, Transferencia" required></div>
                <div><label class="label">Descripción</label><input name="descripcion" class="field"></div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="es_efectivo" value="1" class="rounded border-cream-300"> Es efectivo (suma al saldo base de caja)</label>
                <button class="btn-dark">Añadir medio</button>
            </form>
            <div class="flex flex-wrap gap-2">
                @foreach($metodos as $m)
                    <span class="badge bg-cream-200 text-ink-700">{{ $m->nombre }} @if($m->esEfectivo())(efectivo)@endif</span>
                @endforeach
            </div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="card card-body">
            <h2 class="mb-3 font-serif text-lg font-semibold">Categorías de productos</h2>
            <p class="mb-2 text-sm text-ink-500">Administrarlas en el módulo de inventario.</p>
            <div class="flex flex-wrap gap-2">
                @foreach($categoriasProducto as $cat)<span class="badge bg-blush-50 text-blush-600">{{ $cat->nombre }}</span>@endforeach
            </div>
            <form method="POST" action="{{ route('configuracion.categoria') }}" class="mt-4 space-y-2">
                @csrf
                <input type="hidden" name="tipo" value="producto">
                <input name="nombre" class="field" placeholder="Nueva categoría" required>
                <button class="btn-ghost">Añadir</button>
            </form>
        </div>

        <div class="card card-body">
            <h2 class="mb-3 font-serif text-lg font-semibold">Categorías de servicios</h2>
            <p class="mb-2 text-sm text-ink-500">Administrarlas en el módulo de servicios.</p>
            <div class="flex flex-wrap gap-2">
                @foreach($categoriasServicio as $cat)<span class="badge bg-blush-50 text-blush-600">{{ $cat->nombre }}</span>@endforeach
            </div>
            <form method="POST" action="{{ route('configuracion.categoria') }}" class="mt-4 space-y-2">
                @csrf
                <input type="hidden" name="tipo" value="servicio">
                <input name="nombre" class="field" placeholder="Nueva categoría" required>
                <button class="btn-ghost">Añadir</button>
            </form>
        </div>

        <div class="card card-body">
            <h2 class="mb-3 font-serif text-lg font-semibold">Categorías de gastos</h2>
            <div class="flex flex-wrap gap-2">
                @foreach($categoriasGasto as $cat)<span class="badge bg-blush-50 text-blush-600">{{ $cat->nombre }}</span>@endforeach
            </div>
            <form method="POST" action="{{ route('configuracion.categoria') }}" class="mt-4 space-y-2">
                @csrf
                <input type="hidden" name="tipo" value="gasto">
                <input name="nombre" class="field" placeholder="Ej: Arriendo, Servicios públicos, Papelería" required>
                <button class="btn-ghost">Añadir</button>
            </form>
        </div>
    </div>
@endsection