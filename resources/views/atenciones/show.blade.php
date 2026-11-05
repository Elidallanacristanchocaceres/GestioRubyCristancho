@extends('layouts.app')

@section('titulo', 'Atención #' . $atencion->id)
@section('subtitulo', ($atencion->cliente?->nombre ?? 'Sin cliente') . ' · ' . $atencion->fecha->format('d/m/Y'))

@section('acciones')
    @if($atencion->esActivo())
        <a href="{{ route('atenciones.edit', $atencion) }}" class="btn-primary">Editar</a>
        <form method="POST" action="{{ route('atenciones.anular', $atencion) }}" onsubmit="return confirm('¿Anular esta atención?')">
            @csrf
            <button class="btn-ghost">Anular</button>
        </form>
    @endif
    <a href="{{ route('atenciones.index') }}" class="btn-ghost">Volver</a>
@endsection

@section('contenido')
    <div class="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="stat-card"><p class="text-sm text-ink-500">Total</p><p class="font-serif text-2xl font-semibold">{{ \App\Support\Money::formato($atencion->total) }}</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Pagado</p><p class="font-serif text-2xl font-semibold text-green-700">{{ \App\Support\Money::formato($atencion->pagado) }}</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Saldo</p><p class="font-serif text-2xl font-semibold {{ \App\Support\Money::mayorQue($saldo, '0') ? 'text-amber-600' : '' }}">{{ \App\Support\Money::formato($saldo) }}</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Estado</p><p class="pt-1"><span class="badge {{ $atencion->esActivo() ? 'bg-green-100 text-green-700' : 'bg-ink-100 text-ink-600' }}">{{ $atencion->estadoLabel() }}</span></p></div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="card card-body lg:col-span-2">
            <h2 class="mb-4 font-serif text-lg font-semibold">Servicios realizados</h2>
            <div class="table-wrap border-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Servicio</th>
                            <th class="text-right">Precio</th>
                            <th class="text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($atencion->detalles as $d)
                            <tr>
                                <td>
                                    {{ $d->etiqueta() }}
                                    @if(! $d->servicio_id)<span class="badge ml-2 bg-cream-100 text-ink-600">Manual</span>@endif
                                    @if($d->descripcion)<span class="block text-xs text-ink-500">{{ $d->descripcion }}</span>@endif
                                </td>
                                <td class="text-right">{{ \App\Support\Money::formato($d->precio_unitario) }}</td>
                                <td class="text-right font-medium">{{ \App\Support\Money::formato($d->subtotal) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-6 text-center text-ink-500">Sin servicios registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex justify-end border-t border-cream-200 pt-4">
                <span class="text-sm text-ink-500">Total:</span>
                <span class="ml-2 font-serif text-2xl font-semibold">{{ \App\Support\Money::formato($atencion->total) }}</span>
            </div>
        </div>

        <div class="card card-body space-y-3 text-sm">
            <h2 class="font-serif text-lg font-semibold">Detalles</h2>
            <div class="flex justify-between"><span class="text-ink-500">Cliente</span><span class="font-medium">{{ $atencion->cliente?->nombre ?? '—' }}</span></div>
            <div class="flex justify-between"><span class="text-ink-500">Profesional</span><span>{{ $atencion->estilista?->nombre ?? '—' }}</span></div>
            <div class="flex justify-between"><span class="text-ink-500">Método de pago</span><span>{{ $atencion->metodo_pago ?? '—' }}</span></div>
            <div class="flex justify-between"><span class="text-ink-500">Cita vinculada</span><span>{{ $atencion->cita?->id ? '#' . $atencion->cita->id : '—' }}</span></div>
            <div class="flex justify-between"><span class="text-ink-500">Registrado por</span><span>{{ $atencion->usuario?->name ?? '—' }}</span></div>
            @if($atencion->descripcion)
                <div><p class="text-ink-500">Descripción</p><p>{{ $atencion->descripcion }}</p></div>
            @endif
            @if($atencion->observaciones)
                <div><p class="text-ink-500">Observaciones</p><p>{{ $atencion->observaciones }}</p></div>
            @endif
        </div>
    </div>
@endsection
