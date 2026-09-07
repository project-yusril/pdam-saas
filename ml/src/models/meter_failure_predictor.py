import os
import numpy as np
import pandas as pd
from sklearn.metrics import accuracy_score, precision_score, recall_score, f1_score, roc_auc_score
from sklearn.model_selection import train_test_split
import xgboost as xgb
from src.artifacts import ArtifactContractError, load_artifact, save_artifact, validate_training_data


class MeterFailurePredictor:
    def __init__(self, model_path, hyperparams=None):
        self.model_path = model_path
        self.hyperparams = hyperparams or {
            "n_estimators": 100,
            "max_depth": 4,
            "learning_rate": 0.1,
            "random_state": 42,
        }
        self.model = None
        self.metrics = {}
        self.feature_cols = None
        self.preprocessing = {}

    def _build_features(self, meters_df, anomaly_df):
        if meters_df.empty:
            return pd.DataFrame(), []

        features = []
        for _, meter in meters_df.iterrows():
            row = {
                "meter_id": meter.get("meter_id", meter.get("customer_id")),
                "customer_id": meter.get("customer_id"),
                "serial_number": meter.get("serial_number", ""),
                "meter_age_days": float(meter.get("days_since_install", 0) or 0),
                "meter_age_years": float(meter.get("days_since_install", 0) or 0) / 365.25,
                "days_since_calibration": float(meter.get("days_since_calibration", 0) or 0),
                "diameter": float(meter.get("diameter", 0) or 0),
                "tamper_flag": 1 if str(meter.get("tamper_status", "")).lower() in ("tampered", "1", "yes", "true") else 0,
                "tariff_category_id": int(meter.get("tariff_category_id", 0) or 0),
                "meter_condition_score": self._condition_score(meter.get("condition", "")),
                "tariff_group": meter.get("tariff_group", ""),
            }

            row["calibration_overdue"] = 1 if row["days_since_calibration"] > 365 * 3 else 0
            row["is_old_meter"] = 1 if row["meter_age_years"] > 5 else 0
            row["age_calibration_interaction"] = row["meter_age_years"] * row["calibration_overdue"]

            if not anomaly_df.empty:
                meter_anomalies = anomaly_df[anomaly_df["customer_id"] == row["customer_id"]]
                row["anomaly_count"] = len(meter_anomalies)
                row["high_severity_count"] = (meter_anomalies["severity"] == "high").sum() if "severity" in meter_anomalies.columns else 0
                row["spike_count"] = (meter_anomalies["rule_code"] == "spike").sum() if "rule_code" in meter_anomalies.columns else 0
                row["zero_streak_count"] = (meter_anomalies["rule_code"] == "zero_streak").sum() if "rule_code" in meter_anomalies.columns else 0
            else:
                row["anomaly_count"] = 0
                row["high_severity_count"] = 0
                row["spike_count"] = 0
                row["zero_streak_count"] = 0

            row["anomaly_density"] = row["anomaly_count"] / (row["meter_age_days"] / 30 + 1)

            label = 0
            condition = str(meter.get("condition", "")).lower()
            if condition in ("rusak", "broken", "faulty", "damaged", "error"):
                label = 1
            elif row["meter_age_years"] > 10 and row["anomaly_count"] > 5:
                label = 1
            elif row["calibration_overdue"] == 1 and row["anomaly_count"] > 3:
                label = 1
            elif row["tamper_flag"] == 1 and row["anomaly_count"] > 2:
                label = 1
            row["failure_label"] = label

            features.append(row)

        result = pd.DataFrame(features)

        exclude_cols = ["meter_id", "customer_id", "serial_number", "failure_label", "tariff_group"]
        feature_cols = [c for c in result.columns if c not in exclude_cols]
        feature_cols = [c for c in feature_cols if c in result.select_dtypes(include=[np.number]).columns]

        return result, feature_cols

    def _condition_score(self, condition):
        mapping = {
            "baik": 0, "good": 0, "baru": 0, "new": 0,
            "cukup": 1, "fair": 1, "normal": 1,
            "buruk": 2, "poor": 2, "worn": 2,
            "rusak": 3, "broken": 3, "faulty": 3, "damaged": 3, "error": 3,
        }
        return mapping.get(str(condition).lower(), 0)

    def train(self, X, y, validation_split=0.2):
        validate_training_data(X, y)
        if not set(np.asarray(y, dtype=int)).issubset({0, 1}):
            raise ValueError("Meter failure targets must be binary")
        class_counts = np.bincount(np.asarray(y, dtype=int), minlength=2)
        if X.shape[0] < 10 or class_counts.min() < 2:
            raise ValueError("Meter failure training requires at least 10 samples and 2 samples per class")

        X_train, X_val, y_train, y_val = train_test_split(
            X, y, test_size=validation_split, random_state=42, stratify=y if len(set(y)) > 1 else None
        )

        pos = int(y_train.sum())
        neg = len(y_train) - pos
        if pos > 0:
            self.hyperparams["scale_pos_weight"] = neg / pos

        self.model = xgb.XGBClassifier(**self.hyperparams)
        self.model.fit(X_train, y_train)

        y_pred = self.model.predict(X_val)
        y_prob = self.model.predict_proba(X_val)[:, 1] if hasattr(self.model, "predict_proba") else y_pred

        self.metrics = {
            "accuracy": float(accuracy_score(y_val, y_pred)),
            "precision": float(precision_score(y_val, y_pred, zero_division=0)),
            "recall": float(recall_score(y_val, y_pred, zero_division=0)),
            "f1": float(f1_score(y_val, y_pred, zero_division=0)),
            "roc_auc": float(roc_auc_score(y_val, y_prob) if len(set(y_val)) > 1 else 0.5),
            "n_samples": X.shape[0],
            "n_failures": int(y.sum()),
        }
        return self.metrics

    def predict(self, X):
        if self.model is None:
            raise RuntimeError("Model not trained or loaded")
        validate_training_data(X)
        return self.model.predict_proba(X)[:, 1]

    def generate_predictions(self, data_loader, pdam_org_id, period):
        meters = data_loader.get_meters_for_failure_prediction(pdam_org_id)
        if meters.empty:
            return []

        anomalies = data_loader.get_anomaly_history(pdam_org_id, months=24)

        features_df, feature_cols = self._build_features(meters, anomalies)

        if features_df.empty:
            return []

        model_feature_cols = self.feature_cols or feature_cols
        X = features_df.reindex(columns=model_feature_cols, fill_value=0).fillna(0).values
        probabilities = self.predict(X)

        records = []
        for i, (_, row) in enumerate(features_df.iterrows()):
            prob = float(probabilities[i])
            records.append({
                "pdam_org_id": pdam_org_id,
                "model_name": "meter_failure_xgboost",
                "prediction_type": "meter_failure",
                "entity_type": "meter",
                "entity_id": int(row["meter_id"]) if pd.notna(row.get("meter_id")) else int(row["customer_id"]),
                "period": period,
                "predicted_value": prob,
                "confidence_min": max(0, prob - 0.15),
                "confidence_max": min(1.0, prob + 0.15),
                "actual_value": int(row["failure_label"]),
                "features": {c: float(row.get(c, 0)) for c in model_feature_cols[:10]},
                "status": "predicted",
            })
        return records

    def save(self):
        if self.model is None:
            raise RuntimeError("Cannot save an untrained model")
        if not self.feature_cols:
            raise RuntimeError("Cannot save model without feature metadata")
        data = {
            "model": self.model,
            "metrics": self.metrics,
            "hyperparams": self.hyperparams,
            "feature_cols": self.feature_cols,
            "preprocessing": self.preprocessing,
        }
        try:
            save_artifact(self.model_path, "meter_failure", list(self.feature_cols), data,
                               getattr(self, "provenance", None))
        except ArtifactContractError as exc:
            raise RuntimeError(str(exc)) from exc

    def load(self):
        data, _ = load_artifact(self.model_path, "meter_failure")
        self.model = data["model"]
        self.metrics = data.get("metrics", {})
        self.hyperparams = data.get("hyperparams", self.hyperparams)
        self.feature_cols = data["artifact_contract"]["feature_names"]
        self.preprocessing = data.get("preprocessing", {})
