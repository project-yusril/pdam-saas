import os
import pickle
import tempfile
import unittest
from unittest.mock import MagicMock, patch

try:
    import numpy as np
    import pandas as pd
except (ImportError, ValueError) as error:
    raise unittest.SkipTest(f"ML numerical dependencies are unavailable: {error}")

from src.models.churn_predictor import ChurnPredictor
from src.models.consumption_predictor import ConsumptionPredictor
from src.models.meter_failure_predictor import MeterFailurePredictor
from src.preprocessing import Preprocessor


class RecordingRegressor:
    def __init__(self):
        self.inputs = []

    def predict(self, X):
        values = np.asarray(X, dtype=float)
        self.inputs.append(values.copy())
        return values[:, 0]


class RecordingClassifier:
    def __init__(self):
        self.inputs = []

    def predict_proba(self, X):
        values = np.asarray(X, dtype=float)
        self.inputs.append(values.copy())
        probabilities = np.clip(values[:, 0] / 10.0, 0, 1)
        return np.column_stack([1 - probabilities, probabilities])


class FeatureContractTest(unittest.TestCase):
    def test_preprocessor_reindexes_columns_and_uses_training_categories(self):
        preprocessor = Preprocessor()
        frame = pd.DataFrame({"extra": [99], "plan": ["new"], "usage": [7.5]})

        encoded = preprocessor.apply_categorical_metadata(
            frame, {"plan": ["basic", "premium"]}
        )
        aligned = preprocessor.align_features(encoded, ["usage", "plan", "missing"])

        np.testing.assert_allclose(aligned.values, [[7.5, -1.0, 0.0]])
        self.assertEqual(aligned.columns.tolist(), ["usage", "plan", "missing"])

    def test_consumption_serving_uses_stored_order_and_category_mapping(self):
        predictor = ConsumptionPredictor("unused.pkl")
        predictor.model = RecordingRegressor()
        predictor.feature_names = ["lag_1", "tariff_group", "missing_feature"]
        predictor.preprocessing = {
            "categorical_classes": {"tariff_group": ["business", "home"], "status": ["paid"]}
        }
        features = pd.DataFrame({
            "customer_id": [8], "period": ["2026-06"], "consumption": [11.0],
            "tariff_group": ["home"], "status": ["paid"], "lag_1": [4.5],
            "unexpected": [123.0],
        })
        loader = MagicMock()
        loader.get_billing_history.return_value = pd.DataFrame({"present": [1]})

        with patch("src.preprocessing.Preprocessor.build_consumption_features", return_value=features):
            records = predictor.generate_predictions(loader, 3, "2026-07")

        np.testing.assert_allclose(predictor.model.inputs[0], [[4.5, 1.0, 0.0]])
        self.assertEqual(records[0]["predicted_value"], 4.5)

    def test_churn_serving_reindexes_to_artifact_features(self):
        predictor = ChurnPredictor("unused.pkl")
        predictor.model = RecordingClassifier()
        predictor.feature_cols = ["risk", "tariff_group", "missing_feature"]
        predictor.preprocessing = {"categorical_classes": {"tariff_group": ["A", "B"]}}
        features = pd.DataFrame({
            "customer_id": [4], "churned": [0], "tariff_group": ["B"],
            "risk": [6.0], "unexpected": [88.0],
        })
        preprocessor = Preprocessor()
        preprocessor.build_churn_features = MagicMock(return_value=features)
        loader = MagicMock()

        records = predictor.generate_predictions(loader, preprocessor, 2, "2026-07")

        np.testing.assert_allclose(predictor.model.inputs[0], [[6.0, 1.0, 0.0]])
        self.assertAlmostEqual(records[0]["predicted_value"], 0.6)

    def test_meter_serving_reindexes_to_artifact_features(self):
        predictor = MeterFailurePredictor("unused.pkl")
        predictor.model = RecordingClassifier()
        predictor.feature_cols = ["anomaly_count", "meter_age_years", "missing_feature"]
        features = pd.DataFrame({
            "meter_id": [12], "customer_id": [5], "failure_label": [0],
            "meter_age_years": [3.0], "anomaly_count": [8.0], "extra": [1.0],
        })
        predictor._build_features = MagicMock(return_value=(features, ["meter_age_years", "anomaly_count", "extra"]))
        loader = MagicMock()
        loader.get_meters_for_failure_prediction.return_value = pd.DataFrame({"meter_id": [12]})
        loader.get_anomaly_history.return_value = pd.DataFrame()

        records = predictor.generate_predictions(loader, 2, "2026-07")

        np.testing.assert_allclose(predictor.model.inputs[0], [[8.0, 3.0, 0.0]])
        self.assertAlmostEqual(records[0]["predicted_value"], 0.8)

    def test_artifact_round_trip_persists_feature_and_preprocessing_metadata(self):
        with tempfile.TemporaryDirectory() as directory:
            path = os.path.join(directory, "consumption.pkl")
            predictor = ConsumptionPredictor(path)
            predictor.model = RecordingRegressor()
            predictor.feature_names = ["second", "first"]
            predictor.preprocessing = {"categorical_classes": {"status": ["paid", "unpaid"]}}
            predictor.save()

            with open(path, "rb") as artifact_file:
                artifact = pickle.load(artifact_file)
            loaded = ConsumptionPredictor(path)
            loaded.load()

        self.assertEqual(artifact["feature_names"], ["second", "first"])
        self.assertEqual(loaded.feature_names, ["second", "first"])
        self.assertEqual(loaded.preprocessing, predictor.preprocessing)

    def test_insufficient_training_data_never_creates_dummy_models(self):
        cases = [
            (ConsumptionPredictor("unused.pkl"), np.zeros((9, 2)), np.zeros(9)),
            (ChurnPredictor("unused.pkl"), np.zeros((10, 2)), np.array([0] * 9 + [1])),
            (MeterFailurePredictor("unused.pkl"), np.zeros((4, 2)), np.array([0, 0, 1, 1])),
        ]

        for predictor, X, y in cases:
            with self.subTest(predictor=type(predictor).__name__):
                with self.assertRaises(ValueError):
                    predictor.train(X, y)
                self.assertIsNone(predictor.model)

    def test_save_rejects_missing_feature_contract(self):
        with tempfile.TemporaryDirectory() as directory:
            predictors = [
                ConsumptionPredictor(os.path.join(directory, "consumption.pkl")),
                ChurnPredictor(os.path.join(directory, "churn.pkl")),
                MeterFailurePredictor(os.path.join(directory, "meter.pkl")),
            ]
            for predictor in predictors:
                predictor.model = MagicMock()
                with self.subTest(predictor=type(predictor).__name__):
                    with self.assertRaises(RuntimeError):
                        predictor.save()
                    self.assertFalse(os.path.exists(predictor.model_path))


if __name__ == "__main__":
    unittest.main()
