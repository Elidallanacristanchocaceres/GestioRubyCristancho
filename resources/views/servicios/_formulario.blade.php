@php($s = $servicio)
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label class="label">Nombre *</label>
        <input name="nombre" value="{{ old('nombre', $s->nombre) }}" class="field" required>
    </div>
    <div>
        <label class="label">Categoría *</label>
        <select name="categoria_servicio_id" class="field" required>
            <option value="">Seleccione…</option>
            @foreach($categorias as $cat)
                <option value="{{ $cat->id }}" @selected((int) old('categoria_servicio_id', $s->categoria_servicio_id) === $cat->id)>{{ $cat->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label">Duración (minutos) *</label>
        <input name="duracion_minutos" type="number" min="5" value="{{ old('duracion_minutos', $s->duracion_minutos) }}" class="field" required>
    </div>
    <div>
        <label class="label">Precio *</label>
        <input name="precio" type="number" step="0.01" min="0" value="{{ old('precio', $s->precio) }}" class="field" required>
    </div>
    <div>
        <label class="label">Costo de mano de obra</label>
        <input name="costo_mano_obra" type="number" step="0.01" min="0" value="{{ old('costo_mano_obra', $s->costo_mano_obra) }}" class="field">
    </div>
    <label class="flex items-center gap-2 pt-6 text-sm">
        <input type="checkbox" name="activo" value="1" class="rounded border-cream-300" @checked(old('activo', $s->activo ?? true))>
        Servicio activo
    </label>
    <div class="sm:col-span-2">
        <label class="label">Descripción</label>
        <textarea name="descripcion" class="field" rows="2">{{ old('descripcion', $s->descripcion) }}</textarea>
    </div>
</div>
