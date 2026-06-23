@extends('layouts.app')
@section('title', 'Pago · SOLE')

@section('content')
<h1 class="text-xl font-bold mb-4">Finalizar compra</h1>

<div class="grid grid-cols-1 md:grid-cols-[1fr_320px] gap-6">
    {{-- Metodo de pago --}}
    <form action="{{ route('checkout.store') }}" method="POST" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-4">
        @csrf
        <h2 class="font-semibold">Método de pago</h2>

        @php
            $modes = [
                'tarjeta_credito'   => '💳 Tarjeta de crédito',
                'tarjeta_debito'    => '💳 Tarjeta de débito',
                'billetera_digital' => '📲 Billetera digital (Yape / Plin)',
                'contra_entrega'    => '💵 Contra entrega',
            ];
        @endphp

        @foreach($modes as $val => $label)
            <label class="flex items-center gap-3 border border-slate-200 rounded-lg px-4 py-3 cursor-pointer hover:border-sky-400">
                <input type="radio" name="payment_mode" value="{{ $val }}" @checked($loop->first) required>
                <span>{{ $label }}</span>
            </label>
        @endforeach

        @error('payment_mode') <p class="text-red-500 text-sm">{{ $message }}</p> @enderror

        <button class="w-full bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg py-3 font-medium mt-2">
            Confirmar y pagar
        </button>
    </form>

    {{-- Resumen --}}
    <aside class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 h-fit">
        <h2 class="font-semibold mb-3">Resumen</h2>
        <div class="space-y-2 text-sm">
            @foreach($items as $item)
                <div class="flex justify-between">
                    <span class="text-slate-600">{{ $item['product']->name }} × {{ $item['qty'] }}</span>
                    <span>S/ {{ number_format($item['subtotal'], 2) }}</span>
                </div>
            @endforeach
        </div>
        <div class="border-t mt-3 pt-3 flex justify-between font-bold">
            <span>Total</span>
            <span>S/ {{ number_format($total, 2) }}</span>
        </div>
    </aside>
</div>
@endsection
