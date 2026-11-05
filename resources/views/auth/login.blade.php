@extends('layouts.guest')

@section('titulo', 'Iniciar sesión')

@section('contenido')
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <label class="label" for="email">Correo electrónico</label>
            <input id="email" name="email" type="email" class="field" value="{{ old('email') }}" required autofocus>
        </div>
        <div>
            <label class="label" for="password">Contraseña</label>
            <input id="password" name="password" type="password" class="field" required>
        </div>
        <label class="flex items-center gap-2 text-sm text-ink-700">
            <input type="checkbox" name="remember" value="1" class="rounded border-cream-300">
            Recordarme
        </label>
        <button type="submit" class="btn-primary w-full">Entrar</button>
    </form>
@endsection
