import os
import numpy as np
import pandas as pd
from sklearn.ensemble import IsolationForest
from src.artifacts import ArtifactContractError, load_artifact, save_artifact, validate_training_data


class AnomalyDetector:
    DEFAULT_HYPERPARAMS = {
        "n_estimators": 150,
        "contamination": 0.05,
        "max_samples": 0.8,
        "random_state": 42,
    }

    def __init__(self, model_path, hyperparams=None):
        self.model_path = model_path
        # user config boleh hanya sebagian (mis. ml/config.yaml models.anomaly)
        self.hyperparams = {**self.DEFAULT_HYPERPARAMS, **(hyperparams or {})}
        self.model = None
        self.thresholds = {}
        self.feature_cols = None

    def _build_features(self, readings_df):
        df = readings_df.copy()
        df = df.sort_values(["customer_id", "period"])

        features = []
        for customer_id, group in df.groupby("customer_id"):
            group = group.sort_values("period")
            vals = group["reading_value"].astype(float)
            cons = vals.diff().fillna(0)

            if len(vals) < 2:
                continue

            for i in range(1, len(vals)):
                window = cons[max(0, i - 3) : i + 1]
                row = {
                    "customer_id": customer_id,
                    "period": group.iloc[i]["period"],
                    "reading_value": group.iloc[i]["reading_value"],
                    "consumption": cons.iloc[i],
                    "reading_type_flag": 1 if group.iloc[i].get("reading_type") == "estimated" else 0,
                }
                row["rolling_mean_3"] = window.mean()
                row["rolling_std_3"] = window.std() if len(window) > 1 else 0.0
                row["lag_1"] = cons.iloc[i - 1] if i >= 1 else 0.0
                row["lag_2"] = cons.iloc[i - 2] if i >= 2 else 0.0
                row["pct_change"] = (cons.iloc[i] - cons.iloc[i - 1]) / (cons.iloc[i - 1] + 1e-6) if i >= 1 else 0.0
                row["cumulative"] = vals.iloc[i] - vals.iloc[0]
                row["is_rollover"] = 1 if group.iloc[i].get("is_rollover") else 0

                month_str = str(group.iloc[i]["period"])
                if "-" in month_str:
                    row["month"] = int(month_str.split("-")[1])
                    row["month_sin"] = np.sin(2 * np.pi * row["month"] / 12)
                    row["month_cos"] = np.cos(2 * np.pi * row["month"] / 12)

                features.append(row)

        if not features:
            return pd.DataFrame(), np.empty((0, 0)), [], [], []

        result = pd.DataFrame(features)
        feature_cols = [c for c in result.columns if c not in ["customer_id", "period", "reading_value", "consumption"]]
        feature_cols = [c for c in feature_cols if c in result.select_dtypes(include=[np.number]).columns]

        X = result[feature_cols].fillna(0).values
        return result, X, feature_cols, list(result["customer_id"]), list(result["period"])

    def train(self, X, feature_cols=None):
        validate_training_data(X)
        if X.shape[0] < 10:
            raise ValueError("Anomaly training requires at least 10 samples")
        if feature_cols is not None:
            self.feature_cols = list(feature_cols)
        if not self.feature_cols or len(self.feature_cols) != X.shape[1]:
            raise ValueError("Anomaly training requires feature metadata matching X")
        self.model = IsolationForest(**self.hyperparams)
        self.model.fit(X)

        scores = self.model.decision_function(X)
        self.thresholds["decision_mean"] = float(np.mean(scores))
        self.thresholds["decision_std"] = float(np.std(scores))

        anomaly_labels = self.model.predict(X)
        anomaly_count = int((anomaly_labels == -1).sum())

        return {
            "n_samples": X.shape[0],
            "anomalies_detected": anomaly_count,
            "anomaly_rate": float(anomaly_count / X.shape[0]),
            "contamination": self.hyperparams["contamination"],
            "decision_mean": self.thresholds["decision_mean"],
            "decision_std": self.thresholds["decision_std"],
        }

    def predict(self, X):
        if self.model is None:
            raise RuntimeError("Model not trained or loaded")
        validate_training_data(X)
        labels = self.model.predict(X)
        scores = self.model.decision_function(X)
        return labels, scores

    def detect_anomalies_rule_based(self, readings_df):
        df = readings_df.sort_values(["customer_id", "period"])
        events = []

        for customer_id, group in df.groupby("customer_id"):
            group = group.sort_values("period")
            vals = group["consumption"].astype(float).values if "consumption" in group.columns else group["reading_value"].astype(float).values
            periods = group["period"].values

            if len(vals) < 4:
                continue

            avg_3 = np.mean(vals[-4:-1]) if len(vals) >= 4 else np.mean(vals[:-1])
            current = vals[-1]
            current_period = periods[-1]

            if avg_3 > 0 and current > 2 * avg_3:
                events.append({"customer_id": customer_id, "period": current_period, "rule": "spike", "severity": "high", "score": min(1.0, current / (avg_3 * 3))})

            if avg_3 > 0 and current < 0.5 * avg_3 and current > 0:
                events.append({"customer_id": customer_id, "period": current_period, "rule": "drop", "severity": "medium", "score": 1.0 - current / avg_3})

            zero_streak = 0
            for v in reversed(vals):
                if v == 0:
                    zero_streak += 1
                else:
                    break
            if zero_streak >= 2:
                events.append({"customer_id": customer_id, "period": current_period, "rule": "zero_streak", "severity": "high", "score": min(1.0, zero_streak / 4)})

            estimate_streak = 0
            for _, r in group.iloc[::-1].iterrows():
                if r.get("reading_type") == "estimated":
                    estimate_streak += 1
                else:
                    break
            if estimate_streak >= 3:
                events.append({"customer_id": customer_id, "period": current_period, "rule": "repeated_estimate", "severity": "high", "score": min(1.0, estimate_streak / 5)})

        return events

    def detect(self, readings_df):
        features_df, X, feature_cols, cust_ids, periods = self._build_features(readings_df)
        if X.shape[0] == 0:
            return [], []

        if not self.feature_cols:
            raise RuntimeError("Loaded anomaly model has no feature metadata")
        aligned = features_df.reindex(columns=self.feature_cols, fill_value=0).fillna(0).values
        ml_labels, ml_scores = self.predict(aligned)

        ml_events = []
        for i in range(len(ml_labels)):
            if ml_labels[i] == -1:
                ml_events.append({
                    "customer_id": cust_ids[i],
                    "period": periods[i],
                    "rule": "isolation_forest",
                    "severity": "medium",
                    "score": float(np.abs(ml_scores[i])),
                })

        consumption_readings = readings_df.copy()
        if "consumption" not in consumption_readings.columns:
            consumption_readings["consumption"] = consumption_readings.groupby("customer_id")["reading_value"].diff().fillna(0)

        rule_events = self.detect_anomalies_rule_based(consumption_readings)

        seen = set()
        merged = []
        for e in ml_events + rule_events:
            key = (e["customer_id"], e["period"], e["rule"])
            if key not in seen:
                seen.add(key)
                merged.append(e)

        return merged, ml_events

    def generate_predictions(self, data_loader, pdam_org_id, period):
        readings = data_loader.get_meter_readings(pdam_org_id, months=24)
        if readings.empty:
            return []

        combined_events, ml_events = self.detect(readings)

        records = []
        for event in combined_events:
            confidence = float(event["score"])
            records.append({
                "pdam_org_id": pdam_org_id,
                "model_name": "anomaly_isolation_forest",
                "prediction_type": "anomaly",
                "entity_type": "customer",
                "entity_id": int(event["customer_id"]),
                "period": event["period"],
                "predicted_value": confidence,
                "confidence_min": max(0, confidence - 0.1),
                "confidence_max": min(1.0, confidence + 0.1),
                "actual_value": None,
                "features": {"rule": event["rule"], "severity": event["severity"]},
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
            "hyperparams": self.hyperparams,
            "thresholds": self.thresholds,
            "feature_cols": self.feature_cols,
        }
        try:
            save_artifact(self.model_path, "anomaly", list(self.feature_cols), data,
                           getattr(self, "provenance", None))
        except ArtifactContractError as exc:
            raise RuntimeError(str(exc)) from exc

    def load(self):
        data, _ = load_artifact(self.model_path, "anomaly")
        self.model = data["model"]
        self.hyperparams = data.get("hyperparams", self.hyperparams)
        self.thresholds = data.get("thresholds", {})
        self.feature_cols = data["artifact_contract"]["feature_names"]
