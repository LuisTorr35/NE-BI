@extends('layouts.app')
@section('title', 'Catálogo · SOLE')

@php
    $icons = [
        'laptops_accesorios' => '💻',
        'celulares'          => '📱',
        'wearables'          => '⌚',
        'electro_cocina'     => '🍳',
        'linea_blanca'       => '🧊',
    ];
@endphp

@section('content')
<div class="grid grid-cols-1 md:grid-cols-[220px_1fr] gap-6">

    {{-- Filtro de categorias --}}
    <aside class="space-y-1">
        <h2 class="font-semibold text-slate-500 text-xs uppercase mb-2">Categorías</h2>
        <a href="{{ route('catalog.index') }}"
           class="block px-3 py-2 rounded-lg text-sm {{ !$category ? 'bg-slate-900 text-white' : 'hover:bg-slate-200' }}">
            Todos los productos
        </a>
        @foreach($categories as $key => $label)
            <a href="{{ route('catalog.index', ['cat' => $key]) }}"
               class="block px-3 py-2 rounded-lg text-sm {{ $category === $key ? 'bg-slate-900 text-white' : 'hover:bg-slate-200' }}">
                {{ $icons[$key] ?? '•' }} {{ $label }}
            </a>
        @endforeach
    </aside>

    {{-- Grilla de productos --}}
    <section>
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-lg font-bold">
                {{ $category ? $categories[$category] : 'Todos los productos' }}
                @if($search) <span class="text-slate-400 font-normal">· “{{ $search }}”</span> @endif
            </h1>
            <span class="text-sm text-slate-500">{{ $products->total() }} productos</span>
        </div>

        @if($products->isEmpty())
            <p class="text-slate-500">No se encontraron productos.</p>
        @else
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($products as $p)
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
                        <a href="{{ route('catalog.show', $p) }}"
                           class="h-32 flex items-center justify-center text-5xl bg-gradient-to-br from-slate-100 to-slate-200">
                            {{ $icons[$p->category] ?? '📦' }}
                        </a>
                        <div class="p-3 flex flex-col flex-1">
                            <span class="text-[11px] text-sky-600 font-medium">{{ $p->brand }}</span>
                            <a href="{{ route('catalog.show', $p) }}" class="text-sm font-semibold leading-tight hover:text-sky-600 line-clamp-2">
                                {{ $p->name }}
                            </a>
                            <div class="mt-auto pt-2 flex items-center justify-between">
                                <span class="font-bold text-slate-900">S/ {{ number_format($p->price, 2) }}</span>
                                <form action="{{ route('cart.add', $p) }}" method="POST">
                                    @csrf
                                    <button class="bg-sky-600 hover:bg-sky-700 text-white text-xs rounded-lg px-3 py-1.5">
                                        Agregar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">{{ $products->links() }}</div>
        @endif
    </section>
</div>
@endsection
