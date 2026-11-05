@php($p = $proveedor)
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div><label class="label">Nombre *</label><input name="nombre" value="{{ old('nombre', $p->nombre) }}" class="field" required></div>
    <div><label class="label">NIT</label><input name="nit" value="{{ old('nit', $p->nit) }}" class="field"></div>
    <div><label class="label">Contacto</label><input name="contacto" value="{{ old('contacto', $p->contacto) }}" class="field"></div>
    <div><label class="label">Teléfono</label><input name="telefono" value="{{ old('telefono', $p->telefono) }}" class="field"></div>
    <div><label class="label">WhatsApp</label><input name="whatsapp" value="{{ old('whatsapp', $p->whatsapp) }}" class="field"></div>
    <div><label class="label">Correo</label><input name="email" type="email" value="{{ old('email', $p->email) }}" class="field"></div>
    <div class="sm:col-span-2"><label class="label">Dirección</label><input name="direccion" value="{{ old('direccion', $p->direccion) }}" class="field"></div>
    <div class="sm:col-span-2"><label class="label">Observaciones</label><textarea name="observaciones" class="field" rows="2">{{ old('observaciones', $p->observaciones) }}</textarea></div>
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="activo" value="1" class="rounded border-cream-300" @checked(old('activo', $p->activo ?? true))>
        Proveedor activo
    </label>
</div>
