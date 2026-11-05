@extends('layouts.app')

@section('titulo', $cliente->nombre)
@section('subtitulo', 'Cliente desde ' . $cliente->created_at->format('d/m/Y'))
@section('acciones')
    <a href="{{ route('clientes.edit', $cliente) }}" class="btn-ghost">Editar</a>
    <a href="{{ route('consumos.create', ['cliente' => $cliente->id]) }}" class="btn-primary">Registrar consumo</a>
@endsection

@section('contenido')
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="card card-body space-y-2 text-sm">
            <h2 class="font-serif text-lg font-semibold">Datos de contacto</h2>
            <p><span class="text-ink-500">Celular:</span> {{ $cliente->celular }}</p>
            <p><span class="text-ink-500">WhatsApp:</span> {{ $cliente->whatsapp ?? '—' }}</p>
            <p><span class="text-ink-500">Correo:</span> {{ $cliente->email ?? '—' }}</p>
            <p><span class="text-ink-500">Nacimiento:</span> {{ optional($cliente->fecha_nacimiento)->format('d/m/Y') ?? '—' }} @if($cliente->edad) ({{ $cliente->edad }} años)@endif</p>
            <p><span class="text-ink-500">Dirección:</span> {{ $cliente->direccion ?? '—' }}</p>
            <p><span class="text-ink-500">Saldo pendiente:</span> {{ \App\Support\Money::formato($cliente->saldo_pendiente) }}</p>
            @if($cliente->preferencias_cabello)<p><span class="text-ink-500">Preferencias:</span> {{ $cliente->preferencias_cabello }}</p>@endif
            @if($cliente->formula_color)<p><span class="text-ink-500">Fórmula color:</span> {{ $cliente->formula_color }}</p>@endif
            @if($cliente->observaciones)<p><span class="text-ink-500">Observaciones:</span> {{ $cliente->observaciones }}</p>@endif
            @unless($cliente->activo)
                <form method="POST" action="{{ route('clientes.reactivar', $cliente) }}">
                    @csrf @method('PATCH')
                    <button class="btn-ghost mt-2">Reactivar cliente</button>
                </form>
            @endunless
        </div>

        <div class="card card-body xl:col-span-2">
            <h2 class="mb-3 font-serif text-lg font-semibold">Registros de servicio</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Fecha</th><th>Servicio</th><th>Profesional</th><th>Detalle</th><th>Precio</th></tr></thead>
                    <tbody>
                        @forelse($consumos as $consumo)
                            <tr>
                                <td>{{ $consumo->fecha->format('d/m/Y') }}</td>
                                <td>{{ $consumo->servicio?->nombre }}</td>
                                <td>{{ $consumo->estilista?->nombre ?? '—' }}</td>
                                <td>{{ $consumo->descripcion ?? '—' }}</td>
                                <td>{{ \App\Support\Money::formato($consumo->precio_cobrado) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-ink-500">Sin registros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('partials.paginacion', ['paginable' => $consumos])

            <h2 class="mb-3 mt-6 font-serif text-lg font-semibold">Compras realizadas</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Número</th><th>Fecha</th><th>Total</th><th>Saldo</th><th>Estado</th></tr></thead>
                    <tbody>
                        @forelse($ventas as $venta)
                            <tr>
                                <td><a href="{{ route('ventas.show', $venta) }}" class="text-blush-600 hover:underline">{{ $venta->numero }}</a></td>
                                <td>{{ $venta->fecha->format('d/m/Y') }}</td>
                                <td>{{ \App\Support\Money::formato($venta->total) }}</td>
                                <td>{{ \App\Support\Money::formato($venta->saldo_pendiente) }}</td>
                                <td>{{ ucfirst($venta->estado) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-ink-500">Sin ventas registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('partials.paginacion', ['paginable' => $ventas])
        </div>
    </div>
@endsection
