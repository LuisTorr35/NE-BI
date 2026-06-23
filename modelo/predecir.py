"""
Scoring por lotes: calcula la probabilidad de churn de TODOS los clientes de la
tabla `customers` (MySQL) y la escribe de vuelta en las columnas
`churn_probability`, `churn_level` y `churn_scored_at`.

Es el puente entre el modelo (Python) y la tienda (Laravel): Laravel solo lee
esas columnas para la pestana de BI. Reentrenar/reescorar = volver a correr esto.

Uso:  python3 predecir.py
"""
from pathlib import Path
from datetime import datetime
from urllib.parse import quote_plus

import pandas as pd
import joblib
from sqlalchemy import create_engine, text

import features as F

RAIZ = Path(__file__).resolve().parent
MODELO = RAIZ / "artefactos" / "modelo.pkl"


def _leer_env(clave: str, defecto: str = "") -> str:
    """Lee una variable del .env de Laravel (tienda/.env), sin dependencias extra.

    Asi el modelo usa SIEMPRE la misma BD que la tienda: no hay que tocar este
    archivo si cambian las credenciales, solo el .env.
    """
    env_path = RAIZ.parent / ".env"
    if env_path.exists():
        for linea in env_path.read_text().splitlines():
            linea = linea.strip()
            if not linea or linea.startswith("#") or "=" not in linea:
                continue
            k, v = linea.split("=", 1)
            if k.strip() == clave:
                return v.strip().strip('"').strip("'")
    return defecto


# Conexion a MySQL/MariaDB tomada del .env de Laravel (con defaults de XAMPP)
DB_HOST = _leer_env("DB_HOST", "127.0.0.1")
DB_PORT = _leer_env("DB_PORT", "3306")
DB_NAME = _leer_env("DB_DATABASE", "ne_bi")
DB_USER = _leer_env("DB_USERNAME", "root")
DB_PASS = _leer_env("DB_PASSWORD", "")
DB_URL = (
    f"mysql+pymysql://{DB_USER}:{quote_plus(DB_PASS)}@{DB_HOST}:{DB_PORT}/{DB_NAME}"
)


def main():
    print(">> Cargando modelo...")
    modelo = joblib.load(MODELO)

    engine = create_engine(DB_URL)

    print(">> Leyendo clientes de MySQL...")
    df = pd.read_sql("SELECT * FROM customers", engine)
    print(f"   {len(df)} clientes")

    # Mismas transformaciones que en entrenamiento
    df_prep = F.preparar(df)
    X = df_prep[F.NUMERICAS + F.CATEGORICAS]

    print(">> Calculando probabilidades de churn...")
    proba = modelo.predict_proba(X)[:, 1]

    df_prep["churn_probability"] = proba
    df_prep["churn_level"] = [F.nivel_churn(p) for p in proba]

    ahora = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    # Actualizacion masiva (executemany en una transaccion)
    registros = [
        {"id": int(r.id), "p": float(r.churn_probability), "n": r.churn_level, "t": ahora}
        for r in df_prep.itertuples()
    ]

    print(">> Escribiendo resultados en MySQL...")
    with engine.begin() as conn:
        conn.execute(
            text("""UPDATE customers
                    SET churn_probability = :p, churn_level = :n, churn_scored_at = :t
                    WHERE id = :id"""),
            registros,
        )

    # Resumen
    resumen = df_prep["churn_level"].value_counts().to_dict()
    print("\n>> Clientes por nivel de riesgo:")
    for nivel in ["alto", "medio", "moderado", "bajo"]:
        print(f"   {nivel:<9} {resumen.get(nivel, 0):>5}   -> {F.accion(nivel)}")
    print(f"\n>> Listo. Scoreados {len(df_prep)} clientes ({ahora}).")


if __name__ == "__main__":
    main()
