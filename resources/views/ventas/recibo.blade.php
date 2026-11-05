<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo {{ $venta->numero }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white p-6 text-ink-900" onload="window.print()">
    <div class="mx-auto max-w-md">
        <div class="text-center">
            <p class="font-serif text-xl font-semibold">Ruby Cristancho Peluquería</p>
            <p class="text-sm text-ink-500">Recibo de venta</p>
        </div>
        <div class="mt-4 border-t border-b border-cream-200 py-3 text-sm">
            <div class="flex justify-between"><span>Número</span><span class="font-mono">{{ $venta->numero }}</span></div>
            <div class="flex justify-between"><span>Fecha</span><span>{{ $venta->fecha_hora->format('d/m/Y H:i') }}</span></div>
            <div class="flex justify-between"><span>Cliente</span><span>{{ $venta->cliente?->nombre ?? 'Consumidor final' }}</span></div>
            <div class="flex justify-between"><span>Atendió</span><span>{{ $venta->estilista?->nombre ?? '—' }}</span></div>
        </div>
        <table class="mt-4 w-full text-sm">
            <thead><tr class="border-b border-cream-200 text-left"><th class="py-1">Descripción</th><th>Cant.</th><th class="text-right">Valor</th></tr></thead>
            <tbody>
                @foreach($venta->detalles as $detalle)
                    <tr class="border-b border-cream-100">
                        <td class="py-1">{{ $detalle->descripcion }}</td>
                        <td>{{ \App\Support\Money::formatoDecimal($detalle->cantidad) }}</td>
                        <td class="text-right">{{ \App\Support\Money::formato($detalle->subtotal) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-3 space-y-1 text-sm">
            <div class="flex justify-between"><span>Subtotal</span><span>{{ \App\Support\Money::formato($venta->subtotal) }}</span></div>
            <div class="flex justify-between"><span>Descuento</span><span>-{{ \App\Support\Money::formato($venta->descuento) }}</span></div>
            <div class="flex justify-between border-t border-cream-200 pt-1 text-base font-semibold"><span>Total</span><span>{{ \App\Support\Money::formato($venta->total) }}</span></div>
            <div class="flex justify-between"><span>Pagado</span><span>{{ \App\Support\Money::formato($venta->total_pagado) }}</span></div>
            <div class="flex justify-between"><span>Saldo</span><span>{{ \App\Support\Money::formato($venta->saldo_pendiente) }}</span></div>
        </div>
        <p class="mt-6 text-center text-xs text-ink-500">¡Gracias por su visita!</p>
    </div>
</body>
</html>
