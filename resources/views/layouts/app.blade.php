<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SOLE')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex flex-col">

@php
    $cart = session()->get('cart', []);
    $cartCount = array_sum($cart);
    $customer = auth('customer')->user();
@endphp

<header class="bg-slate-900 text-white sticky top-0 z-20 shadow">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4">
        <a href="{{ route('catalog.index') }}" class="text-xl font-bold tracking-tight">
            <span class="text-sky-400">SOLE</span>
        </a>

        <form action="{{ route('catalog.index') }}" method="GET" class="flex-1 max-w-md hidden sm:block">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar productos..."
                   class="w-full rounded-lg px-3 py-1.5 text-sm text-slate-800">
        </form>

        <nav class="ml-auto flex items-center gap-4 text-sm">
            <a href="{{ route('cart.index') }}" class="relative hover:text-sky-400">
                🛒 Carrito
                @if($cartCount > 0)
                    <span class="absolute -top-2 -right-3 bg-sky-500 text-white text-xs rounded-full px-1.5">{{ $cartCount }}</span>
                @endif
            </a>

            @if($customer)
                <span class="text-slate-300 hidden md:inline">Hola, {{ explode(' ', $customer->name)[0] }}</span>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button class="hover:text-sky-400">Salir</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="hover:text-sky-400">Iniciar sesión</a>
            @endif
        </nav>
    </div>
</header>

@if(session('ok'))
    <div class="max-w-6xl mx-auto px-4 w-full mt-4">
        <div class="bg-emerald-100 border border-emerald-300 text-emerald-800 rounded-lg px-4 py-2 text-sm">
            {{ session('ok') }}
        </div>
    </div>
@endif

<main class="max-w-6xl mx-auto px-4 py-6 w-full flex-1">
    @yield('content')
</main>

<footer class="bg-slate-900 text-slate-400 text-xs text-center py-4 mt-8">
    SOLE · Tienda de electrodomésticos y tecnología · Proyecto académico
</footer>

</body>
</html>
