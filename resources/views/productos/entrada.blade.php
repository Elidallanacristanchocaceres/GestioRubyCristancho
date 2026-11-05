@extends('layouts.app')

@section('titulo', 'Registrar entrada de inventario')
@section('subtitulo', 'Registra la mercancía recibida: se actualiza el stock y queda el historial')
@section('acciones')
    <a href="{{ route('productos.index') }}" class="btn-ghost">Volver al inventario</a>
@endsection

@section('contenido')
    <form method="POST" action="{{ route('productos.entrada.store') }}" class="card card-body max-w-3xl space-y-4">
        @csrf
        <div>
            <label class="label">Producto *</label>
            <select name="producto_id" id="producto_id" class="field" required>
                <option value="">Selecciona el producto…</option>
                @foreach($productos as $producto)
                    <option value="{{ $producto->id }}"
                            data-contenido="{{ ($producto->contenido_envase ?? 0) > 0 ? \App\Support\Money::formatoDecimal($producto->contenido_envase) : '' }}"
                            data-unidad="{{ $producto->unidadAbrev() }}"
                            @selected((int) old('producto_id', request('producto_id')) === $producto->id)>
                        {{ $producto->nombre }} — {{ $producto->categoria?->nombre ?? 'Sin categoría' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="label">Cantidad de envases recibidos *</label>
                <input id="cantidad_envases" name="cantidad_envases" type="number" step="1" min="1" value="{{ old('cantidad_envases') }}" class="field" required>
            </div>
            <div>
                <label class="label">Contenido de cada envase</label>
                <input id="contenido_envase" name="contenido_envase" type="number" step="0.001" min="0.001" value="{{ old('contenido_envase') }}" class="field" placeholder="Ej: 60, 300, 950">
                <p class="mt-1 text-xs text-ink-500">Se sugiere automáticamente según el producto; cámbialo solo si la presentación difiere.</p>
            </div>
        </div>

        <div class="rounded-xl border border-cream-200 bg-cream-50 p-4 text-sm">
            <div class="flex justify-between"><span class="text-ink-500">Cantidad total recibida</span><span id="preview_total" class="font-serif text-lg font-semibold">—</span></div>
            <div id="preview_unidad" class="flex justify-between text-xs text-ink-500"><span>Unidad de control</span><span>—</span></div>
            <p class="mt-1 text-xs text-ink-500">Ejemplo: 4 envases × 60 g cada uno = 240 g recibidos.</p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="label">Costo de compra total (opcional)</label>
                <input name="costo_total" type="number" step="0.01" min="0" value="{{ old('costo_total') }}" class="field" placeholder="Ej: 48000">
            </div>
            <div>
                <label class="label">Proveedor (opcional)</label>
                <select name="proveedor_id" class="field">
                    <option value="">—</option>
                    @foreach($proveedores as $prov)
                        <option value="{{ $prov->id }}" @selected((int) old('proveedor_id') === $prov->id)>{{ $prov->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Fecha de recepción</label>
                <input name="fecha_ingreso" type="date" value="{{ old('fecha_ingreso', now()->format('Y-m-d')) }}" class="field">
            </div>
        </div>

        <fieldset class="rounded-xl border border-cream-200 p-4">
            <legend class="px-1 text-sm font-semibold text-ink-700">Estado de los envases</legend>
            <label class="flex items-start gap-2 text-sm">
                <input type="radio" name="estado_envase" value="nuevo" class="mt-1" @checked(old('estado_envase', 'nuevo') === 'nuevo')>
                <span>
                    <span class="font-medium">Envases nuevos (sellados)</span>
                    <span class="block text-xs text-ink-500">Suman su contenido completo al stock.</span>
                </span>
            </label>
            <label class="mt-3 flex items-start gap-2 text-sm">
                <input type="radio" name="estado_envase" value="empezado" class="mt-1" @checked(old('estado_envase') === 'empezado')>
                <span>
                    <span class="font-medium">Envases ya empezados</span>
                    <span class="block text-xs text-ink-500">
                        Se registran pero NO suman stock: no se puede suponer cuánto queda dentro.
                        Quedan pendientes de medición y se activan al pesarlos.
                    </span>
                </span>
            </label>
        </fieldset>

        <div>
            <label class="label">Observaciones</label>
            <textarea name="observaciones" class="field" rows="2" placeholder="Ej: pedido #123, remisión…">{{ old('observaciones') }}</textarea>
        </div>

        <button class="btn-primary">Guardar entrada</button>
    </form>

    @push('scripts')
    <script>
        (function () {
            const producto = document.getElementById('producto_id');
            const envases = document.getElementById('cantidad_envases');
            const contenido = document.getElementById('contenido_envase');
            const total = document.getElementById('preview_total');
            const totalUnidad = document.getElementById('preview_unidad');

            function esPendiente() {
                const marcado = document.querySelector('input[name="estado_envase"]:checked');
                return marcado && marcado.value === 'empezado';
            }

            function actualizar() {
                const opt = producto.options[producto.selectedIndex];
                const sug = opt ? opt.dataset.contenido || '' : '';
                const unidad = opt ? opt.dataset.unidad || '' : '';
                if (sug && !contenido.value) contenido.value = sug;
                totalUnidad.querySelector('span:nth-child(2)').textContent = unidad || '—';
                const e = parseFloat(envases.value || 0);
                const c = parseFloat(contenido.value || 0);
                const t = e * c;

                if (esPendiente()) {
                    total.textContent = 'No suma stock';
                    return;
                }

                total.textContent = (e > 0 && c > 0) ? t.toLocaleString('es-CO') + ' ' + (unidad === 'und' ? '' : unidad) : '—';
            }

            producto.addEventListener('change', function () {
                contenido.value = '';
                actualizar();
            });
            envases.addEventListener('input', actualizar);
            contenido.addEventListener('input', actualizar);
            document.querySelectorAll('input[name="estado_envase"]').forEach(function (radio) {
                radio.addEventListener('change', actualizar);
            });
        })();
    </script>
    @endpush
@endsection