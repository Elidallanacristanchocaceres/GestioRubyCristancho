@extends('layouts.app')

@section('titulo', 'Panel de control')
@section('subtitulo', 'Resumen general de ' . config('app.name'))
@section('acciones')
    <a href="{{ route('ventas.create') }}" class="btn-primary">Nueva venta</a>
    <a href="{{ route('consumos.create') }}" class="btn-ghost">Nuevo registro diario</a>
@endsection

@section('contenido')
    @php
        $maxSerie = collect($serie)->max(fn ($d) => (float) $d['ventas']) ?: 1;
    @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat-card">
            <p class="text-sm text-ink-500">Ventas de hoy</p>
            <p class="font-serif text-2xl font-semibold text-ink-900">{{ \App\Support\Money::formato($ventas_hoy) }}</p>
            <p class="text-xs text-ink-500">Cobrado: {{ \App\Support\Money::formato($ingresos_hoy) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-sm text-ink-500">Ventas del mes</p>
            <p class="font-serif text-2xl font-semibold text-ink-900">{{ \App\Support\Money::formato($ventas_mes) }}</p>
            <p class="text-xs text-ink-500">Cobrado: {{ \App\Support\Money::formato($ingresos_mes) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-sm text-ink-500">Saldo en caja</p>
            <p class="font-serif text-2xl font-semibold {{ $caja ? 'text-green-700' : 'text-ink-500' }}">{{ \App\Support\Money::formato($saldo_caja) }}</p>
            <p class="text-xs text-ink-500">{{ $caja ? 'Caja abierta desde ' . $caja->fecha_apertura->format('d/m H:i') : 'Sin caja abierta' }}</p>
        </div>
        <div class="stat-card">
            <p class="text-sm text-ink-500">Utilidad estimada del mes</p>
            <p class="font-serif text-2xl font-semibold {{ \App\Support\Money::mayorQue($utilidad_mes, '0') ? 'text-green-700' : 'text-red-600' }}">{{ \App\Support\Money::formato($utilidad_mes) }}</p>
            <p class="text-xs text-ink-500">Ganancia bruta: {{ \App\Support\Money::formato($ganancia_mes) }}</p>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="card card-body xl:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-serif text-lg font-semibold">Ventas de los últimos 14 días</h2>
                <span class="text-xs text-ink-500">Máximo: {{ \App\Support\Money::formato($maxSerie) }}</span>
            </div>
            <div class="flex h-48 items-end gap-1.5">
                @foreach($serie as $dia)
                    @php
                        $altura = $maxSerie > 0 ? (float) $dia['ventas'] / $maxSerie * 100 : 0;
                    @endphp
                    <div class="group flex flex-1 flex-col items-center justify-end" title="{{ $dia['label'] }}: {{ \App\Support\Money::formato($dia['ventas']) }}">
                        <div class="w-full rounded-t bg-blush-400 transition group-hover:bg-blush-600" style="height: {{ max(2, $altura) }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex justify-between text-[10px] text-ink-500">
                <span>{{ $serie[0]['label'] ?? '' }}</span>
                <span>{{ end($serie)['label'] ?? '' }}</span>
            </div>
        </div>

        <div class="card card-body">
            <h2 class="mb-4 font-serif text-lg font-semibold">Resumen financiero del mes</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-ink-500">Ventas realizadas</dt><dd class="font-semibold">{{ \App\Support\Money::formato($estado_resultados_mes['ventas']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-ink-500">Consumo de inventario</dt><dd>-{{ \App\Support\Money::formato($estado_resultados_mes['consumo_inventario']) }}</dd></div>
                <div class="flex justify-between border-t border-cream-200 pt-2"><dt class="text-ink-500">Ganancia bruta</dt><dd class="font-semibold">{{ \App\Support\Money::formato($estado_resultados_mes['ganancia_bruta']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-ink-500">Gastos pagados</dt><dd>-{{ \App\Support\Money::formato($estado_resultados_mes['gastos']) }}</dd></div>
                <div class="flex justify-between border-t border-cream-200 pt-2 text-base"><dt class="font-semibold">Utilidad neta</dt><dd class="font-semibold {{ \App\Support\Money::mayorQue($estado_resultados_mes['utilidad_neta'], '0') ? 'text-green-700' : 'text-red-600' }}">{{ \App\Support\Money::formato($estado_resultados_mes['utilidad_neta']) }}</dd></div>
                <div class="flex justify-between text-xs text-ink-500"><dt>Cartera por cobrar</dt><dd>{{ \App\Support\Money::formato($por_cobrar) }}</dd></div>
                <div class="flex justify-between text-xs text-ink-500"><dt>Cartera por pagar</dt><dd>{{ \App\Support\Money::formato($por_pagar) }}</dd></div>
            </dl>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="card card-body">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-serif text-lg font-semibold">Registro diario de hoy ({{ $registros_hoy }})</h2>
                <a href="{{ route('consumos.index') }}" class="text-sm text-blush-600 hover:underline">Ver registros</a>
            </div>
            <div class="mb-3 flex gap-5 text-sm">
                <div><p class="text-xs text-ink-500">Cobrado</p><p class="font-semibold">{{ \App\Support\Money::formato($registros_hoy_ingresos) }}</p></div>
                <div><p class="text-xs text-ink-500">Costo productos</p><p class="font-semibold">{{ \App\Support\Money::formato($registros_hoy_costo) }}</p></div>
            </div>
            @forelse($registros_hoy_lista as $registro)
                <div class="flex items-center justify-between border-b border-cream-100 py-2 last:border-0">
                    <div>
                        <p class="text-sm font-medium">{{ $registro->cliente?->nombre ?? 'Sin cliente' }}</p>
                        <p class="text-xs text-ink-500">{{ $registro->descripcion ?? $registro->servicio?->nombre ?? 'Sin detalle' }}@if($registro->estilista) · {{ $registro->estilista->nombre }}@endif</p>
                    </div>
                    <a href="{{ route('consumos.show', $registro) }}" class="text-sm font-medium text-blush-600 hover:underline">{{ \App\Support\Money::formato($registro->precio_cobrado) }}</a>
                </div>
            @empty
                <p class="text-sm text-ink-500">No hay registros para hoy.</p>
            @endforelse
        </div>

        <div class="card card-body">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-serif text-lg font-semibold">Alertas de inventario</h2>
                <a href="{{ route('productos.index') }}" class="text-sm text-blush-600 hover:underline">Ver inventario</a>
            </div>
            @php($alertas = $productos_agotados->map(fn ($p) => ['p' => $p, 'tipo' => 'agotado'])->merge($productos_bajos->map(fn ($p) => ['p' => $p, 'tipo' => 'bajo'])))
            @forelse($alertas as $alerta)
                <div class="flex items-center justify-between border-b border-cream-100 py-2 last:border-0">
                    <div>
                        <p class="text-sm font-medium">{{ $alerta['p']->nombre }}</p>
                        <p class="text-xs text-ink-500">Stock: {{ \App\Support\Money::formatoDecimal($alerta['p']->stock) }} {{ $alerta['p']->unidadAbrev() }} / mín {{ \App\Support\Money::formatoDecimal($alerta['p']->stock_minimo) }}</p>
                    </div>
                    <span class="badge {{ $alerta['tipo'] === 'agotado' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">
                        {{ $alerta['tipo'] === 'agotado' ? 'Agotado' : 'Bajo' }}
                    </span>
                </div>
            @empty
                <p class="text-sm text-ink-500">Todo el inventario está en niveles adecuados.</p>
            @endforelse

            @if($productos_vencen->isNotEmpty())
                <h3 class="mt-4 text-sm font-semibold text-ink-700">Próximos a vencer</h3>
                @foreach($productos_vencen as $p)
                    <div class="flex items-center justify-between py-1 text-sm">
                        <span>{{ $p->nombre }}</span>
                        <span class="text-xs text-amber-700">{{ $p->fecha_vencimiento->format('d/m/Y') }}</span>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
@endsection
