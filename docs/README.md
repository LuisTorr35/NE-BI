# Documentación explicativa — NE-BI

Explicaciones del código y un **mini-curso de Machine Learning** aplicado a este
proyecto, pensado para aprender desde cero.

## 📘 Mini-curso de ML (léelo en orden)

1. [03 · Fundamentos de Machine Learning](03-fundamentos-ml.md) — qué es ML,
   aprendizaje supervisado, entrenar, overfitting, train/test, fuga de datos.
2. [04 · Python y librerías](04-python-y-librerias.md) — pandas, scikit-learn
   (`fit`/`transform`/`predict`, Pipeline), joblib, SQLAlchemy, FastAPI.
3. [05 · Los algoritmos](05-algoritmos.md) — Regresión Logística y Random Forest
   explicados con intuición, hiperparámetros, `predict_proba`.
4. [06 · El desbalance de clases](06-desbalance.md) — por qué 16.8% es un problema,
   `class_weight` vs SMOTE.
5. [07 · Métricas y validación](07-metricas-y-validacion.md) — matriz de confusión,
   recall/precision/F1/AUC, umbrales, validación cruzada.

## 🔍 Explicación del código

- [01 · `features.py` explicado](01-features-py.md) — el módulo compartido de
  preparación de datos, función por función.
- [02 · Tratamiento de datos numéricos y categóricos](02-tratamiento-datos.md) — cómo
  se imputa, escala y codifica (`ColumnTransformer`).
- [08 · Chatbot BI (Groq + Llama)](08-chatbot-bi.md) — el asistente del admin con
  tool-calling: cómo responde con datos reales sin inventar.
- [09 · Cómo el modelo se comunica con el sistema](09-modelo-y-sistema.md) — la tubería
  Python ↔ MySQL ↔ Laravel y cómo terminas viendo a los clientes en riesgo.

## 📂 Otros documentos del proyecto
- `PLAN.md` (raíz) — plan metodológico completo.
- `modelo/README.md` — implementación del modelo (pipeline, métricas, integración).

---

### Ruta sugerida de lectura
Si vienes desde cero: **03 → 04 → 05 → 06 → 07** (el mini-curso), y luego **01 → 02**
para ver el código concreto. Al final, abre `modelo/entrenar.py` con estos capítulos
al lado: lo vas a entender línea por línea.
