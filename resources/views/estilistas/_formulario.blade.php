@php($e = $estilista)
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label class="label">Nombre completo *</label>
        <input name="nombre" value="{{ old('nombre', $e->nombre) }}" class="field" required>
    </div>
    <div>
        <label class="label">Especialidad</label>
        <input name="especialidad" value="{{ old('especialidad', $e->especialidad) }}" class="field">
    </div>
    <div>
        <label class="label">Teléfono</label>
        <input name="telefono" value="{{ old('telefono', $e->telefono) }}" class="field">
    </div>
    <div>
        <label class="label">Fecha de contratación *</label>
        <input name="fecha_contratacion" type="date" value="{{ old('fecha_contratacion', optional($e->fecha_contratacion)->format('Y-m-d')) }}" class="field" required>
    </div>
    <div>
        <label class="label">Salario base</label>
        <input name="salario_base" type="number" step="0.01" min="0" value="{{ old('salario_base', $e->salario_base) }}" class="field">
    </div>
    <label class="flex items-center gap-2 pt-6 text-sm">
        <input type="checkbox" name="activo" value="1" class="rounded border-cream-300" @checked(old('activo', $e->activo ?? true))>
        Profesional activo
    </label>
</div>
