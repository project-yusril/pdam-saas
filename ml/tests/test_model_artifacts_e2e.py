import json
import os
import tempfile
import unittest

try:
    import numpy as np
    import pandas as pd
except (ImportError, ValueError) as error:
    raise unittest.SkipTest(f"ML numerical dependencies are unavailable: {error}")

from src.artifacts import ArtifactContractError, manifest_path
from src.models.anomaly_detector import AnomalyDetector
from src.models.churn_predictor import ChurnPredictor
from src.models.consumption_predictor import ConsumptionPredictor
from src.models.meter_failure_predictor import MeterFailurePredictor
from src.preprocessing import Preprocessor
from src.synthetic_fixtures import make_synthetic_frames
from src.validation_artifacts import MODEL_FILENAMES, SUMMARY_FILENAME, generate_validation_artifacts


FAST_XGB = {"n_estimators": 8, "max_depth": 2, "learning_rate": 0.2, "random_state": 42}


class ModelArtifactEndToEndTest(unittest.TestCase):
    def test_fixture_generator_writes_four_validated_artifacts_and_summary(self):
        with tempfile.TemporaryDirectory() as directory:
            summary = generate_validation_artifacts(output_dir=directory)

            self.assertEqual(summary["artifact_kind"], "fixture_validation")
            self.assertFalse(summary["production_calibrated"])
            self.assertEqual(set(summary["artifacts"]), set(MODEL_FILENAMES))
            self.assertTrue(os.path.isfile(os.path.join(directory, SUMMARY_FILENAME)))
            for name, filename in MODEL_FILENAMES.items():
                self.assertTrue(os.path.isfile(os.path.join(directory, filename)))
                self.assertTrue(os.path.isfile(manifest_path(os.path.join(directory, filename))))
                self.assertTrue(summary["artifacts"][name]["load_validated"])
                self.assertTrue(summary["artifacts"][name]["finite_smoke_prediction"])
                self.assertEqual(len(summary["artifacts"][name]["sha256"]), 64)

    def test_all_models_train_save_load_and_predict_in_temporary_paths(self):
        frames = make_synthetic_frames()
        with tempfile.TemporaryDirectory() as directory:
            preprocessor = Preprocessor()
            X, y, _ = preprocessor.prepare_consumption_data(frames["billing"])
            consumption = ConsumptionPredictor(os.path.join(directory, "consumption.pkl"), FAST_XGB)
            consumption.train(X, y)
            consumption.feature_names = list(preprocessor.feature_columns)
            consumption.preprocessing = {"categorical_classes": preprocessor.categorical_metadata(["tariff_group", "status"])}
            consumption.save()
            loaded_consumption = ConsumptionPredictor(consumption.model_path)
            loaded_consumption.load()
            self.assertTrue(np.isfinite(loaded_consumption.predict(X[:3])).all())

            anomaly = AnomalyDetector(os.path.join(directory, "anomaly.pkl"), {"n_estimators": 8, "contamination": 0.1, "random_state": 42})
            _, anomaly_X, anomaly_features, _, _ = anomaly._build_features(frames["readings"])
            anomaly.train(anomaly_X, anomaly_features)
            anomaly.save()
            loaded_anomaly = AnomalyDetector(anomaly.model_path)
            loaded_anomaly.load()
            labels, scores = loaded_anomaly.predict(anomaly_X[:3])
            self.assertEqual(len(labels), 3)
            self.assertTrue(np.isfinite(scores).all())

            churn_frame = preprocessor.build_churn_features(frames["billing"], frames["payments"], frames["customers"], frames["churn_labels"])
            churn_frame = preprocessor.encode_categorical(churn_frame, ["tariff_group", "meter_condition", "tamper_status"])
            churn_frame = preprocessor.fill_missing(churn_frame)
            churn_features = [column for column in churn_frame.select_dtypes(include=[np.number]).columns if column not in ["customer_id", "churned"]]
            churn_X, churn_y = churn_frame[churn_features].values, churn_frame["churned"].values
            churn = ChurnPredictor(os.path.join(directory, "churn.pkl"), FAST_XGB)
            churn.train(churn_X, churn_y)
            churn.feature_cols = churn_features
            churn.save()
            loaded_churn = ChurnPredictor(churn.model_path)
            loaded_churn.load()
            self.assertTrue(np.isfinite(loaded_churn.predict(churn_X[:3])).all())

            meter = MeterFailurePredictor(os.path.join(directory, "meter.pkl"), FAST_XGB)
            meter_frame, meter_features = meter._build_features(frames["meters"], frames["anomalies"])
            meter_X, meter_y = meter_frame[meter_features].values, meter_frame["failure_label"].values
            meter.train(meter_X, meter_y)
            meter.feature_cols = meter_features
            meter.save()
            loaded_meter = MeterFailurePredictor(meter.model_path)
            loaded_meter.load()
            self.assertTrue(np.isfinite(loaded_meter.predict(meter_X[:3])).all())

            for predictor in [consumption, anomaly, churn, meter]:
                with open(manifest_path(predictor.model_path), encoding="utf-8") as manifest_file:
                    manifest = json.load(manifest_file)
                self.assertEqual(manifest["schema_version"], 1)
                self.assertEqual(manifest["checksum"]["algorithm"], "sha256")
                self.assertTrue(manifest["feature_names"])

    def test_corrupt_and_incomplete_artifacts_are_rejected_before_load(self):
        with tempfile.TemporaryDirectory() as directory:
            path = os.path.join(directory, "model.pkl")
            model = ConsumptionPredictor(path)
            model.model = object()
            model.feature_names = ["usage"]
            model.save()
            with open(path, "ab") as artifact_file:
                artifact_file.write(b"corrupt")
            with self.assertRaises(ArtifactContractError):
                ConsumptionPredictor(path).load()
            os.unlink(manifest_path(path))
            with self.assertRaises(ArtifactContractError):
                ConsumptionPredictor(path).load()

    def test_numerical_safety_and_anomaly_save_rejection(self):
        bad_X = np.array([[1.0], [np.inf]] + [[1.0]] * 8)
        cases = [
            (ConsumptionPredictor("unused"), (bad_X, np.arange(10.0))),
            (ChurnPredictor("unused"), (bad_X, np.array([0, 1] * 5))),
            (MeterFailurePredictor("unused"), (bad_X, np.array([0, 1] * 5))),
        ]
        for predictor, arguments in cases:
            with self.subTest(type(predictor).__name__), self.assertRaises(ValueError):
                predictor.train(*arguments)

        with tempfile.TemporaryDirectory() as directory:
            anomaly = AnomalyDetector(os.path.join(directory, "anomaly.pkl"))
            with self.assertRaises(ValueError):
                anomaly.train(np.zeros((9, 2)), ["a", "b"])
            anomaly.model = object()
            with self.assertRaises(RuntimeError):
                anomaly.save()
            self.assertFalse(os.path.exists(anomaly.model_path))


if __name__ == "__main__":
    unittest.main()
