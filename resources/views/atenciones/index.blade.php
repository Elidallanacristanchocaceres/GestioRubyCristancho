@extends('layouts.app')

@section('titulo', 'Registro de clientes')
@section('subtitulo', 'Qué se le realizó a cada cliente y el total del día')
@section('acciones')
    <a href="{{ route('atenciones.create') }}" class="btn-primary">Nueva atención</a>
    <a href="{{ route('consumos.index') }}" class="btn-ghost">Registro de productos</a>
    <a href="{{ route('clientes.index') }}" class="btn-ghost">Clientes</a>
@endsection

@section('contenido')
    <div class="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="stat-card">
            <p class="text-sm text-ink-500">Atenciones</p>
            <p class="font-serif text-2xl font-semibold">{{ $totalRegistros }}</p>
            <p class="text-xs text-ink-500">{{ \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y') }} a {{ \Illuminate\Support\Carbon::parse($hasta)->format('d/m/Y') }}</p>
        </div>
        <div class="stat-card">
            <p class="text-sm text-ink-500">Facturado</p>
            <p class="font-serif text-2xl font-semibold">{{ \App\Support\Money::formato($totalFacturado) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-sm text-ink-500">Pagado</p>
            <p class="font-serif text-2xl font-semibold text-green-700">{{ \App\Support\Money::formato($totalPagado) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-sm text-ink-500">Saldo pendiente</p>
            <p class="font-serif text-2xl font-semibold {{ \App\Support\Money::mayorQue($totalSaldo, '0') ? 'text-amber-600' : '' }}">{{ \App\Support\Money::formato($totalSaldo) }}</p>
        </div>
    </div>

    <form method="GET" class="card card-body mb-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-12">
            <div class="lg:col-span-2">
                <label class="label">Período</label>
                <select name="periodo" class="field">
                    <option value="dia" @selected($periodo === 'dia')>Día</option>
                    <option value="semana" @selected($periodo === 'semana')>Semana</option>
                    <option value="mes" @selected($periodo === 'mes')>Mes</option>
                    <option value="rango" @selected($periodo === 'rango')>Rango</option>
                    <option value="todo" @selected($periodo === 'todo')>Todos</option>
                </select>
            </div>
            <div class="lg:col-span-2">
                <label class="label">Desde</label>
                <input type="date" name="desde" value="{{ request('desde', $desde) }}" class="field">
            </div>
            <div class="lg:col-span-2">
                <label class="label">Hasta</label>
                <input type="date" name="hasta" value="{{ request('hasta', $hasta) }}" class="field">
            </div>
            <div class="lg:col-span-2">
                <label class="label">Cliente</label>
                <select name="cliente" class="field">
                    <option value="">Todos</option>
                    @foreach($clientes as $c)
                        <option value="{{ $c->id }}" @selected((int) request('cliente') === $c->id)>{{ $c->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="lg:col-span-2">
                <label class="label">Profesional</label>
                <select name="estilista" class="field">
                    <option value="">Todos</option>
                    @foreach($estilistas as $e)
                        <option value="{{ $e->id }}" @selected((int) request('estilista') === $e->id)>{{ $e->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="lg:col-span-2 flex items-end gap-2 pb-1">
                <button class="btn-dark">Filtrar</button>
                <a href="{{ route('atenciones.index') }}" class="btn-ghost">Hoy</a>
            </div>
        </div>
    </form>

    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-cream-200 px-4 py-3">
            <div>
                <h2 class="font-serif text-lg font-semibold">Atenciones guardadas</h2>
                <p class="text-xs text-ink-500">
                    @if($periodo === 'todo')
                        Mostrando todas las atenciones del salón
                    @else
                        Del {{ \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($hasta)->format('d/m/Y') }}
                    @endif
                </p>
            </div>
            @if($periodo !== 'todo' && $totalRegistros === 0 && $hayRegistrosTotales)
                <a href="{{ route('atenciones.index', ['periodo' => 'todo']) }}" class="btn-sm btn-primary">Ver todas</a>
            @endif
        </div>
        <div class="table-wrap border-0 rounded-none">
            <table class="table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Servicios</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Pagado</th>
                        <th>Estado</th>
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($atenciones as $atencion)
                        <tr>
                            <td class="whitespace-nowrap">{{ $atencion->fecha->format('d/m/Y') }}</td>
                            <td>
                                <span class="font-medium">{{ $atencion->cliente?->nombre ?? 'Sin cliente' }}</span>
                                @if($atencion->estilista)<span class="block text-xs text-ink-500">{{ $atencion->estilista->nombre }}</span>@endif
                            </td>
                            <td class="text-xs text-ink-600">
                                {{ $atencion->detalles->map(fn ($d) => $d->etiqueta())->take(3)->implode(' · ') ?: ($atencion->descripcion ?? '—') }}
                                @if($atencion->detalles->count() > 3)<span class="text-ink-400"> +{{ $atencion->detalles->count() - 3 }}</span>@endif
                            </td>
                            <td class="text-right font-medium">{{ \App\Support\Money::formato($atencion->total) }}</td>
                            <td class="text-right">{{ \App\Support\Money::formato($atencion->pagado) }}</td>
                            <td>
                                @if(! $atencion->esActivo())
                                    <span class="badge bg-ink-100 text-ink-600">Anulada</span>
                                @elseif($atencion->tieneSaldo())
                                    <span class="badge bg-amber-100 text-amber-700">Con saldo</span>
                                @else
                                    <span class="badge bg-green-100 text-green-700">Pagada</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('atenciones.show', $atencion) }}" class="btn-link">Ver</a>
                                @if($atencion->esActivo())
                                    <a href="{{ route('atenciones.edit', $atencion) }}" class="btn-link">Editar</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-ink-500">
                                <p>No hay atenciones registradas en este período.</p>
                                <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
                                    @if($periodo !== 'todo' && $hayRegistrosTotales)
                                        <a href="{{ route('atenciones.index', ['periodo' => 'todo']) }}" class="btn-sm btn-ghost">Ver todas</a>
                                    @endif
                                    <a href="{{ route('atenciones.create') }}" class="btn-sm btn-primary">Registrar atención</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $atenciones->links() }}</div>
@endsection
