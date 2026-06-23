# 03 · Fundamentos de Machine Learning

> Capítulo base del mini-curso. Aquí van los conceptos que necesitas para entender
> todo lo demás, explicados con ejemplos de **tu propio proyecto** (predicción de
> churn).

---

## 1. ¿Qué es Machine Learning (aprendizaje automático)?

En programación normal **tú escribes las reglas**:

```
SI tenure < 1 Y complain = 1  ENTONCES  "se va a ir"
```

El problema: ¿qué umbral? ¿qué combinación? Con 20 variables, las reglas a mano son
imposibles de acertar.

En Machine Learning **le das ejemplos y la máquina descubre las reglas sola**:

```
Le muestras 5,630 clientes con su resultado real (se fue / se quedó)
   → el algoritmo encuentra los patrones que separan a unos de otros
   → produce un "modelo" que predice clientes nuevos
```

No le programas la lógica del churn; **la aprende de los datos**.

---

## 2. Aprendizaje supervisado

Tu proyecto es **aprendizaje supervisado**: cada ejemplo de entrenamiento viene con
la "respuesta correcta" (la columna `Churn`: 1 = abandonó, 0 = se quedó). El modelo
aprende mirando muchos casos donde ya sabe la respuesta.

- **Supervisado** = tienes la respuesta (etiqueta) → es tu caso.
- No supervisado = no hay respuesta, buscas grupos (ej. segmentar clientes).

Como la respuesta es **sí/no** (2 clases), es **clasificación binaria**. Si
predijeras un número (ej. cuánto gastará), sería *regresión*.

---

## 3. El vocabulario clave (con tus datos)

| Término | Qué es | En tu proyecto |
|---|---|---|
| **Feature** (variable / predictor) | Un dato de entrada | `tenure`, `complain`, `cashback_amount`… |
| **Target** (objetivo / etiqueta) | Lo que quieres predecir | `Churn` (1/0) |
| **Instancia / muestra** | Una fila | Un cliente |
| **Modelo** | La "fórmula" aprendida | El Random Forest entrenado (`modelo.pkl`) |
| **Entrenar** (`fit`) | Aprender los patrones de los datos | Lo hace `entrenar.py` |
| **Predecir** (`predict`) | Aplicar lo aprendido a datos nuevos | Lo hace `predecir.py` / la API |

La forma mental: tienes una tabla **X** (las features, 5630 filas × ~18 columnas) y
un vector **y** (el target, 5630 valores de 0/1). El modelo aprende la relación
`X → y`.

---

## 4. ¿Qué significa "entrenar" un modelo?

Entrenar = **ajustar los parámetros internos del modelo para que sus predicciones se
parezcan lo más posible a las respuestas reales**.

Analogía: un estudiante practica con un examen resuelto. Mira pregunta + respuesta,
ajusta su forma de pensar, y repite hasta acertar la mayoría. Luego le tomas un
examen *nuevo* para ver si de verdad aprendió (no si memorizó).

En código eso es una sola línea:
```python
modelo.fit(X_train, y_train)   # "estudia" con los datos de entrenamiento
```

---

## 5. El concepto MÁS importante: generalizar (no memorizar)

El objetivo no es acertar en los datos que ya viste, sino en **clientes nuevos**.

- **Overfitting (sobreajuste):** el modelo "memoriza" el entrenamiento, incluido el
  ruido. Saca 100% en lo conocido y falla en lo nuevo. Como un alumno que memoriza
  las respuestas pero no entiende la materia.
- **Underfitting (subajuste):** el modelo es demasiado simple y ni siquiera capta el
  patrón. Falla en todo.
- **Bien ajustado:** capta el patrón general y funciona en datos nuevos.

### ¿Cómo lo controlamos? Separando los datos
Por eso en `entrenar.py` partimos los datos en dos:
```python
X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.20, stratify=y)
```
- **80% entrenamiento** → el modelo aprende aquí.
- **20% prueba (test)** → el modelo NUNCA lo ve durante el entrenamiento; sirve para
  medir honestamente si generaliza.

> `stratify=y` mantiene la proporción de churn (16.8%) en ambas partes, para que el
> test sea representativo.

---

## 6. Fuga de datos (data leakage)

El error más común y traicionero: que información del test se "cuele" en el
entrenamiento. Si pasa, las métricas salen infladas y el modelo decepciona en
producción.

Ejemplo: si calcularas la mediana para imputar usando *todos* los datos, esa mediana
ya "vio" el test. Por eso en el proyecto la imputación, el escalado y el One-Hot
viven **dentro del Pipeline**, que los calcula solo con el train. (Detalle en
`docs/02-tratamiento-datos.md`.)

---

## 7. El ciclo completo (resumen visual)

```
   Datos crudos (5,630 clientes)
        │  limpiar + features  (features.py)
        ▼
   X (features) + y (target)
        │  train_test_split
        ▼
   ┌─────────────┐        ┌──────────┐
   │  80% TRAIN  │        │ 20% TEST │
   └─────────────┘        └──────────┘
        │ fit()                 │
        ▼                       │
   Modelo entrenado  ──predict──┘  → métricas honestas (recall, F1, AUC)
        │
        ▼
   Se aplica a clientes reales (predecir.py / API)
```

---

## Siguiente capítulo
`docs/04-python-y-librerias.md` — el Python concreto (pandas, scikit-learn) que hace
posible todo esto.
