@extends('layouts.admin')
@section('title', 'Dashboard BI')

@php
    $catLabels = [
        'Mobile Phone' => '📱 Celulares', 'Laptop & Accessory' => '💻 Laptops',
        'Fashion' => '⌚ Wearables', 'Grocery' => '🍳 Electro cocina', 'Others' => '🧊 Línea blanca',
    ];

    // --- Datos para los gráficos ---
    $nivelOrden = ['alto', 'medio', 'moderado', 'bajo'];
    $nivelData  = collect($nivelOrden)->map(fn ($n) => (int) ($porNivel[$n] ?? 0))->all();

    $catNombres   = $porCategoria->map(fn ($c) => $catLabels[$c->prefered_order_cat] ?? $c->prefered_order_cat)->values();
    $catTotales   = $porCategoria->pluck('total')->map(fn ($v) => (int) $v)->values();
    $catEnRiesgo  = $porCategoria->pluck('en_riesgo')->map(fn ($v) => (int) $v)->values();
    $catPctRiesgo = $porCategoria->map(fn ($c) => $c->total ? round($c->en_riesgo / $c->total * 100, 1) : 0)->values();

    $ciudadLabels = $porCiudad->map(fn ($c) => 'Tier ' . $c->city_tier)->values();
    $ciudadProb   = $porCiudad->pluck('prob_media')->map(fn ($v) => (float) $v)->values();
    $ciudadTotal  = $porCiudad->pluck('total')->map(fn ($v) => (int) $v)->values();

    $pctRiesgo  = $total ? round($enRiesgo / $total * 100, 1) : 0;
@endphp

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Dashboard de clientes en riesgo de abandono</h1>
    <span class="text-xs text-slate-500">
        Último scoring: {{ $ultimoScoring ? \Carbon\Carbon::parse($ultimoScoring)->format('d/m/Y H:i') : 'sin datos' }}
    </span>
</div>

{{-- KPIs --}}
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
    <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-200">
        <div class="text-3xl font-bold">{{ number_format($total) }}</div>
        <div class="text-sm text-slate-500">Clientes totales</div>
    </div>
    <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-200">
        <div class="text-3xl font-bold text-red-600">{{ number_format($enRiesgo) }}</div>
        <div class="text-sm text-slate-500">En riesgo (alto + medio)</div>
    </div>
    <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-200">
        <div class="text-3xl font-bold text-amber-500">{{ $pctRiesgo }}%</div>
        <div class="text-sm text-slate-500">Tasa de riesgo</div>
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

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    {{-- Donut: distribución por nivel --}}
    <div class="bg-white rounded-xl p-6 shadow-sm border border-slate-200">
        <h2 class="font-semibold">Clientes según su riesgo de abandono</h2>
        <p class="text-xs text-slate-500 mb-4">Cómo se reparten los clientes entre los 4 niveles de riesgo.</p>
        <div class="relative h-64"><canvas id="chartNivel"></canvas></div>
    </div>

    {{-- Barra: prob media por ciudad --}}
    <div class="bg-white rounded-xl p-6 shadow-sm border border-slate-200">
        <h2 class="font-semibold">Riesgo de abandono por tipo de ciudad</h2>
        <p class="text-xs text-slate-500 mb-4">Probabilidad media de que un cliente abandone, según su tipo de ciudad.</p>
        <div class="relative h-64"><canvas id="chartCiudad"></canvas></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Barras agrupadas: total vs en riesgo por categoría --}}
    <div class="bg-white rounded-xl p-6 shadow-sm border border-slate-200">
        <h2 class="font-semibold">Clientes vs. en riesgo por categoría</h2>
        <p class="text-xs text-slate-500 mb-4">Total de clientes y cuántos están en riesgo, por categoría favorita.</p>
        <div class="relative h-72"><canvas id="chartCategoria"></canvas></div>
    </div>

    {{-- Barras horizontales: % en riesgo por categoría --}}
    <div class="bg-white rounded-xl p-6 shadow-sm border border-slate-200">
        <h2 class="font-semibold">% en riesgo por categoría</h2>
        <p class="text-xs text-slate-500 mb-4">Qué categorías concentran mayor proporción de clientes en riesgo.</p>
        <div class="relative h-72"><canvas id="chartCategoriaPct"></canvas></div>
    </div>
</div>

<a href="{{ route('admin.riesgo.index') }}" class="text-sm text-sky-600 hover:underline mt-4 inline-block">
    Ver lista completa de clientes en riesgo →
</a>

<script>
const COLORS = {
    alto: '#ef4444', medio: '#f97316', moderado: '#facc15', bajo: '#10b981',
    sky: '#0ea5e9', emerald: '#10b981', red: '#ef4444', slate: '#cbd5e1',
};

// Plugin para texto en el centro de un doughnut.
const centerText = (texto, sub, color) => ({
    id: 'centerText-' + texto,
    afterDraw(chart) {
        const {ctx, chartArea: {left, right, top, bottom}} = chart;
        const x = (left + right) / 2, y = (top + bottom) / 2;
        ctx.save();
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillStyle = color; ctx.font = 'bold 28px sans-serif';
        ctx.fillText(texto, x, y - 6);
        ctx.fillStyle = '#94a3b8'; ctx.font = '12px sans-serif';
        ctx.fillText(sub, x, y + 18);
        ctx.restore();
    }
});

Chart.defaults.font.family = 'ui-sans-serif, system-ui, sans-serif';
Chart.defaults.color = '#64748b';

// 1) Donut por nivel de riesgo
new Chart(document.getElementById('chartNivel'), {
    type: 'doughnut',
    data: {
        labels: ['Alto', 'Medio', 'Moderado', 'Bajo'],
        datasets: [{
            data: @json($nivelData),
            backgroundColor: [COLORS.alto, COLORS.medio, COLORS.moderado, COLORS.bajo],
            borderWidth: 2, borderColor: '#fff',
        }],
    },
    options: {
        maintainAspectRatio: false, cutout: '62%',
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } },
            tooltip: { callbacks: {
                label: (ctx) => {
                    const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                    const pct = total ? (ctx.parsed / total * 100).toFixed(1) : 0;
                    return ` ${ctx.label}: ${ctx.parsed.toLocaleString()} clientes (${pct}%)`;
                },
            } },
        },
    },
    plugins: [centerText('{{ number_format($total) }}', 'clientes', '#1e293b')],
});

// 2) Barras: riesgo de abandono por tipo de ciudad
new Chart(document.getElementById('chartCiudad'), {
    type: 'bar',
    data: {
        labels: @json($ciudadLabels),
        datasets: [{
            label: '% prob. media',
            data: @json($ciudadProb),
            backgroundColor: COLORS.sky, borderRadius: 6,
        }],
    },
    options: {
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: {
                label: (ctx) => ' Prob. media: ' + ctx.parsed.y + '%',
                afterLabel: (ctx) => @json($ciudadTotal)[ctx.dataIndex] + ' clientes',
            } },
        },
        scales: { y: { beginAtZero: true, ticks: { callback: v => v + '%' } } },
    },
});

// 3) Barras agrupadas: total vs en riesgo por categoría
new Chart(document.getElementById('chartCategoria'), {
    type: 'bar',
    data: {
        labels: @json($catNombres),
        datasets: [
            { label: 'Total', data: @json($catTotales), backgroundColor: COLORS.slate, borderRadius: 6 },
            { label: 'En riesgo', data: @json($catEnRiesgo), backgroundColor: COLORS.red, borderRadius: 6 },
        ],
    },
    options: {
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12 } },
            tooltip: { callbacks: {
                label: (ctx) => {
                    const totales = @json($catTotales);
                    const val = ctx.parsed.y;
                    if (ctx.dataset.label === 'En riesgo') {
                        const t = totales[ctx.dataIndex];
                        const pct = t ? (val / t * 100).toFixed(1) : 0;
                        return ` En riesgo: ${val.toLocaleString()} (${pct}% de la categoría)`;
                    }
                    return ` Total: ${val.toLocaleString()} clientes`;
                },
            } },
        },
        scales: { y: { beginAtZero: true } },
    },
});

// 4) Barras horizontales: % en riesgo por categoría
new Chart(document.getElementById('chartCategoriaPct'), {
    type: 'bar',
    data: {
        labels: @json($catNombres),
        datasets: [{
            label: '% en riesgo',
            data: @json($catPctRiesgo),
            backgroundColor: COLORS.medio, borderRadius: 6,
        }],
    },
    options: {
        indexAxis: 'y', maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: {
                label: (ctx) => ' ' + ctx.parsed.x + '% de la categoría en riesgo',
            } },
        },
        scales: { x: { beginAtZero: true, ticks: { callback: v => v + '%' } } },
    },
});
</script>
@endsection
