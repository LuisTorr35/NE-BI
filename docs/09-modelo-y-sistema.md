# 09 · Cómo el modelo se comunica con el sistema

> Explicación de la "tubería" entre el modelo de Machine Learning (Python) y la
> tienda/panel (Laravel + MySQL): cómo se conectan y cómo terminas viendo a los
> **clientes en peligro de abandonar**.

---

## 1. La idea en una frase

El modelo **no habla directamente** con las pantallas. El punto de encuentro es la
**base de datos**: el modelo escribe el riesgo en la tabla `customers`, y todo el
sistema (panel, detalle, chatbot) **lee de ahí**.

```
   Python (modelo)                 MySQL (ne_bi)                Laravel (vistas)
 ┌────────────────┐   escribe   ┌──────────────────┐  lee   ┌──────────────────┐
 │ predecir.py    │ ──────────► │ customers.       │ ─────► │ Panel "Clientes  │
 │ api.py         │             │  churn_probability│        │  en riesgo"      │
 │ (modelo.pkl)   │             │  churn_level      │        │ Detalle cliente  │
 └────────────────┘             │  churn_scored_at  │        │ Chatbot BI       │
                                └──────────────────┘        └──────────────────┘
```

Ventaja: las vistas son rápidas (solo `SELECT`, no ejecutan el modelo en cada carga)
y el modelo puede correr cuando se quiera sin frenar la tienda.

---

## 2. Las columnas puente (tabla `customers`)

| Columna | Qué guarda | Quién la escribe |
|---|---|---|
| `churn_probability` | Probabilidad 0–1 de que el cliente abandone | `predecir.py` (lotes) / `api.py` (en vivo) |
| `churn_level` | Nivel legible: `alto` / `medio` / `moderado` / `bajo` | igual |
| `churn_scored_at` | Cuándo se calculó por última vez | igual |

El nivel se deriva de la probabilidad en `features.py` (`nivel_churn`):

| Nivel | Probabilidad | Acción (`F.accion`) |
|---|---|---|
| **alto** | ≥ 0.70 | Cupón de descuento inmediato |
| **medio** | 0.40 – 0.69 | Correo personalizado con productos de su categoría favorita |
| **moderado** | 0.20 – 0.39 | Campaña de remarketing |
| **bajo** | < 0.20 | Sin acción / fidelización normal |

---

## 3. Las dos formas de calcular el riesgo

### 3.1 Por lotes — `predecir.py` (todos los clientes)
```
SELECT * FROM customers ─► features.preparar() ─► modelo.predict_proba()
                                                        │
                          UPDATE customers SET churn_* ◄┘   (todos a la vez)
```
- Se conecta a MySQL leyendo las credenciales del **mismo `.env` de Laravel**
  (`DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, …). Así el modelo y la tienda **nunca se
  desincronizan**: si cambias la BD en `.env`, el modelo la usa automáticamente.
- Se corre a mano cuando quieres refrescar el riesgo de toda la base:
  `cd modelo && python predecir.py`.

### 3.2 En vivo — `api.py` (un cliente, bajo demanda)
Servicio **FastAPI** que Laravel llama por HTTP cuando pulsas "Re-evaluar":

```
Admin (detalle cliente)
   │  POST /predict  {tenure, complain, prefered_order_cat, ...}
   ▼
api.py  (uvicorn, puerto 9000)
   │  features.preparar() ─► modelo.predict_proba()
   ▼
{ "churn_probability": 0.83, "churn_level": "alto", "accion_sugerida": "..." }
   │
   ▼
Laravel actualiza customers.churn_probability / churn_level / churn_scored_at
```

- La URL la define `CHURN_API_URL` en `.env` (por defecto `http://127.0.0.1:9000`) y
  **debe coincidir con el puerto de uvicorn** (`uvicorn api:app --port 9000`).
- En Laravel lo hace `RiesgoController@evaluar` (`Http::post(...)`).
- Si el servicio Python está apagado, solo falla la re-evaluación en vivo; el panel
  sigue mostrando el último resultado por lotes.

> Ambos caminos usan el **mismo `modelo.pkl` y el mismo `features.py`**, así que dan
> resultados consistentes; la única diferencia es "todos a la vez" vs "uno ahora".

---

## 4. Cómo terminas viendo a los clientes en peligro

Una vez el riesgo está en la BD, el sistema te lo entrega por **tres vías**:

1. **Panel BI → "Clientes en riesgo"** (`RiesgoController@index`)
   Lista ordenada por probabilidad descendente, con filtros (nivel, categoría,
   ciudad). Es el "a quién contactar primero".

2. **Detalle del cliente** (`RiesgoController@show`)
   Su % de riesgo, su nivel, la acción sugerida, productos recomendados de su
   categoría, y el botón **"Re-evaluar en vivo"** (camino §3.2).

3. **Chatbot BI** (botón flotante 🤖 abajo a la derecha, disponible en todo el panel)
   Preguntas en lenguaje natural. El bot **no inventa**: consulta esas mismas
   columnas con herramientas seguras (`BiTools`) vía tool-calling y redacta la
   respuesta con datos reales (incluidos porcentajes ya calculados). Detalle en
   [`08-chatbot-bi.md`](08-chatbot-bi.md).

---

## 5. Resumen del flujo completo

```
E Commerce Dataset.xlsx
        │ entrenar.py
        ▼
   modelo.pkl ──────────────┐
                            │ (lo cargan predecir.py y api.py)
   ┌────────────────────────┴───────────────────────┐
   │ predecir.py (lotes)        api.py (en vivo)     │
   └────────────────────────┬───────────────────────┘
                            ▼ escriben
              customers.churn_probability / churn_level
                            │ leen
   ┌────────────────────────┴───────────────────────┐
   │ Panel riesgo   Detalle cliente   Chatbot BI     │
   └─────────────────────────────────────────────────┘
```

**El modelo "te da" los clientes en peligro escribiendo su riesgo en la BD; el panel y
el chatbot solo lo muestran.**
