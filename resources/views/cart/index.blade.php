@extends('layouts.app')
@section('title', 'Carrito · SOLE')

@section('content')
<h1 class="text-xl font-bold mb-4">Tu carrito</h1>

@if($items->isEmpty())
    <div class="bg-white rounded-xl border border-slate-200 p-8 text-center text-slate-500">
        Tu carrito está vacío.
        <a href="{{ route('catalog.index') }}" class="text-sky-600 hover:underline">Ver productos</a>
    </div>
@else
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 divide-y">
        @foreach($items as $item)
            <div class="flex items-center gap-4 p-4">
                <div class="flex-1">
                    <div class="font-semibold">{{ $item['product']->name }}</div>
                    <div class="text-sm text-slate-500">S/ {{ number_format($item['product']->price, 2) }} × {{ $item['qty'] }}</div>
                </div>
                <div class="font-bold">S/ {{ number_format($item['subtotal'], 2) }}</div>
                <form action="{{ route('cart.remove', $item['product']) }}" method="POST">
                    @csrf @method('DELETE')
                    <button class="text-red-500 hover:text-red-700 text-sm">Quitar</button>
                </form>
            </div>
        @endforeach
    </div>

    <div class="flex items-center justify-between mt-4">
        <form action="{{ route('cart.clear') }}" method="POST">
            @csrf @method('DELETE')
            <button class="text-sm text-slate-500 hover:text-slate-700">Vaciar carrito</button>
        </form>
        <div class="text-right">
            <div class="text-sm text-slate-500">Total</div>
            <div class="text-2xl font-bold">S/ {{ number_format($total, 2) }}</div>
        </div>
    </div>

    <div class="mt-4 text-right">
        @auth('customer')
            <a href="{{ route('checkout.index') }}" class="inline-block bg-sky-600 hover:bg-sky-700 text-white rounded-lg px-6 py-2.5 font-medium">
                Continuar al pago
            </a>
        @else
            <a href="{{ route('login') }}" class="inline-block bg-sky-600 hover:bg-sky-700 text-white rounded-lg px-6 py-2.5 font-medium">
                Inicia sesión para comprar
            </a>
        @endauth
    </div>
@endif
@endsection
