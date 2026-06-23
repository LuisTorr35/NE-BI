<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') · SOLE</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
<div class="flex min-h-screen">

    {{-- Sidebar --}}
    <aside class="w-60 bg-slate-900 text-slate-300 flex flex-col">
        <div class="px-5 py-4 text-white font-bold text-lg border-b border-slate-700">
            SOLE <span class="text-sky-400">Admin</span>
        </div>
        <nav class="flex-1 p-3 space-y-1 text-sm">
            <a href="{{ route('admin.dashboard') }}"
               class="block px-3 py-2 rounded-lg {{ request()->routeIs('admin.dashboard') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                📊 Dashboard BI
            </a>
            <a href="{{ route('admin.riesgo.index') }}"
               class="block px-3 py-2 rounded-lg {{ request()->routeIs('admin.riesgo.*') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                ⚠️ Clientes en riesgo
            </a>
            <a href="{{ route('admin.asistente.index') }}"
               class="block px-3 py-2 rounded-lg {{ request()->routeIs('admin.asistente.*') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                🤖 Asistente BI
            </a>
            <a href="{{ route('catalog.index') }}" target="_blank"
               class="block px-3 py-2 rounded-lg hover:bg-slate-800">
                🛒 Ver tienda
            </a>
        </nav>
        <form action="{{ route('admin.logout') }}" method="POST" class="p-3 border-t border-slate-700">
            @csrf
            <button class="w-full text-left px-3 py-2 rounded-lg text-sm hover:bg-slate-800">
                ⏻ Cerrar sesión ({{ auth('web')->user()->name ?? 'admin' }})
            </button>
        </form>
    </aside>

    {{-- Contenido --}}
    <main class="flex-1 p-8">
        @if(session('ok'))
            <div class="mb-4 bg-emerald-100 border border-emerald-300 text-emerald-800 rounded-lg px-4 py-2 text-sm">{{ session('ok') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 bg-red-100 border border-red-300 text-red-800 rounded-lg px-4 py-2 text-sm">{{ session('error') }}</div>
        @endif
        @yield('content')
    </main>
</div>
</body>
</html>
