# Modelo de Predicción de Churn — Implementación

> Cómo se implementó el sistema de predicción de abandono de clientes (customer churn)
> para NE-BI Electro. Modelo en **Python + scikit-learn**, integrado a la tienda
> **Laravel + MySQL** por dos vías: scoring por lotes y un servicio en vivo (FastAPI).

---

## 1. Resumen

Se entrenó un clasificador binario que estima la **probabilidad de que un cliente
abandone** (`Churn` 1/0). El modelo aprende del dataset de Kaggle (5,630 clientes,
16.8% de churn) adaptado al rubro electro/tecnología, y se aplica sobre la tabla
`customers` de la tienda para alimentar la pestaña de Business Intelligence del
panel admin.

**Modelo elegido: Random Forest (con `class_weight='balanced'`)** por dar el mejor
balance recall/F1 (ver §5).

---

## 2. Estructura de archivos

```
modelo/
├── features.py        # Preparación de datos COMPARTIDA (entrenamiento + scoring)
├── entrenar.py        # Entrena, compara 3 modelos y guarda el mejor
├── predecir.py        # Scoring por lotes -> escribe churn_probability en MySQL
├── api.py             # Servicio FastAPI para predicción en vivo (un cliente)
├── requirements.txt   # Dependencias Python
└── artefactos/
    ├── modelo.pkl     # Pipeline entrenado (preprocesado + Random Forest)
    └── metricas.json  # Métricas de comparación e importancia de variables
```

El punto crítico de diseño: **`features.py` centraliza toda la transformación** para
que el entrenamiento (desde el `.xlsx`) y el scoring (desde MySQL) usen exactamente
las mismas columnas y los mismos pasos. Si divergieran, el modelo recibiría un
espacio de variables distinto al que aprendió.

---

## 3. Pipeline de datos (etapas)

### 3.1 Carga
Se lee `E Commerce Dataset.xlsx` (hoja `E Comm`) y se renombran las columnas a
`snake_case`, idénticas a las de la tabla `customers` de MySQL.

### 3.2 Limpieza — consolidación de categorías duplicadas
El dataset trae etiquetas repetidas que se unifican antes de todo:
- `prefered_order_cat`: `Mobile` → `Mobile Phone`
- `preferred_payment_mode`: `CC` → `Credit Card`, `COD` → `Cash on Delivery`, `UPI` → `E wallet`
- `preferred_login_device`: `Phone` → `Mobile Phone`

### 3.3 Feature engineering (`agregar_features`)
- **`es_cliente_nuevo`** = `tenure <= 1`. El segmento de 0–1 mes concentra ~52% del
  churn; esta variable resultó la **2ª más importante** del modelo.
- **`cupones_por_pedido`** = `coupon_used / order_count` (sensibilidad a promociones).

### 3.4 Preprocesado (`ColumnTransformer` dentro del Pipeline)
- **Numéricas**: imputación por **mediana** + **StandardScaler**.
- **Categóricas**: imputación por **moda** + **OneHotEncoder** (`handle_unknown='ignore'`).

Todo va dentro de un `Pipeline` de scikit-learn, de modo que la imputación y el
escalado se ajustan **solo con el set de entrenamiento** (sin fuga de datos).

### 3.5 Partición
`train_test_split` 80/20 **estratificado** por la variable objetivo, `random_state=42`.

### 3.6 Manejo del desbalance (16.8% churn)
Se comparan dos estrategias: `class_weight='balanced'` y **SMOTE** (sobremuestreo
sintético de la clase minoritaria, vía `imbalanced-learn`).

---

## 4. Algoritmos

| Modelo | Rol | Manejo de desbalance |
|---|---|---|
| Regresión Logística | Base interpretable | `class_weight='balanced'` |
| **Random Forest** | **Principal** | `class_weight='balanced'` |
| Random Forest + SMOTE | Comparación | SMOTE |

Validación cruzada estratificada (5-fold) sobre F1, y evaluación final en el set de
test reservado.

---

## 5. Resultados (set de prueba, 1,126 clientes)

| Modelo | Accuracy | Precision | Recall | F1 | ROC-AUC |
|---|---|---|---|---|---|
| Regresión Logística | 0.845 | 0.524 | 0.858 | 0.651 | 0.903 |
| **Random Forest (balanced)** ⭐ | **0.961** | **0.832** | **0.963** | **0.893** | **0.995** |
| Random Forest + SMOTE | 0.949 | 0.863 | 0.826 | 0.844 | 0.986 |

> Se prioriza **Recall** (detectar a los que se van) sin sacrificar demasiada
> precisión. El Random Forest balanceado detecta el **96.3%** de los churners.
> Matriz de confusión del modelo elegido `[[TN, FP], [FN, TP]] = [[899, 37], [7, 183]]`:
> solo 7 churners se escapan.

### Top variables (importancia del Random Forest)
1. `tenure` (0.174) · 2. `es_cliente_nuevo` (0.159) · 3. `cashback_amount` (0.074)
· 4. `complain` (0.063) · 5. `warehouse_to_home` (0.054) · 6. `day_since_last_order`
(0.049) · `prefered_order_cat=Mobile Phone`, `marital_status=Single`…

Esto **confirma el análisis exploratorio**: la antigüedad y el ser cliente nuevo
dominan, seguidos del reclamo y la recencia.

---

## 6. Integración con la tienda (Laravel + MySQL)

Dos modos, complementarios (enfoque híbrido):

### 6.1 Scoring por lotes — `predecir.py`
Lee todos los `customers` de MySQL, calcula la probabilidad y **escribe de vuelta**
`churn_probability`, `churn_level` y `churn_scored_at`. Laravel solo lee esas
columnas para la pestaña BI. Reentrenar/reescorar = volver a correr el script.

Resultado real sobre los 5,630 clientes:

| Nivel | Clientes | Churn real observado | Acción |
|---|---|---|---|
| alto (≥0.70) | 904 | 99.3% | Cupón inmediato |
| medio (0.40–0.69) | 222 | 22.1% | Correo personalizado |
| moderado (0.20–0.39) | 475 | 0.2% | Remarketing |
| bajo (<0.20) | 4,029 | 0.0% | Sin acción |

### 6.2 Predicción en vivo — `api.py` (FastAPI)
Endpoint `POST /predict` que recibe el comportamiento de un cliente y devuelve su
probabilidad al instante. Laravel lo llama cuando un cliente es nuevo o su
comportamiento cambia (compra, reclamo). Ejemplo:

```bash
curl -X POST localhost:9000/predict -H "Content-Type: application/json" \
     -d '{"tenure":1,"complain":1,"prefered_order_cat":"Mobile Phone"}'
# -> {"churn_probability":0.8582,"churn_level":"alto","accion_sugerida":"Cupon de descuento inmediato"}
```

---

## 7. Umbrales de acción (PLAN.md §7)

| Probabilidad | Nivel | Acción de retención |
|---|---|---|
| ≥ 0.70 | alto | Cupón de descuento inmediato |
| 0.40 – 0.69 | medio | Correo con productos de su categoría favorita |
| 0.20 – 0.39 | moderado | Campaña de remarketing |
| < 0.20 | bajo | Sin acción / fidelización normal |

Definidos en `features.py` (`nivel_churn` y `ACCIONES`), reutilizados por el scoring
y la API.

---

## 8. Cómo ejecutar

```bash
cd modelo
pip install -r requirements.txt

python3 entrenar.py     # entrena y guarda artefactos/modelo.pkl + metricas.json
python3 predecir.py     # escribe las probabilidades en MySQL (tabla customers)

uvicorn api:app --port 9000     # levanta el servicio de predicción en vivo
```

Requisitos: MySQL/MariaDB de XAMPP corriendo, BD `ne_bi` con la tabla `customers`
poblada (seeder de Laravel). Conexión en `predecir.py`: `root` sin password en
`127.0.0.1:3306`.

---

## 9. Limitaciones (honestidad metodológica)

1. **Métricas optimistas en el scoring masivo**: `predecir.py` puntúa también
   clientes que el modelo vio en entrenamiento; por eso el nivel "alto" muestra
   99.3% de churn real. La medida honesta del desempeño es el **set de test**
   (recall 0.963, ver §5), no el conteo sobre toda la tabla.
2. **Dataset muy separable**: un ROC-AUC de 0.995 es altísimo y típico de este
   dataset de Kaggle; en datos reales de producción se espera un desempeño menor.
3. **Dominancia de `tenure`**: la antigüedad pesa mucho. Conviene vigilar que el
   modelo no dependa casi de una sola variable.
4. **Artefactos contraintuitivos del dataset**: mayor `satisfaction_score` y menor
   `day_since_last_order` correlacionan con MÁS churn. Se documentan como hallazgo a
   investigar, no como insight de negocio (ver PLAN.md §10).
5. **Datos no propios**: en producción el modelo se reentrenaría con el historial
   real de la tienda cuando exista.
