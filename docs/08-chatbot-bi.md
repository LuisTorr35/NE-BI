# 08 · Chatbot BI (Groq + Llama, con tool-calling)

> Un asistente en el panel admin al que le preguntas en lenguaje natural sobre el
> riesgo de abandono y responde con **datos reales** de la base de datos.

---

## 1. El problema que resuelve

Un modelo de lenguaje (Llama) **no conoce** a los 5,630 clientes de SOLE. Si solo le
mandas la pregunta *"¿quiénes están en riesgo?"*, se inventa nombres y números
(alucina). Hay que **anclarlo** a la base de datos real.

La solución es **tool-calling** (llamada de funciones): le damos al modelo un catálogo
de "herramientas" (consultas seguras), él decide cuál usar, nosotros la ejecutamos en
Laravel y le devolvemos los datos para que redacte la respuesta.

---

## 2. El flujo completo

```
Admin escribe: "¿quiénes son los 5 de mayor riesgo?"
        │
        ▼
GroqService manda la pregunta + las definiciones de herramientas a Groq/Llama
        │
        ▼
Llama responde: "llama a clientes_en_riesgo(nivel=alto, limite=5)"   ← NO texto, una tool_call
        │
        ▼
BiTools ejecuta esa consulta Eloquent → trae 5 clientes REALES de MySQL
        │
        ▼
GroqService devuelve esos datos a Llama (rol "tool")
        │
        ▼
Llama redacta en español: "Los 5 clientes con mayor riesgo son: ..."
        │
        ▼
Se muestra en el chat
```

El ciclo "pedir herramienta → ejecutar → devolver" puede repetirse hasta 4 veces por
si necesita varias consultas. Lo controla un `for` en `GroqService::preguntar()`.

---

## 3. Las piezas (archivos)

| Archivo | Rol |
|---|---|
| `.env` | Credenciales: `GROQ_API_KEY`, `GROQ_MODEL`, `GROQ_BASE_URL` (NUNCA hardcodeadas) |
| `config/services.php` | Expone esas variables como `config('services.groq.*')` |
| `app/Services/BiTools.php` | Las **herramientas**: consultas Eloquent acotadas + sus definiciones |
| `app/Services/GroqService.php` | Cliente HTTP a Groq + orquesta el ciclo de tool-calling |
| `app/Http/Controllers/Admin/ChatbotController.php` | Endpoints web + historial en sesión |
| `resources/views/admin/asistente/index.blade.php` | La interfaz de chat (Tailwind + JS) |
| rutas en `routes/web.php` | `/admin/asistente`, `/asistente/preguntar`, `/asistente/limpiar` |

---

## 4. Las herramientas (BiTools)

Son funciones PHP que el modelo puede pedir. **No hay SQL libre**: el modelo solo elige
de esta lista cerrada, con parámetros validados. Por eso es seguro (sin inyección) y
está siempre anclado a datos reales.

| Herramienta | Qué devuelve | Ejemplo de pregunta |
|---|---|---|
| `resumen_riesgo()` | Conteo por nivel (alto/medio/moderado/bajo) | "¿cuántos en cada nivel?" |
| `clientes_en_riesgo(nivel, categoria, ciudad, limite)` | Lista filtrada, ordenada por probabilidad | "los de alto riesgo en celulares" |
| `buscar_cliente(nombre)` | Riesgo y datos de un cliente concreto | "¿X se va a abandonar?" |
| `stats_por_categoria()` | Riesgo agrupado por categoría favorita | "¿qué categoría tiene más riesgo?" |
| `stats_por_ciudad()` | Riesgo agrupado por City Tier | "¿qué ciudad tiene más riesgo?" |

Detalles de seguridad/calidad:
- **Tope de 50 filas** (`LIMITE_MAX`) para no inflar tokens.
- `normalizarCategoria()` mapea términos en español ("celulares" → `Mobile Phone`).
- Las definiciones en formato OpenAI/Groq están en `BiTools::definiciones()`.

---

## 4.1 ¿Cómo decide el modelo QUÉ herramienta usar?

No hay reglas `if/else` programadas. **El modelo elige solo**, leyendo la
**descripción en lenguaje natural** de cada herramienta. Ese contexto vive en dos sitios:

1. **Las descripciones** en `BiTools::definiciones()`. Cada herramienta lleva un campo
   `description` con frases tipo *"Úsalo para…"* que indican cuándo aplica:
   ```php
   'name' => 'buscar_cliente',
   'description' => 'Busca un cliente por nombre... Úsalo para "¿este cliente se va a ir?".',
   ```
2. **El envío** en `GroqService::preguntar()`, que en CADA llamada manda esas
   definiciones junto a la pregunta:
   ```php
   'tools'       => BiTools::definiciones(),  // las descripciones
   'tool_choice' => 'auto',                   // el modelo decide si usar alguna
   ```

Flujo de la decisión:
- El modelo compara tu pregunta contra cada `description` y elige la que mejor calza.
- En vez de texto, responde un `tool_calls` (ej. `buscar_cliente(nombre="Alan Cepeda")`).
- El `for` de `preguntar()` ejecuta esa función real y le devuelve los datos.

> Para que reconozca mejor un tipo de pregunta, se mejora la `description` de la
> herramienta correspondiente (no se toca código de control de flujo).

## 5. El "system prompt" (las reglas del bot)

En `GroqService::systemPrompt()` se le imponen reglas estrictas:
- Solo habla de churn/clientes de SOLE; rechaza temas ajenos.
- **Nunca inventa** datos: para cualquier dato concreto DEBE llamar a una herramienta.
- Habla de "probabilidad de abandono", no de certezas.
- Sugiere la acción de retención según el nivel (alto → cupón, etc.).

---

## 6. Por qué tool-calling y no otras opciones

| Enfoque | Por qué NO se usó |
|---|---|
| Inyectar todo el contexto fijo | No escala: no puede responder búsquedas específicas sin meter miles de filas |
| Texto → SQL (el modelo escribe SQL) | Riesgo de inyección y de SQL inválido |
| **Tool-calling** ✅ | Exacto, seguro (lista cerrada de consultas) y sin alucinaciones |

---

## 7. Configuración

En `.env`:
```
GROQ_API_KEY=gsk_...        # se obtiene gratis en https://console.groq.com/keys
GROQ_MODEL=llama-3.3-70b-versatile
GROQ_BASE_URL=https://api.groq.com/openai/v1
```
La API de Groq es **compatible con la de OpenAI**, por eso el endpoint es
`/chat/completions` y soporta `tools` + `tool_choice` igual que OpenAI.

> Si la key expira o es inválida, Groq responde `401 invalid_request_error`. Genera
> una nueva en la consola de Groq y reemplázala en `.env` (luego `php artisan config:clear`).

---

## 8. Cómo se usa

1. Entra al admin → menú lateral **🤖 Asistente BI**.
2. Escribe una pregunta o usa las sugerencias rápidas.
3. Bajo cada respuesta verás qué herramienta consultó (ej. `🔧 consultó: clientes_en_riesgo`),
   prueba de que la respuesta sale de datos reales y no inventados.
