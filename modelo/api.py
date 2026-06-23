"""
Servicio de prediccion en vivo (FastAPI).

Laravel llama a este endpoint cuando necesita evaluar a un cliente al instante
(p.ej. un cliente nuevo, o uno cuyo comportamiento acaba de cambiar tras una
compra o un reclamo). Devuelve la probabilidad de churn, el nivel y la accion.

Levantar:  uvicorn api:app --port 9000 --reload
           (Laravel lo busca en http://127.0.0.1:9000 vía CHURN_API_URL en .env)
Probar:    curl -X POST localhost:9000/predict -H "Content-Type: application/json" \
                -d '{"tenure":1,"complain":1,"prefered_order_cat":"Mobile Phone"}'
"""
from pathlib import Path
from typing import Optional

import pandas as pd
import joblib
from fastapi import FastAPI
from pydantic import BaseModel

import features as F

MODELO = Path(__file__).resolve().parent / "artefactos" / "modelo.pkl"
modelo = joblib.load(MODELO)

app = FastAPI(title="NE-BI · Prediccion de Churn", version="1.0")


class ClienteIn(BaseModel):
    """Comportamiento del cliente. Todo opcional: el imputador maneja faltantes."""
    tenure: Optional[float] = None
    preferred_login_device: Optional[str] = None
    city_tier: Optional[int] = None
    warehouse_to_home: Optional[float] = None
    preferred_payment_mode: Optional[str] = None
    gender: Optional[str] = None
    hour_spend_on_app: Optional[float] = None
    number_of_device_registered: Optional[int] = None
    prefered_order_cat: Optional[str] = None
    satisfaction_score: Optional[int] = None
    marital_status: Optional[str] = None
    number_of_address: Optional[int] = None
    complain: Optional[int] = None
    order_amount_hike_from_last_year: Optional[float] = None
    coupon_used: Optional[float] = None
    order_count: Optional[float] = None
    day_since_last_order: Optional[float] = None
    cashback_amount: Optional[float] = None


@app.get("/")
def salud():
    return {"servicio": "prediccion de churn", "estado": "ok"}


@app.post("/predict")
def predict(cliente: ClienteIn):
    df = pd.DataFrame([cliente.model_dump()])
    df = F.preparar(df)
    X = df[F.NUMERICAS + F.CATEGORICAS]

    prob = float(modelo.predict_proba(X)[:, 1][0])
    nivel = F.nivel_churn(prob)

    return {
        "churn_probability": round(prob, 4),
        "churn_level": nivel,
        "accion_sugerida": F.accion(nivel),
    }
