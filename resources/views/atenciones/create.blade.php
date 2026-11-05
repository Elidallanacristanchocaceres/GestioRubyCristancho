@extends('layouts.app')

@php($editando = $atencion !== null)

@section('titulo', $editando ? 'Editar atención #' . $atencion->id : 'Registrar atención')
@section('subtitulo', 'Anota qué se le realizó al cliente y el total')

@section('acciones')
    <a href="{{ $editando ? route('atenciones.show', $atencion) : route('atenciones.index') }}" class="btn-ghost">Volver</a>
@endsection

@section('contenido')
    <div id="aviso-global" class="mb-5 hidden rounded-xl border px-4 py-3 text-sm"></div>

    <form id="form-atencion" method="POST"
          action="{{ $editando ? route('atenciones.update', $atencion) : route('atenciones.store') }}">
        @csrf
        @if($editando) @method('PUT') @endif
        <input type="hidden" name="token" value="{{ old('token', $token) }}">

        {{-- Datos de la atención --}}
        <div class="card card-body mb-6">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-cream-200 pb-4">
                <div>
                    <h2 class="font-serif text-lg font-semibold">Datos de la atención</h2>
                    <p class="text-xs text-ink-500">Cuándo se hizo y cuánto se cobró</p>
                </div>
                <p class="text-xs text-ink-500">Aquí no se piden datos del cliente.</p>
            </div>

            <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="label">Fecha *</label>
                    <input type="date" name="fecha" value="{{ old('fecha', $atencion?->fecha?->toDateString() ?? now()->toDateString()) }}" class="field @error('fecha') border-red-400 @enderror" required>
                    @error('fecha')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">Profesional que atendió</label>
                    <select name="estilista_id" class="field">
                        <option value="">—</option>
                        @foreach($estilistas as $e)
                            <option value="{{ $e->id }}" @selected((int) old('estilista_id', $atencion?->estilista_id) === $e->id)>{{ $e->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Método de pago</label>
                    <input name="metodo_pago" value="{{ old('metodo_pago', $atencion?->metodo_pago) }}" class="field" placeholder="Efectivo, Nequi, transferencia…" maxlength="60">
                </div>
                <div>
                    <label class="label">Monto pagado (COP)</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-ink-500">$</span>
                        <input type="number" step="0.01" min="0" name="pagado" id="in-pagado" value="{{ old('pagado', $atencion?->pagado) }}" class="field pl-7" placeholder="0">
                    </div>
                    <p class="mt-1 text-xs text-ink-500">Si dejas 0, la atención queda con saldo pendiente.</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Observaciones</label>
                    <input name="observaciones" value="{{ old('observaciones', $atencion?->observaciones) }}" class="field" placeholder="Notas internas, fórmula, etc.">
                </div>
            </div>
        </div>

        {{-- Servicios realizados --}}
        <div class="card card-body mb-6">
            <div class="mb-5 flex flex-wrap items-end justify-between gap-4 border-b border-cream-200 pb-4">
                <div>
                    <h2 class="font-serif text-lg font-semibold">Servicios realizados</h2>
                    <p class="text-xs text-ink-500">Elige un servicio del catálogo o escribe uno manual, y pon el precio en pesos</p>
                </div>
                <button type="button" class="btn-ghost" onclick="agregarLinea()">+ Agregar servicio</button>
            </div>

            <div id="lineas" class="space-y-4"></div>
            <p id="vacio" class="rounded-xl border border-dashed border-cream-300 bg-cream-50/50 px-4 py-6 text-center text-sm text-ink-500">
                Aún no has agregado servicios a esta atención.
            </p>
            @error('detalles')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        {{-- Resumen --}}
        <div class="card card-body">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                    <span><strong id="tot-servicios">0</strong> <span class="text-ink-500">servicios</span></span>
                    <span>Saldo: <strong id="tot-saldo">$0</strong></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-ink-500">Total:</span>
                    <span class="font-serif text-2xl font-semibold" id="tot-total">$0</span>
                </div>
            </div>
            <div class="mt-5 flex flex-wrap items-center justify-end gap-3 border-t border-cream-200 pt-4">
                <a href="{{ $editando ? route('atenciones.show', $atencion) : route('atenciones.index') }}" class="btn-ghost">Cancelar</a>
                <button type="submit" class="btn-primary">Guardar atención</button>
            </div>
        </div>
    </form>

    <template id="tpl-linea">
        <div class="linea rounded-2xl border border-cream-200 bg-white p-4 sm:p-5">
            <div class="grid grid-cols-1 gap-x-6 gap-y-4 lg:grid-cols-12">
                <div class="lg:col-span-4">
                    <label class="label">Servicio</label>
                    <select class="field sel-servicio"></select>
                    <input type="hidden" class="in-servicio-id">
                    <p class="mt-1.5 text-xs text-ink-500">Si no está en la lista, elige “Servicio manual” y escribe el nombre.</p>
                </div>
                <div class="lg:col-span-3 in-nombre-wrap hidden">
                    <label class="label">Nombre del servicio</label>
                    <input type="text" class="field in-nombre" placeholder="Ej: Corte + balayage">
                    <p class="mt-1 text-xs text-ink-500">Obligatorio cuando el servicio es manual.</p>
                </div>
                <div class="lg:col-span-2">
                    <label class="label">Descripción (opcional)</label>
                    <input type="text" class="field in-descripcion" placeholder="Ej: Tinte 8.3 + matiz">
                </div>
                <div class="lg:col-span-2">
                    <label class="label">Precio (COP)</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-ink-500">$</span>
                        <input type="number" step="0.01" min="0" class="field in-precio pl-7" placeholder="0">
                    </div>
                    <p class="mt-1 text-xs text-ink-500">Puedes cambiarlo a mano.</p>
                </div>
                <div class="lg:col-span-1 flex items-end justify-end pb-1">
                    <button type="button" class="btn-link-danger" onclick="quitarLinea(this)">Quitar</button>
                </div>
            </div>
            <div class="mt-3 text-right text-sm">
                <span class="text-ink-500">Subtotal:</span>
                <span class="font-serif text-lg font-semibold in-subtotal">$0</span>
            </div>
        </div>
    </template>
@endsection

@push('scripts')
<script>
    const servicios = @json($servicios->map(fn ($s) => ['id' => $s->id, 'nombre' => $s->nombre, 'precio' => (string) $s->precio]));
    const lineasIniciales = @json(old('detalles', $lineas));
    const MANUAL = '__manual__';
    const money = v => '$' + Number(v || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });

    function aviso(tipo, mensaje) {
        const caja = document.getElementById('aviso-global');
        const estilos = {
            ok: 'border-emerald-200 bg-emerald-50 text-emerald-800',
            error: 'border-rose-200 bg-rose-50 text-rose-800',
            info: 'border-cream-300 bg-cream-50 text-ink-700',
        };
        caja.className = 'mb-5 rounded-xl border px-4 py-3 text-sm ' + (estilos[tipo] || estilos.info);
        caja.textContent = mensaje;
    }

    function agregarLinea(datos = null) {
        const contenedor = document.getElementById('lineas');
        const tpl = document.getElementById('tpl-linea');
        const clone = tpl.content.cloneNode(true);
        contenedor.appendChild(clone);

        const nueva = contenedor.lastElementChild;
        const sel = nueva.querySelector('.sel-servicio');
        sel.innerHTML = '<option value="' + MANUAL + '">✍️ Servicio manual (fuera del catálogo)</option>' +
            servicios.map(s => '<option value="' + s.id + '" data-precio="' + s.precio + '" data-nombre="' + s.nombre + '">' + s.nombre + '</option>').join('');

        if (datos) {
            const servicioId = datos.servicio_id ?? '';
            sel.value = servicioId ? servicioId : MANUAL;
            nueva.querySelector('.in-nombre').value = datos.nombre ?? '';
            nueva.querySelector('.in-descripcion').value = datos.descripcion ?? '';
            nueva.querySelector('.in-precio').value = datos.precio_unitario ?? '';
        } else {
            sel.value = MANUAL;
        }

        sel.addEventListener('change', () => onServicio(sel));
        nueva.querySelector('.in-precio').addEventListener('input', recalcular);
        nueva.querySelector('.in-nombre').addEventListener('input', recalcular);
        nueva.querySelector('.in-descripcion').addEventListener('input', recalcular);

        onServicio(sel, true);
        actualizar();
        recalcular();
        numerarLineas();
    }

    function onServicio(sel, restoring = false) {
        const opt = sel.selectedOptions[0];
        const linea = sel.closest('.linea');
        const precio = linea.querySelector('.in-precio');
        const nombre = linea.querySelector('.in-nombre');
        const wrapNombre = linea.querySelector('.in-nombre-wrap');
        const manual = !opt || opt.value === MANUAL;

        wrapNombre.classList.toggle('hidden', !manual);

        if (manual) {
            linea.querySelector('.in-servicio-id').value = '';
            return;
        }

        linea.querySelector('.in-servicio-id').value = opt.value;
        nombre.value = '';

        // El precio del catálogo sirve de base, pero se puede cambiar a mano.
        if (!restoring && opt.dataset.precio && !precio.value) {
            precio.value = opt.dataset.precio;
        }

        recalcular();
    }

    function quitarLinea(boton) {
        boton.closest('.linea').remove();
        actualizar();
        recalcular();
        numerarLineas();
    }

    function numerarLineas() {
        document.querySelectorAll('#lineas .linea').forEach((linea, i) => {
            linea.querySelector('.in-servicio-id').name = 'detalles[' + i + '][servicio_id]';
            linea.querySelector('.in-nombre').name = 'detalles[' + i + '][nombre]';
            linea.querySelector('.in-descripcion').name = 'detalles[' + i + '][descripcion]';
            linea.querySelector('.in-precio').name = 'detalles[' + i + '][precio_unitario]';
        });
    }

    function actualizar() {
        const n = document.querySelectorAll('#lineas .linea').length;
        document.getElementById('vacio').style.display = n ? 'none' : 'block';
    }

    function recalcular() {
        let total = 0;
        let n = 0;
        document.querySelectorAll('#lineas .linea').forEach(l => {
            const precio = parseFloat(l.querySelector('.in-precio')?.value || 0);
            l.querySelector('.in-subtotal').textContent = money(precio);
            if (precio >= 0 && (l.querySelector('.in-servicio-id')?.value || l.querySelector('.in-nombre')?.value || l.querySelector('.in-descripcion')?.value)) n++;
            total += precio;
        });
        const pagado = parseFloat(document.getElementById('in-pagado')?.value || 0);
        document.getElementById('tot-total').textContent = money(total);
        document.getElementById('tot-saldo').textContent = money(Math.max(total - pagado, 0));
        document.getElementById('tot-servicios').textContent = n;
    }

    function validarLineas() {
        const lineas = [...document.querySelectorAll('#lineas .linea')];
        if (!lineas.length) {
            aviso('error', 'Agrega al menos un servicio realizado.');
            return false;
        }

        for (const linea of lineas) {
            const esManual = !linea.querySelector('.in-servicio-id').value;
            const nombre = linea.querySelector('.in-nombre').value.trim();
            const descripcion = linea.querySelector('.in-descripcion').value.trim();

            if (esManual && !nombre && !descripcion) {
                aviso('error', 'Escribe el nombre del servicio manual (o una descripción).');
                return false;
            }
            if (linea.querySelector('.in-precio').value === '') {
                aviso('error', 'Indica el precio de cada servicio en pesos.');
                return false;
            }
        }

        return true;
    }

    document.getElementById('in-pagado')?.addEventListener('input', recalcular);

    document.getElementById('form-atencion')?.addEventListener('submit', (e) => {
        if (!validarLineas()) {
            e.preventDefault();
        }
    });

    window.addEventListener('DOMContentLoaded', () => {
        if (lineasIniciales.length) {
            lineasIniciales.forEach(l => agregarLinea(l));
        } else {
            agregarLinea();
        }
    });
</script>
@endpush
