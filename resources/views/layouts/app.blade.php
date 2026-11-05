<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Panel') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
<div class="flex min-h-screen">
    <aside class="hidden w-64 flex-col border-r border-cream-200 bg-white lg:flex">
        <div class="flex items-center gap-3 border-b border-cream-200 px-5 py-5">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blush-500 font-serif text-lg font-bold text-white">R</div>
            <div>
                <p class="font-serif text-sm font-semibold leading-tight text-ink-900">Ruby Cristancho</p>
                <p class="text-xs text-ink-500">Peluquería &amp; Estética</p>
            </div>
        </div>
        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4 text-sm">
            @php($u = auth()->user())
            @php($link = fn ($ruta, $activo) => 'flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition '
                . (request()->routeIs($activo) ? 'bg-blush-50 text-blush-600' : 'text-ink-700 hover:bg-cream-100'))
            <a href="{{ route('dashboard') }}" class="{{ $link('dashboard', 'dashboard') }}">
                <span>Panel</span></a>
            <a href="{{ route('ventas.index') }}" class="{{ $link('ventas.index', 'ventas.*') }}"><span>Ventas</span></a>
            <a href="{{ route('caja.index') }}" class="{{ $link('caja.index', 'caja.*') }}"><span>Caja diaria</span></a>
            <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase tracking-wide text-ink-500">Registros diarios</p>
            <a href="{{ route('atenciones.index') }}" class="{{ $link('atenciones.index', 'atenciones.*') }}"><span>Registro de clientes</span></a>
            <a href="{{ route('consumos.index') }}" class="{{ $link('consumos.index', 'consumos.*') }}"><span>Registro de productos</span></a>
            <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase tracking-wide text-ink-500">Inventario</p>
            <a href="{{ route('productos.index') }}" class="{{ $link('productos.index', 'productos.*') }}"><span>Productos</span></a>
            <a href="{{ route('compras.index') }}" class="{{ $link('compras.index', 'compras.*') }}"><span>Compras</span></a>
            <a href="{{ route('reportes.inventario') }}" class="{{ $link('reportes.inventario', 'reportes.inventario') }}"><span>Inventario valorizado</span></a>
            <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase tracking-wide text-ink-500">Catálogos</p>
            <a href="{{ route('servicios.index') }}" class="{{ $link('servicios.index', 'servicios.*') }}"><span>Servicios</span></a>
            <a href="{{ route('clientes.index') }}" class="{{ $link('clientes.index', 'clientes.*') }}"><span>Clientes</span></a>
            <a href="{{ route('estilistas.index') }}" class="{{ $link('estilistas.index', 'estilistas.*') }}"><span>Profesionales</span></a>
            <a href="{{ route('proveedores.index') }}" class="{{ $link('proveedores.index', 'proveedores.*') }}"><span>Proveedores</span></a>
            <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase tracking-wide text-ink-500">Contabilidad</p>
            <a href="{{ route('gastos.index') }}" class="{{ $link('gastos.index', 'gastos.*') }}"><span>Gastos</span></a>
            <a href="{{ route('reportes.index') }}" class="{{ $link('reportes.index', 'reportes.*') }}"><span>Reportes</span></a>
            @if($u->esAdmin())
                <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase tracking-wide text-ink-500">Administración</p>
                <a href="{{ route('usuarios.index') }}" class="{{ $link('usuarios.index', 'usuarios.*') }}"><span>Usuarios</span></a>
                <a href="{{ route('auditoria.index') }}" class="{{ $link('auditoria.index', 'auditoria.*') }}"><span>Auditoría</span></a>
                <a href="{{ route('configuracion.index') }}" class="{{ $link('configuracion.index', 'configuracion.*') }}"><span>Configuración</span></a>
            @endif
        </nav>
        <div class="border-t border-cream-200 px-4 py-4 text-sm">
            <p class="font-semibold text-ink-900">{{ $u->name }}</p>
            <p class="text-xs text-ink-500">{{ $u->rolLabel() }}</p>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button type="submit" class="btn-ghost w-full">Cerrar sesión</button>
            </form>
        </div>
    </aside>

    <div class="flex flex-1 flex-col">
        <header class="flex items-center justify-between gap-4 border-b border-cream-200 bg-white px-4 py-4 sm:px-6">
            <div>
                <h1 class="font-serif text-xl font-semibold text-ink-900">@yield('titulo', 'Panel')</h1>
                @hasSection('subtitulo')<p class="text-sm text-ink-500">@yield('subtitulo')</p>@endif
            </div>
            <div class="flex items-center gap-2">
                @yield('acciones')
            </div>
        </header>

        <main class="flex-1 px-4 py-6 sm:px-6">
            @include('partials.alerts')
            @yield('contenido')
        </main>
    </div>
</div>

{{-- Navegación inferior para móviles --}}
<nav class="fixed inset-x-0 bottom-0 z-20 flex justify-around border-t border-cream-200 bg-white py-2 text-xs lg:hidden">
    <a href="{{ route('dashboard') }}" class="px-2 text-ink-700">Panel</a>
    <a href="{{ route('ventas.create') }}" class="rounded-full bg-blush-500 px-3 py-1 font-semibold text-white">+ Venta</a>
    <a href="{{ route('productos.index') }}" class="px-2 text-ink-700">Stock</a>
    <a href="{{ route('caja.index') }}" class="px-2 text-ink-700">Caja</a>
</nav>
<div class="h-14 lg:hidden"></div>

@stack('scripts')
</body>
</html>
