# 06 · El desbalance de clases

> Por qué que solo el 16.8% de clientes abandonen es un problema, y cómo lo maneja
> tu proyecto.

---

## 1. El problema

En tu dataset:
- **83.2%** se quedan (clase 0)
- **16.8%** abandonan (clase 1) ← la que te interesa

Las clases están **desbalanceadas**: hay casi 5 veces más "se quedan" que
"abandonan". Esto engaña al modelo y a las métricas.

### El "modelo tramposo"
Imagina un modelo que **siempre dice "se queda"**, sin pensar. ¿Su accuracy?

```
acierta el 83.2% (todos los que se quedan)
→ 83% de "precisión global"... ¡pero detecta CERO churners!
```

Un 83% que suena bien esconde un modelo **inútil** para tu objetivo (encontrar a los
que se van). Por eso **el accuracy engaña** con clases desbalanceadas, y por eso usas
recall/F1/AUC (ver `docs/07-metricas-y-validacion.md`).

### Por qué el modelo "tiende a la pereza"
Durante el entrenamiento, el modelo minimiza errores totales. Como hay muchísimos más
"se quedan", la forma fácil de equivocarse poco es **ignorar a la minoría**. Hay que
forzarlo a prestarle atención a los churners.

---

## 2. Solución A: `class_weight="balanced"`  (la que usa tu modelo final)

Le dice al algoritmo: *"equivócarte con un churner cuesta MÁS que equivocarte con uno
que se queda"*. Penaliza más los errores de la clase minoritaria.

```python
RandomForestClassifier(class_weight="balanced", ...)
```

Internamente da más "peso" a la clase rara, en proporción inversa a su frecuencia.
Así el modelo deja de ignorar a los churners. **Ventaja:** no inventa datos, no
requiere librerías extra, es simple y funcionó mejor en tu caso.

---

## 3. Solución B: SMOTE (sobremuestreo sintético)

SMOTE = *Synthetic Minority Over-sampling Technique*. En vez de pesar, **crea
ejemplos sintéticos** de la clase minoritaria para igualar las cantidades.

¿Cómo? Toma dos churners parecidos y "inventa" un cliente intermedio entre ellos
(interpolación). Repite hasta balancear.

```python
from imblearn.over_sampling import SMOTE
ImbPipeline([("prep", prep), ("smote", SMOTE()), ("clf", RandomForest())])
```

> ⚠️ Detalle crítico: SMOTE se aplica **solo al entrenamiento**, NUNCA al test
> (inventar datos de prueba sería trampa). Por eso va dentro del `ImbPipeline`, que
> lo aplica solo en `fit`.

---

## 4. Lo que hiciste en el proyecto: comparar

`entrenar.py` entrena 3 enfoques y los compara honestamente:

| Modelo | Recall | F1 | ROC-AUC |
|---|---|---|---|
| Regresión Logística (balanced) | 0.858 | 0.651 | 0.903 |
| **Random Forest (balanced)** ⭐ | **0.963** | **0.893** | **0.995** |
| Random Forest + SMOTE | 0.826 | 0.844 | 0.986 |

Conclusión: para tus datos, **`class_weight="balanced"` superó a SMOTE**. No siempre
gana lo más sofisticado — por eso se comparan, no se asume.

---

## 5. Resumen

- Desbalance = una clase es mucho más rara que la otra (16.8% vs 83.2%).
- Peligro: el modelo ignora a la minoría y el accuracy lo disimula.
- Soluciones: **pesar** las clases (`class_weight`) o **balancear** con datos
  sintéticos (SMOTE).
- Regla de oro: aplicar el balanceo **solo al train**, y medir en un test intacto.

---

## Siguiente capítulo
`docs/07-metricas-y-validacion.md` — cómo medir de verdad si el modelo es bueno.
