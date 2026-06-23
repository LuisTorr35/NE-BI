@extends('layouts.admin')
@section('title', 'Ficha de cliente')

@php
    $badge = [
        'alto' => 'bg-red-100 text-red-700', 'medio' => 'bg-orange-100 text-orange-700',
        'moderado' => 'bg-yellow-100 text-yellow-700', 'bajo' => 'bg-emerald-100 text-emerald-700',
    ];
    $prob = round($customer->churn_probability * 100, 1);
@endphp

@section('content')
<a href="{{ route('admin.riesgo.index') }}" class="text-sm text-sky-600 hover:underline">&larr; Volver a la lista</a>

<div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6 mt-4">

    {{-- Comportamiento --}}
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h1 class="text-xl font-bold">{{ $customer->name }}</h1>
        <p class="text-sm text-slate-400 mb-5">{{ $customer->email }} · Cliente #{{ $customer->customer_code }}</p>

        <h2 class="font-semibold text-sm text-slate-500 uppercase mb-3">Comportamiento de compra</h2>
        <dl class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
            @php
                $campos = [
                    'Antigüedad (meses)' => $customer->tenure,
                    'Días desde últ. orden' => $customer->day_since_last_order,
                    'N° de pedidos' => $customer->order_count,
                    'Cupones usados' => $customer->coupon_used,
                    'Cashback (S/)' => $customer->cashback_amount,
                    'Satisfacción' => $customer->satisfaction_score,
                    'Reclamo' => $customer->complain ? 'Sí ⚠️' : 'No',
                    'Categoría favorita' => $customer->prefered_order_cat,
                    'Método de pago' => $customer->preferred_payment_mode,
                    'Ciudad (Tier)' => $customer->city_tier,
                    'Estado civil' => $customer->marital_status,
                    'Dispositivo' => $customer->preferred_login_device,
                ];
            @endphp
            @foreach($campos as $label => $val)
                <div>
                    <dt class="text-slate-400 text-xs">{{ $label }}</dt>
                    <dd class="font-medium">{{ $val ?? '—' }}</dd>
                </div>
            @endforeach
        </dl>
    </div>

    {{-- Predicción + acción --}}
    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 p-6 text-center">
            <div class="text-sm text-slate-500 mb-1">Probabilidad de abandono</div>
            <div class="text-5xl font-bold">{{ $prob }}%</div>
            <span class="inline-block mt-3 px-3 py-1 rounded-full text-sm font-medium {{ $badge[$customer->churn_level] ?? '' }}">
                Riesgo {{ ucfirst($customer->churn_level) }}
            </span>

            <div class="mt-5 p-3 bg-slate-50 rounded-lg text-sm text-left">
                <div class="text-xs text-slate-400 uppercase mb-1">Acción de retención sugerida</div>
                <div class="font-medium">{{ $customer->accionSugerida() }}</div>
            </div>

            <form action="{{ route('admin.riesgo.evaluar', $customer) }}" method="POST" class="mt-4">
                @csrf
                <button class="w-full bg-sky-600 hover:bg-sky-700 text-white rounded-lg py-2.5 text-sm font-medium">
                    🔄 Re-evaluar en vivo (API)
                </button>
            </form>
            <p class="text-[11px] text-slate-400 mt-2">Llama al servicio FastAPI del modelo.</p>
        </div>

        @if($sugeridos->isNotEmpty())
            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h2 class="font-semibold text-sm mb-3">Productos para la oferta personalizada</h2>
                <div class="space-y-2">
                    @foreach($sugeridos as $p)
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-600">{{ $p->name }}</span>
                            <span class="font-medium">S/ {{ number_format($p->price, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
