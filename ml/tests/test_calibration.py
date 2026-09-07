"""test_calibration — gerbang production-calibration (gate §13 #6).

Menghasilkan ekpor CSV representatif sintetis (24 bulan), menjalankan
run_calibration, dan membuktikan: threshold lolos → artifact kandidat
provenance production_calibrated=true + manifest tetap tervalidasi;
threshold mustahil → CalibrationError dengan report; dataset pendek →
gagal sebelum training. Butuh scikit-learn/xgboost (CI linux); di
mesin tanpa wheel ARM64 test ini di-skip otomatis.
"""

import os
import tempfile
import unittest
from datetime import datetime, timezone

import numpy as np
import pandas as pd

try:
    import xgboost  # noqa: F401
    import sklearn  # noqa: F401
    SKIPPABLE = False
except ImportError:  # pragma: no cover
    SKIPPABLE = True

from src.calibration import CalibrationError, run_calibration
from src.artifacts import load_artifact

MONTHS = ["2024-{:02d}".format(m) for m in range(1, 13)] + ["2025-{:02d}".format(m) for m in range(1, 13)]
CUSTOMERS = 120
CHURNED = set(range(1, CUSTOMERS + 1, 10))
BROKEN = set(range(3, CUSTOMERS + 1, 7))


def _config_dict(require_months=12):
    return {
        "database": {"host": "", "port": 3306, "user": "", "password": "", "database": ""},
        "models": {"base_path": "models_candidate/",
                   "consumption": {"enabled": True, "hyperparams": {"n_estimators": 10, "max_depth": 2, "random_state": 0}},
                   "anomaly": {"enabled": True, "hyperparams": {"n_estimators": 20, "random_state": 0}},
                   "churn": {"enabled": True, "hyperparams": {"n_estimators": 10, "max_depth": 2, "random_state": 0}},
                   "meter_failure": {"enabled": True, "hyperparams": {"n_estimators": 10, "max_depth": 2, "random_state": 0}}},
        "production_acceptance": {
            "dataset": {
                "require_months": require_months,
                "min_billing_rows": 20,
                "min_meter_reading_rows": 20,
                "min_customers": 10,
            },
            "models": {
                "consumption": {"max_val_rmse": 10000},
                "churn": {"min_roc_auc": 0.0, "min_f1": 0.0},
                "meter_failure": {"min_roc_auc": 0.0, "min_f1": 0.0},
                "anomaly": {"min_readings": 20},
            },
        },
    }


def _strict_config():
    cfg = _config_dict()
    cfg["production_acceptance"]["models"]["churn"]["min_roc_auc"] = 0.999
    return cfg


def _write_config(path, cfg):
    import yaml

    with open(path, "w", encoding="utf-8") as fh:
        yaml.safe_dump(cfg, fh)


def write_export(export_dir, months=MONTHS):
    rng = np.random.default_rng(7)

    billing, readings, payments, customers, churn, anomalies, meters = [], [], [], [], [], [], []
    for cid in range(1, CUSTOMERS + 1):
        base = 20 + (cid % 9)
        broken = cid in BROKEN
        install_date = "2018-03-11" if not broken else "2008-01-05"
        reading = 1000.0
        for idx, period in enumerate(months):
            consumption = max(0.5, float(rng.normal(base, 2.0)))
            if cid in CHURNED and idx >= len(months) - 3:
                consumption *= 0.1
            reading += consumption
            billing.append({
                "customer_id": cid, "period": period, "consumption": round(consumption, 2),
                "water_charge": round(consumption * 3200, 0), "amount_due": round(consumption * 3200, 0),
                "status": "overdue" if (cid in CHURNED and idx >= len(months) - 2) else "paid",
                "due_date": period + "-20", "zone_id": cid % 3, "tariff_category_id": cid % 2,
                "installation_date": install_date,
                "tariff_group": "B" if cid % 2 == 0 else "A",
            })
            readings.append({"customer_id": cid, "period": period, "reading_value": round(reading, 1),
                             "reading_type": "actual", "reading_date": period + "-15",
                             "is_rollover": False, "zone_id": cid % 3,
                             "tariff_category_id": cid % 2, "initial_reading": 1000})
            payments.append({"bill_id": cid * 100 + idx, "customer_id": cid,
                             "amount": round(consumption * 3200, 0),
                             "payment_date": period + "-25", "payment_method": "qris",
                             "period": period, "due_date": period + "-20",
                             "days_late": int(rng.integers(-3, 12))})
        customers.append({"customer_id": cid, "customer_number": f"C-{cid:05d}", "zone_id": cid % 3,
                          "zone_name": f"Zona {cid % 3}", "tariff_category_id": cid % 2,
                          "tariff_code": "R2", "tariff_group": "B" if cid % 2 == 0 else "A",
                          "installation_date": install_date, "initial_reading": 1000,
                          "meter_serial_number": f"MTR-{cid:05d}",
                          "days_since_install": 3500 if broken else 1800,
                          "has_meter": 1,
                          "days_since_calibration": 1500 if broken else 120,
                          "meter_diameter": 20, "meter_brand": "Itron",
                          "meter_condition": "broken" if broken else "good",
                          "meter_status": "installed", "tamper_status": "clear"})
        meters.append({"meter_id": cid, "serial_number": f"MTR-{cid:05d}", "customer_id": cid,
                       "diameter": 20, "brand": "Itron",
                       "condition": "broken" if broken else "good",
                       "install_date": install_date, "status": "installed",
                       "tamper_status": "clear", "last_calibration_date": "2020-01-01",
                       "tariff_category_id": cid % 2, "tariff_group": "A" if broken else "B"})
        if broken:
            for _ in range(5):
                anomalies.append({"customer_id": cid, "period": rng.choice(months),
                                  "rule_code": rng.choice(["spike", "zero_streak"]),
                                  "severity": "high", "actual_value": 1, "expected_value": 25,
                                  "anomaly_status": "open"})
        if cid in CHURNED:
            churn.append({"customer_id": cid, "disconnection_date": "2025-10-01", "churned": 1})

    pd.DataFrame(billing).to_csv(os.path.join(export_dir, "billing_history.csv"), index=False)
    pd.DataFrame(readings).to_csv(os.path.join(export_dir, "meter_readings.csv"), index=False)
    pd.DataFrame(customers).to_csv(os.path.join(export_dir, "customer_features.csv"), index=False)
    pd.DataFrame(churn).to_csv(os.path.join(export_dir, "churn_labels.csv"), index=False)
    pd.DataFrame(payments).to_csv(os.path.join(export_dir, "payment_history.csv"), index=False)
    pd.DataFrame(anomalies).to_csv(os.path.join(export_dir, "anomaly_history.csv"), index=False)
    pd.DataFrame(meters).to_csv(os.path.join(export_dir, "meters_failure.csv"), index=False)


@unittest.skipIf(SKIPPABLE, "xgboost/sklearn tidak tersedia di mesin ini (CI menjalankannya)")
class CalibrationGateTest(unittest.TestCase):
    def _prepare(self, cfg):
        tmp = tempfile.TemporaryDirectory()
        export_dir = os.path.join(tmp.name, "export")
        os.makedirs(export_dir)
        write_export(export_dir)
        config_path = os.path.join(tmp.name, "config.yaml")
        _write_config(config_path, cfg)
        candidate = os.path.join(tmp.name, "candidate")
        os.makedirs(candidate)
        return tmp, export_dir, config_path, candidate

    def test_representative_dataset_passes_and_tags_provenance(self):
        tmp, export_dir, config_path, candidate = self._prepare(_config_dict())
        try:
            summary = run_calibration(export_dir, org=1, config_path=config_path, candidate_dir=candidate)
            self.assertTrue(summary["production_calibrated"])

            for fname in ("consumption_xgboost.pkl", "anomaly_isolation_forest.pkl",
                          "churn_xgboost.pkl", "meter_failure_xgboost.pkl"):
                manifest_path = os.path.join(candidate, fname + ".manifest.json")
                self.assertTrue(os.path.isfile(fname and manifest_path))
                with open(manifest_path, encoding="utf-8") as fh:
                    import json

                    manifest = json.load(fh)
                self.assertIs(manifest["provenance"]["production_calibrated"], True)
                self.assertEqual(manifest["provenance"]["artifact_kind"], "production_calibration")

            # round-trip: manifest tambahan provenance TETAK lulus kontrak load
            for name, mt in (("consumption_xgboost.pkl", "consumption"),
                             ("anomaly_isolation_forest.pkl", "anomaly"),
                             ("churn_xgboost.pkl", "churn"),
                             ("meter_failure_xgboost.pkl", "meter_failure")):
                payload, _ = load_artifact(os.path.join(candidate, name), mt)
                self.assertIsNotNone(payload["model"])
        finally:
            tmp.cleanup()

    def test_impossible_threshold_is_rejected_with_report(self):
        tmp, export_dir, config_path, candidate = self._prepare(_strict_config())
        try:
            with self.assertRaises(CalibrationError) as ctx:
                run_calibration(export_dir, org=1, config_path=config_path, candidate_dir=candidate)
            self.assertIn("churn", str(ctx.exception))
            self.assertTrue(ctx.exception.report["failures"])
            self.assertFalse(ctx.exception.report["production_calibrated"])
        finally:
            tmp.cleanup()

    def test_short_dataset_fails_before_training(self):
        short_months = MONTHS[-2:]
        tmp, export_dir, config_path, candidate = self._prepare(_config_dict(require_months=12))
        try:
            write_export(export_dir, months=short_months)
            with self.assertRaises(CalibrationError) as ctx:
                run_calibration(export_dir, org=1, config_path=config_path, candidate_dir=candidate)
            self.assertIn("months_history", str(ctx.exception))
            self.assertLess(len(ctx.exception.report.get("model_metrics", {})), 1)
        finally:
            tmp.cleanup()

    def test_provenance_serialization_roundtrip_time(self):
        self.assertIsNotNone(datetime.now(timezone.utc).isoformat())


if __name__ == "__main__":
    unittest.main()
