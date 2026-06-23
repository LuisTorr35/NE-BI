# 04 · Python y librerías en este proyecto

> Las herramientas de Python que usa el modelo, explicadas para que entiendas qué
> hace cada una y por qué. No necesitas dominar Python entero: necesitas estas piezas.

---

## 1. Las librerías y para qué sirve cada una

| Librería | Rol | Dónde se usa |
|---|---|---|
| **pandas** | Manejar tablas de datos (DataFrame) | leer el dataset, limpiar, columnas |
| **numpy** | Cálculo numérico con arreglos | por debajo de pandas y sklearn |
| **scikit-learn** (sklearn) | Machine Learning: modelos, preprocesado, métricas | el núcleo del modelo |
| **imbalanced-learn** (imblearn) | Manejo de desbalance (SMOTE) | comparar estrategias |
| **joblib** | Guardar/cargar el modelo entrenado (`.pkl`) | persistir el modelo |
| **SQLAlchemy + pymysql** | Conectarse a MySQL desde Python | leer/escribir en la BD |
| **FastAPI + uvicorn** | Crear el servicio web de predicción en vivo | `api.py` |

---

## 2. pandas: el DataFrame

Un **DataFrame** es una tabla (como una hoja de Excel) en memoria. Es la estructura
central de todo el proyecto.

```python
import pandas as pd
df = pd.read_excel("E Commerce Dataset.xlsx", sheet_name="E Comm")
```

Operaciones que verás en el código:

```python
df.rename(columns=RENOMBRAR)   # renombrar columnas
df["tenure"]                    # seleccionar una columna (una "Serie")
df[["tenure", "complain"]]      # seleccionar varias columnas
df["tenure"].fillna(0)          # rellenar valores faltantes
df["tenure"].median()           # calcular la mediana
df.copy()                       # copiar (no tocar el original)
(df["tenure"] <= 1).astype(int) # crear columna 0/1 a partir de una condición
```

**`NaN`** = "Not a Number" = celda vacía / dato faltante. pandas lo usa para los
huecos del dataset (las 7 columnas con faltantes).

---

## 3. scikit-learn: las 3 ideas que tienes que entender

### a) Todo objeto sigue el mismo patrón: `fit` y `transform`/`predict`
- `.fit(datos)` → **aprende** algo de los datos (la mediana, las categorías, los patrones).
- `.transform(datos)` → **aplica** lo aprendido (rellena, escala, codifica).
- `.predict(datos)` → en los modelos, **devuelve la predicción**.

Ejemplo:
```python
scaler = StandardScaler()
scaler.fit(X_train)        # aprende media y desviación del TRAIN
scaler.transform(X_test)   # aplica esa misma escala al TEST
```

### b) El `Pipeline`: encadenar pasos
Un `Pipeline` une varios pasos en uno solo, en orden:

```python
modelo = Pipeline([
    ("prep", preprocesador),                # 1. imputar + escalar + codificar
    ("clf", RandomForestClassifier(...)),   # 2. el clasificador
])
modelo.fit(X_train, y_train)   # ejecuta TODOS los pasos en cadena
```

**Por qué es clave:** garantiza que el preprocesado se aprende solo del train y se
aplica idéntico en test y producción → **evita la fuga de datos** automáticamente.

### c) El `ColumnTransformer`: distinto trato por tipo de columna
Aplica un pipeline a las numéricas y otro a las categóricas (ver
`docs/02-tratamiento-datos.md`).

---

## 4. Guardar y cargar el modelo: joblib

Entrenar toma tiempo; no quieres reentrenar cada vez. Lo guardas una vez:

```python
import joblib
joblib.dump(modelo, "artefactos/modelo.pkl")   # guardar (en entrenar.py)
modelo = joblib.load("artefactos/modelo.pkl")   # cargar (en predecir.py / api.py)
```

El `.pkl` contiene **todo el Pipeline** (preprocesado + modelo entrenado), listo para
usar. Esto es lo que conecta el entrenamiento con la predicción.

---

## 5. Conectar Python con MySQL: SQLAlchemy

`predecir.py` lee los clientes de la base de datos y escribe las probabilidades:

```python
from sqlalchemy import create_engine
engine = create_engine("mysql+pymysql://root:@127.0.0.1:3306/ne_bi")
df = pd.read_sql("SELECT * FROM customers", engine)   # MySQL -> DataFrame
```

Así Python (el modelo) y Laravel (la tienda) comparten la **misma base de datos**.

---

## 6. La API en vivo: FastAPI

FastAPI convierte una función de Python en un servicio web que Laravel puede llamar:

```python
@app.post("/predict")
def predict(cliente: ClienteIn):
    ...
    return {"churn_probability": prob, "churn_level": nivel}
```

`uvicorn` es el servidor que mantiene esa función "escuchando" en un puerto (9000).

---

## Cómo se conecta todo

```
pandas (leer/limpiar)  →  scikit-learn (entrenar)  →  joblib (.pkl)
                                                          │
                              ┌───────────────────────────┤
                              ▼                           ▼
                    predecir.py (lote→MySQL)        api.py (en vivo)
                    SQLAlchemy                       FastAPI/uvicorn
```

---

## Siguiente capítulo
`docs/05-algoritmos.md` — qué son y cómo "piensan" la Regresión Logística y el
Random Forest.
