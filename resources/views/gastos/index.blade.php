@extends('layouts.app')

@section('titulo', 'Gastos')
@section('acciones')
    <a href="{{ route('gastos.create') }}" class="btn-primary">Nuevo gasto</a>
@endsection

@section('contenido')
    <form method="GET" class="card card-body mb-4 flex flex-wrap items-end gap-3">
        <div class="min-w-40 flex-1">
            <label class="label">Buscar</label>
            <input name="q" value="{{ request('q') }}" class="field" placeholder="Descripción">
        </div>
        <div>
            <label class="label">Categoría</label>
            <select name="categoria" class="field">
                <option value="">Todas</option>
                @foreach($categorias as $cat)
                    <option value="{{ $cat->id }}" @selected((int) request('categoria') === $cat->id)>{{ $cat->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="label">Desde</label><input type="date" name="desde" value="{{ request('desde') }}" class="field"></div>
        <div><label class="label">Hasta</label><input type="date" name="hasta" value="{{ request('hasta') }}" class="field"></div>
        <div>
            <label class="label">Estado</label>
            <select name="estado" class="field">
                <option value="">Todos</option>
                <option value="pagado" @selected(request('estado') === 'pagado')>Pagado</option>
                <option value="pendiente" @selected(request('estado') === 'pendiente')>Pendiente</option>
            </select>
        </div>
        <button class="btn-dark">Filtrar</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Fecha</th><th>Descripción</th><th>Categoría</th><th>Proveedor</th><th>Medio</th><th>Estado</th><th class="text-right">Monto</th><th></th></tr></thead>
            <tbody>
                @forelse($gastos as $gasto)
                    <tr>
                        <td>{{ $gasto->fecha->format('d/m/Y') }}</td>
                        <td class="font-medium">{{ $gasto->descripcion }}</td>
                        <td>{{ $gasto->categoria?->nombre ?? '—' }}</td>
                        <td>{{ $gasto->proveedor?->nombre ?? '—' }}</td>
                        <td>{{ $gasto->metodoPago?->nombre ?? '—' }}</td>
                        <td><span class="badge {{ $gasto->estaPagado() ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">{{ ucfirst($gasto->estado) }}</span></td>
                        <td class="text-right font-medium">{{ \App\Support\Money::formato($gasto->monto) }}</td>
                        <td class="text-right"><a href="{{ route('gastos.show', $gasto) }}" class="text-blush-600 hover:underline">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-ink-500">No hay gastos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.paginacion', ['paginable' => $gastos])
@endsection
