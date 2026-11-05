@php($g = $gasto)
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label class="label">Descripción *</label>
        <input name="descripcion" value="{{ old('descripcion', $g->descripcion) }}" class="field" required>
    </div>
    <div>
        <label class="label">Monto *</label>
        <input name="monto" type="number" step="0.01" min="0.01" value="{{ old('monto', $g->monto) }}" class="field" required>
    </div>
    <div>
        <label class="label">Categoría</label>
        <select name="categoria_gasto_id" class="field">
            <option value="">—</option>
            @foreach($categorias as $cat)
                <option value="{{ $cat->id }}" @selected((int) old('categoria_gasto_id', $g->categoria_gasto_id) === $cat->id)>{{ $cat->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label">Proveedor</label>
        <select name="proveedor_id" class="field">
            <option value="">—</option>
            @foreach($proveedores as $prov)
                <option value="{{ $prov->id }}" @selected((int) old('proveedor_id', $g->proveedor_id) === $prov->id)>{{ $prov->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label">Medio de pago</label>
        <select name="metodo_pago_id" class="field">
            <option value="">—</option>
            @foreach($metodos as $m)
                <option value="{{ $m->id }}" @selected((int) old('metodo_pago_id', $g->metodo_pago_id) === $m->id)>{{ $m->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label">Fecha *</label>
        <input name="fecha" type="date" value="{{ old('fecha', optional($g->fecha)->format('Y-m-d')) }}" class="field" required>
    </div>
    <div>
        <label class="label">Estado *</label>
        <select name="estado" class="field" required>
            <option value="pagado" @selected(old('estado', $g->estado) === 'pagado')>Pagado</option>
            <option value="pendiente" @selected(old('estado', $g->estado) === 'pendiente')>Pendiente</option>
        </select>
    </div>
    <div>
        <label class="label">Comprobante</label>
        <input name="comprobante" value="{{ old('comprobante', $g->comprobante) }}" class="field">
    </div>
    @unless($g->exists)
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="afectar_caja" value="1" class="rounded border-cream-300" checked>
            Afectar caja (si está abierta y el gasto es pagado)
        </label>
    @endunless
</div>
