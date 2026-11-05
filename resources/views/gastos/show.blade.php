@extends('layouts.app')

@section('titulo', 'Gasto: ' . $gasto->descripcion)
@section('subtitulo', $gasto->fecha->format('d/m/Y') . ' · ' . ucfirst($gasto->estado))
@section('acciones')
    <a href="{{ route('gastos.edit', $gasto) }}" class="btn-ghost">Editar</a>
    <form method="POST" action="{{ route('gastos.destroy', $gasto) }}" onsubmit="return confirm('¿Eliminar gasto?')">
        @csrf @method('DELETE')
        <input type="hidden" name="motivo" value="Eliminación desde interfaz">
        <button class="btn-danger">Eliminar</button>
    </form>
@endsection

@section('contenido')
    <div class="card card-body max-w-2xl">
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-ink-500">Monto</dt><dd class="font-serif text-xl font-semibold">{{ \App\Support\Money::formato($gasto->monto) }}</dd></div>
            <div class="flex justify-between"><dt class="text-ink-500">Categoría</dt><dd>{{ $gasto->categoria?->nombre ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-ink-500">Proveedor</dt><dd>{{ $gasto->proveedor?->nombre ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-ink-500">Medio de pago</dt><dd>{{ $gasto->metodoPago?->nombre ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-ink-500">Comprobante</dt><dd>{{ $gasto->comprobante ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-ink-500">Registrado por</dt><dd>{{ $gasto->creador?->name ?? '—' }}</dd></div>
            @if($gasto->compra)<div class="flex justify-between"><dt class="text-ink-500">Compra asociada</dt><dd><a href="{{ route('compras.show', $gasto->compra) }}" class="text-blush-600 hover:underline">Ver compra</a></dd></div>@endif
        </dl>
    </div>
@endsection
