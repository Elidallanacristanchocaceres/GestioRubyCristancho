@extends('layouts.app')

@section('titulo', 'Caja del ' . $caja->fecha_apertura->format('d/m/Y'))
@section('subtitulo', ucfirst($caja->estado))
@section('acciones')
    @if($caja->estaAbierta())
        <a href="{{ route('caja.index') }}" class="btn-ghost">Movimientos</a>
    @else
        <form method="POST" action="{{ route('caja.reabrir', $caja) }}" onsubmit="return confirm('¿Reabrir la caja?')">
            @csrf
            <input type="hidden" name="motivo" value="Reapertura desde interfaz">
            <button class="btn-ghost">Reabrir caja</button>
        </form>
    @endif
@endsection

@section('contenido')
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="card card-body xl:col-span-2">
            <h2 class="mb-3 font-serif text-lg font-semibold">Movimientos</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Hora</th><th>Concepto</th><th>Medio</th><th>Tipo</th><th>Monto</th></tr></thead>
                    <tbody>
                        @forelse($caja->movimientos as $mov)
                            <tr>
                                <td>{{ $mov->created_at->format('d/m H:i') }}</td>
                                <td>{{ $mov->concepto }}</td>
                                <td>{{ $mov->metodoPago?->nombre ?? '—' }}</td>
                                <td>{{ ucfirst($mov->tipo) }}</td>
                                <td class="{{ $mov->tipo === 'ingreso' ? 'text-green-700' : 'text-red-600' }}">{{ \App\Support\Money::formato($mov->monto) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-ink-500">Sin movimientos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card card-body">
                <h2 class="mb-3 font-serif text-lg font-semibold">Resumen</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-ink-500">Saldo inicial</dt><dd>{{ \App\Support\Money::formato($caja->saldo_inicial) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-500">Ingresos</dt><dd>{{ \App\Support\Money::formato($caja->total_ingresos) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-500">Egresos</dt><dd>{{ \App\Support\Money::formato($caja->total_egresos) }}</dd></div>
                    <div class="flex justify-between border-t border-cream-200 pt-2"><dt class="text-ink-500">Esperado</dt><dd class="font-semibold">{{ \App\Support\Money::formato($caja->saldo_final_esperado) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-500">Real</dt><dd class="font-semibold">{{ \App\Support\Money::formato($caja->saldo_final_real) }}</dd></div>
                    <div class="flex justify-between text-base"><dt class="font-semibold">Diferencia</dt><dd class="font-semibold {{ \App\Support\Money::menorQue((string) $caja->diferencia, '0') ? 'text-red-600' : 'text-green-700' }}">{{ \App\Support\Money::formato($caja->diferencia) }}</dd></div>
                </dl>
                @if($caja->notas_cierre)<p class="mt-3 text-xs text-ink-500">Notas: {{ $caja->notas_cierre }}</p>@endif
            </div>

            @if($caja->estaAbierta())
                <div class="card card-body">
                    <h2 class="mb-3 font-serif text-lg font-semibold">Cerrar caja (conteo físico)</h2>
                    <form method="POST" action="{{ route('caja.cerrar', $caja) }}" class="space-y-3">
                        @csrf
                        @foreach($saldosMetodo as $saldo)
                            <div>
                                <label class="label">{{ $saldo['metodo']->nombre }} <span class="text-xs text-ink-500">(esperado {{ \App\Support\Money::formato($saldo['esperado']) }})</span></label>
                                <input name="conteos[{{ $saldo['metodo']->id }}]" type="number" step="0.01" min="0" value="{{ $saldo['esperado'] }}" class="field" required>
                            </div>
                        @endforeach
                        <div>
                            <label class="label">Notas de cierre</label>
                            <textarea name="notas_cierre" class="field" rows="2"></textarea>
                        </div>
                        <button class="btn-primary w-full">Cerrar caja</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endsection
