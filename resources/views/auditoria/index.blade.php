@extends('layouts.app')

@section('titulo', 'Auditoría')
@section('subtitulo', 'Bitácora de cambios y acciones del sistema')

@section('contenido')
    <form method="GET" class="card card-body mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="label">Módulo</label>
            <select name="modulo" class="field">
                <option value="">Todos</option>
                @foreach($modulos as $modulo)
                    <option value="{{ $modulo }}" @selected(request('modulo') === $modulo)>{{ ucfirst($modulo) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Acción</label>
            <select name="accion" class="field">
                <option value="">Todas</option>
                @foreach(['crear','actualizar','eliminar','anular','pago','apertura','cierre'] as $acc)
                    <option value="{{ $acc }}" @selected(request('accion') === $acc)>{{ ucfirst($acc) }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="label">Desde</label><input type="date" name="desde" value="{{ request('desde') }}" class="field"></div>
        <div><label class="label">Hasta</label><input type="date" name="hasta" value="{{ request('hasta') }}" class="field"></div>
        <button class="btn-dark">Filtrar</button>
    </form>

    <div class="card card-body">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Módulo</th><th>Modelo</th><th>Motivo</th></tr></thead>
                <tbody>
                    @forelse($registros as $reg)
                        <tr>
                            <td class="whitespace-nowrap">{{ $reg->created_at->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $reg->usuario?->name ?? 'Sistema' }}</td>
                            <td><span class="badge bg-cream-200 text-ink-700">{{ ucfirst($reg->accion) }}</span></td>
                            <td>{{ ucfirst($reg->modulo) }}</td>
                            <td class="text-xs">{{ class_basename($reg->modelo) }} #{{ $reg->modelo_id ?? '—' }}</td>
                            <td class="text-xs">{{ $reg->motivo ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-ink-500">Sin registros de auditoría.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.paginacion', ['paginable' => $registros])
    </div>
@endsection