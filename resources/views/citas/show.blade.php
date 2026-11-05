@extends('layouts.app')

@section('titulo', 'Cita de ' . ($cita->cliente?->nombre ?? 'cliente'))
@section('subtitulo', $cita->fecha_hora->format('d/m/Y H:i') . ' · ' . $cita->estadoLabel())
@section('acciones')
    @if($cita->esEditable())
        <a href="{{ route('citas.edit', $cita) }}" class="btn-ghost">Editar</a>
    @endif
    @if($cita->estado !== 'cancelada')
        <form method="POST" action="{{ route('citas.estado', $cita) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="estado" value="cancelada">
            <button class="btn-danger" onclick="return confirm('¿Cancelar esta cita?')">Cancelar cita</button>
        </form>
    @endif
@endsection

@section('contenido')
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="card card-body space-y-2 text-sm xl:col-span-1">
            <h2 class="font-serif text-lg font-semibold">Detalles</h2>
            <p><span class="text-ink-500">Cliente:</span> {{ $cita->cliente?->nombre }}</p>
            <p><span class="text-ink-500">Servicio:</span> {{ $cita->servicio?->nombre }}</p>
            <p><span class="text-ink-500">Profesional:</span> {{ $cita->estilista?->nombre }}</p>
            <p><span class="text-ink-500">Duración:</span> {{ $cita->duracion_minutos }} min ({{ $cita->fecha_hora->format('H:i') }} - {{ $cita->horaFin()->format('H:i') }})</p>
            <p><span class="text-ink-500">Precio:</span> {{ \App\Support\Money::formato($cita->precio) }}</p>
            <p><span class="text-ink-500">Anticipo:</span> {{ \App\Support\Money::formato($cita->anticipo) }}</p>
            <p><span class="text-ink-500">Saldo:</span> {{ \App\Support\Money::formato($cita->saldo_pendiente) }}</p>
            @if($cita->notas)<p><span class="text-ink-500">Notas:</span> {{ $cita->notas }}</p>@endif

            <form method="POST" action="{{ route('citas.estado', $cita) }}" class="mt-3 flex items-end gap-2">
                @csrf @method('PATCH')
                <div class="flex-1">
                    <label class="label">Cambiar estado</label>
                    <select name="estado" class="field">
                        @foreach(['pendiente','confirmada','en_progreso','completada'] as $est)
                            <option value="{{ $est }}" @selected($cita->estado === $est)>{{ ucfirst(str_replace('_', ' ', $est)) }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn-dark">Aplicar</button>
            </form>

            @if($cita->venta)
                <a href="{{ route('ventas.show', $cita->venta) }}" class="btn-gold mt-3 w-full">Ver venta {{ $cita->venta->numero }}</a>
            @else
                <a href="{{ route('ventas.create', ['cita' => $cita->id]) }}" class="btn-primary mt-3 w-full">Registrar venta / cobro</a>
            @endif
        </div>

        <div class="card card-body xl:col-span-2">
            <h2 class="mb-3 font-serif text-lg font-semibold">Productos consumidos en la cita</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Producto</th><th>Cantidad</th><th>Costo</th><th>Estado</th></tr></thead>
                    <tbody>
                        @forelse($cita->consumos as $consumo)
                            <tr>
                                <td>{{ $consumo->producto?->nombre }}</td>
                                <td>{{ \App\Support\Money::formatoDecimal($consumo->cantidad) }} {{ $consumo->producto?->unidadAbrev() }}</td>
                                <td>{{ \App\Support\Money::formato($consumo->costo_total) }}</td>
                                <td>{{ ucfirst($consumo->estado) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-ink-500">Sin consumos registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <h2 class="mb-3 mt-6 font-serif text-lg font-semibold">Historial del cliente</h2>
            @forelse($historialCliente as $hist)
                <div class="flex items-center justify-between border-b border-cream-100 py-2 text-sm last:border-0">
                    <span>{{ $hist->fecha_hora->format('d/m/Y H:i') }} · {{ $hist->servicio?->nombre }}</span>
                    <span class="text-ink-500">{{\App\Support\Money::formato($hist->precio)}}</span>
                </div>
            @empty
                <p class="text-sm text-ink-500">Sin historial previo.</p>
            @endforelse
        </div>
    </div>
@endsection
