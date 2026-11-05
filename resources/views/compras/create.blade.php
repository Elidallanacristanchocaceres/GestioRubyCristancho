@extends('layouts.app')

@section('titulo', 'Nueva compra')
@section('subtitulo', 'Registra la entrada de productos; se crean lotes FIFO automáticamente')

@section('contenido')
    <form method="POST" action="{{ route('compras.store') }}" class="space-y-6" id="form-compra">
        @csrf
        <div class="card card-body grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="label">Proveedor</label>
                <select name="proveedor_id" class="field">
                    <option value="">—</option>
                    @foreach($proveedores as $prov)
                        <option value="{{ $prov->id }}" @selected((int) old('proveedor_id') === $prov->id)>{{ $prov->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="label">Número de factura</label><input name="numero_factura" value="{{ old('numero_factura') }}" class="field"></div>
            <div><label class="label">Fecha de compra *</label><input name="fecha_compra" type="date" value="{{ old('fecha_compra', now()->toDateString()) }}" class="field" required></div>
            <div><label class="label">Fecha de recepción</label><input name="fecha_recepcion" type="date" value="{{ old('fecha_recepcion', now()->toDateString()) }}" class="field"></div>
            <div><label class="label">Descuento</label><input name="descuento" type="number" step="0.01" min="0" value="{{ old('descuento', '0') }}" class="field"></div>
            <div>
                <label class="label">Categoría de gasto</label>
                <select name="categoria_gasto_id" class="field">
                    <option value="">Productos (por defecto)</option>
                    @foreach($categoriasGasto as $cat)<option value="{{ $cat->id }}">{{ $cat->nombre }}</option>@endforeach
                </select>
            </div>
        </div>

        <div class="card card-body">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-serif text-lg font-semibold">Productos comprados</h2>
                <button type="button" class="btn-ghost" onclick="agregarDetalle()">+ Añadir producto</button>
            </div>
            <div id="detalles" class="space-y-3"></div>
            <p id="detalles-vacio" class="text-sm text-ink-500">Agrega al menos un producto.</p>
        </div>

        <div class="card card-body grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="label">¿Registrar como gasto?</label>
                <select name="registrar_gasto" class="field"><option value="1">Sí</option><option value="0">No</option></select>
            </div>
            <div>
                <label class="label">Medio de pago (para gasto/pago)</label>
                <select name="metodo_pago_id" class="field">
                    <option value="">—</option>
                    @foreach($metodos as $m)<option value="{{ $m->id }}">{{ $m->nombre }}</option>@endforeach
                </select>
            </div>
            <div><label class="label">Monto pagado ahora</label><input name="monto_pagado" type="number" step="0.01" min="0" value="{{ old('monto_pagado', '0') }}" class="field"></div>
            <div><label class="label">Afectar caja</label><select name="afectar_caja" class="field"><option value="1">Sí</option><option value="0">No</option></select></div>
            <div class="sm:col-span-3"><label class="label">Notas</label><textarea name="notas" class="field" rows="2">{{ old('notas') }}</textarea></div>
        </div>

        <div class="flex items-center justify-between card card-body">
            <div class="text-sm"><span class="text-ink-500">Subtotal estimado:</span> <span id="total-estimado" class="font-serif text-xl font-semibold">$0</span></div>
            <div class="flex gap-3">
                <a href="{{ route('compras.index') }}" class="btn-ghost">Cancelar</a>
                <button class="btn-primary">Registrar compra</button>
            </div>
        </div>
    </form>

    <template id="tpl-detalle">
        <div class="detalle rounded-xl border border-cream-200 p-3">
            <div class="flex flex-wrap items-end gap-2">
                <div class="min-w-56 flex-1">
                    <label class="label">Producto</label>
                    <select class="field sel-producto" onchange="onProducto(this)">
                        <option value="">Seleccione…</option>
                        @foreach($productos as $prod)
                            <option value="{{ $prod->id }}" data-unidad="{{ $prod->unidad_control }}" data-contenido="{{ $prod->contenido_envase }}" data-costo="{{ $prod->precio_costo }}">{{ $prod->nombre }} ({{ $prod->unidadAbrev() }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-28"><label class="label">Envases</label><input type="number" step="0.001" min="0.001" class="field in-envases" value="1" oninput="recalcular()"></div>
                <div class="w-28"><label class="label">Contenido/env</label><input type="number" step="0.001" min="0" class="field in-contenido" value="1" oninput="recalcular()"></div>
                <div class="w-28"><label class="label">Precio/env</label><input type="number" step="0.01" min="0" class="field in-precio" value="0" oninput="recalcular()"></div>
                <div class="w-24"><label class="label">Desc.</label><input type="number" step="0.01" min="0" class="field in-descuento" value="0" oninput="recalcular()"></div>
                <button type="button" class="btn-ghost" onclick="this.closest('.detalle').remove(); recalcular()">Quitar</button>
            </div>
        </div>
    </template>
@endsection

@push('scripts')
<script>
    function agregarDetalle() {
        document.getElementById('detalles').appendChild(document.getElementById('tpl-detalle').content.cloneNode(true));
        actualizarIndices();
    }
    function onProducto(sel) {
        const opt = sel.selectedOptions[0];
        const det = sel.closest('.detalle');
        if (opt?.dataset.costo && parseFloat(opt.dataset.costo) > 0) det.querySelector('.in-precio').value = opt.dataset.costo;
        if (opt?.dataset.contenido && parseFloat(opt.dataset.contenido) > 0) det.querySelector('.in-contenido').value = opt.dataset.contenido;
        recalcular();
    }
    function actualizarIndices() {
        document.querySelectorAll('#detalles .detalle').forEach((det, i) => {
            det.querySelector('.sel-producto').name = `detalles[${i}][producto_id]`;
            det.querySelector('.in-envases').name = `detalles[${i}][cantidad_envases]`;
            det.querySelector('.in-contenido').name = `detalles[${i}][contenido_envase]`;
            det.querySelector('.in-precio').name = `detalles[${i}][precio_unitario]`;
            det.querySelector('.in-descuento').name = `detalles[${i}][descuento]`;
        });
        document.getElementById('detalles-vacio').style.display = document.querySelectorAll('#detalles .detalle').length ? 'none' : 'block';
    }
    function recalcular() {
        let total = 0;
        document.querySelectorAll('#detalles .detalle').forEach(det => {
            const env = parseFloat(det.querySelector('.in-envases')?.value || 0);
            const precio = parseFloat(det.querySelector('.in-precio')?.value || 0);
            const desc = parseFloat(det.querySelector('.in-descuento')?.value || 0);
            total += env * precio - desc;
        });
        total -= parseFloat(document.querySelector('input[name=descuento]')?.value || 0);
        document.getElementById('total-estimado').textContent = '$' + Math.max(0, total).toLocaleString('es-CO', { maximumFractionDigits: 0 });
    }
    document.getElementById('form-compra').addEventListener('submit', actualizarIndices);
    window.addEventListener('DOMContentLoaded', agregarDetalle);
</script>
@endpush
