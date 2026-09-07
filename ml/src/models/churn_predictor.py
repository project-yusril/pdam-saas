import os
import numpy as np
import pandas as pd
from sklearn.metrics import accuracy_score, precision_score, recall_score, f1_score, roc_auc_score, confusion_matrix
from sklearn.model_selection import train_test_split
import xgboost as xgb
from src.artifacts import ArtifactContractError, load_artifact, save_artifact, validate_training_data


class ChurnPredictor:
    def __init__(self, model_path, hyperparams=None):
        self.model_path = model_path
        self.hyperparams = hyperparams or {
            "n_estimators": 150,
            "max_depth": 5,
            "learning_rate": 0.08,
            "scale_pos_weight": 3,
            "random_state": 42,
        }
        self.model = None
        self.metrics = {}
        self.feature_cols = None
        self.preprocessing = {}

    def train(self, X, y, validation_split=0.2):
        validate_training_data(X, y)
        if not set(np.asarray(y, dtype=int)).issubset({0, 1}):
            raise ValueError("Churn targets must be binary")
        class_counts = np.bincount(np.asarray(y, dtype=int), minlength=2)
        if X.shape[0] < 10 or class_counts.min() < 2:
            raise ValueError("Churn training requires at least 10 samples and 2 samples per class")

        X_train, X_val, y_train, y_val = train_test_split(
            X, y, test_size=validation_split, random_state=42, stratify=y if y.sum() > 1 else None
        )

        pos_count = int(y_train.sum())
        neg_count = len(y_train) - pos_count
        if pos_count > 0:
            self.hyperparams["scale_pos_weight"] = neg_count / pos_count

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
            "n_positive": int(y.sum()),
        }
        return self.metrics

    def predict(self, X):
        if self.model is None:
            raise RuntimeError("Model not trained or loaded")
        validate_training_data(X)
        probabilities = self.model.predict_proba(X)[:, 1]
        return probabilities

    def generate_predictions(self, data_loader, preprocessor, pdam_org_id, period):
        billing = data_loader.get_billing_history(pdam_org_id, months=24)
        payment = data_loader.get_payment_history(pdam_org_id, months=24)
        customers = data_loader.get_customer_features(pdam_org_id)
        churn_labels = data_loader.get_customer_churn_labels(pdam_org_id, window_days=180)

        features_df = preprocessor.build_churn_features(billing, payment, customers, churn_labels)
        if features_df.empty:
            return []

        categorical_cols = ["tariff_group", "meter_condition", "tamper_status"]
        categorical_classes = self.preprocessing.get("categorical_classes")
        if categorical_classes:
            features_df = preprocessor.apply_categorical_metadata(features_df, categorical_classes)
        else:
            features_df = preprocessor.encode_categorical(features_df, categorical_cols)
        features_df = preprocessor.fill_missing(features_df)

        exclude_cols = ["customer_id", "churned"]
        feature_cols = [c for c in features_df.columns if c not in exclude_cols]
        feature_cols = [c for c in feature_cols if c in features_df.select_dtypes(include=[np.number]).columns]

        model_feature_cols = self.feature_cols or feature_cols
        X = preprocessor.align_features(features_df, model_feature_cols).values
        probabilities = self.predict(X)

        records = []
        for i, (_, row) in enumerate(features_df.iterrows()):
            prob = float(probabilities[i])
            records.append({
                "pdam_org_id": pdam_org_id,
                "model_name": "churn_xgboost",
                "prediction_type": "churn_risk",
                "entity_type": "customer",
                "entity_id": int(row["customer_id"]),
                "period": period,
                "predicted_value": prob,
                "confidence_min": max(0, prob - 0.15),
                "confidence_max": min(1.0, prob + 0.15),
                "actual_value": int(row.get("churned", 0)),
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
            save_artifact(self.model_path, "churn", list(self.feature_cols), data,
                           getattr(self, "provenance", None))
        except ArtifactContractError as exc:
            raise RuntimeError(str(exc)) from exc

    def load(self):
        data, _ = load_artifact(self.model_path, "churn")
        self.model = data["model"]
        self.metrics = data.get("metrics", {})
        self.hyperparams = data.get("hyperparams", self.hyperparams)
        self.feature_cols = data["artifact_contract"]["feature_names"]
        self.preprocessing = data.get("preprocessing", {})
