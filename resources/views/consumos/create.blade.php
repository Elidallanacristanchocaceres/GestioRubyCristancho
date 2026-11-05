@extends('layouts.app')

@php
    $editando = $consumo !== null;
    $gruposFormulario = $mostrarOtros
        ? ['tintes', 'tratamientos', 'coactivadores', 'decolorantes', 'otros']
        : ['tintes', 'tratamientos', 'coactivadores', 'decolorantes'];
@endphp

@section('titulo', $editando ? 'Registro del día #' . $consumo->id : 'Registro diario')
@section('subtitulo', 'Guarda cada producto que vas usando y anota qué se hizo y cuánto se cobró')

@section('acciones')
    <a href="{{ $editando ? route('consumos.show', $consumo) : route('consumos.index') }}" class="btn-ghost">Volver</a>
@endsection

@section('contenido')
    <div id="aviso-global" class="mb-5 hidden rounded-xl border px-4 py-3 text-sm"></div>

    {{-- Cabecera de la jornada: se guarda junto con cada producto --}}
    <div class="card card-body mb-6">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-cream-200 pb-4">
            <div>
                <h2 class="font-serif text-lg font-semibold">Datos de la jornada</h2>
                <p class="text-xs text-ink-500">Se aplican a todos los productos que guardes</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <span id="estado-cabecera" class="badge bg-cream-100 text-ink-500">Sin guardar</span>
                <button type="button" id="btn-cabecera" class="btn-ghost hidden" onclick="guardarCabecera()">Guardar datos</button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="label">Fecha del registro *</label>
                <input type="date" id="cab-fecha" value="{{ old('fecha', $consumo?->fecha?->toDateString() ?? now()->toDateString()) }}" class="field" onchange="marcarCabecera()">
            </div>
            <div>
                <label class="label">Qué hicimos *</label>
                <input id="cab-descripcion" value="{{ old('descripcion', $consumo?->descripcion) }}" class="field" placeholder="Ej: Color, corte, cepillado…" onchange="marcarCabecera()">
                <p class="mt-1 text-xs text-ink-500">Lo que se le hizo a la persona (color, corte, cepillado…). El cliente y el servicio se registran aparte.</p>
            </div>
            <div>
                <label class="label">Cobrado</label>
                <input type="number" step="0.01" min="0" id="cab-precio" value="{{ old('precio_cobrado', $consumo?->precio_cobrado) }}" class="field" placeholder="0" onchange="marcarCabecera()">
                <p class="mt-1 text-xs text-ink-500">Lo que se le cobró por el trabajo.</p>
            </div>
            <div>
                <label class="label">Profesional (opcional)</label>
                <select id="cab-estilista" class="field" onchange="marcarCabecera()">
                    <option value="">—</option>
                    @foreach($estilistas as $e)
                        <option value="{{ $e->id }}" @selected((int) old('estilista_id', $consumo?->estilista_id) === $e->id)>{{ $e->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="label">Observaciones</label>
                <input id="cab-observaciones" value="{{ old('observaciones', $consumo?->observaciones) }}" class="field" placeholder="Fórmula, notas internas…" onchange="marcarCabecera()">
            </div>
        </div>
    </div>

    {{-- Categorías: una tarjeta amplia por tipo de producto --}}
    <div class="space-y-6">
        @foreach($gruposFormulario as $clave)
            @php $meta = \App\Services\RegistroDiarioService::GRUPOS[$clave] ?? ['etiqueta' => 'Otros productos', 'resumen' => 'Producto fuera de las categorías']; @endphp
            <div class="card card-body" data-grupo="{{ $clave }}">
                <div class="mb-5 flex flex-wrap items-end justify-between gap-4 border-b border-cream-200 pb-4">
                    <div>
                        <h2 class="font-serif text-lg font-semibold">{{ $meta['etiqueta'] }}</h2>
                        <p class="text-xs text-ink-500">{{ $meta['resumen'] }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                        <div class="text-sm">
                            <span class="text-ink-500">Gramos:</span>
                            <span id="sub-{{ $clave }}-gramos" class="font-semibold">0 g</span>
                            <span class="text-ink-400 mx-1">·</span>
                            <span class="text-ink-500">Costo:</span>
                            <span id="sub-{{ $clave }}-costo" class="font-semibold">$0</span>
                        </div>
                        <button type="button" class="btn-ghost" onclick="agregarLinea('{{ $clave }}')">+ Agregar producto</button>
                    </div>
                </div>

                <div id="lineas-{{ $clave }}" class="space-y-4"></div>
                <p id="vacio-{{ $clave }}" class="rounded-xl border border-dashed border-cream-300 bg-cream-50/50 px-4 py-6 text-center text-sm text-ink-500">
                    Aún no has registrado productos en esta categoría.
                </p>
            </div>
        @endforeach
    </div>

    {{-- Resumen del día --}}
    <div class="card card-body mt-6">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-2 border-b border-cream-200 pb-4">
            <h2 class="font-serif text-lg font-semibold">Resumen del día</h2>
            <span class="text-sm text-ink-500">Gramos y costo por categoría</span>
        </div>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            @foreach($gruposFormulario as $clave)
                @php $meta = \App\Services\RegistroDiarioService::GRUPOS[$clave] ?? ['etiqueta' => 'Otros productos']; @endphp
                <div class="rounded-xl border border-cream-200 bg-cream-50/50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-ink-500">{{ $meta['etiqueta'] }}</p>
                    <p class="mt-2 font-serif text-xl font-semibold"><span id="resumen-{{ $clave }}-gramos">0 g</span></p>
                    <p class="text-xs text-ink-500"><span id="resumen-{{ $clave }}-costo">$0</span></p>
                </div>
            @endforeach
        </div>
        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-cream-200 pt-4">
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                <span><strong id="tot-tintes">0 g</strong> <span class="text-ink-500">Tintes</span></span>
                <span><strong id="tot-tratamientos">0 g</strong> <span class="text-ink-500">Tratamientos</span></span>
                <span><strong id="tot-coactivadores">0 g</strong> <span class="text-ink-500">Coactivadores</span></span>
                <span><strong id="tot-decolorantes">0 g</strong> <span class="text-ink-500">Decolorantes</span></span>
                @if($mostrarOtros)
                    <span><strong id="tot-otros">0 g</strong> <span class="text-ink-500">Otros</span></span>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <span class="text-sm text-ink-500">Costo total del día:</span>
                <span class="font-serif text-2xl font-semibold"><span id="total-costo">$0</span></span>
            </div>
        </div>
    </div>

    <div class="mt-6 flex flex-wrap items-center justify-end gap-3">
        <a href="{{ $editando ? route('consumos.show', $consumo) : route('consumos.index') }}" class="btn-ghost">Cancelar</a>
        <a href="{{ route('consumos.index') }}" class="btn-primary">Ir al registro diario</a>
    </div>

    <template id="tpl-linea">
        <div class="linea rounded-2xl border border-cream-200 bg-white p-4 sm:p-5">
            <div class="grid grid-cols-1 gap-x-6 gap-y-4 lg:grid-cols-12">
                <div class="lg:col-span-5">
                    <label class="label">Producto</label>
                    <select class="field sel-producto" onchange="onProducto(this)"></select>
                    <p class="info-producto mt-1.5 text-xs text-ink-500"></p>
                </div>
                <div class="lg:col-span-2">
                    <label class="label">Gramos usados</label>
                    <input type="number" step="0.001" min="0.001" class="field in-cantidad" value="" placeholder="0">
                </div>
                <div class="lg:col-span-2">
                    <label class="label">Costo por gramo</label>
                    <input type="number" step="0.000001" min="0" class="field in-costo" value="" placeholder="0">
                </div>
                <div class="lg:col-span-3">
                    <label class="label">Notas (opcional)</label>
                    <input type="text" class="field in-notas" placeholder="Fórmula, detalle…">
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-cream-100 pt-4">
                <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-sm">
                    <span class="text-ink-500">Subtotal:</span>
                    <span class="font-serif text-lg font-semibold"><span class="in-linea">$0</span></span>
                    <span class="estado-linea text-ink-500"></span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="btn-ghost" onclick="quitarLinea(this)">Quitar</button>
                    <button type="button" class="btn-primary btn-guardar" onclick="guardarLinea(this)">Guardar producto</button>
                </div>
            </div>
        </div>
    </template>
@endsection

@push('scripts')
<script>
    const productosPorGrupo = @json($productos);
    const lineasIniciales = @json($lineas);
    const grupos = @json($gruposFormulario);
    const costoTinte = @json(\App\Services\RegistroDiarioService::COSTO_GRAMO_TINTE);
    let consumoId = @json($consumo?->id);
    let token = @json(old('token', $token));
    const rutaGuardar = @json(route('consumos.guardarProducto'));
    const rutaQuitarBase = @json(route('consumos.quitarProducto', ['consumo' => '__ID__']));
    const rutaCabecera = @json(route('consumos.cabecera', ['consumo' => '__ID__']));
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

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
        if (tipo === 'ok') setTimeout(() => caja.classList.add('hidden'), 4000);
    }

    function marcarCabecera() {
        const badge = document.getElementById('estado-cabecera');
        badge.className = 'badge bg-amber-100 text-amber-800';
        badge.textContent = 'Cambios sin guardar';
    }

    function marcarCabeceraGuardada() {
        const badge = document.getElementById('estado-cabecera');
        badge.className = 'badge bg-emerald-100 text-emerald-800';
        badge.textContent = 'Jornada guardada';
    }

    function mostrarBotonCabecera() {
        const boton = document.getElementById('btn-cabecera');
        if (boton) boton.classList.toggle('hidden', !consumoId);
    }

    async function guardarCabecera() {
        if (!consumoId) {
            aviso('info', 'Estos datos se guardan junto con el primer producto que registres.');
            return;
        }

        const boton = document.getElementById('btn-cabecera');
        boton.disabled = true;

        try {
            const resp = await fetch(rutaCabecera.replace('__ID__', consumoId), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    fecha: document.getElementById('cab-fecha').value,
                    descripcion: document.getElementById('cab-descripcion').value || null,
                    precio_cobrado: document.getElementById('cab-precio').value || null,
                    observaciones: document.getElementById('cab-observaciones').value || null,
                    estilista_id: document.getElementById('cab-estilista').value || null,
                }),
            });
            const data = await resp.json();

            if (!resp.ok) {
                const mensaje = data.message || (data.errors ? Object.values(data.errors).flat()[0] : 'No se pudo guardar.');
                aviso('error', mensaje);
                return;
            }

            marcarCabeceraGuardada();
            aviso('ok', 'Datos de la jornada guardados.');
        } catch (e) {
            aviso('error', 'No se pudo conectar con el servidor. Intenta de nuevo.');
        } finally {
            boton.disabled = false;
        }
    }

    function cabecera() {
        return {
            token: token,
            fecha: document.getElementById('cab-fecha').value,
            estilista_id: document.getElementById('cab-estilista').value || null,
            precio_cobrado: document.getElementById('cab-precio').value || null,
            descripcion: document.getElementById('cab-descripcion').value || null,
            observaciones: document.getElementById('cab-observaciones').value || null,
        };
    }

    function agregarLinea(grupo, datos = null) {
        const contenedor = document.getElementById('lineas-' + grupo);
        if (!contenedor) return;

        const tpl = document.getElementById('tpl-linea');
        const clone = tpl.content.cloneNode(true);
        const linea = clone.querySelector('.linea');
        linea.dataset.grupo = grupo;
        contenedor.appendChild(clone);

        const nueva = contenedor.lastElementChild;
        const sel = nueva.querySelector('.sel-producto');
        const lista = productosPorGrupo[grupo] || [];
        sel.innerHTML = '<option value="">Seleccione…</option>' +
            lista.map(p =>
                '<option value="' + p.id + '" data-nombre="' + (p.nombre || '') + '" data-costo="' + (p.costo || '') + '" data-costo-config="' + (p.tiene_costo ? 1 : '') + '" data-stock="' + (p.stock || '') + '">' + (p.nombre || '') + '</option>'
            ).join('');

        if (datos) {
            sel.value = datos.producto_id ?? '';
            onProducto(sel);
            const inCosto = nueva.querySelector('.in-costo');
            if (datos.costo_unitario && inCosto && !inCosto.readOnly) inCosto.value = datos.costo_unitario;
            const inCant = nueva.querySelector('.in-cantidad');
            if (inCant) inCant.value = datos.cantidad ?? '';
            const inNotas = nueva.querySelector('.in-notas');
            if (inNotas) inNotas.value = datos.notas ?? '';
            if (datos.id) marcarGuardada(nueva, datos.id);
        }

        nueva.querySelectorAll('input, select').forEach(el => {
            el.addEventListener('input', () => marcarCabecera());
            el.addEventListener('change', () => marcarCabecera());
        });
        nueva.querySelector('.in-cantidad').addEventListener('input', recalcular);
        nueva.querySelector('.in-costo').addEventListener('input', recalcular);

        actualizar();
        recalcular();
    }

    function onProducto(sel) {
        const opt = sel.selectedOptions[0];
        const linea = sel.closest('.linea');
        const info = linea.querySelector('.info-producto');
        const costo = linea.querySelector('.in-costo');
        const grupo = linea.dataset.grupo;

        if (!opt || !opt.value) {
            costo.value = '';
            costo.readOnly = false;
            info.textContent = '';
            recalcular();
            return;
        }

        const configurado = parseFloat(opt.dataset.costo || '0');
        const tieneCosto = opt.dataset.costoConfig === '1' || configurado > 0;
        const stock = opt.dataset.stock || '0';

        if (grupo === 'tintes') {
            costo.value = costoTinte;
            costo.readOnly = true;
            info.textContent = 'Regla del salón: ' + costoTinte + ' por gramo · stock en inventario: ' + stock;
        } else if (tieneCosto) {
            costo.value = configurado;
            costo.readOnly = true;
            info.textContent = 'Precio configurado en la ficha: ' + configurado.toLocaleString('es-CO') + ' por gramo · stock: ' + stock;
        } else {
            costo.value = '';
            costo.readOnly = false;
            costo.placeholder = 'Sin precio configurado';
            info.textContent = 'Sin precio por gramo en la ficha. Escribe el costo para poder registrar este producto · stock: ' + stock;
        }

        recalcular();
    }

    function actualizar() {
        document.querySelectorAll('.card[data-grupo]').forEach(card => {
            const g = card.dataset.grupo;
            const vacio = document.getElementById('vacio-' + g);
            const n = document.querySelectorAll('#lineas-' + g + ' .linea').length;
            if (vacio) vacio.style.display = n ? 'none' : 'block';
        });
    }

    function marcarGuardada(linea, detalleId) {
        linea.dataset.detalleId = detalleId;
        linea.classList.add('bg-cream-50');
        const boton = linea.querySelector('.btn-guardar');
        boton.textContent = 'Guardar cambios';
        boton.dataset.guardada = '1';
        setEstado(linea, 'ok', 'Guardado');
    }

    function setEstado(linea, tipo, texto) {
        const el = linea.querySelector('.estado-linea');
        const clases = {
            ok: 'badge bg-emerald-100 text-emerald-800',
            error: 'badge bg-rose-100 text-rose-800',
            cargando: 'badge bg-amber-100 text-amber-800',
            pendiente: 'badge bg-cream-100 text-ink-500',
        };
        el.className = 'estado-linea ' + (clases[tipo] || clases.pendiente);
        el.textContent = texto;
    }

    async function guardarLinea(boton) {
        const linea = boton.closest('.linea');
        const sel = linea.querySelector('.sel-producto');
        const cantidad = linea.querySelector('.in-cantidad').value;
        const costoUnitario = linea.querySelector('.in-costo').value;
        const notas = linea.querySelector('.in-notas').value;

        if (!sel.value) {
            setEstado(linea, 'error', 'Elige un producto');
            aviso('error', 'Debes elegir un producto antes de guardarlo.');
            return;
        }
        if (!document.getElementById('cab-descripcion').value.trim()) {
            setEstado(linea, 'error', 'Falta qué se hizo');
            aviso('error', 'Escribe qué hiciste en "Qué hicimos" antes de guardar el producto.');
            return;
        }
        if (!(parseFloat(cantidad) > 0)) {
            setEstado(linea, 'error', 'Falta el peso');
            aviso('error', 'Indica los gramos usados (mayor que cero).');
            return;
        }
        if (linea.querySelector('.in-costo').readOnly === false && !(parseFloat(costoUnitario) > 0)) {
            setEstado(linea, 'error', 'Falta el costo');
            aviso('error', 'Escribe el costo por gramo de este producto.');
            return;
        }

        boton.disabled = true;
        setEstado(linea, 'cargando', 'Guardando…');

        const cuerpo = Object.assign(cabecera(), {
            producto_id: sel.value,
            cantidad: cantidad,
            costo_unitario: costoUnitario || null,
            notas: notas || null,
            detalle_id: linea.dataset.detalleId ? Number(linea.dataset.detalleId) : null,
        });

        try {
            const resp = await fetch(rutaGuardar, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(cuerpo),
            });
            const data = await resp.json();

            if (!resp.ok) {
                const mensaje = data.message || (data.errors ? Object.values(data.errors).flat()[0] : 'No se pudo guardar.');
                setEstado(linea, 'error', 'Error');
                aviso('error', mensaje);
                boton.disabled = false;
                return;
            }

            token = data.token;
            if (data.consumo_id) consumoId = data.consumo_id;
            mostrarBotonCabecera();
            if (data.linea && data.linea.id) marcarGuardada(linea, data.linea.id);
            else setEstado(linea, 'ok', 'Guardado');

            marcarCabeceraGuardada();

            aviso('ok', 'Producto guardado. El inventario ya fue descontado.');
            boton.disabled = false;
        } catch (e) {
            setEstado(linea, 'error', 'Error de red');
            aviso('error', 'No se pudo conectar con el servidor. Intenta de nuevo.');
            boton.disabled = false;
        }
    }

    async function quitarLinea(boton) {
        const linea = boton.closest('.linea');
        const detalleId = linea.dataset.detalleId;

        if (!detalleId) {
            linea.remove();
            actualizar();
            recalcular();
            return;
        }

        if (!confirm('¿Quitar este producto? El inventario que se descontó volverá a su lugar.')) return;

        boton.disabled = true;
        setEstado(linea, 'cargando', 'Quitando…');

        try {
            const resp = await fetch(rutaQuitarBase.replace('__ID__', consumoId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ detalle_id: Number(detalleId) }),
            });
            const data = await resp.json();

            if (!resp.ok) {
                const mensaje = data.message || 'No se pudo quitar el producto.';
                setEstado(linea, 'error', 'Error');
                aviso('error', mensaje);
                boton.disabled = false;
                return;
            }

            linea.remove();
            actualizar();
            recalcular();
            aviso('ok', 'Producto quitado. El inventario fue restaurado.');
        } catch (e) {
            setEstado(linea, 'error', 'Error de red');
            aviso('error', 'No se pudo conectar con el servidor.');
            boton.disabled = false;
        }
    }

    function recalcular() {
        const acumulado = {};
        grupos.forEach(g => acumulado[g] = { gramos: 0, costo: 0 });

        document.querySelectorAll('.card[data-grupo] .linea').forEach(l => {
            const grupo = l.dataset.grupo;
            if (!acumulado[grupo]) return;

            const cant = parseFloat(l.querySelector('.in-cantidad')?.value || 0);
            const costo = parseFloat(l.querySelector('.in-costo')?.value || 0);
            const sub = cant * costo;

            l.querySelector('.in-linea').textContent = money(sub);

            acumulado[grupo].gramos += cant;
            acumulado[grupo].costo += sub;
        });

        let totalCosto = 0;

        grupos.forEach(g => {
            const a = acumulado[g];
            const elG = document.getElementById('sub-' + g + '-gramos');
            const elC = document.getElementById('sub-' + g + '-costo');
            const rG = document.getElementById('resumen-' + g + '-gramos');
            const rC = document.getElementById('resumen-' + g + '-costo');
            const gFmt = Number(a.gramos.toFixed(3)).toLocaleString('es-CO') + ' g';

            if (elG) elG.textContent = gFmt;
            if (elC) elC.textContent = money(a.costo);
            if (rG) rG.textContent = gFmt;
            if (rC) rC.textContent = money(a.costo);

            totalCosto += a.costo;
        });

        const fmt = v => Number((v || 0).toFixed(3)).toLocaleString('es-CO') + ' g';
        const set = (id, val) => { const e = document.getElementById(id); if (e) e.textContent = val; };
        set('tot-tintes', fmt(acumulado['tintes']?.gramos || 0));
        set('tot-tratamientos', fmt(acumulado['tratamientos']?.gramos || 0));
        set('tot-coactivadores', fmt(acumulado['coactivadores']?.gramos || 0));
        set('tot-decolorantes', fmt(acumulado['decolorantes']?.gramos || 0));
        if (acumulado['otros']) set('tot-otros', fmt(acumulado['otros'].gramos || 0));
        set('total-costo', money(totalCosto));
    }

    window.addEventListener('DOMContentLoaded', () => {
        if (lineasIniciales.length) {
            lineasIniciales.forEach(l => agregarLinea(l.grupo ?? 'tintes', l));
            marcarCabeceraGuardada();
        } else {
            agregarLinea('tintes');
        }
        mostrarBotonCabecera();
    });
</script>
@endpush
