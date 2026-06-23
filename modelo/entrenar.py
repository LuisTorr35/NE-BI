"""
Entrenamiento del modelo de churn.

Etapas (PLAN.md secciones 3-5):
  1. Carga del dataset (xlsx)
  2. Limpieza: consolidar categorias + feature engineering (features.py)
  3. Preparacion: imputacion + codificacion + escalado (ColumnTransformer)
  4. Split estratificado 80/20
  5. Entrenamiento de 3 enfoques y comparacion:
       - Regresion Logistica (base interpretable, class_weight=balanced)
       - Random Forest (principal, class_weight=balanced)
       - Random Forest + SMOTE (comparacion de manejo de desbalance)
  6. Seleccion del mejor por F1 y guardado de artefactos.

Uso:  python3 entrenar.py
"""
import json
from pathlib import Path

import numpy as np
import pandas as pd
import joblib

from sklearn.compose import ColumnTransformer
from sklearn.pipeline import Pipeline
from sklearn.impute import SimpleImputer
from sklearn.preprocessing import StandardScaler, OneHotEncoder
from sklearn.linear_model import LogisticRegression
from sklearn.ensemble import RandomForestClassifier
from sklearn.model_selection import train_test_split, cross_val_score, StratifiedKFold
from sklearn.metrics import (
    accuracy_score, precision_score, recall_score, f1_score,
    roc_auc_score, confusion_matrix, classification_report,
)
from imblearn.pipeline import Pipeline as ImbPipeline
from imblearn.over_sampling import SMOTE

import features as F

RAIZ = Path(__file__).resolve().parent
DATASET = RAIZ.parent / "E Commerce Dataset.xlsx"
ART = RAIZ / "artefactos"
ART.mkdir(exist_ok=True)
SEED = 42


def cargar_datos() -> pd.DataFrame:
    df = pd.read_excel(DATASET, sheet_name="E Comm")
    df = df.rename(columns=F.RENOMBRAR)
    df = F.preparar(df)
    return df


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


def evaluar(nombre, modelo, X_test, y_test) -> dict:
    pred = modelo.predict(X_test)
    proba = modelo.predict_proba(X_test)[:, 1]
    cm = confusion_matrix(y_test, pred)
    m = {
        "modelo": nombre,
        "accuracy": round(accuracy_score(y_test, pred), 4),
        "precision": round(precision_score(y_test, pred), 4),
        "recall": round(recall_score(y_test, pred), 4),
        "f1": round(f1_score(y_test, pred), 4),
        "roc_auc": round(roc_auc_score(y_test, proba), 4),
        "matriz_confusion": cm.tolist(),  # [[TN, FP], [FN, TP]]
    }
    print(f"\n=== {nombre} ===")
    print(f"  Accuracy={m['accuracy']}  Precision={m['precision']}  "
          f"Recall={m['recall']}  F1={m['f1']}  ROC-AUC={m['roc_auc']}")
    print(f"  Matriz confusion [[TN,FP],[FN,TP]] = {m['matriz_confusion']}")
    return m


def main():
    print(">> Cargando dataset...")
    df = cargar_datos()
    X = df[F.NUMERICAS + F.CATEGORICAS]
    y = df[F.OBJETIVO].astype(int)
    print(f"   Filas: {len(df)}  |  Churn: {y.mean()*100:.1f}%")

    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.20, stratify=y, random_state=SEED
    )
    print(f"   Train: {len(X_train)}  Test: {len(X_test)}")

    prep = construir_preprocesador()

    # --- 1. Regresion Logistica (base interpretable) ---
    log_reg = Pipeline([
        ("prep", prep),
        ("clf", LogisticRegression(max_iter=1000, class_weight="balanced", random_state=SEED)),
    ])

    # --- 2. Random Forest (principal) ---
    rf = Pipeline([
        ("prep", prep),
        ("clf", RandomForestClassifier(
            n_estimators=300, max_depth=None, min_samples_leaf=2,
            class_weight="balanced", n_jobs=-1, random_state=SEED)),
    ])

    # --- 3. Random Forest + SMOTE (comparacion de desbalance) ---
    rf_smote = ImbPipeline([
        ("prep", prep),
        ("smote", SMOTE(random_state=SEED)),
        ("clf", RandomForestClassifier(
            n_estimators=300, min_samples_leaf=2, n_jobs=-1, random_state=SEED)),
    ])

    modelos = {
        "Regresion Logistica (balanced)": log_reg,
        "Random Forest (balanced)": rf,
        "Random Forest + SMOTE": rf_smote,
    }

    cv = StratifiedKFold(n_splits=5, shuffle=True, random_state=SEED)
    resultados = []
    entrenados = {}
    for nombre, modelo in modelos.items():
        print(f"\n>> Entrenando: {nombre}")
        f1_cv = cross_val_score(modelo, X_train, y_train, cv=cv, scoring="f1", n_jobs=-1)
        modelo.fit(X_train, y_train)
        m = evaluar(nombre, modelo, X_test, y_test)
        m["f1_cv_mean"] = round(f1_cv.mean(), 4)
        m["f1_cv_std"] = round(f1_cv.std(), 4)
        print(f"  F1 CV (5-fold): {m['f1_cv_mean']} +/- {m['f1_cv_std']}")
        resultados.append(m)
        entrenados[nombre] = modelo

    # --- Seleccion del mejor por F1 en test ---
    mejor = max(resultados, key=lambda r: r["f1"])
    nombre_mejor = mejor["modelo"]
    modelo_final = entrenados[nombre_mejor]
    print(f"\n>> MEJOR MODELO: {nombre_mejor} (F1={mejor['f1']}, ROC-AUC={mejor['roc_auc']})")

    # --- Importancia de variables (del mejor si es RF) / coeficientes LogReg ---
    nombres_features = (
        modelo_final.named_steps["prep"].get_feature_names_out().tolist()
    )
    clf = modelo_final.named_steps["clf"]
    importancias = None
    if hasattr(clf, "feature_importances_"):
        pares = sorted(zip(nombres_features, clf.feature_importances_),
                       key=lambda x: x[1], reverse=True)
        importancias = [{"feature": f, "importancia": round(float(v), 4)} for f, v in pares[:15]]
        print("\n>> Top 15 variables (importancia):")
        for p in importancias:
            print(f"   {p['feature']:<45} {p['importancia']}")

    # --- Guardar artefactos ---
    joblib.dump(modelo_final, ART / "modelo.pkl")
    with open(ART / "metricas.json", "w") as fh:
        json.dump({
            "seleccionado": nombre_mejor,
            "comparacion": resultados,
            "top_importancias": importancias,
            "churn_base_pct": round(float(y.mean()) * 100, 1),
            "n_train": len(X_train), "n_test": len(X_test),
        }, fh, indent=2, ensure_ascii=False)

    print(f"\n>> Guardado: {ART/'modelo.pkl'}")
    print(f">> Guardado: {ART/'metricas.json'}")
    print("\n>> Reporte de clasificacion del mejor modelo:")
    print(classification_report(y_test, modelo_final.predict(X_test),
                                target_names=["permanece", "abandona"]))


if __name__ == "__main__":
    main()
