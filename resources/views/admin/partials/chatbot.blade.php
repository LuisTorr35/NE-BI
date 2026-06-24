@php($chatConfigurado = app(\App\Services\GroqService::class)->configurado())

{{-- Widget de chatbot flotante (abajo a la derecha), disponible en todo el panel --}}
<div id="cb-root" class="fixed bottom-6 right-6 z-50 flex flex-col items-end">

    {{-- Ventana del chat --}}
    <div id="cb-panel"
         class="hidden mb-3 w-[360px] max-w-[calc(100vw-3rem)] h-[520px] max-h-[calc(100vh-7rem)]
                bg-white rounded-2xl shadow-2xl border border-slate-200 flex flex-col overflow-hidden">

        {{-- Cabecera --}}
        <div class="bg-slate-900 text-white px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                </span>
                <div>
                    <div class="text-sm font-semibold leading-tight">Asistente BI</div>
                    <div class="text-[11px] text-slate-400 leading-tight">Pregúntame sobre el riesgo de tus clientes</div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button id="cb-limpiar" title="Limpiar conversación"
                        class="text-slate-400 hover:text-white text-xs">🗑</button>
                <button id="cb-cerrar" title="Cerrar"
                        class="text-slate-400 hover:text-white text-lg leading-none">×</button>
            </div>
        </div>

        @unless($chatConfigurado)
            <div class="bg-amber-100 border-b border-amber-300 text-amber-800 px-3 py-2 text-[11px]">
                ⚠️ Falta <code>GROQ_API_KEY</code> en <code>.env</code>; el chat no responderá.
            </div>
        @endunless

        {{-- Historial --}}
        <div id="cb-chat" class="flex-1 overflow-y-auto p-3 space-y-3 bg-slate-50">
            <div id="cb-placeholder" class="text-slate-400 text-xs text-center mt-10">
                👋 Hola. Escribe una pregunta para empezar.
            </div>
            {{-- Sugerencias rápidas --}}
            <div class="flex flex-wrap gap-1.5 justify-center" id="cb-sugerencias">
                @foreach([
                    '¿Quiénes son los 5 clientes con mayor riesgo?',
                    '¿Cuántos clientes hay en cada nivel?',
                    '¿Qué categoría tiene más riesgo?',
                ] as $s)
                    <button class="cb-sugerencia text-[11px] bg-white border border-slate-300 rounded-full px-2.5 py-1 hover:bg-sky-50 hover:border-sky-400">{{ $s }}</button>
                @endforeach
            </div>
        </div>

        {{-- Entrada --}}
        <form id="cb-form" class="border-t border-slate-200 p-2 flex gap-2 bg-white">
            <input id="cb-input" type="text" maxlength="500" autocomplete="off"
                   class="flex-1 border border-slate-300 rounded-full px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-400"
                   placeholder="Escribe tu pregunta…">
            <button id="cb-enviar" type="submit"
                    class="bg-sky-600 text-white w-10 h-10 rounded-full hover:bg-sky-700 disabled:opacity-50 flex items-center justify-center shrink-0">
                ➤
            </button>
        </form>
    </div>

    {{-- Botón flotante --}}
    <button id="cb-toggle"
            class="bg-sky-600 hover:bg-sky-700 text-white w-14 h-14 rounded-full shadow-2xl flex items-center justify-center text-2xl transition-transform hover:scale-105">
        <span id="cb-icon">🤖</span>
    </button>
</div>

<script>
(function () {
    const panel   = document.getElementById('cb-panel');
    const toggle  = document.getElementById('cb-toggle');
    const icon    = document.getElementById('cb-icon');
    const cerrar  = document.getElementById('cb-cerrar');
    const chat    = document.getElementById('cb-chat');
    const form    = document.getElementById('cb-form');
    const input   = document.getElementById('cb-input');
    const enviar  = document.getElementById('cb-enviar');
    const limpiar = document.getElementById('cb-limpiar');
    const sugBox  = document.getElementById('cb-sugerencias');
    const CSRF    = '{{ csrf_token() }}';

    function abrir(v) {
        panel.classList.toggle('hidden', !v);
        icon.textContent = v ? '✕' : '🤖';
        if (v) input.focus();
    }
    toggle.addEventListener('click', () => abrir(panel.classList.contains('hidden')));
    cerrar.addEventListener('click', () => abrir(false));

    function burbuja(texto, lado) {
        document.getElementById('cb-placeholder')?.remove();
        sugBox?.remove();
        const wrap = document.createElement('div');
        wrap.className = 'flex ' + (lado === 'user' ? 'justify-end' : 'justify-start');
        const color = lado === 'user' ? 'bg-sky-600 text-white' : 'bg-white border border-slate-200 text-slate-800';
        const b = document.createElement('div');
        b.className = `max-w-[85%] ${color} rounded-2xl px-3 py-2 text-sm whitespace-pre-wrap shadow-sm`;
        b.textContent = texto;
        wrap.appendChild(b);
        chat.appendChild(wrap);
        chat.scrollTop = chat.scrollHeight;
        return b;
    }

    async function preguntar(mensaje) {
        burbuja(mensaje, 'user');
        input.value = '';
        enviar.disabled = true;
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
                    m.textContent = '🔧 ' + data.herramientas.join(', ');
                    cargando.appendChild(m);
                }
            } else {
                cargando.textContent = '⚠️ ' + (data.error || 'Error desconocido.');
            }
        } catch (e) {
            cargando.textContent = '⚠️ Error de conexión.';
        } finally {
            enviar.disabled = false;
            chat.scrollTop = chat.scrollHeight;
        }
    }

    form.addEventListener('submit', e => {
        e.preventDefault();
        const m = input.value.trim();
        if (m) preguntar(m);
    });

    document.querySelectorAll('.cb-sugerencia').forEach(b =>
        b.addEventListener('click', () => preguntar(b.textContent.trim())));

    limpiar.addEventListener('click', async () => {
        await fetch('{{ route('admin.asistente.limpiar') }}', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json'},
        });
        chat.innerHTML = '<div id="cb-placeholder" class="text-slate-400 text-xs text-center mt-10">Conversación reiniciada. Escribe una pregunta.</div>';
    });
})();
</script>
