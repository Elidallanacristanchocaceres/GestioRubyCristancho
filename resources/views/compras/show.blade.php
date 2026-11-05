@extends('layouts.app')

@section('titulo', 'Compra ' . ($compra->numero_factura ?? ('#' . $compra->id)))
@section('subtitulo', ($compra->proveedor?->nombre ?? 'Sin proveedor') . ' · ' . optional($compra->fecha_compra)->format('d/m/Y'))

@section('contenido')
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="card card-body xl:col-span-2">
            <h2 class="mb-3 font-serif text-lg font-semibold">Productos</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Producto</th><th>Cantidad</th><th>Envases</th><th>Precio/env</th><th>Subtotal</th></tr></thead>
                    <tbody>
                        @foreach($compra->detalles as $detalle)
                            <tr>
                                <td>{{ $detalle->producto?->nombre }}</td>
                                <td>{{ \App\Support\Money::formatoDecimal($detalle->cantidad) }} {{ $detalle->producto?->unidadAbrev() }}</td>
                                <td>{{ \App\Support\Money::formatoDecimal($detalle->cantidad_envases) }} × {{ \App\Support\Money::formatoDecimal($detalle->contenido_envase) }}</td>
                                <td>{{ \App\Support\Money::formato($detalle->precio_unitario) }}</td>
                                <td>{{ \App\Support\Money::formato($detalle->subtotal) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="text-sm">
                        <tr><td colspan="4" class="text-right text-ink-500">Subtotal</td><td>{{ \App\Support\Money::formato($compra->subtotal) }}</td></tr>
                        <tr><td colspan="4" class="text-right text-ink-500">Descuento</td><td>-{{ \App\Support\Money::formato($compra->descuento) }}</td></tr>
                        <tr><td colspan="4" class="text-right font-semibold">Total</td><td class="font-semibold">{{ \App\Support\Money::formato($compra->total) }}</td></tr>
                    </tfoot>
                </table>
            </div>
            @if($compra->notas)<p class="mt-3 text-sm text-ink-500">Notas: {{ $compra->notas }}</p>@endif
        </div>

        <div class="space-y-6">
            <div class="card card-body">
                <h2 class="mb-3 font-serif text-lg font-semibold">Resumen</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-ink-500">Total</dt><dd class="font-semibold">{{ \App\Support\Money::formato($compra->total) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-500">Pagado</dt><dd>{{ \App\Support\Money::formato($compra->total_pagado) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-500">Saldo</dt><dd class="{{ \App\Support\Money::mayorQue((string) $compra->saldo_pendiente, '0') ? 'text-red-600' : 'text-green-700' }}">{{ \App\Support\Money::formato($compra->saldo_pendiente) }}</dd></div>
                    @if($compra->gasto)<div class="flex justify-between"><dt class="text-ink-500">Gasto asociado</dt><dd><a href="{{ route('gastos.show', $compra->gasto) }}" class="text-blush-600 hover:underline">Ver</a></dd></div>@endif
                </dl>
            </div>

            @if(\App\Support\Money::mayorQue((string) $compra->saldo_pendiente, '0'))
                <div class="card card-body">
                    <h2 class="mb-3 font-serif text-lg font-semibold">Registrar pago</h2>
                    <form method="POST" action="{{ route('compras.pagos', $compra) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="label">Medio de pago</label>
                            <select name="metodo_pago_id" class="field" required>@foreach($metodos as $m)<option value="{{ $m->id }}">{{ $m->nombre }}</option>@endforeach</select>
                        </div>
                        <div><label class="label">Monto</label><input name="monto" type="number" step="0.01" min="0.01" max="{{ $compra->saldo_pendiente }}" value="{{ $compra->saldo_pendiente }}" class="field" required></div>
                        <div><label class="label">Referencia</label><input name="referencia" class="field"></div>
                        <div><label class="label">Afectar caja</label><select name="afectar_caja" class="field"><option value="1">Sí</option><option value="0">No</option></select></div>
                        <button class="btn-primary w-full">Guardar pago</button>
                    </form>
                </div>
            @endif

            <div class="card card-body">
                <h2 class="mb-3 font-serif text-lg font-semibold">Pagos</h2>
                @forelse($compra->pagos as $pago)
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
