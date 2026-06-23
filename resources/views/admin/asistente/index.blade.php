@extends('layouts.admin')
@section('title', 'Asistente BI')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold">🤖 Asistente BI</h1>
            <p class="text-sm text-slate-500">Pregúntame en lenguaje natural sobre el riesgo de abandono de tus clientes.</p>
        </div>
        <button id="btn-limpiar" class="text-xs text-slate-500 hover:text-red-600">Limpiar conversación</button>
    </div>

    @unless($configurado)
        <div class="mb-4 bg-amber-100 border border-amber-300 text-amber-800 rounded-lg px-4 py-3 text-sm">
            ⚠️ Falta configurar <code>GROQ_API_KEY</code> en el archivo <code>.env</code>.
        </div>
    @endunless

    {{-- Sugerencias rápidas --}}
    <div class="flex flex-wrap gap-2 mb-4" id="sugerencias">
        @foreach([
            '¿Quiénes son los 5 clientes con mayor riesgo?',
            '¿Cuántos clientes hay en cada nivel de riesgo?',
            '¿Qué categoría tiene más clientes en riesgo?',
            '¿El cliente Alan Cepeda se va a abandonar?',
        ] as $s)
            <button class="sugerencia text-xs bg-white border border-slate-300 rounded-full px-3 py-1 hover:bg-sky-50 hover:border-sky-400">{{ $s }}</button>
        @endforeach
    </div>

    {{-- Historial de chat --}}
    <div id="chat" class="bg-white border border-slate-200 rounded-xl p-4 h-[420px] overflow-y-auto space-y-3 mb-3">
        <div class="text-slate-400 text-sm text-center mt-32" id="placeholder">
            Escribe una pregunta para empezar…
        </div>
    </div>

    {{-- Entrada --}}
    <form id="form-chat" class="flex gap-2">
        <input id="input-msg" type="text" maxlength="500" autocomplete="off"
               class="flex-1 border border-slate-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-sky-400"
               placeholder="Ej: dame los clientes de alto riesgo en celulares…">
        <button id="btn-enviar" type="submit"
                class="bg-sky-600 text-white px-5 py-2 rounded-lg hover:bg-sky-700 disabled:opacity-50">
            Enviar
        </button>
    </form>
</div>

<script>
const chat        = document.getElementById('chat');
const form        = document.getElementById('form-chat');
const input       = document.getElementById('input-msg');
const btnEnviar   = document.getElementById('btn-enviar');
const placeholder = document.getElementById('placeholder');
const CSRF        = '{{ csrf_token() }}';

function burbuja(texto, lado, meta = '') {
    placeholder?.remove();
    const wrap = document.createElement('div');
    wrap.className = 'flex ' + (lado === 'user' ? 'justify-end' : 'justify-start');
    const color = lado === 'user' ? 'bg-sky-600 text-white' : 'bg-slate-100 text-slate-800';
    wrap.innerHTML = `<div class="max-w-[80%] ${color} rounded-2xl px-4 py-2 text-sm whitespace-pre-wrap"></div>`;
    wrap.firstChild.textContent = texto;
    if (meta) {
        const m = document.createElement('div');
        m.className = 'text-[10px] text-slate-400 mt-1';
        m.textContent = meta;
        wrap.firstChild.appendChild(m);
    }
    chat.appendChild(wrap);
    chat.scrollTop = chat.scrollHeight;
    return wrap.firstChild;
}

async function enviar(mensaje) {
    burbuja(mensaje, 'user');
    input.value = '';
    btnEnviar.disabled = true;
    const cargando = burbuja('Pensando…', 'bot');

    try {
        const r = await fetch('{{ route('admin.asistente.preguntar') }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json'},
            body: JSON.stringify({mensaje}),
        });
        const data = await r.json();
        if (data.ok) {
            cargando.textContent = data.respuesta;
            if (data.herramientas?.length) {
                const m = document.createElement('div');
                m.className = 'text-[10px] text-slate-400 mt-1';
                m.textContent = '🔧 consultó: ' + data.herramientas.join(', ');
                cargando.appendChild(m);
            }
        } else {
            cargando.textContent = '⚠️ ' + (data.error || 'Error desconocido.');
        }
    } catch (e) {
        cargando.textContent = '⚠️ Error de conexión.';
    } finally {
        btnEnviar.disabled = false;
        chat.scrollTop = chat.scrollHeight;
    }
}

form.addEventListener('submit', e => {
    e.preventDefault();
    const m = input.value.trim();
    if (m) enviar(m);
});

document.querySelectorAll('.sugerencia').forEach(b =>
    b.addEventListener('click', () => enviar(b.textContent.trim())));

document.getElementById('btn-limpiar').addEventListener('click', async () => {
    await fetch('{{ route('admin.asistente.limpiar') }}', {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json'},
    });
    chat.innerHTML = '<div class="text-slate-400 text-sm text-center mt-32">Conversación reiniciada. Escribe una pregunta…</div>';
});
</script>
@endsection
