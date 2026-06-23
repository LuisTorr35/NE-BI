# 05 · Los algoritmos: Regresión Logística y Random Forest

> Cómo "piensan" los dos modelos del proyecto. Primero la intuición, luego el porqué
> de elegir Random Forest.

---

## 1. Regresión Logística — el modelo base interpretable

### La idea
Es una fórmula que **suma el aporte de cada variable** y lo convierte en una
probabilidad entre 0 y 1.

```
puntaje = w0 + w1·tenure + w2·complain + w3·cashback + ...
probabilidad = funcion_logistica(puntaje)   → valor entre 0 y 1
```

Cada `w` (peso o **coeficiente**) lo aprende el entrenamiento:
- peso **positivo** → esa variable **sube** la probabilidad de churn.
- peso **negativo** → la **baja**.
- peso grande (en valor absoluto) → más influyente.

La "función logística" (o sigmoide) es una curva en S que aplasta cualquier número
al rango 0–1, para poder leerlo como probabilidad.

### Por qué es "interpretable"
Puedes leer los coeficientes y explicar el modelo: *"a más antigüedad (`tenure`),
menos churn"* (coeficiente negativo). Es transparente, ideal como **modelo base**
para comparar.

### Su límite
Asume relaciones **lineales** (línea recta). Si el efecto de una variable es
"escalonado" o depende de otra, la regresión logística se queda corta. Por eso saca
F1 = 0.65 en tu proyecto: capta la tendencia pero no los matices.

---

## 2. Random Forest — el modelo principal

### Primero: ¿qué es un árbol de decisión?
Un árbol hace **preguntas encadenadas** de sí/no hasta decidir:

```
¿tenure <= 1?
├── Sí → ¿complain = 1?
│        ├── Sí → ALTO riesgo de churn
│        └── No → riesgo medio
└── No → ¿cashback < 100?
         ├── Sí → riesgo medio
         └── No → BAJO riesgo
```

El entrenamiento elige **qué pregunta y qué umbral** en cada nodo, buscando separar
mejor a los que se van de los que se quedan. Es justo como pensaría una persona, pero
optimizado con datos.

**Problema de un solo árbol:** si crece mucho, memoriza (overfitting).

### El "bosque": muchos árboles votando
**Random Forest = un bosque de muchos árboles** (en tu proyecto, **300**). El truco:

1. Cada árbol se entrena con una **muestra aleatoria distinta** de los datos.
2. Cada árbol, en cada pregunta, solo puede mirar un **subconjunto aleatorio de
   variables**.
3. La predicción final es el **voto/promedio de los 300 árboles**.

Analogía: en vez de preguntarle a un solo experto, le preguntas a 300 expertos que
estudiaron cosas ligeramente distintas, y promedias. Los errores individuales se
cancelan → **predicción más robusta y precisa**. Esto se llama *ensemble* (conjunto).

### Por qué gana en tu proyecto
- Capta relaciones **no lineales** y **combinaciones** de variables (tenure × complain).
- Es robusto al ruido y a outliers.
- Da **importancia de variables** (cuánto aporta cada una).

Resultado real (set de test): **Recall 0.963, F1 0.893, ROC-AUC 0.995** — muy por
encima de la regresión logística.

---

## 3. Comparación directa

| | Regresión Logística | Random Forest |
|---|---|---|
| Cómo decide | Suma ponderada → probabilidad | 300 árboles votan |
| Relaciones | Solo lineales | Lineales y no lineales |
| Interpretabilidad | Alta (coeficientes) | Media (importancia de variables) |
| Necesita escalado | Sí | No |
| F1 en tu proyecto | 0.651 | **0.893** ⭐ |
| Rol | Base / referencia | **Modelo elegido** |

> Buena práctica: siempre tener un **modelo base simple** (regresión logística) para
> saber si el modelo complejo (Random Forest) de verdad vale la pena. Aquí sí: pasa
> de 0.65 a 0.89 de F1.

---

## 4. ¿Qué son los hiperparámetros?

Son las "perillas" que tú configuras *antes* de entrenar (no se aprenden de los
datos). En tu Random Forest:

```python
RandomForestClassifier(
    n_estimators=300,        # cuántos árboles
    min_samples_leaf=2,      # mínimo de muestras por hoja (evita memorizar)
    class_weight="balanced", # compensar el desbalance (ver cap. 06)
    random_state=42,         # reproducibilidad (mismos resultados siempre)
)
```

Ajustar estas perillas para encontrar las mejores se llama *tuning* (el plan menciona
`GridSearchCV`, que prueba combinaciones automáticamente).

---

## 5. De la predicción a la probabilidad

Los modelos pueden dar dos cosas:
- `.predict(X)` → la clase (0 o 1).
- `.predict_proba(X)` → la **probabilidad** (ej. 0.85).

Tu proyecto usa **`predict_proba`** porque necesitas el matiz: no es lo mismo 0.95
que 0.45. Esa probabilidad es la que `nivel_churn()` convierte en alto/medio/
moderado/bajo y dispara la acción de retención.

```python
proba = modelo.predict_proba(X)[:, 1]   # [:, 1] = probabilidad de la clase "abandona"
```

---

## Siguiente capítulo
`docs/06-desbalance.md` — por qué que solo 16.8% abandonen es un problema, y cómo se
maneja.
