<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Acceso') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-cream-100 px-4">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-blush-500 font-serif text-2xl font-bold text-white">R</div>
            <h1 class="mt-3 font-serif text-xl font-semibold text-ink-900">Ruby Cristancho Peluquería</h1>
            <p class="text-sm text-ink-500">Sistema de gestión</p>
        </div>
        <div class="card card-body">
            @include('partials.alerts')
            @yield('contenido')
        </div>
    </div>
</body>
</html>
