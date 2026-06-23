"""
Preparacion de features compartida entre entrenamiento y scoring.

Punto clave: el entrenamiento (desde el CSV) y la prediccion (desde MySQL) DEBEN
usar exactamente las mismas transformaciones y los mismos nombres de columna, o el
modelo recibiria un espacio de features distinto al entrenado. Por eso todo vive aqui.

Convencion: usamos nombres snake_case identicos a las columnas de la tabla
`customers` de MySQL, asi el modelo entrenado se aplica directo sobre la BD.
"""
import pandas as pd

# CSV del dataset (nombres originales) -> snake_case (como en MySQL)
RENOMBRAR = {
    "Tenure": "tenure",
    "PreferredLoginDevice": "preferred_login_device",
    "CityTier": "city_tier",
    "WarehouseToHome": "warehouse_to_home",
    "PreferredPaymentMode": "preferred_payment_mode",
    "Gender": "gender",
    "HourSpendOnApp": "hour_spend_on_app",
    "NumberOfDeviceRegistered": "number_of_device_registered",
    "PreferedOrderCat": "prefered_order_cat",
    "SatisfactionScore": "satisfaction_score",
    "MaritalStatus": "marital_status",
    "NumberOfAddress": "number_of_address",
    "Complain": "complain",
    "OrderAmountHikeFromlastYear": "order_amount_hike_from_last_year",
    "CouponUsed": "coupon_used",
    "OrderCount": "order_count",
    "DaySinceLastOrder": "day_since_last_order",
    "CashbackAmount": "cashback_amount",
    "Churn": "actual_churn",
}

# Columnas que entran al modelo
NUMERICAS = [
    "tenure", "city_tier", "warehouse_to_home", "hour_spend_on_app",
    "number_of_device_registered", "satisfaction_score", "number_of_address",
    "complain", "order_amount_hike_from_last_year", "coupon_used",
    "order_count", "day_since_last_order", "cashback_amount",
    # features derivadas (ver agregar_features)
    "es_cliente_nuevo", "cupones_por_pedido",
]
CATEGORICAS = [
    "preferred_login_device", "preferred_payment_mode",
    "gender", "prefered_order_cat", "marital_status",
]
OBJETIVO = "actual_churn"


def consolidar_categorias(df: pd.DataFrame) -> pd.DataFrame:
    """Unifica las etiquetas duplicadas del dataset (Mobile/Mobile Phone, CC/Credit Card...)."""
    df = df.copy()
    mapas = {
        "prefered_order_cat": {"Mobile": "Mobile Phone"},
        "preferred_payment_mode": {"CC": "Credit Card", "COD": "Cash on Delivery", "UPI": "E wallet"},
        "preferred_login_device": {"Phone": "Mobile Phone"},
    }
    for col, mapa in mapas.items():
        if col in df.columns:
            df[col] = df[col].replace(mapa)
    return df


def agregar_features(df: pd.DataFrame) -> pd.DataFrame:
    """Feature engineering documentado en PLAN.md (seccion 6)."""
    df = df.copy()
    # Cliente nuevo: el segmento de 0-1 mes concentra ~52% del churn
    df["es_cliente_nuevo"] = (df["tenure"].fillna(0) <= 1).astype(int)
    # Sensibilidad a promos por pedido (evita division por cero)
    df["cupones_por_pedido"] = df["coupon_used"].fillna(0) / df["order_count"].fillna(0).replace(0, 1)
    return df


def preparar(df: pd.DataFrame) -> pd.DataFrame:
    """Pipeline de preparacion previo al ColumnTransformer (consolidar + derivar)."""
    return agregar_features(consolidar_categorias(df))


# --- Umbrales de accion (PLAN.md seccion 7) ---
def nivel_churn(prob: float) -> str:
    if prob >= 0.70:
        return "alto"
    if prob >= 0.40:
        return "medio"
    if prob >= 0.20:
        return "moderado"
    return "bajo"


ACCIONES = {
    "alto":     "Cupon de descuento inmediato",
    "medio":    "Correo personalizado con productos de su categoria favorita",
    "moderado": "Campana de remarketing",
    "bajo":     "Sin accion / fidelizacion normal",
}


def accion(nivel: str) -> str:
    return ACCIONES.get(nivel, "")
