@php($c = $cita)
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label class="label">Cliente *</label>
        <select name="cliente_id" class="field" required>
            <option value="">Seleccione…</option>
            @foreach($clientes as $cli)
                <option value="{{ $cli->id }}" @selected((int) old('cliente_id', $c->cliente_id) === $cli->id)>{{ $cli->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label">Servicio *</label>
        <select name="servicio_id" class="field" required>
            <option value="">Seleccione…</option>
            @foreach($servicios as $srv)
                <option value="{{ $srv->id }}" data-precio="{{ $srv->precio }}" data-duracion="{{ $srv->duracion_minutos }}" @selected((int) old('servicio_id', $c->servicio_id) === $srv->id)>{{ $srv->nombre }} — {{ \App\Support\Money::formato($srv->precio) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label">Profesional *</label>
        <select name="estilista_id" class="field" required>
            <option value="">Seleccione…</option>
            @foreach($estilistas as $e)
                <option value="{{ $e->id }}" @selected((int) old('estilista_id', $c->estilista_id) === $e->id)>{{ $e->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label">Fecha y hora *</label>
        <input name="fecha_hora" type="datetime-local" value="{{ old('fecha_hora', optional($c->fecha_hora)->format('Y-m-d\TH:i')) }}" class="field" required>
    </div>
    <div>
        <label class="label">Duración (minutos) *</label>
        <input name="duracion_minutos" type="number" min="5" value="{{ old('duracion_minutos', $c->duracion_minutos ?? 60) }}" class="field" required>
    </div>
    <div>
        <label class="label">Precio</label>
        <input name="precio" type="number" step="0.01" min="0" value="{{ old('precio', $c->precio) }}" class="field">
    </div>
    <div>
        <label class="label">Anticipo</label>
        <input name="anticipo" type="number" step="0.01" min="0" value="{{ old('anticipo', $c->anticipo ?? '0') }}" class="field">
    </div>
    <div>
        <label class="label">Estado *</label>
        <select name="estado" class="field" required>
            @foreach(['pendiente','confirmada','en_progreso','completada','cancelada'] as $est)
                <option value="{{ $est }}" @selected(old('estado', $c->estado) === $est)>{{ ucfirst(str_replace('_', ' ', $est)) }}</option>
            @endforeach
        </select>
    </div>
    <div class="sm:col-span-2">
        <label class="label">Notas</label>
        <textarea name="notas" class="field" rows="2">{{ old('notas', $c->notas) }}</textarea>
    </div>
    @if($clientes->isEmpty() || $servicios->isEmpty() || $estilistas->isEmpty())
        <p class="sm:col-span-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            Necesitas al menos un cliente, un servicio y un profesional activos para agendar.
        </p>
    @endif
</div>
