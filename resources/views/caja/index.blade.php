@extends('layouts.app')

@section('titulo', 'Caja diaria')
@section('acciones')
    @if(! $cajaActiva)
        <a href="{{ route('caja.create') }}" class="btn-primary">Abrir caja</a>
    @endif
@endsection

@section('contenido')
    @if($cajaActiva)
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="card card-body xl:col-span-2">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-serif text-lg font-semibold">Caja abierta</h2>
                        <p class="text-sm text-ink-500">Apertura: {{ $cajaActiva->fecha_apertura->format('d/m/Y H:i') }} · Saldo inicial {{ \App\Support\Money::formato($cajaActiva->saldo_inicial) }}</p>
                    </div>
                    <a href="{{ route('caja.show', $cajaActiva) }}" class="btn-dark">Cerrar caja</a>
                </div>

                <h3 class="mb-2 text-sm font-semibold text-ink-700">Saldos por medio de pago</h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($saldosMetodo as $saldo)
                        <div class="rounded-xl border border-cream-200 p-3">
                            <p class="text-xs text-ink-500">{{ $saldo['metodo']->nombre }}</p>
                            <p class="font-serif text-lg font-semibold">{{ \App\Support\Money::formato($saldo['esperado']) }}</p>
                        </div>
                    @endforeach
                </div>

                <h3 class="mb-2 mt-6 text-sm font-semibold text-ink-700">Movimiento manual</h3>
                <form method="POST" action="{{ route('caja.movimiento') }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div><label class="label">Tipo</label><select name="tipo" class="field"><option value="ingreso">Ingreso</option><option value="egreso">Egreso</option></select></div>
                    <div class="w-32"><label class="label">Monto</label><input name="monto" type="number" step="0.01" min="0.01" class="field" required></div>
                    <div><label class="label">Medio</label><select name="metodo_pago_id" class="field">@foreach($saldosMetodo as $s)<option value="{{ $s['metodo']->id }}">{{ $s['metodo']->nombre }}</option>@endforeach</select></div>
                    <div class="min-w-40 flex-1"><label class="label">Concepto</label><input name="concepto" class="field" required></div>
                    <button class="btn-dark">Registrar</button>
                </form>
            </div>

            <div class="card card-body">
                <h2 class="mb-3 font-serif text-lg font-semibold">Movimientos de hoy</h2>
                @forelse($movimientos as $mov)
                    <div class="flex items-center justify-between border-b border-cream-100 py-2 text-sm last:border-0">
                        <div>
                            <p class="font-medium">{{ $mov->concepto }}</p>
                            <p class="text-xs text-ink-500">{{ $mov->created_at->format('H:i') }} · {{ $mov->metodoPago?->nombre }} · {{ $mov->creador?->name }}</p>
                        </div>
                        <span class="{{ $mov->tipo === 'ingreso' ? 'text-green-700' : 'text-red-600' }}">
                            {{ $mov->tipo === 'ingreso' ? '+' : '-' }}{{ \App\Support\Money::formato($mov->monto) }}
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-ink-500">Sin movimientos.</p>
                @endforelse
                @include('partials.paginacion', ['paginable' => $movimientos])
            </div>
        </div>
    @else
        <div class="card card-body text-center">
            <p class="text-ink-500">No hay una caja abierta.</p>
            <a href="{{ route('caja.create') }}" class="btn-primary mx-auto mt-3">Abrir caja</a>
        </div>
    @endif

    <div class="mt-6 card card-body">
        <h2 class="mb-3 font-serif text-lg font-semibold">Historial de cierres</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Apertura</th><th>Cierre</th><th>Inicial</th><th>Ingresos</th><th>Egresos</th><th>Esperado</th><th>Real</th><th>Diferencia</th><th></th></tr></thead>
                <tbody>
                    @forelse($historial as $caja)
                        <tr>
                            <td>{{ $caja->fecha_apertura->format('d/m/Y H:i') }}</td>
                            <td>{{ optional($caja->fecha_cierre)->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>{{ \App\Support\Money::formato($caja->saldo_inicial) }}</td>
                            <td>{{ \App\Support\Money::formato($caja->total_ingresos) }}</td>
                            <td>{{ \App\Support\Money::formato($caja->total_egresos) }}</td>
                            <td>{{ \App\Support\Money::formato($caja->saldo_final_esperado) }}</td>
                            <td>{{ \App\Support\Money::formato($caja->saldo_final_real) }}</td>
                            <td class="{{ \App\Support\Money::menorQue((string) $caja->diferencia, '0') ? 'text-red-600' : 'text-green-700' }}">{{ \App\Support\Money::formato($caja->diferencia) }}</td>
                            <td class="text-right"><a href="{{ route('caja.show', $caja) }}" class="text-blush-600 hover:underline">Ver</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-ink-500">Sin cierres registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.paginacion', ['paginable' => $historial])
    </div>
@endsection
