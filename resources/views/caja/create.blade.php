@extends('layouts.app')

@section('titulo', 'Abrir caja')

@section('contenido')
    <form method="POST" action="{{ route('caja.store') }}" class="card card-body max-w-xl space-y-4">
        @csrf
        <div>
            <label class="label">Saldo inicial (base en efectivo) *</label>
            <input name="saldo_inicial" type="number" step="0.01" min="0" value="{{ old('saldo_inicial', '0') }}" class="field" required>
        </div>
        <div>
            <label class="label">Nota de apertura</label>
            <textarea name="nota_apertura" class="field" rows="2">{{ old('nota_apertura') }}</textarea>
        </div>
        <div class="flex gap-3">
            <button class="btn-primary">Abrir caja</button>
            <a href="{{ route('caja.index') }}" class="btn-ghost">Cancelar</a>
        </div>
    </form>
@endsection
