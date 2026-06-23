# `features.py` explicado función por función

> Archivo: `modelo/features.py`
> Es el **corazón compartido** del modelo: define cómo se transforman los datos
> *antes* de entrar al algoritmo. Lo usan `entrenar.py`, `predecir.py` y `api.py`.
> Todo vive aquí para que entrenamiento y predicción apliquen **exactamente las
> mismas transformaciones** (si difirieran, el modelo recibiría datos distintos a
> los que aprendió y fallaría).

---

## 1. Las constantes (configuración)

### `RENOMBRAR`
Diccionario que traduce los nombres del dataset original a **snake_case**:

```python
"Tenure" -> "tenure",  "CityTier" -> "city_tier",  "Churn" -> "actual_churn"  ...
```

**¿Por qué?** Las columnas de la tabla `customers` en MySQL usan snake_case. Al
renombrar el CSV con este diccionario, el modelo aprende con los **mismos nombres**
que luego encontrará en la base de datos → el modelo entrenado se aplica directo
sobre MySQL sin traducciones extra.

### `NUMERICAS`
Lista de columnas **numéricas** que entran al modelo (antigüedad, cashback, n° de
pedidos…). Incluye al final las **2 variables derivadas** (`es_cliente_nuevo`,
`cupones_por_pedido`) que se crean en `agregar_features`.

### `CATEGORICAS`
Lista de columnas **categóricas** (texto): dispositivo, método de pago, género,
categoría favorita, estado civil. Se convertirán a números con One-Hot Encoding.

### `OBJETIVO`
El nombre de la columna a predecir: `"actual_churn"`.

> Separar numéricas y categóricas importa porque cada grupo recibe un tratamiento
> distinto: las numéricas se escalan, las categóricas se codifican.
> (Ver `docs/02-tratamiento-datos.md`.)

---

## 2. Funciones de transformación

### `consolidar_categorias(df)`
**Qué hace:** unifica etiquetas duplicadas del dataset.
- `prefered_order_cat`: `"Mobile"` → `"Mobile Phone"`
- `preferred_payment_mode`: `"CC"` → `"Credit Card"`, `"COD"` → `"Cash on Delivery"`, `"UPI"` → `"E wallet"`
- `preferred_login_device`: `"Phone"` → `"Mobile Phone"`

**Por qué:** el dataset escribe el mismo concepto de dos formas. Sin unificar, el
modelo trataría "Mobile" y "Mobile Phone" como categorías distintas, partiendo la
señal en dos.

**Detalles técnicos:**
- `df = df.copy()` → trabaja sobre una copia para no modificar el original.
- `if col in df.columns` → solo reemplaza si la columna existe (robusto: la API a
  veces recibe clientes con campos faltantes).
- `.replace(mapa)` → hace el reemplazo según el diccionario.

### `agregar_features(df)` — Feature engineering
Crea **2 variables nuevas** que mejoran la predicción:

1. **`es_cliente_nuevo`** = `1` si `tenure <= 1`, si no `0`.
   - `.fillna(0)` → si la antigüedad falta, la trata como 0 (cliente nuevo).
   - `(... <= 1)` da `True`/`False`; `.astype(int)` lo pasa a `1`/`0`.
   - **Por qué:** el segmento de 0–1 mes concentra ~52% del churn. Esta variable
     resultó la **2.ª más importante** del modelo.

2. **`cupones_por_pedido`** = `coupon_used / order_count` (sensibilidad a promos).
   - `.fillna(0)` en el numerador → cupones faltantes = 0.
   - `.replace(0, 1)` en el denominador → **evita la división por cero** (si tiene
     0 pedidos, divide entre 1 en vez de romper).

### `preparar(df)` — la función "todo en uno"
```python
return agregar_features(consolidar_categorias(df))
```
Encadena las dos anteriores (primero consolida, luego deriva). Es la **única** que
llaman `entrenar.py`, `predecir.py` y `api.py`, garantizando los mismos pasos.

> Importante: `preparar` hace la limpieza *previa*. La imputación de faltantes, el
> escalado y el One-Hot **no** están aquí — eso lo hace el `ColumnTransformer`
> dentro del `Pipeline` de sklearn, porque esos pasos deben "aprenderse" solo del
> set de entrenamiento (ver `docs/02-tratamiento-datos.md`).

---

## 3. Helpers de umbral (lógica de negocio)

### `nivel_churn(prob)`
Convierte una probabilidad (0.0–1.0) en una **etiqueta de riesgo**:
- `>= 0.70` → `"alto"`
- `>= 0.40` → `"medio"`
- `>= 0.20` → `"moderado"`
- resto → `"bajo"`

Las condiciones van de mayor a menor: gana la primera que se cumple.

### `ACCIONES`
Diccionario que mapea cada nivel a su **acción de retención** (alto → cupón,
medio → correo, moderado → remarketing, bajo → nada).

### `accion(nivel)`
Devuelve la acción de un nivel. Usa `.get(nivel, "")` → si el nivel no existe,
devuelve cadena vacía en vez de error.

---

## Resumen en una línea
`features.py` = **traducir nombres** (`RENOMBRAR`) + **definir qué columna es qué**
(`NUMERICAS`/`CATEGORICAS`) + **limpiar y enriquecer** (`consolidar_categorias`,
`agregar_features`, `preparar`) + **traducir probabilidad a acción de negocio**
(`nivel_churn`, `accion`). Todo centralizado para que entrenamiento y predicción
sean idénticos.
