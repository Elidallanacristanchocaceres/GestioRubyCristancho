@php($u = $usuario)
<div class="space-y-4">
    <div><label class="label">Nombre completo *</label><input name="name" value="{{ old('name', $u->name) }}" class="field" required></div>
    <div><label class="label">Cédula *</label><input name="cedula" value="{{ old('cedula', $u->cedula) }}" class="field" required></div>
    <div><label class="label">Correo electrónico (usuario de acceso)</label><input name="email" type="email" value="{{ old('email', $u->email) }}" class="field"></div>
    <div>
        <label class="label">Rol *</label>
        <select name="role" class="field" required>
            <option value="admin" @selected(old('role', $u->role) === 'admin')>Administrador</option>
            <option value="empleado" @selected(old('role', $u->role) === 'empleado')>Empleado</option>
            <option value="invitado" @selected(old('role', $u->role) === 'invitado')>Invitado</option>
        </select>
    </div>
    <div>
        <label class="label">{{ $crear ? 'Contraseña de acceso *' : 'Nueva contraseña (dejar vacío para no cambiar)' }}</label>
        <input name="password" type="password" minlength="8" class="field" {{ $crear ? 'required' : '' }}>
    </div>
</div>