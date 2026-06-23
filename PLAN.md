# Plan del Proyecto — E-commerce de Electro/Tecnología con Predicción de Churn

> Sistema de Business Intelligence para predicción temprana de abandono de clientes (customer churn) integrado a una tienda virtual de electrodomésticos y tecnología.

---

## 1. Resumen ejecutivo

Construimos una tienda virtual de **electro/tecnología** cuyo catálogo y base de clientes se derivan del dataset *Ecommerce Customer Churn Analysis and Prediction* (Ankit Verma, Kaggle): **5,630 clientes × 20 variables**, con `Churn` como objetivo binaria (1 = abandona, 0 = permanece).

Sobre esos clientes montamos un **panel de administración con BI** que identifica clientes en riesgo de abandono y sugiere acciones de retención según umbrales de probabilidad. El modelo se entrena con el dataset adaptado al rubro y, en producción, se reentrenaría con datos propios cuando el negocio acumule historial.

---

## 2. Lectura de los datos (lo que fundamenta TODO el diseño)

**Tasa base de churn: 16.8%** (83.2% se quedan). Desbalance real → no usar *accuracy* como métrica principal.

### 2.1 Drivers de abandono (churn por segmento, dato real)

| Variable | Segmento de mayor riesgo | Churn | Comparación |
|---|---|---|---|
| **Tenure (antigüedad)** | 0–1 mes | **51.8%** | vs 5.0% en 13+ meses |
| **Complain (reclamo)** | Con reclamo | **31.7%** | vs 10.9% sin reclamo |
| **MaritalStatus** | Soltero | **26.7%** | vs 11.5% casado |
| **PreferedOrderCat** | Mobile Phone | **27.4%** | vs 4.9% Grocery |
| **PreferredPaymentMode** | Contra entrega (COD) | **24.9%** | vs 14.2% crédito |
| **SatisfactionScore** | 5 (máximo) | **23.8%** | ⚠️ contraintuitivo (ver §10) |
| **CityTier** | Tier 3 | **21.4%** | vs 14.5% Tier 1 |
| **PreferredLoginDevice** | Computer | **19.8%** | vs 15.6% móvil |

### 2.2 Correlación de variables numéricas con Churn

| Variable | corr | Lectura |
|---|---|---|
| Tenure | **−0.35** | El driver numérico más fuerte; a más antigüedad, menos churn |
| DaySinceLastOrder | −0.16 | ⚠️ contraintuitivo (ver §10) |
| CashbackAmount | −0.15 | Más cashback → menos churn |
| NumberOfDeviceRegistered | +0.11 | Más dispositivos → más churn |
| WarehouseToHome | +0.08 | Más lejos → leve más churn |
| Resto (HourSpendOnApp, OrderCount, CouponUsed, OrderAmountHike) | ≈0 | Señal débil por sí solas |

**Conclusión de negocio:** el abandono es principalmente un problema de **onboarding** (clientes nuevos) y de **experiencia/reclamos**, no de clientes antiguos. La estrategia de retención debe priorizar los primeros 1–3 meses de vida del cliente.

---

## 3. La tienda que se puede armar

### 3.1 Rubro justificado por los datos
~73% de los pedidos son tecnología (Laptop & Accessory 36% + celulares 37% sumando "Mobile Phone" y "Mobile"). → **Tienda de electro y tecnología.**

### 3.2 Catálogo (mapeo `PreferedOrderCat` → categorías de la tienda)

| Categoría dataset | Categoría tienda | % pedidos | Churn |
|---|---|---|---|
| Laptop & Accessory | Laptops y accesorios | 36.4% | 10.2% |
| Mobile Phone + Mobile *(fusionar)* | Celulares y smartphones | ~37% | 27.4% |
| Fashion | Wearables (smartwatch, audífonos) | 14.7% | 15.5% |
| Grocery | Pequeños electrodomésticos de cocina | 7.3% | 4.9% |
| Others | Línea blanca y otros | 4.7% | 7.6% |

### 3.3 Métodos de pago (mapeo `PreferredPaymentMode` → contexto peruano)

| Valor dataset | Método tienda |
|---|---|
| Debit Card | Tarjeta de débito |
| Credit Card + CC *(fusionar)* | Tarjeta de crédito |
| E wallet + UPI *(fusionar)* | Billetera digital (Yape / Plin) |
| COD + Cash on Delivery *(fusionar)* | Contra entrega |

### 3.4 Segmentos de cliente (para personalización)
Combinando `CityTier`, `MaritalStatus`, `Gender`, `Tenure` y categoría favorita se pueden definir buyer personas reales: p. ej. "soltero, Tier 3, comprador de celulares, <3 meses" = segmento de **máximo riesgo**.

---

## 4. Inteligencia de negocios: qué campos sirven y para qué

| Campo | Uso en BI | Uso en el modelo |
|---|---|---|
| `Tenure` | Cohortes de antigüedad, curva de retención | Predictor #1 |
| `Complain` | KPI de calidad de servicio | Predictor fuerte y accionable |
| `SatisfactionScore` | NPS/CSAT, alertas | Predictor (revisar §10) |
| `DaySinceLastOrder` | Recencia (RFM) | Predictor |
| `OrderCount` | Frecuencia (RFM) | Predictor + feature engineering |
| `CashbackAmount` | Valor monetario (RFM), ROI de incentivos | Predictor |
| `CouponUsed` | Sensibilidad a promos | Predictor + ratio |
| `PreferedOrderCat` | Mix de catálogo, recomendación | Predictor categórico |
| `PreferredPaymentMode` | Conversión por medio de pago | Predictor categórico |
| `CityTier` | Expansión geográfica, logística | Predictor |
| `HourSpendOnApp`, `PreferredLoginDevice`, `NumberOfDeviceRegistered` | Engagement digital | Predictores de soporte |
| `MaritalStatus`, `Gender` | Segmentación demográfica | Predictores demográficos |
| `WarehouseToHome` | Eficiencia logística | Predictor menor |
| `NumberOfAddress` | Indicador de actividad/multi-ubicación | Predictor menor |
| `CustomerID` | Identificador | **Excluir del modelo** (no es feature) |

**Dashboards de BI sugeridos:** (1) Curva de retención por cohorte de `Tenure`; (2) Churn por categoría y por medio de pago; (3) Impacto de reclamos; (4) Análisis RFM (Recencia=`DaySinceLastOrder`, Frecuencia=`OrderCount`, Monetario=`CashbackAmount`); (5) Mapa de riesgo por `CityTier`.

---

## 5. Pipeline del sistema de predicción

### Etapa 1 — Definir objetivo
Clasificación binaria: `Churn` (1=abandona, 0=permanece). Métrica de negocio: **maximizar recall sobre churners** sin disparar el costo de falsos positivos.

### Etapa 2 — Recolección y descripción
Carga del `.xlsx` (hoja `E Comm`), EDA, estadísticos, distribuciones, correlaciones (ya hecho en §2).

### Etapa 3 — Preparación de datos
1. **Limpieza de duplicados de categoría** (crítico, antes de todo):
   - `PreferedOrderCat`: "Mobile" → "Mobile Phone"
   - `PreferredPaymentMode`: "CC" → "Credit Card", "COD" → "Cash on Delivery", "UPI" → "E wallet"
   - `PreferredLoginDevice`: "Phone" → "Mobile Phone"
2. **Imputación de faltantes** (7 columnas, ~4–5% c/u): mediana para numéricas
   (`Tenure`, `DaySinceLastOrder`, `OrderAmountHikeFromlastYear`, `OrderCount`, `CouponUsed`, `HourSpendOnApp`, `WarehouseToHome`).
3. **Codificación categórica**: One-Hot para nominales (categoría, pago, estado civil, dispositivo, género); `CityTier` y `SatisfactionScore` tratables como ordinales.
4. **Escalado**: StandardScaler para Regresión Logística (Random Forest no lo necesita).
5. **Split**: 80/20 estratificado por `Churn` (`stratify=y`, `random_state` fijo).
6. **Desbalance** (aplicar SOLO al train): `class_weight='balanced'` y/o **SMOTE**. Comparar ambos.

### Etapa 4 — Selección de algoritmos
- **Regresión Logística** — modelo base interpretable (coeficientes = dirección/peso de cada driver).
- **Random Forest** — modelo principal (no lineal, importancia de variables, robusto).
- *(Opcional para mejorar)* Gradient Boosting / XGBoost como comparación.

### Etapa 5 — Entrenamiento (Python: pandas + scikit-learn)
`Pipeline` de sklearn (imputación → encoding → escalado → modelo) para evitar fuga de datos. Validación cruzada estratificada (k=5). Ajuste de hiperparámetros con `GridSearchCV` optimizando F1 o recall.

### Etapa 6 — Aplicación con umbrales de acción
`predict_proba` → probabilidad de churn por cliente → reglas de retención (§7).

---

## 6. Feature engineering recomendado (mejora la señal)

- **Flag cliente nuevo**: `Tenure <= 1` (captura el 51.8% de churn).
- **Ratio cupón/pedido**: `CouponUsed / OrderCount`.
- **Segmento RFM** combinando recencia, frecuencia y monetario.
- **Flag inactividad reciente** sobre `DaySinceLastOrder`.
- **Interacción** reclamo × satisfacción baja.

---

## 7. Umbrales de acción y retención

| Probabilidad de churn | Nivel | Acción de retención |
|---|---|---|
| Alta (≥ 0.70) | 🔴 Crítico | Cupón de descuento inmediato |
| Media (0.40 – 0.69) | 🟠 Riesgo | Correo personalizado con productos de su categoría favorita |
| Moderada (0.20 – 0.39) | 🟡 Vigilar | Campaña de remarketing |
| Baja (< 0.20) | 🟢 Sano | Sin acción / fidelización normal |

> Los cortes son configurables; deben **calibrarse contra la curva precision-recall** y el costo del incentivo vs. el valor del cliente. Priorizar acciones de onboarding para clientes con `Tenure ≤ 1`.

---

## 8. Arquitectura e integración

```
Dataset (.xlsx)
   │  limpieza + entrenamiento (notebook / script Python)
   ▼
Modelo serializado (joblib .pkl) + scaler/encoder
   │
   ▼
Backend tienda  ──►  endpoint /predict (probabilidad por cliente)
   │
   ▼
Panel Admin  ──►  Lista "Clientes en riesgo": [Cliente | Prob. | Nivel | Acción sugerida | botón Aplicar]
```

- **Modelado**: notebook Jupyter para EDA/entrenamiento; export del pipeline con `joblib`.
- **Backend**: framework web (Flask/FastAPI o el que use el grupo) que carga el `.pkl` y expone predicciones.
- **Frontend admin**: tabla ordenable por probabilidad, filtros por categoría/ciudad/segmento, y los dashboards de BI de §4.

---

## 9. Métricas de evaluación

- **Modelo**: Recall (churners), Precision, **F1**, **ROC-AUC**, matriz de confusión. *(No accuracy como principal.)*
- **Negocio**: tasa de retención post-acción, ROI de cupones, % de clientes en riesgo recuperados, costo por cliente retenido.

---

## 10. Riesgos y limitaciones (documentar honestamente)

1. **Artefactos contraintuitivos del dataset**:
   - `SatisfactionScore` alto correlaciona con MÁS churn (5 → 23.8%).
   - `DaySinceLastOrder` bajo correlaciona con MÁS churn.
   Probables artefactos de cómo se construyó el dataset. **No presentarlos como insights de negocio** sin validar; documentarlos como hallazgo a investigar.
2. **Datos no propios**: el modelo se entrena con datos adaptados, no con clientes reales de la tienda → en producción se reentrena con historial propio.
3. **Adaptación, no realidad**: el mapeo de categorías/pagos al rubro electro y a Perú es una decisión de diseño del proyecto, no un dato del origen.
4. **Posible fuga temporal**: `Tenure` domina tanto que conviene verificar que el modelo no dependa de una sola variable; reportar importancia de features.

---

## 11. Roadmap por fases / entregables

| Fase | Entregable |
|---|---|
| 1. Datos | Notebook de EDA + dataset limpio (categorías fusionadas, imputado) |
| 2. Modelado | Pipeline entrenado (LogReg + RF), comparativa de métricas, modelo serializado |
| 3. Integración | Endpoint de predicción + carga del modelo en el backend |
| 4. Tienda | Catálogo de electro/tech + flujo de compra |
| 5. Panel BI | Lista de clientes en riesgo + dashboards + acciones por umbral |
| 6. Documentación | Justificación metodológica + limitaciones + plan de reentrenamiento |
