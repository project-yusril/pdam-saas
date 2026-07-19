import os
import sys
import argparse
import logging
import yaml
from datetime import datetime

import mysql.connector
from dotenv import load_dotenv

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from src.data_loader import DataLoader
from src.db_config import resolve_database_config
from src.preprocessing import Preprocessor
from src.models.consumption_predictor import ConsumptionPredictor
from src.models.anomaly_detector import AnomalyDetector
from src.models.churn_predictor import ChurnPredictor
from src.models.meter_failure_predictor import MeterFailurePredictor

load_dotenv()
logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger(__name__)


def load_config(config_path=None):
    if config_path is None:
        config_path = os.path.join(
            os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
            "config.yaml",
        )
    with open(config_path, "r", encoding="utf-8") as f:
        return yaml.safe_load(f) or {}


class Pipeline:
    def __init__(self, config_path=None):
        base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        self.config_path = config_path or os.path.join(base_dir, "config.yaml")
        self.config = load_config(self.config_path)
        self.base_dir = base_dir

        db_cfg = self.config.get("database", {})
        self.data_loader = DataLoader(**resolve_database_config(db_cfg))
        self.preprocessor = Preprocessor()

        model_base = os.path.join(base_dir, self.config.get("models", {}).get("base_path", "models/"))

        consumption_cfg = self.config.get("models", {}).get("consumption", {})
        self.consumption_predictor = ConsumptionPredictor(
            model_path=os.path.join(model_base, "consumption_xgboost.pkl"),
            hyperparams=consumption_cfg.get("hyperparams"),
        )

        anomaly_cfg = self.config.get("models", {}).get("anomaly", {})
        self.anomaly_detector = AnomalyDetector(
            model_path=os.path.join(model_base, "anomaly_isolation_forest.pkl"),
            hyperparams=anomaly_cfg.get("hyperparams"),
        )

        churn_cfg = self.config.get("models", {}).get("churn", {})
        self.churn_predictor = ChurnPredictor(
            model_path=os.path.join(model_base, "churn_xgboost.pkl"),
            hyperparams=churn_cfg.get("hyperparams"),
        )

        meter_cfg = self.config.get("models", {}).get("meter_failure", {})
        self.meter_predictor = MeterFailurePredictor(
            model_path=os.path.join(model_base, "meter_failure_xgboost.pkl"),
            hyperparams=meter_cfg.get("hyperparams"),
        )

        self.predictors = {
            "consumption": self.consumption_predictor,
            "anomaly": self.anomaly_detector,
            "churn": self.churn_predictor,
            "meter_failure": self.meter_predictor,
        }

    def enabled_model_names(self):
        model_config = self.config.get("models", {})
        return [
            name for name in self.predictors
            if model_config.get(name, {}).get("enabled", True)
        ]

    def _resolve_models(self, models):
        requested = self.enabled_model_names() if models is None else list(models)
        unknown = sorted(set(requested) - set(self.predictors))
        if unknown:
            raise ValueError(f"Unknown model(s): {', '.join(unknown)}")
        disabled = [name for name in requested if name not in self.enabled_model_names()]
        if disabled:
            raise ValueError(f"Disabled model(s): {', '.join(disabled)}")
        return requested

    def run_training(self, pdam_org_id, models=None):
        models = self._resolve_models(models)

        results = {}

        if "consumption" in models:
            logger.info("Training consumption predictor...")
            try:
                billing = self.data_loader.get_billing_history(pdam_org_id, months=24)
                logger.info(f"  Loaded {len(billing)} billing records")
                X, y, cust_ids = self.preprocessor.prepare_consumption_data(billing)
                if X is not None and y is not None and len(X) >= 10:
                    metrics = self.consumption_predictor.train(X, y)
                    self.consumption_predictor.feature_names = list(self.preprocessor.feature_columns)
                    self.consumption_predictor.preprocessing = {
                        "categorical_classes": self.preprocessor.categorical_metadata(["tariff_group", "status"]),
                        "missing_value": 0,
                    }
                    self.consumption_predictor.save()
                    results["consumption"] = metrics
                    logger.info(f"  Consumption model trained: RMSE={metrics.get('val_rmse', 'N/A'):.3f}" if metrics.get('val_rmse') else "  Trained")
                else:
                    logger.warning("  Not enough data for consumption model")
                    results["consumption"] = {"error": "insufficient_data"}
            except Exception as e:
                logger.error(f"  Consumption training failed: {e}")
                results["consumption"] = {"error": str(e)}

        if "anomaly" in models:
            logger.info("Training anomaly detector...")
            try:
                readings = self.data_loader.get_meter_readings(pdam_org_id, months=24)
                logger.info(f"  Loaded {len(readings)} meter readings")
                features_df, X, feature_cols, cust_ids, periods = self.anomaly_detector._build_features(readings)
                if X.shape[0] >= 10:
                    metrics = self.anomaly_detector.train(X, feature_cols)
                    self.anomaly_detector.save()
                    results["anomaly"] = metrics
                    logger.info(f"  Anomaly model trained: {metrics.get('anomaly_rate', 'N/A')}")
                else:
                    logger.warning("  Not enough data for anomaly model")
                    results["anomaly"] = {"error": "insufficient_data"}
            except Exception as e:
                logger.error(f"  Anomaly training failed: {e}")
                results["anomaly"] = {"error": str(e)}

        if "churn" in models:
            logger.info("Training churn predictor...")
            try:
                billing = self.data_loader.get_billing_history(pdam_org_id, months=24)
                payment = self.data_loader.get_payment_history(pdam_org_id, months=24)
                customers = self.data_loader.get_customer_features(pdam_org_id)
                churn_labels = self.data_loader.get_customer_churn_labels(pdam_org_id, window_days=180)
                logger.info(f"  Billing: {len(billing)}, Payments: {len(payment)}, Churn labels: {len(churn_labels)}")

                features_df = self.preprocessor.build_churn_features(billing, payment, customers, churn_labels)
                if not features_df.empty and "churned" in features_df.columns:
                    categorical_cols = ["tariff_group", "meter_condition", "tamper_status"]
                    features_df = self.preprocessor.encode_categorical(features_df, categorical_cols)
                    features_df = self.preprocessor.fill_missing(features_df)

                    exclude = ["customer_id", "churned"]
                    feature_cols = [c for c in features_df.columns if c not in exclude]
                    feature_cols = [c for c in feature_cols if c in features_df.select_dtypes(include=["number"]).columns]

                    X = features_df[feature_cols].fillna(0).values
                    y = features_df["churned"].values

                    class_counts = {value: int((y == value).sum()) for value in set(y)}
                    if len(X) >= 10 and len(class_counts) == 2 and min(class_counts.values()) >= 2:
                        metrics = self.churn_predictor.train(X, y)
                        self.churn_predictor.feature_cols = feature_cols
                        self.churn_predictor.preprocessing = {
                            "categorical_classes": self.preprocessor.categorical_metadata(categorical_cols),
                            "missing_value": 0,
                        }
                        self.churn_predictor.save()
                        results["churn"] = metrics
                        logger.info(f"  Churn model trained: ROC-AUC={metrics.get('roc_auc', 'N/A')}")
                    else:
                        logger.warning("  Not enough churn samples")
                        results["churn"] = {"error": "insufficient_churn_samples"}
                else:
                    logger.warning("  No churn features generated")
                    results["churn"] = {"error": "no_features"}
            except Exception as e:
                logger.error(f"  Churn training failed: {e}")
                results["churn"] = {"error": str(e)}

        if "meter_failure" in models:
            logger.info("Training meter failure predictor...")
            try:
                meters = self.data_loader.get_meters_for_failure_prediction(pdam_org_id)
                anomalies = self.data_loader.get_anomaly_history(pdam_org_id, months=24)
                logger.info(f"  Loaded {len(meters)} meters, {len(anomalies)} anomalies")

                features_df, feature_cols = self.meter_predictor._build_features(meters, anomalies)
                if not features_df.empty and "failure_label" in features_df.columns:
                    X = features_df[feature_cols].fillna(0).values
                    y = features_df["failure_label"].values

                    logger.info(f"  Features shape: {X.shape}, Failures: {y.sum()}")
                    class_counts = {value: int((y == value).sum()) for value in set(y)}
                    if len(X) >= 10 and len(class_counts) == 2 and min(class_counts.values()) >= 2:
                        metrics = self.meter_predictor.train(X, y)
                        self.meter_predictor.feature_cols = feature_cols
                        self.meter_predictor.preprocessing = {"missing_value": 0}
                        self.meter_predictor.save()
                        results["meter_failure"] = metrics
                        logger.info(f"  Meter failure model trained: ROC-AUC={metrics.get('roc_auc', 'N/A')}")
                    else:
                        logger.warning("  Not enough labeled failure samples")
                        results["meter_failure"] = {"error": "insufficient_failure_samples"}
                else:
                    logger.warning("  No meter features generated")
                    results["meter_failure"] = {"error": "no_meters"}
            except Exception as e:
                logger.error(f"  Meter failure training failed: {e}")
                results["meter_failure"] = {"error": str(e)}

        return results

    def run_prediction(self, pdam_org_id, period, models=None):
        models = self._resolve_models(models)

        all_records = []
        failures = {}

        if "consumption" in models:
            logger.info("Generating consumption predictions...")
            try:
                self.consumption_predictor.load()
                records = self.consumption_predictor.generate_predictions(
                    self.data_loader, pdam_org_id, period
                )
                logger.info(f"  Generated {len(records)} consumption predictions")
                all_records.extend(records)
            except FileNotFoundError as e:
                logger.error("  Consumption model not found")
                failures["consumption"] = str(e)
            except Exception as e:
                logger.error(f"  Consumption prediction failed: {e}")
                failures["consumption"] = str(e)

        if "anomaly" in models:
            logger.info("Generating anomaly predictions...")
            try:
                self.anomaly_detector.load()
                records = self.anomaly_detector.generate_predictions(
                    self.data_loader, pdam_org_id, period
                )
                logger.info(f"  Generated {len(records)} anomaly predictions")
                all_records.extend(records)
            except FileNotFoundError as e:
                logger.error("  Anomaly model not found")
                failures["anomaly"] = str(e)
            except Exception as e:
                logger.error(f"  Anomaly prediction failed: {e}")
                failures["anomaly"] = str(e)

        if "churn" in models:
            logger.info("Generating churn predictions...")
            try:
                self.churn_predictor.load()
                records = self.churn_predictor.generate_predictions(
                    self.data_loader, self.preprocessor, pdam_org_id, period
                )
                logger.info(f"  Generated {len(records)} churn predictions")
                all_records.extend(records)
            except FileNotFoundError as e:
                logger.error("  Churn model not found")
                failures["churn"] = str(e)
            except Exception as e:
                logger.error(f"  Churn prediction failed: {e}")
                failures["churn"] = str(e)

        if "meter_failure" in models:
            logger.info("Generating meter failure predictions...")
            try:
                self.meter_predictor.load()
                records = self.meter_predictor.generate_predictions(
                    self.data_loader, pdam_org_id, period
                )
                logger.info(f"  Generated {len(records)} meter failure predictions")
                all_records.extend(records)
            except FileNotFoundError as e:
                logger.error("  Meter failure model not found")
                failures["meter_failure"] = str(e)
            except Exception as e:
                logger.error(f"  Meter failure prediction failed: {e}")
                failures["meter_failure"] = str(e)

        if failures:
            detail = "; ".join(f"{name}: {error}" for name, error in failures.items())
            raise RuntimeError(f"Prediction failed for requested model(s): {detail}")

        if all_records:
            self._write_to_db(all_records)
            logger.info(f"Wrote {len(all_records)} total prediction records to ml_predictions")
        else:
            logger.warning("No predictions generated")

        return all_records

    def _write_to_db(self, records):
        conn = self.data_loader._connect()
        cursor = conn.cursor()
        try:
            sql = """
                INSERT INTO ml_predictions
                    (pdam_org_id, model_name, prediction_type, entity_type, entity_id,
                     period, predicted_value, confidence_min, confidence_max,
                     actual_value, features, status, created_at, updated_at)
                VALUES
                    (%(pdam_org_id)s, %(model_name)s, %(prediction_type)s, %(entity_type)s, %(entity_id)s,
                     %(period)s, %(predicted_value)s, %(confidence_min)s, %(confidence_max)s,
                     %(actual_value)s, %(features)s, %(status)s, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    predicted_value = VALUES(predicted_value),
                    confidence_min = VALUES(confidence_min),
                    confidence_max = VALUES(confidence_max),
                    actual_value = VALUES(actual_value),
                    features = VALUES(features),
                    status = VALUES(status),
                    updated_at = NOW()
            """
            import json
            for record in records:
                params = dict(record)
                params["features"] = json.dumps(params.get("features", {}))
                if params.get("actual_value") is None:
                    params["actual_value"] = None
                cursor.execute(sql, params)
            conn.commit()
        except Exception as e:
            logger.error(f"Database write failed: {e}")
            conn.rollback()
            raise
        finally:
            cursor.close()
            conn.close()


def main():
    parser = argparse.ArgumentParser(description="PDAM ML Pipeline")
    subparsers = parser.add_subparsers(dest="command", required=True)

    train_parser = subparsers.add_parser("train", help="Train models")
    train_parser.add_argument("--org", type=int, required=True, help="PDAM organization ID")
    train_parser.add_argument("--model", type=str, default=None, help="Specific model to train")
    train_parser.add_argument("--config", type=str, default=None, help="Config file path")

    predict_parser = subparsers.add_parser("predict", help="Generate predictions")
    predict_parser.add_argument("--org", type=int, required=True, help="PDAM organization ID")
    predict_parser.add_argument("--period", type=str, required=True, help="Period YYYY-MM")
    predict_parser.add_argument("--model", type=str, default=None, help="Specific model for prediction")
    predict_parser.add_argument("--config", type=str, default=None, help="Config file path")

    train_all_parser = subparsers.add_parser("train-all", help="Train all models")
    train_all_parser.add_argument("--org", type=int, required=True, help="PDAM organization ID")
    train_all_parser.add_argument("--config", type=str, default=None, help="Config file path")

    predict_all_parser = subparsers.add_parser("predict-all", help="Predict all models")
    predict_all_parser.add_argument("--org", type=int, required=True, help="PDAM organization ID")
    predict_all_parser.add_argument("--period", type=str, required=True, help="Period YYYY-MM")
    predict_all_parser.add_argument("--config", type=str, default=None, help="Config file path")

    args = parser.parse_args()

    pipeline = Pipeline(config_path=args.config)

    try:
        if args.command == "train":
            models = [args.model] if args.model else None
            results = pipeline.run_training(args.org, models=models)
            logger.info(f"Training results: {results}")

        elif args.command == "predict":
            models = [args.model] if args.model else None
            records = pipeline.run_prediction(args.org, args.period, models=models)
            logger.info(f"Generated {len(records)} predictions")

        elif args.command == "train-all":
            results = pipeline.run_training(args.org)
            logger.info(f"All training results: {results}")

        elif args.command == "predict-all":
            records = pipeline.run_prediction(args.org, args.period)
            logger.info(f"Generated {len(records)} predictions")
            return

        failures = {name: result["error"] for name, result in results.items() if "error" in result}
        if failures:
            raise RuntimeError(f"Model failures: {failures}")
    except (ValueError, RuntimeError) as exc:
        logger.error(str(exc))
        raise SystemExit(1) from exc


if __name__ == "__main__":
    main()
