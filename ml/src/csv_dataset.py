"""Dataset loader CSV — mirror dari ml/src/data_loader.py.

Dipakai saat kalibrasi production: CSV diekspor oleh
`php artisan pdam:ml-export-training-data` dari DB production (read-only,
satu arah), sehingga pipeline tidak butuh kredensial DB langsung.
"""

import os

import pandas as pd

# Kolom tanggal yang dikembalikan MySQL sebagai datetime; CSV harus setara.
_DATE_COLUMNS = {
    "billing_history": ["due_date", "installation_date"],
    "meter_readings": ["reading_date"],
    "customer_features": ["installation_date", "last_calibration_date"],
    "churn_labels": ["disconnection_date"],
    "payment_history": ["payment_date", "due_date"],
    "anomaly_history": [],
    "meters_failure": ["install_date", "last_calibration_date"],
}


class CsvDataLoader:
    def __init__(self, export_dir):
        self.export_dir = export_dir

    def _load(self, name):
        path = os.path.join(self.export_dir, f"{name}.csv")
        if not os.path.isfile(path):
            raise FileNotFoundError(f"Dataset export hilang: {path} (jalankan pdam:ml-export-training-data)")
        df = pd.read_csv(path)
        for column in _DATE_COLUMNS.get(name, []):
            if column in df.columns:
                df[column] = pd.to_datetime(df[column], errors="coerce")
        return df

    def get_billing_history(self, pdam_org_id, months=24):
        return self._load("billing_history")

    def get_meter_readings(self, pdam_org_id, months=24):
        return self._load("meter_readings")

    def get_customer_features(self, pdam_org_id):
        return self._load("customer_features")

    def get_customer_churn_labels(self, pdam_org_id, window_days=180):
        df = self._load("churn_labels")
        if df.empty:
            return pd.DataFrame(columns=["customer_id", "churned"])
        return df

    def get_active_customers(self, pdam_org_id):
        return self._load("customer_features")[["customer_id"]]

    def get_payment_history(self, pdam_org_id, months=24):
        return self._load("payment_history")

    def get_anomaly_history(self, pdam_org_id, months=24):
        return self._load("anomaly_history")

    def get_meters_for_failure_prediction(self, pdam_org_id):
        return self._load("meters_failure")

    def get_ml_predictions(self, pdam_org_id, *args, **kwargs):
        return pd.DataFrame()

    def dataset_summary(self):
        summary = {}
        for name in _DATE_COLUMNS:
            try:
                df = self._load(name)
            except FileNotFoundError:
                summary[name] = {"rows": 0, "months": 0}
                continue
            months = 0
            if "period" in df.columns and not df.empty:
                months = int(df["period"].astype(str).str[:7].nunique())
            elif not df.empty and "installation_date" in df.columns:
                months = int(df.shape[0])
            summary[name] = {"rows": int(len(df)), "months": months}
        return summary
