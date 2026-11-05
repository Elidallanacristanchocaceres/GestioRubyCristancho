@extends('layouts.app')

@section('titulo', $producto->nombre)
@section('subtitulo', ($producto->categoria?->nombre ?? 'Sin categoría') . ' · ' . ($producto->marca ?? 'Sin marca') . ($producto->linea ? ' · ' . $producto->linea : '') . ($producto->tono ? ' · Tono ' . $producto->tono : ''))
@section('acciones')
    @if($producto->pendiente_confirmacion)
        <span class="badge bg-amber-100 text-amber-700">Pendiente de confirmación</span>
    @endif
    <a href="{{ route('productos.entrada', ['producto_id' => $producto->id]) }}" class="btn-primary">Registrar entrada de inventario</a>
    <a href="{{ route('productos.edit', $producto) }}" class="btn-ghost">Editar</a>
@endsection

@section('contenido')
    @php($porMedir = $producto->lotes->where('medido', false))
    <div class="mb-4 flex flex-wrap gap-x-6 gap-y-1 text-sm text-ink-600">
        <span><span class="text-ink-400">Código/Ref.:</span> {{ $producto->codigo ?? '—' }}</span>
        <span><span class="text-ink-400">Tono:</span> {{ $producto->tono ?? '—' }}</span>
        <span><span class="text-ink-400">Presentación:</span> {{ $producto->presentacionLabel() }}</span>
        <span><span class="text-ink-400">Unidad de control:</span> {{ $producto->unidadLabel() }}</span>
        <span><span class="text-ink-400">Proveedor:</span> {{ $producto->proveedor?->nombre ?? '—' }}</span>
        <span><span class="text-ink-400">Última compra:</span> {{ optional($producto->fecha_compra)->format('d/m/Y') ?? '—' }}</span>
    </div>

    @if($porMedir->isNotEmpty())
        <div class="mb-4 rounded-lg border border-sky-200 bg-sky-50 p-4">
            <p class="font-semibold text-sky-900">Envases pendientes de medición ({{ $porMedir->count() }})</p>
            <p class="mt-1 text-sm text-sky-800">
                Se registran sin sumar al stock porque no se puede suponer cuánto queda dentro.
                Pesa cada uno y escribe lo que queda: recién ahí entra a las existencias.
            </p>
            <div class="mt-3 space-y-2">
                @foreach($porMedir as $lote)
                    <form method="POST" action="{{ route('productos.medir', $producto) }}" class="flex flex-wrap items-end gap-2 rounded border border-sky-200 bg-white p-3">
                        @csrf
                        <input type="hidden" name="lote_id" value="{{ $lote->id }}">
                        <div class="min-w-40 flex-1">
                            <p class="text-xs font-medium text-ink-700">Lote #{{ $lote->id }}</p>
                            <p class="text-xs text-ink-500">
                                {{ $lote->envases > 0 ? \App\Support\Money::formatoDecimal($lote->envases) . ' envase(s)' : 'SinContainers' }}
                                · {{ $lote->estadoEnvaseLabel() }}
                                @if($lote->notas) · {{ \Illuminate\Support\Str::limit($lote->notas, 70) }}@endif
                            </p>
                        </div>
                        <div>
                            <label class="label">Contenido medido ({{ $producto->unidadAbrev() }})</label>
                            <input name="cantidad" type="number" step="0.001" min="0" class="field w-40" required>
                        </div>
                        <button class="btn-dark">Registrar medición</button>
                    </form>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat-card"><p class="text-sm text-ink-500">Stock actual</p><p class="font-serif text-2xl font-semibold {{ $producto->estaAgotado() ? 'text-red-600' : '' }}">{{ \App\Support\Money::formatoDecimal($producto->stock) }} {{ $producto->unidadAbrev() }}</p><p class="text-xs text-ink-500">Mínimo: {{ \App\Support\Money::formatoDecimal($producto->stock_minimo) }}</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Costo por gramo/ml</p><p class="font-serif text-2xl font-semibold">{{ \App\Support\Money::formatoDecimal($producto->costo_unitario, 4) }}</p><p class="text-xs text-ink-500">Compra por envase: {{ \App\Support\Money::formato($producto->costo_envase) }} / {{ \App\Support\Money::formatoDecimal($producto->contenido_envase) }} {{ $producto->unidadAbrev() }}</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Precio de venta</p><p class="font-serif text-2xl font-semibold">{{ \App\Support\Money::formato($producto->precio_venta) }}</p><p class="text-xs text-ink-500">Sugerido: {{ \App\Support\Money::formato($producto->precio_sugerido) }}</p></div>
        <div class="stat-card"><p class="text-sm text-ink-500">Valor en inventario</p><p class="font-serif text-2xl font-semibold">{{ \App\Support\Money::formato(\App\Support\Money::mul((string) $producto->costo_unitario, (string) $producto->stock)) }}</p><p class="text-xs text-ink-500">{{ $producto->lotes->count() }} lote(s)</p></div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="card card-body xl:col-span-2">
            <h2 class="mb-3 font-serif text-lg font-semibold">Kardex de movimientos</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Fecha</th><th>Tipo</th><th>Cantidad</th><th>Stock</th><th>Costo</th><th>Motivo</th><th>Usuario</th></tr></thead>
                    <tbody>
                        @forelse($movimientos as $mov)
                            <tr>
                                <td class="whitespace-nowrap">{{ $mov->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ ucfirst($mov->tipo) }}</td>
                                <td class="{{ \App\Support\Money::menorQue((string) $mov->cantidad, '0', \App\Support\Money::ESCALA_CANTIDAD) ? 'text-red-600' : 'text-green-700' }}">{{ \App\Support\Money::formatoDecimal($mov->cantidad) }}</td>
                                <td>{{ \App\Support\Money::formatoDecimal($mov->stock_nuevo) }}</td>
                                <td>{{ \App\Support\Money::formato($mov->costo_total) }}</td>
                                <td class="text-xs">{{ $mov->motivo }}</td>
                                <td class="text-xs">{{ $mov->usuario?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-ink-500">Sin movimientos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('partials.paginacion', ['paginable' => $movimientos])
        </div>

        <div class="space-y-6">
            <div class="card card-body">
                <h2 class="mb-3 font-serif text-lg font-semibold">Lotes (FIFO)</h2>
                @forelse($producto->lotes as $lote)
                    <div class="border-b border-cream-100 py-2 text-sm last:border-0">
                        <div class="flex justify-between gap-2">
                            <span class="font-medium">Lote #{{ $lote->id }}</span>
                            <span class="text-right">
                                {{ \App\Support\Money::formatoDecimal($lote->cantidad_disponible) }} / {{ \App\Support\Money::formatoDecimal($lote->cantidad_inicial) }}
                                @if($lote->envases > 0)
                                    <span class="block text-xs text-ink-500">{{ \App\Support\Money::formatoDecimal($lote->envases) }} envase(s)</span>
                                @endif
                            </span>
                        </div>
                        <p class="text-xs text-ink-500">
                            Ingreso {{ optional($lote->fecha_ingreso)->format('d/m/Y') }} · costo {{ \App\Support\Money::formatoDecimal($lote->costo_unitario, 4) }}
                        </p>
                        @if(! $lote->medido)
                            <span class="mt-1 inline-block rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-semibold text-sky-700">{{ $lote->estadoEnvaseLabel() }}</span>
                        @elseif($lote->esEnvaseEmpezado())
                            <span class="mt-1 inline-block rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">Empezado</span>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-ink-500">Sin lotes registrados.</p>
                @endforelse
            </div>

            <div class="card card-body" id="consumo">
                <h2 class="mb-3 font-serif text-lg font-semibold">Registrar consumo</h2>
                <p class="mb-3 text-sm text-ink-500">Descuenta solo lo utilizado (gramos, ml o unidades), no el envase completo.</p>
                <form method="POST" action="{{ route('productos.consumo', $producto) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="label">Cantidad utilizada</label>
                        <input name="cantidad" type="number" step="0.001" min="0.001" class="field" placeholder="Ej: 35" required>
                    </div>
                    <div>
                        <label class="label">Motivo o servicio relacionado</label>
                        <input name="motivo" class="field" required placeholder="Ej: decoloración de María, merma, uso en servicio">
                    </div>
                    <button class="btn-gold w-full">Registrar consumo</button>
                </form>
            </div>

            <div class="card card-body" id="ajuste">
                <h2 class="mb-3 font-serif text-lg font-semibold">Ajustar inventario</h2>
                <form method="POST" action="{{ route('productos.ajustar', $producto) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="label">Nueva cantidad real</label>
                        <input name="nueva_cantidad" type="number" step="0.001" min="0" value="{{ \App\Support\Money::formatoDecimal($producto->stock) }}" class="field" required>
                    </div>
                    <div>
                        <label class="label">Motivo del ajuste</label>
                        <input name="motivo" class="field" required placeholder="Ej: conteo físico, merma, corrección">
                    </div>
                    <button class="btn-dark w-full">Registrar ajuste</button>
                </form>
            </div>
        </div>
    </div>
@endsection
