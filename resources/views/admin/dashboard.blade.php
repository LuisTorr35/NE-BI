@extends('layouts.admin')
@section('title', 'Dashboard BI')

@php
    $colores = ['alto' => 'bg-red-500', 'medio' => 'bg-orange-400', 'moderado' => 'bg-yellow-400', 'bajo' => 'bg-emerald-500'];
    $catLabels = [
        'Mobile Phone' => '📱 Celulares', 'Laptop & Accessory' => '💻 Laptops',
        'Fashion' => '⌚ Wearables', 'Grocery' => '🍳 Electro cocina', 'Others' => '🧊 Línea blanca',
    ];
@endphp

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Dashboard de Business Intelligence</h1>
    <span class="text-xs text-slate-500">
        Último scoring: {{ $ultimoScoring ? \Carbon\Carbon::parse($ultimoScoring)->format('d/m/Y H:i') : 'sin datos' }}
    </span>
</div>

{{-- KPIs --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-200">
        <div class="text-3xl font-bold">{{ number_format($total) }}</div>
        <div class="text-sm text-slate-500">Clientes totales</div>
    </div>
    <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-200">
        <div class="text-3xl font-bold text-red-600">{{ number_format($enRiesgo) }}</div>
        <div class="text-sm text-slate-500">En riesgo (alto + medio)</div>
    </div>
    <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-200">
        <div class="text-3xl font-bold">{{ $numProductos }}</div>
        <div class="text-sm text-slate-500">Productos</div>
    </div>
    <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-200">
        <div class="text-3xl font-bold">{{ $numOrdenes }}</div>
        <div class="text-sm text-slate-500">Órdenes</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Distribucion por nivel --}}
    <div class="bg-white rounded-xl p-6 shadow-sm border border-slate-200">
        <h2 class="font-semibold mb-4">Clientes por nivel de riesgo</h2>
        @foreach(['alto','medio','moderado','bajo'] as $nivel)
            @php $n = $porNivel[$nivel] ?? 0; $pct = $total ? round($n/$total*100,1) : 0; @endphp
            <div class="mb-3">
                <div class="flex justify-between text-sm mb-1">
                    <span class="capitalize font-medium">{{ $nivel }}</span>
                    <span class="text-slate-500">{{ number_format($n) }} ({{ $pct }}%)</span>
                </div>
                <div class="h-3 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full {{ $colores[$nivel] }}" style="width: {{ $pct }}%"></div>
                </div>
            </div>
        @endforeach
        <a href="{{ route('admin.riesgo.index') }}" class="text-sm text-sky-600 hover:underline mt-2 inline-block">
            Ver lista de clientes en riesgo →
        </a>
    </div>

    {{-- Riesgo por categoria --}}
    <div class="bg-white rounded-xl p-6 shadow-sm border border-slate-200">
        <h2 class="font-semibold mb-4">Riesgo por categoría favorita</h2>
        <table class="w-full text-sm">
            <thead class="text-slate-400 text-left text-xs uppercase">
                <tr><th class="pb-2">Categoría</th><th class="pb-2 text-right">Total</th><th class="pb-2 text-right">En riesgo</th></tr>
            </thead>
            <tbody class="divide-y">
                @foreach($porCategoria as $c)
                    <tr>
                        <td class="py-2">{{ $catLabels[$c->prefered_order_cat] ?? $c->prefered_order_cat }}</td>
                        <td class="py-2 text-right text-slate-500">{{ $c->total }}</td>
                        <td class="py-2 text-right font-semibold text-red-600">{{ $c->en_riesgo }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Riesgo por ciudad --}}
    <div class="bg-white rounded-xl p-6 shadow-sm border border-slate-200">
        <h2 class="font-semibold mb-4">Probabilidad media de churn por ciudad (CityTier)</h2>
        @foreach($porCiudad as $c)
            <div class="mb-3">
                <div class="flex justify-between text-sm mb-1">
                    <span>Tier {{ $c->city_tier }}</span>
                    <span class="text-slate-500">{{ $c->prob_media }}% · {{ $c->total }} clientes</span>
                </div>
                <div class="h-3 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-sky-500" style="width: {{ $c->prob_media }}%"></div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
