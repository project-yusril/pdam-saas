import os
import sys
import json
import logging
import asyncio
from datetime import datetime

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from fastapi import FastAPI, Query, HTTPException, Header
from fastapi.responses import JSONResponse
from pydantic import BaseModel, Field

from src.pipeline import Pipeline, load_config
from src.health import collect_readiness
from src.security import (
    AiEntitlementInactive,
    InvalidServiceToken,
    ServiceAuthNotConfigured,
    validate_ai_entitlement,
    validate_service_token,
)

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger(__name__)

app = FastAPI(
    title="PDAM ML Prediction API",
    description="Machine learning prediction service for PDAM water utility",
    version="1.0.0",
)

pipeline = Pipeline()
training_lock = asyncio.Lock()


def require_service_token(x_service_token: str | None):
    try:
        validate_service_token(x_service_token)
    except ServiceAuthNotConfigured as exc:
        raise HTTPException(status_code=503, detail=str(exc))
    except InvalidServiceToken as exc:
        raise HTTPException(status_code=401, detail=str(exc))


def require_ai_entitlement(pdam_org_id: int, x_service_token: str | None):
    try:
        validate_ai_entitlement(
            pdam_org_id,
            x_service_token,
            pipeline.data_loader._connect,
        )
    except ServiceAuthNotConfigured as exc:
        raise HTTPException(status_code=503, detail=str(exc))
    except InvalidServiceToken as exc:
        raise HTTPException(status_code=401, detail=str(exc))
    except AiEntitlementInactive as exc:
        raise HTTPException(status_code=403, detail=str(exc))
    except Exception as exc:
        logger.error(f"AI entitlement check failed: {exc}")
        raise HTTPException(status_code=503, detail="Unable to verify AI entitlement")


class TrainRequest(BaseModel):
    pdam_org_id: int = Field(..., description="PDAM organization ID", ge=1)
    models: list[str] | None = Field(
        default=None,
        description="List of models to train: consumption, anomaly, churn, meter_failure",
    )


class PredictRequest(BaseModel):
    pdam_org_id: int = Field(..., description="PDAM organization ID", ge=1)
    period: str = Field(..., description="Period in YYYY-MM format", pattern=r"^\d{4}-\d{2}$")
    models: list[str] | None = Field(
        default=None,
        description="List of models for prediction",
    )


class TrainResponse(BaseModel):
    success: bool
    results: dict
    message: str


class PredictResponse(BaseModel):
    success: bool
    predictions_count: int
    period: str
    message: str


class ModelsInfoResponse(BaseModel):
    models: dict


@app.get("/health")
async def health_check():
    return {"status": "alive", "timestamp": datetime.now().isoformat()}


@app.get("/health/live")
async def liveness_check():
    return await health_check()


@app.get("/health/ready")
async def readiness_check():
    checks = await asyncio.to_thread(collect_readiness, pipeline, logger)
    ready = all(value == "ok" for value in checks.values())
    payload = {
        "status": "ready" if ready else "not_ready",
        "checks": checks,
        "timestamp": datetime.now().isoformat(),
    }
    return JSONResponse(status_code=200 if ready else 503, content=payload)


@app.post("/train", response_model=TrainResponse)
async def train_models(
    request: TrainRequest,
    x_service_token: str | None = Header(default=None, alias="X-Service-Token"),
):
    require_ai_entitlement(request.pdam_org_id, x_service_token)
    if training_lock.locked():
        raise HTTPException(status_code=409, detail="A training job is already running")
    try:
        async with training_lock:
            results = await asyncio.to_thread(
                pipeline.run_training, request.pdam_org_id, request.models
            )
        return TrainResponse(
            success=True,
            results=results,
            message=f"Training completed for org_id={request.pdam_org_id}",
        )
    except Exception as e:
        logger.error(f"Training failed: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/predict", response_model=PredictResponse)
async def generate_predictions(
    request: PredictRequest,
    x_service_token: str | None = Header(default=None, alias="X-Service-Token"),
):
    require_ai_entitlement(request.pdam_org_id, x_service_token)
    try:
        records = await asyncio.to_thread(
            pipeline.run_prediction,
            request.pdam_org_id,
            request.period,
            request.models,
        )
        return PredictResponse(
            success=True,
            predictions_count=len(records),
            period=request.period,
            message=f"Generated {len(records)} predictions for period {request.period}",
        )
    except Exception as e:
        logger.error(f"Prediction failed: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@app.get("/models", response_model=ModelsInfoResponse)
async def list_models(
    x_service_token: str | None = Header(default=None, alias="X-Service-Token"),
):
    require_service_token(x_service_token)
    model_base_dir = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "models")
    info = {}
    model_files = {
        "consumption": "consumption_xgboost.pkl",
        "anomaly": "anomaly_isolation_forest.pkl",
        "churn": "churn_xgboost.pkl",
        "meter_failure": "meter_failure_xgboost.pkl",
    }
    for name, filename in model_files.items():
        predictor = pipeline.predictors[name]
        enabled = name in pipeline.enabled_model_names()
        path = predictor.model_path
        if not enabled:
            info[name] = {"enabled": False, "exists": os.path.exists(path)}
            continue
        if os.path.exists(path):
            try:
                mtime = datetime.fromtimestamp(os.path.getmtime(path))
                size_kb = os.path.getsize(path) / 1024
                info[name] = {
                    "enabled": True,
                    "exists": True,
                    "size_kb": round(size_kb, 2),
                    "last_modified": mtime.isoformat(),
                }

                if name == "consumption":
                    pipeline.consumption_predictor.load()
                    info[name]["metrics"] = pipeline.consumption_predictor.metrics
                elif name == "anomaly":
                    pipeline.anomaly_detector.load()
                    info[name]["metrics"] = pipeline.anomaly_detector.thresholds
                elif name == "churn":
                    pipeline.churn_predictor.load()
                    info[name]["metrics"] = pipeline.churn_predictor.metrics
                elif name == "meter_failure":
                    pipeline.meter_predictor.load()
                    info[name]["metrics"] = pipeline.meter_predictor.metrics
            except Exception as e:
                info[name] = {"exists": True, "error": str(e)}
        else:
            info[name] = {"enabled": True, "exists": False}
    return ModelsInfoResponse(models=info)


@app.get("/predictions/{pdam_org_id}")
async def get_predictions(
    pdam_org_id: int,
    period: str | None = Query(default=None, pattern=r"^\d{4}-\d{2}$"),
    model_name: str | None = Query(default=None, max_length=50),
    prediction_type: str | None = Query(default=None, max_length=30),
    limit: int = Query(default=100, ge=1, le=10000),
    x_service_token: str | None = Header(default=None, alias="X-Service-Token"),
):
    require_ai_entitlement(pdam_org_id, x_service_token)
    try:
        df = pipeline.data_loader.get_ml_predictions(
            pdam_org_id,
            model_name=model_name,
            period=period,
            prediction_type=prediction_type,
            limit=limit,
        )
        if df.empty:
            return {"predictions": [], "total": 0}
        predictions = []
        for _, row in df.iterrows():
            pred = {
                "id": int(row["id"]),
                "pdam_org_id": int(row["pdam_org_id"]),
                "model_name": row["model_name"],
                "prediction_type": row["prediction_type"],
                "entity_type": row.get("entity_type"),
                "entity_id": int(row.get("entity_id", 0)) if row.get("entity_id") is not None else None,
                "period": row.get("period"),
                "predicted_value": float(row["predicted_value"]) if row["predicted_value"] is not None else None,
                "confidence_min": float(row["confidence_min"]) if row["confidence_min"] is not None else None,
                "confidence_max": float(row["confidence_max"]) if row["confidence_max"] is not None else None,
                "actual_value": float(row["actual_value"]) if row["actual_value"] is not None else None,
                "features": row.get("features"),
                "status": row.get("status"),
                "created_at": str(row.get("created_at", "")),
                "updated_at": str(row.get("updated_at", "")),
            }
            predictions.append(pred)

        return {"predictions": predictions, "total": len(predictions)}
    except Exception as e:
        logger.error(f"Failed to get predictions: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@app.get("/predictions/{pdam_org_id}/{period}")
async def get_predictions_by_period(
    pdam_org_id: int,
    period: str,
    model_name: str | None = Query(default=None, max_length=50),
    prediction_type: str | None = Query(default=None, max_length=30),
    x_service_token: str | None = Header(default=None, alias="X-Service-Token"),
):
    return await get_predictions(
        pdam_org_id=pdam_org_id,
        period=period,
        model_name=model_name,
        prediction_type=prediction_type,
        x_service_token=x_service_token,
    )


def main():
    import uvicorn
    config = load_config()
    api_cfg = config.get("api", {})
    host = api_cfg.get("host", "0.0.0.0")
    port = api_cfg.get("port", 8100)
    uvicorn.run("src.api:app", host=host, port=port, reload=True)


if __name__ == "__main__":
    main()
