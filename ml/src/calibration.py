"""Production calibration harness — gate §13 #6 representatif-data ML.

Memuat dataset CSV hasil `php artisan pdam:ml-export-training-data`,
menjalankan training nyata, memeriksa gerbang representativeness dataset +
threshold penerimaan per model, dan memberi provenance
`production_calibrated=true` HANYA bila semua lolos. Artefak diproduksi ke
direktori kandidat terpisah; publikasi ke models/ hanya lewat --promote.
"""

import os
import shutil
from datetime import datetime, timezone

import yaml

from src.csv_dataset import CsvDataLoader
from src.pipeline import Pipeline

MODELS = ("consumption", "anomaly", "churn", "meter_failure")
MODEL_FILES = {
    "consumption": "consumption_xgboost.pkl",
    "anomaly": "anomaly_isolation_forest.pkl",
    "churn": "churn_xgboost.pkl",
    "meter_failure": "meter_failure_xgboost.pkl",
}

DEFAULT_ACCEPTANCE = {
    "dataset": {
        "require_months": 12,
        "min_billing_rows": 2000,
        "min_meter_reading_rows": 2000,
        "min_customers": 100,
    },
    "models": {},
}


class CalibrationError(RuntimeError):
    def __init__(self, message, report=None):
        super().__init__(message)
        self.report = report or {}


def _cfg_load(acceptance_cfg):
    merged = {
        "dataset": {**DEFAULT_ACCEPTANCE["dataset"], **(acceptance_cfg or {}).get("dataset", {})},
        "models": {**DEFAULT_ACCEPTANCE["models"], **(acceptance_cfg or {}).get("models", {})},
    }
    return merged


def check_dataset_gates(summary, dataset_cfg):
    failures, checks = [], []

    def gate(name, value, minimum):
        ok = bool(value) and int(value) >= int(minimum)
        checks.append({"gate": name, "actual": int(value or 0), "min": int(minimum), "pass": ok})
        if not ok:
            failures.append(f"{name}: {int(value or 0)} < minimum {int(minimum)}")

    billing = summary.get("billing_history", {})
    readings = summary.get("meter_readings", {})
    customers = summary.get("customer_features", {})

    months = max(int(billing.get("months") or 0), int(readings.get("months") or 0))
    gate("months_history", months, dataset_cfg["require_months"])
    gate("billing_rows", billing.get("rows"), dataset_cfg["min_billing_rows"])
    gate("meter_reading_rows", readings.get("rows"), dataset_cfg["min_meter_reading_rows"])
    gate("customers", customers.get("rows"), dataset_cfg["min_customers"])
    return checks, failures


def check_model_gates(model, metrics, model_cfg):
    """metrics = hasil train; cek max_*/min_* threshold."""
    checks, failures = [], []
    if not metrics or "error" in metrics:
        failures.append(f"{model}: training gagal ({(metrics or {}).get('error', 'unknown')})")
        return checks, failures
    for key, bound in (model_cfg or {}).items():
        if key.startswith("max_"):
            metric = key[4:]
            value = float(metrics.get(metric, float("inf")))
            ok = value <= float(bound)
            desc = f"{metric}({value:.4f}) <= {bound}"
        elif key.startswith("min_"):
            metric = key[4:] if key != "min_readings" else None
            if metric is None:  # khusus anomaly: gerbang jumlah baris readings sudah lewat dataset check
                continue
            value = float(metrics.get(metric, -1))
            ok = value >= float(bound)
            desc = f"{metric}({value:.4f}) >= {bound}"
        else:
            continue
        checks.append({"model": model, "check": key, "observed": value, "threshold": bound, "pass": ok})
        if not ok:
            failures.append(f"{model}: {desc} GAGAL")
    return checks, failures


def run_calibration(export_dir, org, config_path=None, candidate_dir=None, promote=False):
    """Kembalikan summary dict atau raise CalibrationError dengan report."""
    pipeline = Pipeline(config_path=config_path)
    acceptance = _cfg_load(pipeline.config.get("production_acceptance"))

    loader = CsvDataLoader(export_dir)
    pipeline.data_loader = loader
    dataset_summary = loader.dataset_summary()

    dataset_checks, dataset_failures = check_dataset_gates(dataset_summary, acceptance["dataset"])

    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    models_base = os.path.join(base_dir, pipeline.config.get("models", {}).get("base_path", "models/"))
    candidate = candidate_dir or os.path.join(base_dir, "models_candidate", f"calib-{datetime.now(timezone.utc).strftime('%Y%m%d%H%M%S')}")
    os.makedirs(candidate, exist_ok=True)

    for name in MODELS:
        predictor = pipeline.predictors[name]
        predictor.model_path = os.path.join(candidate, MODEL_FILES[name])
        predictor.provenance = None  # dilengkapi setelah evaluasi

    if dataset_failures:
        raise CalibrationError(
            "Dataset TIDAK representatif untuk production calibration: " + "; ".join(dataset_failures),
            report={"dataset_checks": dataset_checks, "dataset_summary": dataset_summary},
        )

    results = pipeline.run_training(org)

    model_checks, model_failures = [], []
    for name in MODELS:
        checks, fails = check_model_gates(name, results.get(name), acceptance["models"].get(name))
        model_checks.extend(checks)
        model_failures.extend(fails)

    summary = {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "org_id": int(org),
        "export_dir": os.path.abspath(export_dir),
        "dataset_summary": dataset_summary,
        "dataset_checks": dataset_checks,
        "model_metrics": results,
        "model_checks": model_checks,
        "failures": model_failures,
        "production_calibrated": not model_failures,
        "candidate_dir": candidate,
    }

    if model_failures:
        raise CalibrationError(
            "Threshold penerimaan production TIDAK terpenuhi: " + "; ".join(model_failures),
            report=summary,
        )

    provenance = {
        "artifact_kind": "production_calibration",
        "production_calibrated": True,
        "dataset": {
            "org_id": int(org),
            "source": "pdam:ml-export-training-data CSV",
            **{k: v for k, v in dataset_summary.items()},
            "generated_at": summary["generated_at"],
        },
        "acceptance_thresholds": acceptance["models"],
        "metrics": {k: v for k, v in results.items()},
    }
    for name in MODELS:
        pipeline.predictors[name].provenance = provenance
        pipeline.predictors[name].save()  # simpan ulang dengan provenance calibrated
        summary.setdefault("promoted", False)

    if promote:
        for name in MODELS:
            src_file = os.path.join(candidate, MODEL_FILES[name])
            shutil.copy2(src_file, os.path.join(models_base, MODEL_FILES[name]))
            shutil.copy2(src_file + ".manifest.json", os.path.join(models_base, MODEL_FILES[name] + ".manifest.json"))
        summary["promoted"] = True

    with open(os.path.join(candidate, "production_calibration_summary.json"), "w", encoding="utf-8") as fh:
        yaml.safe_dump(summary, fh, default_flow_style=False)

    return summary
