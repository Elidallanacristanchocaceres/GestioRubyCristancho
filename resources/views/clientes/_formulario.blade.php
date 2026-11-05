@php($c = $cliente)
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label class="label">Nombre completo *</label>
        <input name="nombre" value="{{ old('nombre', $c->nombre) }}" class="field" required>
    </div>
    <div>
        <label class="label">Celular *</label>
        <input name="celular" value="{{ old('celular', $c->celular) }}" class="field" required>
    </div>
    <div>
        <label class="label">WhatsApp</label>
        <input name="whatsapp" value="{{ old('whatsapp', $c->whatsapp) }}" class="field">
    </div>
    <div>
        <label class="label">Correo electrónico</label>
        <input name="email" type="email" value="{{ old('email', $c->email) }}" class="field">
    </div>
    <div>
        <label class="label">Fecha de nacimiento</label>
        <input name="fecha_nacimiento" type="date" value="{{ old('fecha_nacimiento', optional($c->fecha_nacimiento)->format('Y-m-d')) }}" class="field">
    </div>
    <div>
        <label class="label">Dirección</label>
        <input name="direccion" value="{{ old('direccion', $c->direccion) }}" class="field">
    </div>
    <div class="sm:col-span-2">
        <label class="label">Preferencias de cabello</label>
        <textarea name="preferencias_cabello" class="field" rows="2">{{ old('preferencias_cabello', $c->preferencias_cabello) }}</textarea>
    </div>
    <div class="sm:col-span-2">
        <label class="label">Fórmula de color</label>
        <textarea name="formula_color" class="field" rows="2">{{ old('formula_color', $c->formula_color) }}</textarea>
    </div>
    <div class="sm:col-span-2">
        <label class="label">Observaciones</label>
        <textarea name="observaciones" class="field" rows="2">{{ old('observaciones', $c->observaciones) }}</textarea>
    </div>
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="activo" value="1" class="rounded border-cream-300" @checked(old('activo', $c->activo ?? true))>
        Cliente activo
    </label>
</div>
