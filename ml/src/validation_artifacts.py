import json
import os

import numpy as np

from src.artifacts import manifest_path
from src.models.anomaly_detector import AnomalyDetector
from src.models.churn_predictor import ChurnPredictor
from src.models.consumption_predictor import ConsumptionPredictor
from src.models.meter_failure_predictor import MeterFailurePredictor
from src.pipeline import load_config
from src.preprocessing import Preprocessor
from src.synthetic_fixtures import make_synthetic_frames


MODEL_FILENAMES = {
    "consumption": "consumption_xgboost.pkl",
    "anomaly": "anomaly_isolation_forest.pkl",
    "churn": "churn_xgboost.pkl",
    "meter_failure": "meter_failure_xgboost.pkl",
}
SUMMARY_FILENAME = "fixture_validation_summary.json"


def _finite_smoke(predictor, X, anomaly=False):
    loaded = type(predictor)(predictor.model_path)
    loaded.load()
    prediction = loaded.predict(X[:3])
    values = prediction[1] if anomaly else prediction
    if not np.isfinite(values).all():
        raise RuntimeError(f"{type(predictor).__name__} produced a non-finite smoke prediction")
    return len(values)


def generate_validation_artifacts(config_path=None, output_dir=None, summary_path=None):
    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    config = load_config(config_path)
    model_config = config.get("models", {})
    enabled = [name for name in MODEL_FILENAMES if model_config.get(name, {}).get("enabled", True)]
    if not enabled:
        raise RuntimeError("No predictors are enabled")

    model_dir = output_dir or os.path.join(base_dir, model_config.get("base_path", "models/"))
    summary_path = summary_path or os.path.join(model_dir, SUMMARY_FILENAME)
    os.makedirs(model_dir, exist_ok=True)
    if os.path.exists(summary_path):
        os.unlink(summary_path)

    frames = make_synthetic_frames()
    preprocessor = Preprocessor()
    generated = {}

    if "consumption" in enabled:
        X, y, _ = preprocessor.prepare_consumption_data(frames["billing"])
        predictor = ConsumptionPredictor(
            os.path.join(model_dir, MODEL_FILENAMES["consumption"]),
            model_config.get("consumption", {}).get("hyperparams"),
        )
        predictor.train(X, y)
        predictor.feature_names = list(preprocessor.feature_columns)
        predictor.preprocessing = {
            "categorical_classes": preprocessor.categorical_metadata(["tariff_group", "status"]),
            "missing_value": 0,
        }
        predictor.save()
        generated["consumption"] = (predictor, X, False)

    if "anomaly" in enabled:
        predictor = AnomalyDetector(
            os.path.join(model_dir, MODEL_FILENAMES["anomaly"]),
            model_config.get("anomaly", {}).get("hyperparams"),
        )
        _, X, feature_cols, _, _ = predictor._build_features(frames["readings"])
        predictor.train(X, feature_cols)
        predictor.save()
        generated["anomaly"] = (predictor, X, True)

    if "churn" in enabled:
        churn_frame = preprocessor.build_churn_features(
            frames["billing"], frames["payments"], frames["customers"], frames["churn_labels"]
        )
        categorical_cols = ["tariff_group", "meter_condition", "tamper_status"]
        churn_frame = preprocessor.encode_categorical(churn_frame, categorical_cols)
        churn_frame = preprocessor.fill_missing(churn_frame)
        feature_cols = [
            column for column in churn_frame.select_dtypes(include=[np.number]).columns
            if column not in ["customer_id", "churned"]
        ]
        X = churn_frame[feature_cols].values
        predictor = ChurnPredictor(
            os.path.join(model_dir, MODEL_FILENAMES["churn"]),
            model_config.get("churn", {}).get("hyperparams"),
        )
        predictor.train(X, churn_frame["churned"].values)
        predictor.feature_cols = feature_cols
        predictor.preprocessing = {
            "categorical_classes": preprocessor.categorical_metadata(categorical_cols),
            "missing_value": 0,
        }
        predictor.save()
        generated["churn"] = (predictor, X, False)

    if "meter_failure" in enabled:
        predictor = MeterFailurePredictor(
            os.path.join(model_dir, MODEL_FILENAMES["meter_failure"]),
            model_config.get("meter_failure", {}).get("hyperparams"),
        )
        meter_frame, feature_cols = predictor._build_features(frames["meters"], frames["anomalies"])
        X = meter_frame[feature_cols].values
        predictor.train(X, meter_frame["failure_label"].values)
        predictor.feature_cols = feature_cols
        predictor.preprocessing = {"missing_value": 0}
        predictor.save()
        generated["meter_failure"] = (predictor, X, False)

    artifact_summaries = {}
    for name, (predictor, X, anomaly) in generated.items():
        smoke_count = _finite_smoke(predictor, X, anomaly)
        with open(manifest_path(predictor.model_path), encoding="utf-8") as manifest_file:
            manifest = json.load(manifest_file)
        artifact_summaries[name] = {
            "artifact_path": os.path.relpath(predictor.model_path, os.path.dirname(summary_path)),
            "manifest_path": os.path.relpath(manifest_path(predictor.model_path), os.path.dirname(summary_path)),
            "sha256": manifest["checksum"]["value"],
            "size_bytes": manifest["size_bytes"],
            "feature_count": len(manifest["feature_names"]),
            "load_validated": True,
            "finite_smoke_prediction": True,
            "smoke_prediction_count": smoke_count,
        }

    summary = {
        "artifact_kind": "fixture_validation",
        "production_calibrated": False,
        "fixture": {"customer_count": 40, "periods": 12, "seed": 42},
        "artifacts": artifact_summaries,
    }
    os.makedirs(os.path.dirname(os.path.abspath(summary_path)), exist_ok=True)
    with open(summary_path, "w", encoding="utf-8") as summary_file:
        json.dump(summary, summary_file, indent=2, sort_keys=True)
        summary_file.write("\n")
    return summary
