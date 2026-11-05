@extends('layouts.app')

@section('titulo', $servicio->nombre)
@section('subtitulo', ($servicio->categoria?->nombre ?? 'Sin categoría') . ' · ' . $servicio->duracion_minutos . ' min')
@section('acciones')
    <a href="{{ route('servicios.edit', $servicio) }}" class="btn-ghost">Editar</a>
    <a href="{{ route('consumos.create', ['servicio' => $servicio->id]) }}" class="btn-primary">Registrar consumo</a>
@endsection

@section('contenido')
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="stat-card"><p class="text-sm text-ink-500">Precio</p><p class="font-serif text-2xl font-semibold">{{ \App\Support\Money::formato($servicio->precio) }}</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Costo estimado de productos</p><p class="font-serif text-2xl font-semibold">{{ \App\Support\Money::formato($servicio->costoProductosEstimado()) }}</p><p class="text-xs text-ink-500">Mano de obra: {{ \App\Support\Money::formato($servicio->costo_mano_obra) }}</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Margen estimado</p><p class="font-serif text-2xl font-semibold text-green-700">{{ \App\Support\Money::formato($servicio->margenEstimado()) }}</p></div>
    </div>

    <div class="mt-6 card card-body">
        <h2 class="mb-3 font-serif text-lg font-semibold">Receta técnica del servicio</h2>
        <p class="mb-4 text-sm text-ink-500">Productos y cantidades sugeridas que se consumen al realizar este servicio. La cantidad real se puede ajustar al momento de la venta.</p>
        <form method="POST" action="{{ route('servicios.receta', $servicio) }}" class="space-y-3">
            @csrf
            <div id="receta-filas" class="space-y-2">
                @forelse($servicio->productos as $item)
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="min-w-56 flex-1">
                            <label class="label">Producto</label>
                            <select name="productos[{{ $loop->index }}][producto_id]" class="field">
                                <option value="">—</option>
                                @foreach($productos as $prod)
                                    <option value="{{ $prod->id }}" @selected($item->producto_id === $prod->id)>{{ $prod->nombre }} ({{ $prod->unidadAbrev() }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-40">
                            <label class="label">Cantidad sugerida</label>
                            <input name="productos[{{ $loop->index }}][cantidad_sugerida]" type="number" step="0.001" min="0.001" value="{{ $item->cantidad_sugerida }}" class="field">
                        </div>
                        <button type="button" class="btn-ghost" onclick="this.closest('div.flex').remove()">Quitar</button>
                    </div>
                @empty
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="min-w-56 flex-1">
                            <label class="label">Producto</label>
                            <select name="productos[0][producto_id]" class="field">
                                <option value="">—</option>
                                @foreach($productos as $prod)
                                    <option value="{{ $prod->id }}">{{ $prod->nombre }} ({{ $prod->unidadAbrev() }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-40">
                            <label class="label">Cantidad sugerida</label>
                            <input name="productos[0][cantidad_sugerida]" type="number" step="0.001" min="0.001" class="field">
                        </div>
                        <button type="button" class="btn-ghost" onclick="this.closest('div.flex').remove()">Quitar</button>
                    </div>
                @endforelse
            </div>
            <button class="btn-primary">Guardar receta</button>
        </form>
    </div>
@endsection
