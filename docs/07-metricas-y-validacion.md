# 07 · Métricas y validación

> Cómo se mide de verdad si un modelo es bueno. Spoiler: NO con accuracy.

---

## 1. La matriz de confusión: el origen de todo

Cuando el modelo predice sobre el test, cada cliente cae en una de 4 casillas:

|  | Predijo: se queda | Predijo: abandona |
|---|---|---|
| **Real: se queda** | ✅ TN (Verdadero Negativo) | ❌ FP (Falso Positivo) |
| **Real: abandona** | ❌ FN (Falso Negativo) | ✅ TP (Verdadero Positivo) |

- **TP**: churner que detectaste → ¡bien, puedes retenerlo!
- **TN**: cliente fiel que dejaste tranquilo → bien.
- **FP**: dijiste que se iba pero se queda → le mandas un cupón "de más" (costo bajo).
- **FN**: se fue y NO lo detectaste → **lo perdiste sin actuar** (el error caro).

Tu Random Forest en el test (1,126 clientes):
```
[[899, 37],     ← de los que se quedan: 899 bien, 37 falsa alarma
 [  7, 183]]    ← de los que se van: solo 7 escapados, 183 detectados
```

---

## 2. Las métricas (todas salen de esas 4 casillas)

### Accuracy (exactitud) — la que ENGAÑA
```
Accuracy = (TP + TN) / total = aciertos / todos
```
Problema: con desbalance, un modelo tramposo que siempre dice "se queda" saca 83%
sin detectar a nadie (ver `docs/06-desbalance.md`). **No la uses como métrica
principal.**

### Recall (sensibilidad) — la MÁS importante para ti
```
Recall = TP / (TP + FN) = "de todos los que se fueron, ¿a cuántos detecté?"
```
Tu modelo: 183 / (183 + 7) = **0.963** → detecta el **96.3%** de los churners.
Es la clave del negocio: cada churner no detectado (FN) es un cliente perdido.

### Precision (precisión) — el costo de las falsas alarmas
```
Precision = TP / (TP + FP) = "de los que marqué como churn, ¿cuántos lo eran?"
```
Tu modelo: 183 / (183 + 37) = **0.832** → cuando avisa, acierta el 83%. Mide cuántos
cupones "gastas de más".

> Recall vs Precision es un **balance**: si avisas de todos (recall alto) sueles tener
> más falsas alarmas (precision baja), y viceversa. Tú priorizas recall porque perder
> un cliente cuesta más que un cupón extra.

### F1 — el equilibrio entre las dos
```
F1 = media armónica de precision y recall
```
Resume ambas en un número (alto solo si las dos son altas). Tu modelo: **0.893**.
Por eso `entrenar.py` elige el mejor modelo por F1.

### ROC-AUC — qué tan bien separa las clases
Mide la capacidad del modelo de **ordenar** clientes por riesgo, en cualquier umbral.
- 0.5 = azar (una moneda).
- 1.0 = perfecto.
Tu modelo: **0.995** (altísimo; nota que este dataset es muy separable, ver
limitaciones en `modelo/README.md`).

---

## 3. ¿Por qué un umbral, y cómo se usa?

El modelo da una **probabilidad** (0.0–1.0). Para decidir "¿actúo o no?" necesitas un
corte (umbral). Tu proyecto no usa un solo corte, usa **4 niveles** (`nivel_churn`):

| Probabilidad | Nivel | Acción |
|---|---|---|
| ≥ 0.70 | alto | cupón inmediato |
| 0.40–0.69 | medio | correo personalizado |
| 0.20–0.39 | moderado | remarketing |
| < 0.20 | bajo | nada |

Mover el umbral cambia el balance recall/precision: bajarlo detecta más churners pero
con más falsas alarmas. Es una decisión de **negocio** (¿cuánto cuesta un cupón vs
perder un cliente?), no solo técnica.

---

## 4. Validación cruzada (cross-validation)

Un solo split 80/20 puede tener suerte o mala suerte según qué filas cayeron en cada
parte. La **validación cruzada** lo hace más confiable:

```
StratifiedKFold(n_splits=5)
```

Divide el entrenamiento en **5 partes (folds)**. Entrena 5 veces: cada vez usa 4
partes para entrenar y 1 distinta para validar. Promedia los 5 resultados.

- **Estratificada** = cada fold mantiene la proporción de churn (16.8%).
- Resultado en tu proyecto: F1 CV = 0.817 ± 0.020 → el "± 0.020" (desviación baja)
  dice que el modelo es **estable**, no dependió de un split afortunado.

```python
cross_val_score(modelo, X_train, y_train, cv=5, scoring="f1")
```

---

## 5. Resumen: qué mirar y en qué orden

1. **Matriz de confusión** → entiende los errores (¿cuántos FN?).
2. **Recall** → ¿detecto a los que se van? (tu prioridad).
3. **Precision** → ¿cuántas falsas alarmas?
4. **F1** → equilibrio de ambas (para elegir modelo).
5. **ROC-AUC** → capacidad de separar, independiente del umbral.
6. **Validación cruzada** → ¿es estable o tuve suerte?
7. **Accuracy** → casi ignórala con datos desbalanceados.

---

## Fin del mini-curso
Con esto tienes el panorama completo: fundamentos (03), herramientas (04),
algoritmos (05), desbalance (06) y evaluación (07). Para ver cómo se aplica todo
junto, lee `modelo/entrenar.py` con estos capítulos al lado.
