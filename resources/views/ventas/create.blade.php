@extends('layouts.app')

@section('titulo', 'Nueva venta')
@section('subtitulo', $cita ? 'Venta asociada a la cita de ' . $cita->cliente?->nombre : 'Registra servicios y productos vendidos')

@section('contenido')
    <form method="POST" action="{{ route('ventas.store') }}" class="space-y-6" id="form-venta">
        @csrf
        @if($cita)
            <input type="hidden" name="cita_id" value="{{ $cita->id }}">
        @endif

        <div class="card card-body grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="label">Cliente</label>
                <select name="cliente_id" class="field">
                    <option value="">Consumidor final</option>
                    @foreach($clientes as $cli)
                        <option value="{{ $cli->id }}" @selected((int) old('cliente_id', $cita?->cliente_id) === $cli->id)>{{ $cli->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Profesional</label>
                <select name="estilista_id" class="field">
                    <option value="">—</option>
                    @foreach($estilistas as $e)
                        <option value="{{ $e->id }}" @selected((int) old('estilista_id', $cita?->estilista_id) === $e->id)>{{ $e->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Descuento general</label>
                <input name="descuento" type="number" step="0.01" min="0" value="{{ old('descuento', '0') }}" class="field">
            </div>
        </div>

        <div class="card card-body">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-serif text-lg font-semibold">Conceptos</h2>
                <div class="flex gap-2">
                    <button type="button" class="btn-ghost" onclick="agregarServicio()">+ Servicio</button>
                    <button type="button" class="btn-ghost" onclick="agregarProducto()">+ Producto</button>
                </div>
            </div>
            <div id="detalles" class="space-y-3"></div>
            <p id="detalles-vacio" class="text-sm text-ink-500">Agrega al menos un servicio o producto.</p>
        </div>

        <div class="card card-body">
            <h2 class="mb-3 font-serif text-lg font-semibold">Pago (opcional: puedes dejarlo para después)</h2>
            <div id="pagos" class="space-y-2"></div>
            <button type="button" class="btn-ghost mt-2" onclick="agregarPago()">+ Añadir medio de pago</button>
        </div>

        <div class="flex items-center justify-between card card-body">
            <div class="text-sm">
                <span class="text-ink-500">Total estimado:</span>
                <span id="total-estimado" class="font-serif text-xl font-semibold">$0</span>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('ventas.index') }}" class="btn-ghost">Cancelar</a>
                <button class="btn-primary">Registrar venta</button>
            </div>
        </div>
    </form>

    <template id="tpl-servicio">
        <div class="detalle rounded-xl border border-cream-200 p-3" data-tipo="servicio">
            <input type="hidden" name="__tipo" value="servicio">
            <div class="flex flex-wrap items-end gap-2">
                <div class="min-w-56 flex-1">
                    <label class="label">Servicio</label>
                    <select class="field sel-servicio" onchange="onServicio(this)">
                        <option value="">Seleccione…</option>
                        @foreach($servicios as $srv)
                            <option value="{{ $srv->id }}" data-precio="{{ $srv->precio }}" data-productos="{{ json_encode($srv->productos->map(fn($p) => ['id' => $p->producto_id, 'nombre' => $p->producto?->nombre, 'cantidad' => $p->cantidad_sugerida, 'unidad' => $p->producto?->unidadAbrev(), 'stock' => $p->producto?->stock])) }}">{{ $srv->nombre }} — {{ \App\Support\Money::formato($srv->precio) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-28"><label class="label">Precio</label><input type="number" step="0.01" class="field in-precio" value="0" oninput="recalcular()"></div>
                <div class="w-24"><label class="label">Desc.</label><input type="number" step="0.01" class="field in-descuento" value="0" oninput="recalcular()"></div>
                <button type="button" class="btn-ghost" onclick="this.closest('.detalle').remove(); recalcular()">Quitar</button>
            </div>
            <div class="consumos mt-2 hidden rounded-lg bg-cream-50 p-2">
                <p class="mb-1 text-xs font-semibold text-ink-500">Productos a consumir (ajusta lo realmente usado):</p>
                <div class="filas-consumo space-y-1"></div>
            </div>
        </div>
    </template>

    <template id="tpl-producto">
        <div class="detalle rounded-xl border border-cream-200 p-3" data-tipo="producto">
            <input type="hidden" name="__tipo" value="producto">
            <div class="flex flex-wrap items-end gap-2">
                <div class="min-w-56 flex-1">
                    <label class="label">Producto</label>
                    <select class="field sel-producto" onchange="onProducto(this)">
                        <option value="">Seleccione…</option>
                        @foreach($productos as $prod)
                            <option value="{{ $prod->id }}" data-precio="{{ $prod->precio_venta }}" data-stock="{{ $prod->stock }}" data-unidad="{{ $prod->unidadAbrev() }}">{{ $prod->nombre }} ({{ \App\Support\Money::formato($prod->precio_venta) }}) · stock {{ \App\Support\Money::formatoDecimal($prod->stock) }} {{ $prod->unidadAbrev() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-24"><label class="label">Cantidad</label><input type="number" step="0.001" min="0.001" class="field in-cantidad" value="1" oninput="recalcular()"></div>
                <div class="w-28"><label class="label">Precio</label><input type="number" step="0.01" class="field in-precio" value="0" oninput="recalcular()"></div>
                <div class="w-24"><label class="label">Desc.</label><input type="number" step="0.01" class="field in-descuento" value="0" oninput="recalcular()"></div>
                <button type="button" class="btn-ghost" onclick="this.closest('.detalle').remove(); recalcular()">Quitar</button>
            </div>
        </div>
    </template>
@endsection

@push('scripts')
<script>
    const metodos = @json($metodos->map(fn ($m) => ['id' => $m->id, 'nombre' => $m->nombre]));

    function agregarServicio() {
        document.getElementById('detalles').appendChild(document.getElementById('tpl-servicio').content.cloneNode(true));
        actualizarIndices();
    }
    function agregarProducto() {
        document.getElementById('detalles').appendChild(document.getElementById('tpl-producto').content.cloneNode(true));
        actualizarIndices();
        recalcular();
    }
    function agregarPago() {
        const wrap = document.getElementById('pagos');
        const div = document.createElement('div');
        div.className = 'flex flex-wrap items-end gap-2';
        div.innerHTML = `
            <div class="min-w-48 flex-1"><label class="label">Medio de pago</label>
                <select class="field p-metodo">${metodos.map(m => `<option value="${m.id}">${m.nombre}</option>`).join('')}</select></div>
            <div class="w-40"><label class="label">Monto</label><input type="number" step="0.01" min="0" class="field p-monto" value="0"></div>
            <div class="w-48"><label class="label">Referencia</label><input class="field p-referencia"></div>
            <button type="button" class="btn-ghost" onclick="this.parentElement.remove()">Quitar</button>`;
        wrap.appendChild(div);
    }
    function onServicio(sel) {
        const opt = sel.selectedOptions[0];
        const detalle = sel.closest('.detalle');
        detalle.querySelector('.in-precio').value = opt?.dataset.precio ?? 0;
        const consumos = JSON.parse(opt?.dataset.productos || '[]');
        const wrap = detalle.querySelector('.consumos');
        const filas = detalle.querySelector('.filas-consumo');
        filas.innerHTML = '';
        if (consumos.length) {
            wrap.classList.remove('hidden');
            consumos.forEach((c, i) => {
                filas.insertAdjacentHTML('beforeend', `
                    <div class="flex items-center gap-2 text-sm">
                        <input type="hidden" class="c-producto" value="${c.id}">
                        <span class="flex-1">${c.nombre} <span class="text-xs text-ink-500">(stock ${c.stock} ${c.unidad})</span></span>
                        <input type="number" step="0.001" min="0" class="field c-cantidad py-1 w-28" value="${c.cantidad}">
                        <span class="text-xs text-ink-500">${c.unidad}</span>
                    </div>`);
            });
        } else {
            wrap.classList.add('hidden');
        }
        recalcular();
    }
    function onProducto(sel) {
        const opt = sel.selectedOptions[0];
        const detalle = sel.closest('.detalle');
        detalle.querySelector('.in-precio').value = opt?.dataset.precio ?? 0;
        recalcular();
    }
    function actualizarIndices() {
        document.querySelectorAll('#detalles .detalle').forEach((det, i) => {
            det.querySelectorAll('[name]').forEach(el => {
                if (el.name === '__tipo') {
                    el.name = `detalles[${i}][tipo]`;
                }
            });
            const sel = det.querySelector('.sel-servicio, .sel-producto');
            if (det.dataset.tipo === 'servicio') {
                det.querySelector('.sel-servicio').name = `detalles[${i}][servicio_id]`;
                det.querySelector('.in-precio').name = `detalles[${i}][precio_unitario]`;
                det.querySelector('.in-descuento').name = `detalles[${i}][descuento]`;
                det.querySelectorAll('.filas-consumo > div').forEach((fila, j) => {
                    fila.querySelector('.c-producto').name = `consumos[${j}][producto_id]`;
                    fila.querySelector('.c-cantidad').name = `consumos[${j}][cantidad]`;
                });
            } else {
                det.querySelector('.sel-producto').name = `detalles[${i}][producto_id]`;
                det.querySelector('.in-cantidad').name = `detalles[${i}][cantidad]`;
                det.querySelector('.in-precio').name = `detalles[${i}][precio_unitario]`;
                det.querySelector('.in-descuento').name = `detalles[${i}][descuento]`;
            }
        });
        document.getElementById('detalles-vacio').style.display =
            document.querySelectorAll('#detalles .detalle').length ? 'none' : 'block';
    }
    function recalcular() {
        let total = 0;
        document.querySelectorAll('#detalles .detalle').forEach(det => {
            const precio = parseFloat(det.querySelector('.in-precio')?.value || 0);
            const cant = parseFloat(det.querySelector('.in-cantidad')?.value || 1);
            const desc = parseFloat(det.querySelector('.in-descuento')?.value || 0);
            total += (precio * (det.dataset.tipo === 'producto' ? cant : 1)) - desc;
        });
        const descGeneral = parseFloat(document.querySelector('input[name=descuento]')?.value || 0);
        total -= descGeneral;
        document.getElementById('total-estimado').textContent =
            '$' + Math.max(0, total).toLocaleString('es-CO', { maximumFractionDigits: 0 });
        document.getElementById('detalles-vacio').style.display =
            document.querySelectorAll('#detalles .detalle').length ? 'none' : 'block';
    }
    document.getElementById('form-venta').addEventListener('submit', actualizarIndices);
    @if($cita)
        @php($detalleServicio = $cita->servicio)
        window.addEventListener('DOMContentLoaded', () => {
            agregarServicio();
            const sel = document.querySelector('#detalles .sel-servicio');
            sel.value = '{{ $cita->servicio_id }}';
            onServicio(sel);
        });
    @endif
</script>
@endpush
