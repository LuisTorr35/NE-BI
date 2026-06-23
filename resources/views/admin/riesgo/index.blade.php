@extends('layouts.admin')
@section('title', 'Clientes en riesgo')

@php
    $badge = [
        'alto' => 'bg-red-100 text-red-700', 'medio' => 'bg-orange-100 text-orange-700',
        'moderado' => 'bg-yellow-100 text-yellow-700', 'bajo' => 'bg-emerald-100 text-emerald-700',
    ];
@endphp

@section('content')
<h1 class="text-2xl font-bold mb-1">Clientes en riesgo de abandono</h1>
<p class="text-sm text-slate-500 mb-6">Ordenados por probabilidad de churn. Acciones de retención sugeridas según umbral.</p>

{{-- Filtros --}}
<form method="GET" class="flex flex-wrap gap-3 mb-5 bg-white p-4 rounded-xl border border-slate-200">
    <select name="nivel" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">Todos los niveles (en riesgo)</option>
        @foreach(['alto','medio','moderado','bajo'] as $n)
            <option value="{{ $n }}" @selected($nivel===$n)>{{ ucfirst($n) }}</option>
        @endforeach
    </select>
    <select name="cat" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">Todas las categorías</option>
        @foreach($categorias as $c)
            <option value="{{ $c }}" @selected($categoria===$c)>{{ $c }}</option>
        @endforeach
    </select>
    <input type="text" name="q" value="{{ $search }}" placeholder="Buscar por nombre..."
           class="rounded-lg border border-slate-300 px-3 py-2 text-sm flex-1 min-w-[160px]">
    <button class="bg-slate-900 text-white rounded-lg px-5 py-2 text-sm">Filtrar</button>
    <a href="{{ route('admin.riesgo.index') }}" class="px-3 py-2 text-sm text-slate-500 hover:text-slate-700">Limpiar</a>
</form>

<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase text-left">
            <tr>
                <th class="px-4 py-3">Cliente</th>
                <th class="px-4 py-3">Categoría</th>
                <th class="px-4 py-3 text-center">Antigüedad</th>
                <th class="px-4 py-3 text-center">Reclamo</th>
                <th class="px-4 py-3 text-center">Probabilidad</th>
                <th class="px-4 py-3">Nivel</th>
                <th class="px-4 py-3">Acción sugerida</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($clientes as $c)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <div class="font-medium">{{ $c->name }}</div>
                        <div class="text-xs text-slate-400">{{ $c->email }}</div>
                    </td>
                    <td class="px-4 py-3 text-slate-600">{{ $c->prefered_order_cat }}</td>
                    <td class="px-4 py-3 text-center">{{ $c->tenure !== null ? $c->tenure.' m' : '—' }}</td>
                    <td class="px-4 py-3 text-center">{{ $c->complain ? '⚠️' : '—' }}</td>
                    <td class="px-4 py-3 text-center font-bold">{{ round($c->churn_probability*100,1) }}%</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $badge[$c->churn_level] ?? '' }}">
                            {{ ucfirst($c->churn_level) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-slate-600 text-xs">{{ $c->accionSugerida() }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.riesgo.show', $c) }}" class="text-sky-600 hover:underline text-xs">Ver →</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">No hay clientes con estos filtros.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $clientes->links() }}</div>
@endsection
