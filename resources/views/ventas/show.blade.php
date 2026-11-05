@extends('layouts.app')

@section('titulo', 'Venta ' . $venta->numero)
@section('subtitulo', $venta->fecha_hora->format('d/m/Y H:i') . ' · ' . ucfirst($venta->estado))
@section('acciones')
    <a href="{{ route('ventas.recibo', $venta) }}" class="btn-ghost">Recibo</a>
    @if($venta->estado !== 'anulada')
        <form method="POST" action="{{ route('ventas.anular', $venta) }}" onsubmit="return confirm('¿Anular la venta? Se revertirá el inventario.')">
            @csrf
            <input type="hidden" name="motivo" value="Anulación desde interfaz">
            <button class="btn-danger">Anular</button>
        </form>
    @endif
@endsection

@section('contenido')
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="card card-body xl:col-span-2">
            <h2 class="mb-3 font-serif text-lg font-semibold">Conceptos</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Descripción</th><th>Tipo</th><th>Cantidad</th><th>Precio</th><th>Desc.</th><th>Subtotal</th></tr></thead>
                    <tbody>
                        @foreach($venta->detalles as $detalle)
                            <tr>
                                <td>{{ $detalle->descripcion }}</td>
                                <td>{{ ucfirst($detalle->tipo) }}</td>
                                <td>{{ \App\Support\Money::formatoDecimal($detalle->cantidad) }}</td>
                                <td>{{ \App\Support\Money::formato($detalle->precio_unitario) }}</td>
                                <td>{{ \App\Support\Money::formato($detalle->descuento) }}</td>
                                <td class="font-medium">{{ \App\Support\Money::formato($detalle->subtotal) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="text-sm">
                        <tr><td colspan="5" class="text-right text-ink-500">Subtotal</td><td>{{ \App\Support\Money::formato($venta->subtotal) }}</td></tr>
                        <tr><td colspan="5" class="text-right text-ink-500">Descuento</td><td>-{{ \App\Support\Money::formato($venta->descuento) }}</td></tr>
                        <tr><td colspan="5" class="text-right font-semibold">Total</td><td class="font-semibold">{{ \App\Support\Money::formato($venta->total) }}</td></tr>
                    </tfoot>
                </table>
            </div>

            @if($venta->consumos->isNotEmpty())
                <h2 class="mb-3 mt-6 font-serif text-lg font-semibold">Consumo de inventario</h2>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Producto</th><th>Cantidad</th><th>Costo</th><th>Estado</th></tr></thead>
                        <tbody>
                            @foreach($venta->consumos as $consumo)
                                <tr>
                                    <td>{{ $consumo->producto?->nombre }}</td>
                                    <td>{{ \App\Support\Money::formatoDecimal($consumo->cantidad) }} {{ $consumo->producto?->unidadAbrev() }}</td>
                                    <td>{{ \App\Support\Money::formato($consumo->costo_total) }}</td>
                                    <td>{{ ucfirst($consumo->estado) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="card card-body">
                <h2 class="mb-3 font-serif text-lg font-semibold">Resumen</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-ink-500">Cliente</dt><dd>{{ $venta->cliente?->nombre ?? 'Consumidor final' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-500">Profesional</dt><dd>{{ $venta->estilista?->nombre ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-500">Total</dt><dd class="font-semibold">{{ \App\Support\Money::formato($venta->total) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-500">Pagado</dt><dd>{{ \App\Support\Money::formato($venta->total_pagado) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-500">Saldo</dt><dd class="{{ $venta->tieneSaldo() ? 'text-red-600' : 'text-green-700' }}">{{ \App\Support\Money::formato($venta->saldo_pendiente) }}</dd></div>
                </dl>
            </div>

            @if($venta->estado !== 'anulada' && $venta->tieneSaldo())
                <div class="card card-body">
                    <h2 class="mb-3 font-serif text-lg font-semibold">Registrar pago</h2>
                    <form method="POST" action="{{ route('ventas.pagos', $venta) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="label">Medio de pago</label>
                            <select name="pagos[0][metodo_pago_id]" class="field" required>
                                @foreach($metodos as $m)<option value="{{ $m->id }}">{{ $m->nombre }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Monto</label>
                            <input name="pagos[0][monto]" type="number" step="0.01" min="0.01" max="{{ $venta->saldo_pendiente }}" value="{{ $venta->saldo_pendiente }}" class="field" required>
                        </div>
                        <div>
                            <label class="label">Referencia</label>
                            <input name="pagos[0][referencia]" class="field">
                        </div>
                        <button class="btn-primary w-full">Guardar pago</button>
                    </form>
                </div>
            @endif

            <div class="card card-body">
                <h2 class="mb-3 font-serif text-lg font-semibold">Pagos</h2>
                @forelse($venta->pagos as $pago)
                    <div class="flex justify-between border-b border-cream-100 py-2 text-sm last:border-0">
                        <span>{{ $pago->metodoPago?->nombre }} · {{ $pago->fecha_hora->format('d/m H:i') }}</span>
                        <span class="font-medium">{{ \App\Support\Money::formato($pago->monto) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-ink-500">Sin pagos registrados.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
