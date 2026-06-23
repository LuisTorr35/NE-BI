# SOLE — E-commerce + Business Intelligence (predicción de churn)

Proyecto académico de **Business Intelligence** sobre un e-commerce de
electrodomésticos y tecnología (**SOLE**). Combina una tienda funcional con un
sistema de **predicción de abandono de clientes (customer churn)** en Machine
Learning y un **asistente conversacional** (chatbot) para consultar el riesgo en
lenguaje natural.

---

## ¿Qué incluye?

| Parte | Tecnología | Carpeta |
|---|---|---|
| Tienda + Panel admin/BI | Laravel 13 (PHP 8.3), Blade, Tailwind, MySQL | raíz (`app/`, `routes/`, …) |
| Modelo de predicción de churn | Python, scikit-learn (Random Forest), FastAPI | [`modelo/`](modelo/) |
| Chatbot BI | Groq + Llama 3.3 (tool-calling) | `app/Services/` |
| Documentación / mini-curso de ML | Markdown | [`docs/`](docs/) |
| Dataset original | Kaggle (5,630 clientes) | `E Commerce Dataset.xlsx` |
| Dump de la BD (datos de demo listos) | SQL | [`database/sql/ne_bi.sql`](database/sql/ne_bi.sql) |

---

## Arquitectura

```
                ┌─────────────────────────┐
                │   Tienda SOLE (Laravel) │  catálogo, carrito, checkout
                │   MySQL  (ne_bi)        │  tabla customers + churn_probability
                └───────────┬─────────────┘
                            │
        ┌───────────────────┼────────────────────┐
        ▼                   ▼                     ▼
  Panel BI admin     Modelo Python         Chatbot BI (Groq/Llama)
  (clientes en       - entrenar.py          tool-calling sobre
   riesgo, detalle)  - predecir.py (lotes)  consultas seguras a
                     - api.py (en vivo)     la BD (BiTools)
```

1. **El modelo** se entrena con el dataset y guarda `modelo/artefactos/modelo.pkl`.
2. **`predecir.py`** puntúa por lotes y escribe `churn_probability` / `churn_level`
   en la tabla `customers` de MySQL.
3. **`api.py`** (FastAPI) permite re-evaluar un cliente en vivo desde el admin.
4. **El panel BI** muestra los clientes en riesgo y el detalle de cada uno.
5. **El chatbot** responde preguntas en español usando datos reales de la BD.

---

## Cómo se llama al modelo (flujo exacto)

Hay **tres formas** en que el sistema usa el modelo, y todas comparten la misma
preparación de datos (`modelo/features.py`) y el mismo artefacto
(`modelo/artefactos/modelo.pkl`):

### A) Entrenamiento (una vez, manual)
```
E Commerce Dataset.xlsx ──> entrenar.py ──> artefactos/modelo.pkl + metricas.json
```
- `entrenar.py` busca el dataset en `modelo/../E Commerce Dataset.xlsx` (la raíz del
  repo). Compara 3 modelos y guarda el mejor (Random Forest) en `artefactos/`.

### B) Scoring por lotes (puntuar a todos, manual / periódico)
```
MySQL customers ──> predecir.py (carga modelo.pkl) ──> escribe de vuelta:
                    churn_probability, churn_level, churn_scored_at
```
- `predecir.py` se conecta a MySQL (`mysql+pymysql://root:@127.0.0.1:3306/ne_bi`),
  lee `SELECT * FROM customers`, predice y hace `UPDATE` de esas columnas.
- El **panel BI** y el **chatbot** solo leen esas columnas ya calculadas (no llaman
  al modelo en cada vista → rápido).

### C) Predicción en vivo (un cliente, automática desde el admin)
```
Admin pulsa "Re-evaluar" en el detalle de un cliente
        │
        ▼
RiesgoController::evaluar  ── HTTP POST {features} ──>  FastAPI api.py  /predict
        │                      (CHURN_API_URL=                (uvicorn, puerto 9000)
        │                       http://127.0.0.1:9000)               │
        ▼                                                            ▼
actualiza customers.churn_probability/level   <── {churn_probability, churn_level,
                                                    accion_sugerida}
```
- La URL la define `CHURN_API_URL` en `.env` (por defecto `http://127.0.0.1:9000`).
  **Debe coincidir con el puerto donde levantas uvicorn** (`--port 9000`).
- Si el servicio Python no está corriendo, el admin muestra un aviso y el resto del
  panel sigue funcionando (usa los valores del último scoring por lotes).

> **Contrato de datos** (lo que se comparte): las features de entrada (tenure,
> complain, prefered_order_cat, etc.) y de salida (`churn_probability`,
> `churn_level`) son las mismas en los tres caminos, definidas en `features.py`.

### Cómo el sistema te muestra los clientes en peligro de abandonar

El modelo guarda en cada cliente una **probabilidad** (0–1) que `features.py` traduce
a un **nivel** legible (`nivel_churn`):

| Nivel | Probabilidad | Acción de retención sugerida (`F.accion`) |
|---|---|---|
| **alto** | ≥ 0.70 | Cupón de descuento inmediato |
| **medio** | 0.40 – 0.69 | Correo personalizado con productos de su categoría favorita |
| **moderado** | 0.20 – 0.39 | Campaña de remarketing |
| **bajo** | < 0.20 | Sin acción / fidelización normal |

Con esos datos ya en la tabla `customers`, el sistema te los entrega por **tres vías**:

1. **Panel BI → "Clientes en riesgo"** (`RiesgoController@index`): lista ordenada por
   probabilidad descendente, con filtros por nivel, categoría y ciudad. Es la lista de
   "a quién contactar primero".
2. **Detalle del cliente** (`RiesgoController@show`): su % de riesgo, su nivel, la
   acción sugerida y productos recomendados; con botón **"Re-evaluar en vivo"**
   (camino C de arriba).
3. **Chatbot BI** (`Asistente BI`): preguntas en lenguaje natural
   ("¿quiénes son los 5 de mayor riesgo?", "¿X se va a ir?"). El bot **no inventa**:
   consulta esas mismas columnas vía herramientas seguras (`BiTools`) y responde con
   datos reales. Ver [`docs/08-chatbot-bi.md`](docs/08-chatbot-bi.md).

> En resumen: **el modelo escribe `churn_probability`/`churn_level` en la BD, y todo el
> sistema (panel, detalle, chatbot) lee de ahí.** Esa columna es el punto de encuentro.

---

## Puesta en marcha

### 1. Base de datos (MySQL / XAMPP)
Arranca MySQL (puerto 3306) e **importa el dump ya listo** — incluye el esquema y
**todos los datos de demo** (5,630 clientes ya scoreados, 39 productos, usuario admin):

```bash
# XAMPP
/opt/lampp/bin/mysql -u root < database/sql/ne_bi.sql
# o MySQL normal
mysql -u root < database/sql/ne_bi.sql
```
El script crea la base `ne_bi` por sí solo (`CREATE DATABASE`), así que no hace falta
crearla a mano. *(Datos ficticios; no son clientes reales.)*

> Alternativa sin dump: crear la base `ne_bi` vacía y usar las migraciones/seeders de
> Laravel (`php artisan migrate --seed`). Pero entonces el riesgo de churn queda vacío
> hasta correr `modelo/predecir.py`. **Con el dump ya viene todo calculado.**

### 2. Tienda (Laravel) — desde la raíz del repo
```bash
composer install
cp .env.example .env          # configura DB_* y GROQ_API_KEY
php artisan key:generate
php artisan serve --port=8090
```
- Admin: `http://localhost:8090/admin/login`
- Tienda: `http://localhost:8090/`

**Credenciales de demo** (vienen en el dump):

| Acceso | Usuario | Contraseña |
|---|---|---|
| Panel admin / BI | `admin@nebi.com` | `admin123` |
| Cliente (tienda) | cualquier email de cliente, ej. `alan.cepeda.54849@correo.com` | `password` |

### 3. Modelo (Python)
```bash
cd modelo
python -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
python entrenar.py            # entrena y guarda artefactos/modelo.pkl
python predecir.py            # puntúa clientes -> MySQL
uvicorn api:app --port 9000   # servicio de predicción en vivo
```

### 4. Chatbot BI
Consigue una API key gratis en <https://console.groq.com/keys> y ponla en
`.env` como `GROQ_API_KEY`. Luego entra al admin → **Asistente BI**.

> ⚠️ La `GROQ_API_KEY` va **solo** en `.env` (que está en `.gitignore`); nunca se
> hardcodea ni se sube al repo.

---

## Ver la pantalla de BI y el chatbot (paso a paso)

Con el dump importado y la tienda corriendo, **el panel BI funciona de inmediato**
(el riesgo ya está calculado). El chatbot necesita además la `GROQ_API_KEY`.

### Pantalla de BI (clientes en riesgo)
1. Entra a `http://localhost:8090/admin/login` con `admin@nebi.com` / `admin123`.
2. En el menú lateral abre **⚠️ Clientes en riesgo**: verás la lista ordenada por
   probabilidad de abandono, con filtros por nivel, categoría y ciudad.
3. Haz clic en un cliente (ej. **Alan Cepeda**, el de mayor riesgo) para ver su
   **detalle**: % de riesgo, nivel, acción sugerida y productos recomendados.
4. *(Opcional)* Botón **"Re-evaluar en vivo"** → requiere el servicio FastAPI
   corriendo (`uvicorn api:app --port 9000`, paso 3).

### Chatbot BI
1. Asegúrate de tener `GROQ_API_KEY` en `.env` (paso 4). Si falta, el chat avisa.
2. En el menú lateral abre **🤖 Asistente BI**.
3. Pregunta en español, por ejemplo:
   - *"¿Quiénes son los 5 clientes con mayor riesgo?"*
   - *"¿Cuántos clientes hay en cada nivel de riesgo?"*
   - *"¿El cliente Alan Cepeda se va a abandonar?"*
   Bajo cada respuesta verás qué herramienta consultó (prueba de que usa datos reales).

> Detalle técnico del panel y el chatbot en
> [`docs/09-modelo-y-sistema.md`](docs/09-modelo-y-sistema.md) y
> [`docs/08-chatbot-bi.md`](docs/08-chatbot-bi.md).

---

## Documentación

Toda la explicación del código y un mini-curso de ML están en [`docs/`](docs/).
Empieza por [`docs/README.md`](docs/README.md). El plan metodológico completo está
en [`PLAN.md`](PLAN.md).

---

## Limitaciones conocidas

Este es un proyecto **académico**; se dejaron fuera (o simplificadas) varias cosas a
propósito:

- **Scoring no automático en el registro**: cuando un cliente nuevo se registra o
  compra, su riesgo **no** se recalcula solo. Hay que correr `predecir.py` (lotes) o
  pulsar "Re-evaluar" en el admin (en vivo). No hay botón de re-scoring masivo desde
  la interfaz.
- **El re-scoring es manual** (no programado): no hay un cron que reescore cada noche;
  se corre `predecir.py` a mano cuando se quiere actualizar el riesgo de todos.
- **El servicio en vivo (FastAPI) debe levantarse aparte** (`uvicorn ... --port 9000`)
  y su puerto debe coincidir con `CHURN_API_URL`. Si no está corriendo, solo falla la
  re-evaluación en vivo; el resto del panel usa el último scoring por lotes.
- **Sin "Mis pedidos"** ni historial de compras para el cliente en la tienda.
- **Dashboard sin gráficos** (Chart.js): muestra conteos/tablas, no visualizaciones.
- **Productos sin imágenes** (solo datos).
- **El modelo se entrenó con un dataset de Kaggle** adaptado al rubro; no son clientes
  reales, y las acciones de retención sugeridas son ilustrativas.
- **Credenciales de demo**: el admin y los datos son **placeholders académicos**.

---

## Notas

- El modelo (`modelo/artefactos/modelo.pkl`, ~17 MB) se incluye en el repo para que
  el sistema funcione sin reentrenar.
- La `GROQ_API_KEY` y el `.env` no se versionan; usa `.env.example` como plantilla.

---

## Notas

- Las credenciales de admin de demo y los datos son **placeholders académicos**.
- El modelo (`modelo/artefactos/modelo.pkl`, ~17 MB) se incluye en el repo para que
  el sistema funcione sin reentrenar.
