# SOLE — E-commerce con predicción de churn

Proyecto académico de Business Intelligence sobre un e-commerce de electrodomésticos
y tecnología. Incluye una tienda funcional, un modelo de predicción de abandono de
clientes (customer churn) y un asistente conversacional para consultar el riesgo en
lenguaje natural.

## Contenido

| Parte | Tecnología | Carpeta |
|---|---|---|
| Tienda + panel admin/BI | Laravel 13 (PHP 8.3), Blade, Tailwind, MySQL | raíz (`app/`, `routes/`, …) |
| Modelo de churn | Python, scikit-learn (Random Forest), FastAPI | [`modelo/`](modelo/) |
| Asistente BI | Groq + Llama 3.3 (tool-calling) | `app/Services/` |
| Documentación | Markdown | [`docs/`](docs/) |
| Dataset original | Kaggle (5,630 clientes) | `E Commerce Dataset.xlsx` |
| Dump de la BD | SQL | [`database/sql/ne_bi.sql`](database/sql/ne_bi.sql) |

## Instalación

### 1. Base de datos

Arranca MySQL (puerto 3306) e importa el dump, que ya trae el esquema y los datos de
demo (5,630 clientes scoreados, 39 productos, usuario admin):

```bash
/opt/lampp/bin/mysql -u root < database/sql/ne_bi.sql   # XAMPP
mysql -u root < database/sql/ne_bi.sql                   # MySQL normal
```

El script crea la base `ne_bi` por sí solo (`CREATE DATABASE`), no hace falta crearla
a mano. Los datos son ficticios.

Alternativa sin dump: crear la base `ne_bi` vacía y usar `php artisan migrate --seed`.
En ese caso el riesgo queda vacío hasta correr `modelo/predecir.py`; con el dump ya
viene calculado.

### 2. Tienda (Laravel)

```bash
composer install
cp .env.example .env          # configura DB_* y GROQ_API_KEY
php artisan key:generate
php artisan serve --port=8090
```

- Admin: `http://localhost:8090/admin/login`
- Tienda: `http://localhost:8090/`

### 3. Modelo (Python)

```bash
cd modelo
python -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
python entrenar.py            # entrena y guarda artefactos/modelo.pkl
python predecir.py            # puntúa clientes -> MySQL
uvicorn api:app --port 9000   # servicio de predicción en vivo
```

### 4. Asistente

Consigue una API key gratis en <https://console.groq.com/keys> y ponla en `.env` como
`GROQ_API_KEY`. La key va solo en `.env` (ignorado por git); nunca se sube al repo.

## Acceso

Credenciales de demo (vienen en el dump):

| Acceso | Usuario | Contraseña |
|---|---|---|
| Panel admin / BI | `admin@nebi.com` | `admin123` |
| Cliente (tienda) | cualquier email de cliente, ej. `alan.cepeda.54849@correo.com` | `password` |

## Uso

Con el dump importado y la tienda corriendo, el panel BI funciona de inmediato (el
riesgo ya está calculado). El asistente necesita además la `GROQ_API_KEY`.

### Panel BI

1. Entra a `http://localhost:8090/admin/login` con `admin@nebi.com` / `admin123`.
2. En el menú lateral abre **Clientes en riesgo**: lista ordenada por probabilidad de
   abandono, con filtros por nivel, categoría y ciudad.
3. Haz clic en un cliente (ej. Alan Cepeda, el de mayor riesgo) para ver su detalle:
   % de riesgo, nivel, acción sugerida y productos recomendados.
4. Opcional: botón "Re-evaluar en vivo" → requiere el servicio FastAPI corriendo
   (`uvicorn api:app --port 9000`, paso 3).

### Asistente

1. Asegúrate de tener `GROQ_API_KEY` en `.env` (paso 4). Si falta, el chat avisa.
2. En el menú lateral abre **Asistente BI**.
3. Pregunta en español, por ejemplo:
   - "¿Quiénes son los 5 clientes con mayor riesgo?"
   - "¿Cuántos clientes hay en cada nivel de riesgo?"
   - "¿El cliente Alan Cepeda se va a abandonar?"

   Bajo cada respuesta verás qué herramienta consultó (prueba de que usa datos reales).

Detalle técnico en [`docs/09-modelo-y-sistema.md`](docs/09-modelo-y-sistema.md) y
[`docs/08-chatbot-bi.md`](docs/08-chatbot-bi.md).

## Arquitectura

```
                ┌─────────────────────────┐
                │   Tienda (Laravel)      │  catálogo, carrito, checkout
                │   MySQL  (ne_bi)        │  tabla customers + churn_probability
                └───────────┬─────────────┘
                            │
        ┌───────────────────┼────────────────────┐
        ▼                   ▼                     ▼
  Panel BI admin     Modelo Python         Asistente BI (Groq/Llama)
  (clientes en       - entrenar.py          tool-calling sobre
   riesgo, detalle)  - predecir.py (lotes)  consultas seguras a
                     - api.py (en vivo)     la BD (BiTools)
```

1. El modelo se entrena con el dataset y guarda `modelo/artefactos/modelo.pkl`.
2. `predecir.py` puntúa por lotes y escribe `churn_probability` / `churn_level` en la
   tabla `customers`.
3. `api.py` (FastAPI) permite re-evaluar un cliente en vivo desde el admin.
4. El panel BI muestra los clientes en riesgo y el detalle de cada uno.
5. El asistente responde preguntas usando datos reales de la BD.

## Cómo se llama al modelo

Tres formas de usar el modelo, todas comparten la preparación de datos
(`modelo/features.py`) y el artefacto (`modelo/artefactos/modelo.pkl`):

### A) Entrenamiento (una vez, manual)

```
E Commerce Dataset.xlsx ──> entrenar.py ──> artefactos/modelo.pkl + metricas.json
```

`entrenar.py` busca el dataset en `modelo/../E Commerce Dataset.xlsx` (raíz del repo),
compara 3 modelos y guarda el mejor (Random Forest).

### B) Scoring por lotes (todos los clientes, manual)

```
MySQL customers ──> predecir.py (carga modelo.pkl) ──> escribe de vuelta:
                    churn_probability, churn_level, churn_scored_at
```

`predecir.py` lee las credenciales del mismo `.env` de Laravel, hace
`SELECT * FROM customers`, predice y actualiza esas columnas. El panel BI y el
asistente solo leen esas columnas ya calculadas (no llaman al modelo en cada vista).

### C) Predicción en vivo (un cliente, desde el admin)

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

La URL la define `CHURN_API_URL` en `.env` (por defecto `http://127.0.0.1:9000`) y
debe coincidir con el puerto de uvicorn. Si el servicio no está corriendo, solo falla
la re-evaluación en vivo; el resto del panel usa el último scoring por lotes.

### Niveles de riesgo

El modelo guarda una probabilidad (0–1) que `features.py` traduce a un nivel
(`nivel_churn`):

| Nivel | Probabilidad | Acción sugerida (`F.accion`) |
|---|---|---|
| alto | ≥ 0.70 | Cupón de descuento inmediato |
| medio | 0.40 – 0.69 | Correo personalizado con productos de su categoría favorita |
| moderado | 0.20 – 0.39 | Campaña de remarketing |
| bajo | < 0.20 | Sin acción / fidelización normal |

En resumen: el modelo escribe `churn_probability` / `churn_level` en la BD, y todo el
sistema (panel, detalle, asistente) lee de ahí.

## Documentación

La explicación del código y un mini-curso de ML están en [`docs/`](docs/). Empieza por
[`docs/README.md`](docs/README.md). El plan metodológico completo está en
[`PLAN.md`](PLAN.md).

## Limitaciones

Proyecto académico; se dejaron fuera (o simplificadas) varias cosas a propósito:

- El scoring no es automático al registrar/comprar: hay que correr `predecir.py`
  (lotes) o pulsar "Re-evaluar" (en vivo). No hay re-scoring masivo desde la interfaz.
- El re-scoring es manual; no hay cron programado.
- El servicio en vivo (FastAPI) se levanta aparte y su puerto debe coincidir con
  `CHURN_API_URL`. Si está apagado, el panel usa el último scoring por lotes.
- Sin "Mis pedidos" ni historial de compras para el cliente.
- Dashboard sin gráficos (muestra conteos/tablas).
- Productos sin imágenes.
- El modelo se entrenó con un dataset de Kaggle adaptado al rubro; no son clientes
  reales y las acciones de retención son ilustrativas.
- Credenciales y datos de demo son placeholders académicos.

## Notas

- El modelo (`modelo/artefactos/modelo.pkl`, ~17 MB) se incluye en el repo para que el
  sistema funcione sin reentrenar.
- La `GROQ_API_KEY` y el `.env` no se versionan; usa `.env.example` como plantilla.
