import os
import numpy as np
import pandas as pd
from datetime import datetime
from sklearn.metrics import mean_squared_error, mean_absolute_error, r2_score
from sklearn.model_selection import train_test_split
import xgboost as xgb
from src.artifacts import ArtifactContractError, load_artifact, save_artifact, validate_training_data


class ConsumptionPredictor:
    def __init__(self, model_path, hyperparams=None):
        self.model_path = model_path
        self.hyperparams = hyperparams or {
            "n_estimators": 200,
            "max_depth": 6,
            "learning_rate": 0.1,
            "subsample": 0.8,
            "colsample_bytree": 0.8,
            "random_state": 42,
        }
        self.model = None
        self.metrics = {}
        self.feature_names = None
        self.preprocessing = {}

    def train(self, X, y, validation_split=0.2):
        validate_training_data(X, y)
        if X.shape[0] < 10:
            raise ValueError("Consumption training requires at least 10 samples")

        X_train, X_val, y_train, y_val = train_test_split(
            X, y, test_size=validation_split, random_state=42
        )

        self.model = xgb.XGBRegressor(**self.hyperparams)
        self.model.fit(X_train, y_train)

        y_pred_train = self.model.predict(X_train)
        y_pred_val = self.model.predict(X_val)

        self.metrics = {
            "train_rmse": float(np.sqrt(mean_squared_error(y_train, y_pred_train))),
            "train_mae": float(mean_absolute_error(y_train, y_pred_train)),
            "train_r2": float(r2_score(y_train, y_pred_train)),
            "val_rmse": float(np.sqrt(mean_squared_error(y_val, y_pred_val))),
            "val_mae": float(mean_absolute_error(y_val, y_pred_val)),
            "val_r2": float(r2_score(y_val, y_pred_val)),
            "n_samples": X.shape[0],
            "n_features": X.shape[1],
        }
        return self.metrics

    def predict(self, X):
        if self.model is None:
            raise RuntimeError("Model not trained or loaded")
        validate_training_data(X)
        predictions = self.model.predict(X)
        return predictions

    def predict_next_month(self, customer_features_df, feature_columns):
        if self.model is None:
            raise RuntimeError("Model not trained or loaded")
        X = customer_features_df[feature_columns].fillna(0).values
        predictions = self.model.predict(X)
        return predictions

    def save(self):
        if self.model is None:
            raise RuntimeError("Cannot save an untrained model")
        if not self.feature_names:
            raise RuntimeError("Cannot save model without feature metadata")
        data = {
            "model": self.model,
            "metrics": self.metrics,
            "hyperparams": self.hyperparams,
            "feature_names": self.feature_names,
            "preprocessing": self.preprocessing,
        }
        try:
            save_artifact(self.model_path, "consumption", list(self.feature_names), data,
                                      getattr(self, "provenance", None))
        except ArtifactContractError as exc:
            raise RuntimeError(str(exc)) from exc

    def load(self):
        data, _ = load_artifact(self.model_path, "consumption")
        self.model = data["model"]
        self.metrics = data.get("metrics", {})
        self.hyperparams = data.get("hyperparams", self.hyperparams)
        self.feature_names = data["artifact_contract"]["feature_names"]
        self.preprocessing = data.get("preprocessing", {})

    def generate_predictions(self, data_loader, pdam_org_id, period):
        billing = data_loader.get_billing_history(pdam_org_id, months=24)
        if billing.empty:
            return []

        from src.preprocessing import Preprocessor

        preprocessor = Preprocessor()
        features_df = preprocessor.build_consumption_features(billing)
        if features_df.empty:
            return []

        categorical_cols = ["tariff_group", "status"]
        categorical_classes = self.preprocessing.get("categorical_classes")
        if categorical_classes:
            features_df = preprocessor.apply_categorical_metadata(features_df, categorical_classes)
        else:
            features_df = preprocessor.encode_categorical(features_df, categorical_cols)

        exclude_cols = ["customer_id", "consumption", "period", "water_charge", "amount_due"]
        feature_cols = [c for c in features_df.columns if c not in exclude_cols]
        feature_cols = [c for c in feature_cols if c in features_df.select_dtypes(include=[np.number]).columns]

        model_feature_cols = self.feature_names or feature_cols
        X_input = preprocessor.align_features(features_df, model_feature_cols).values
        predictions = self.predict(X_input)
        residuals = predictions - X_input[:, 0] if X_input.shape[1] > 0 else np.zeros(len(predictions))
        std_residual = np.std(residuals) if len(residuals) > 1 else 1.0

        records = []
        for i, (_, row) in enumerate(features_df.iterrows()):
            pred = float(predictions[i])
            ci_half = 1.96 * float(std_residual)
            records.append({
                "pdam_org_id": pdam_org_id,
                "model_name": "consumption_xgboost",
                "prediction_type": "consumption",
                "entity_type": "customer",
                "entity_id": int(row["customer_id"]),
                "period": period,
                "predicted_value": pred,
                "confidence_min": max(0, pred - ci_half),
                "confidence_max": pred + ci_half,
                "actual_value": None,
                "features": {c: float(row.get(c, 0)) for c in model_feature_cols[:10]},
                "status": "predicted",
            })
        return records
