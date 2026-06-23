@extends('layouts.app')
@section('title', $product->name . ' · SOLE')

@php
    $icons = [
        'laptops_accesorios' => '💻', 'celulares' => '📱', 'wearables' => '⌚',
        'electro_cocina' => '🍳', 'linea_blanca' => '🧊',
    ];
@endphp

@section('content')
<a href="{{ route('catalog.index') }}" class="text-sm text-sky-600 hover:underline">&larr; Volver al catálogo</a>

<div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-4 bg-white rounded-xl shadow-sm border border-slate-200 p-6">
    <div class="h-64 flex items-center justify-center text-8xl bg-gradient-to-br from-slate-100 to-slate-200 rounded-lg">
        {{ $icons[$product->category] ?? '📦' }}
    </div>

    <div class="flex flex-col">
        <span class="text-sm text-sky-600 font-medium">{{ $product->brand }} · {{ $product->categoryLabel() }}</span>
        <h1 class="text-2xl font-bold mt-1">{{ $product->name }}</h1>
        <p class="text-slate-600 mt-3 text-sm">{{ $product->description }}</p>

        <div class="mt-4 text-3xl font-bold text-slate-900">S/ {{ number_format($product->price, 2) }}</div>
        <p class="text-sm mt-1 {{ $product->stock > 0 ? 'text-emerald-600' : 'text-red-600' }}">
            {{ $product->stock > 0 ? "Stock disponible ({$product->stock})" : 'Sin stock' }}
        </p>

        <form action="{{ route('cart.add', $product) }}" method="POST" class="mt-6 flex items-center gap-3">
            @csrf
            <input type="number" name="qty" value="1" min="1" max="{{ $product->stock }}"
                   class="w-20 rounded-lg border border-slate-300 px-3 py-2">
            <button class="bg-sky-600 hover:bg-sky-700 text-white rounded-lg px-6 py-2 font-medium" @disabled($product->stock < 1)>
                Agregar al carrito
            </button>
        </form>
    </div>
</div>

@if($related->isNotEmpty())
    <h2 class="font-bold mt-8 mb-3">Productos relacionados</h2>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($related as $r)
            <a href="{{ route('catalog.show', $r) }}" class="bg-white rounded-xl shadow-sm border border-slate-200 p-3 hover:shadow-md">
                <div class="h-20 flex items-center justify-center text-4xl">{{ $icons[$r->category] ?? '📦' }}</div>
                <div class="text-sm font-semibold leading-tight line-clamp-2">{{ $r->name }}</div>
                <div class="font-bold text-sm mt-1">S/ {{ number_format($r->price, 2) }}</div>
            </a>
        @endforeach
    </div>
@endif
@endsection
