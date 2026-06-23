# Cómo trata el modelo los datos numéricos y categóricos

> Archivo: `modelo/entrenar.py`, función `construir_preprocesador()`.
> Numéricas y categóricas reciben **tratamientos distintos**, y un
> `ColumnTransformer` aplica cada uno a sus columnas correspondientes.

```python
def construir_preprocesador() -> ColumnTransformer:
    num = Pipeline([
        ("imputar", SimpleImputer(strategy="median")),
        ("escalar", StandardScaler()),
    ])
    cat = Pipeline([
        ("imputar", SimpleImputer(strategy="most_frequent")),
        ("codificar", OneHotEncoder(handle_unknown="ignore")),
    ])
    return ColumnTransformer([
        ("num", num, F.NUMERICAS),
        ("cat", cat, F.CATEGORICAS),
    ])
```

---

## Datos NUMÉRICOS — 2 pasos

### 1. `SimpleImputer(strategy="median")` — rellenar faltantes
7 columnas tenían valores vacíos (`tenure`, `day_since_last_order`, etc.). Este paso
**rellena cada hueco con la mediana** de esa columna.

**¿Por qué mediana y no promedio?** La mediana es **robusta a valores extremos**.
Ej: `warehouse_to_home` va de 5 a 127; un outlier de 127 inflaría el promedio, pero
la mediana casi no se mueve.

### 2. `StandardScaler` — escalar
Transforma cada columna a **media 0 y desviación 1**: `(valor − media) / desviación`.

**¿Por qué?** Las variables están en escalas muy distintas:
- `cashback_amount` → hasta 325
- `satisfaction_score` → 1 a 5
- `complain` → 0 o 1

Sin escalar, la Regresión Logística creería que `cashback` (números grandes) es más
importante solo por su magnitud. Al escalar, **todas compiten en igualdad**.

> Ejemplo: `tenure` con valores [1, 9, 30] → escalado queda ≈ [−1.2, −0.1, +1.8].

---

## Datos CATEGÓRICOS — 2 pasos

### 1. `SimpleImputer(strategy="most_frequent")` — rellenar faltantes
Para texto no existe "mediana", así que rellena con el **valor más frecuente** (la
moda). Ej: si falta el género, pone el más común.

### 2. `OneHotEncoder(handle_unknown="ignore")` — texto a números
Los algoritmos **no entienden texto**, solo números. One-Hot convierte **cada
categoría en su propia columna binaria** (0/1).

Ejemplo con `marital_status`:

| Cliente | marital_status | → | es_Single | es_Married | es_Divorced |
|---|---|---|---|---|---|
| A | Single | → | **1** | 0 | 0 |
| B | Married | → | 0 | **1** | 0 |

Así "Single" no es "mayor" que "Married" — solo presencia/ausencia. Por eso
`prefered_order_cat` se vuelve 5 columnas, `preferred_payment_mode` 4, etc.
(el modelo termina con ~35 columnas).

**`handle_unknown="ignore"`**: si en producción llega una categoría nunca vista
(ej. un método de pago nuevo), en vez de romper la deja en todo-ceros y sigue.
Clave para el scoring en vivo.

---

## El `ColumnTransformer` — el director de orquesta

```python
ColumnTransformer([
    ("num", num, F.NUMERICAS),   # pipeline num SOLO a columnas numéricas
    ("cat", cat, F.CATEGORICAS), # pipeline cat SOLO a categóricas
])
```
Toma el DataFrame, manda cada grupo de columnas a su pipeline, y **pega los
resultados** en una sola matriz de números lista para el algoritmo.

---

## ¿Lo necesitan igual los 2 algoritmos?

| Paso | Regresión Logística | Random Forest |
|---|---|---|
| Imputar faltantes | **Sí** (no acepta NaN) | **Sí** (no acepta NaN) |
| Escalar (StandardScaler) | **Crítico** — sus coeficientes dependen de la escala | **No lo necesita** — los árboles parten por umbrales (`tenure > 5`), da igual la escala |
| One-Hot | **Sí** — necesita números | **Sí** — sklearn necesita números |

> El escalado es indispensable para la Regresión Logística e **inofensivo** para el
> Random Forest. Como ambos comparten el mismo `ColumnTransformer`, se lo aplicamos
> a los dos por simplicidad; el Random Forest simplemente no se beneficia de él.

---

## El detalle más importante: sin fuga de datos (data leakage)

Todo esto vive **dentro del `Pipeline`** junto al modelo. Por eso la mediana, la
media/desviación del escalado y las categorías del One-Hot se calculan **solo con el
set de entrenamiento** (en `.fit()`), y luego se aplican tal cual al test y a
producción.

Si calculáramos la mediana usando *todos* los datos (train + test), habría "fuga":
el modelo tendría información del test durante el entrenamiento y las métricas
saldrían infladas (falsamente buenas).
