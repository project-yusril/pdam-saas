import os
import sys
import argparse
import numpy as np
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
import seaborn as sns

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from src.data_loader import DataLoader
from src.preprocessing import Preprocessor
from src.models.consumption_predictor import ConsumptionPredictor
from src.models.anomaly_detector import AnomalyDetector
from src.models.churn_predictor import ChurnPredictor
from src.models.meter_failure_predictor import MeterFailurePredictor

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUTPUT_DIR = os.path.join(BASE_DIR, "output")


def ensure_output_dir():
    os.makedirs(OUTPUT_DIR, exist_ok=True)


def evaluate_consumption(pipeline, pdam_org_id):
    print("\n=== Evaluating Consumption Model ===")
    billing = pipeline.data_loader.get_billing_history(pdam_org_id, months=24)
    if billing.empty:
        print("  No billing data")
        return
    X, y, cust_ids = pipeline.preprocessor.prepare_consumption_data(billing)
    if X is None or y is None:
        print("  Not enough data")
        return

    model = pipeline.consumption_predictor
    if model.model is None:
        model.train(X, y)
        model.feature_names = list(pipeline.preprocessor.feature_columns)
        model.preprocessing = {
            "categorical_classes": pipeline.preprocessor.categorical_metadata(["tariff_group", "status"]),
            "missing_value": 0,
        }
        model.save()

    y_pred = model.model.predict(X)
    residuals = y - y_pred

    fig, axes = plt.subplots(2, 2, figsize=(12, 10))

    axes[0, 0].scatter(y, y_pred, alpha=0.5, s=8)
    axes[0, 0].plot([y.min(), y.max()], [y.min(), y.max()], "r--", lw=1)
    axes[0, 0].set_xlabel("Actual Consumption (m3)")
    axes[0, 0].set_ylabel("Predicted Consumption (m3)")
    axes[0, 0].set_title("Actual vs Predicted")

    axes[0, 1].hist(residuals, bins=40, edgecolor="black", alpha=0.7)
    axes[0, 1].axvline(0, color="r", linestyle="--")
    axes[0, 1].set_xlabel("Residual")
    axes[0, 1].set_ylabel("Frequency")
    axes[0, 1].set_title("Residual Distribution")

    importances = model.model.feature_importances_
    indices = np.argsort(importances)[-15:]
    axes[1, 0].barh(range(len(indices)), importances[indices])
    axes[1, 0].set_xlabel("Importance")
    axes[1, 0].set_title("Top Feature Importances")

    metrics = model.metrics
    axes[1, 1].axis("off")
    text = f"Train RMSE: {metrics.get('train_rmse', 'N/A'):.4f}\n"
    text += f"Train MAE:  {metrics.get('train_mae', 'N/A'):.4f}\n"
    text += f"Train R2:   {metrics.get('train_r2', 'N/A'):.4f}\n"
    text += f"Val RMSE:   {metrics.get('val_rmse', 'N/A'):.4f}\n"
    text += f"Val MAE:    {metrics.get('val_mae', 'N/A'):.4f}\n"
    text += f"Val R2:     {metrics.get('val_r2', 'N/A'):.4f}\n"
    text += f"Samples:    {metrics.get('n_samples', 'N/A')}"
    axes[1, 1].text(0.1, 0.5, text, fontsize=12, fontfamily="monospace", verticalalignment="center")
    axes[1, 1].set_title("Metrics")

    plt.tight_layout()
    path = os.path.join(OUTPUT_DIR, "consumption_evaluation.png")
    plt.savefig(path, dpi=150)
    plt.close()
    print(f"  Saved: {path}")


def evaluate_anomaly(pipeline, pdam_org_id):
    print("\n=== Evaluating Anomaly Model ===")
    readings = pipeline.data_loader.get_meter_readings(pdam_org_id, months=24)
    if readings.empty:
        print("  No meter readings")
        return

    model = pipeline.anomaly_detector
    features_df, X, feature_cols, cust_ids, periods = model._build_features(readings)
    if X.shape[0] < 10:
        print("  Not enough data")
        return

    if model.model is None:
        model.train(X, feature_cols)
        model.save()

    labels, scores = model.predict(X)
    anomaly_mask = labels == -1

    fig, axes = plt.subplots(1, 3, figsize=(16, 5))

    axes[0].hist(scores, bins=40, edgecolor="black", alpha=0.7)
    axes[0].axvline(model.thresholds.get("decision_mean", 0), color="r", linestyle="--", label="Mean")
    axes[0].set_xlabel("Anomaly Score")
    axes[0].set_ylabel("Frequency")
    axes[0].set_title("Anomaly Score Distribution")
    axes[0].legend()

    cons_col = "consumption"
    consumption_values = features_df[cons_col].values if cons_col in features_df.columns else np.zeros(X.shape[0])

    normal_mask = ~anomaly_mask
    axes[1].scatter(
        consumption_values[normal_mask],
        scores[normal_mask],
        c="blue", alpha=0.5, s=8, label="Normal"
    )
    axes[1].scatter(
        consumption_values[anomaly_mask],
        scores[anomaly_mask],
        c="red", alpha=0.8, s=12, marker="x", label="Anomaly"
    )
    axes[1].set_xlabel("Consumption")
    axes[1].set_ylabel("Anomaly Score")
    axes[1].set_title("Anomalies vs Consumption")
    axes[1].legend()

    anomaly_counts = labels.tolist()
    normal_count = anomaly_counts.count(1)
    anomaly_count = anomaly_counts.count(-1)
    axes[2].bar(["Normal", "Anomaly"], [normal_count, anomaly_count], color=["blue", "red"])
    axes[2].set_title(f"Detection Results (Total: {len(labels)})")

    plt.tight_layout()
    path = os.path.join(OUTPUT_DIR, "anomaly_evaluation.png")
    plt.savefig(path, dpi=150)
    plt.close()
    print(f"  Saved: {path}")
    print(f"  Anomalies detected: {anomaly_count} / {len(labels)} ({100 * anomaly_count / len(labels):.1f}%)")


def evaluate_churn(pipeline, pdam_org_id):
    print("\n=== Evaluating Churn Model ===")
    billing = pipeline.data_loader.get_billing_history(pdam_org_id, months=24)
    payment = pipeline.data_loader.get_payment_history(pdam_org_id, months=24)
    customers = pipeline.data_loader.get_customer_features(pdam_org_id)
    churn_labels = pipeline.data_loader.get_customer_churn_labels(pdam_org_id, window_days=180)

    features_df = pipeline.preprocessor.build_churn_features(billing, payment, customers, churn_labels)
    if features_df.empty or "churned" not in features_df.columns:
        print("  Not enough data for churn evaluation")
        return

    categorical_cols = ["tariff_group", "meter_condition", "tamper_status"]
    features_df = pipeline.preprocessor.encode_categorical(features_df, categorical_cols)
    features_df = pipeline.preprocessor.fill_missing(features_df)

    exclude = ["customer_id", "churned"]
    feature_cols = [c for c in features_df.columns if c not in exclude]
    feature_cols = [c for c in feature_cols if c in features_df.select_dtypes(include=["number"]).columns]

    X = features_df[feature_cols].fillna(0).values
    y = features_df["churned"].values

    model = pipeline.churn_predictor
    if model.model is None:
        model.train(X, y)
        model.feature_cols = feature_cols
        model.preprocessing = {
            "categorical_classes": pipeline.preprocessor.categorical_metadata(categorical_cols),
            "missing_value": 0,
        }
        model.save()

    y_prob = model.model.predict_proba(X)[:, 1]

    fig, axes = plt.subplots(1, 3, figsize=(16, 5))

    for label_val, label_name, color in [(0, "Non-Churn", "blue"), (1, "Churn", "red")]:
        mask = y == label_val
        axes[0].hist(y_prob[mask], bins=30, alpha=0.6, label=label_name, color=color, edgecolor="black")
    axes[0].set_xlabel("Churn Probability")
    axes[0].set_ylabel("Frequency")
    axes[0].set_title("Predicted Probability Distribution")
    axes[0].legend()

    top_n = 10
    importances = model.model.feature_importances_
    indices = np.argsort(importances)[-top_n:]
    axes[1].barh(range(len(indices)), importances[indices])
    axes[1].set_yticks(range(len(indices)))
    axes[1].set_yticklabels([feature_cols[i][:20] for i in indices], fontsize=8)
    axes[1].set_title("Top Feature Importances")

    risk_bins = [0, 0.2, 0.4, 0.6, 0.8, 1.0]
    risk_labels = ["Very Low", "Low", "Medium", "High", "Very High"]
    risk_cats = np.digitize(y_prob, risk_bins[1:])
    counts = [np.sum(risk_cats == i) for i in range(len(risk_labels))]
    axes[2].bar(risk_labels, counts, color=["green", "lightgreen", "yellow", "orange", "red"])
    axes[2].set_title("Risk Level Distribution")
    axes[2].set_ylabel("Customer Count")

    plt.tight_layout()
    path = os.path.join(OUTPUT_DIR, "churn_evaluation.png")
    plt.savefig(path, dpi=150)
    plt.close()
    print(f"  Saved: {path}")
    print(f"  Churn: {y.sum()} / {len(y)}, ROC-AUC: {model.metrics.get('roc_auc', 'N/A')}")


def evaluate_meter_failure(pipeline, pdam_org_id):
    print("\n=== Evaluating Meter Failure Model ===")
    meters = pipeline.data_loader.get_meters_for_failure_prediction(pdam_org_id)
    if meters.empty:
        print("  No meters found")
        return

    anomalies = pipeline.data_loader.get_anomaly_history(pdam_org_id, months=24)
    model = pipeline.meter_predictor
    features_df, feature_cols = model._build_features(meters, anomalies)

    if features_df.empty or "failure_label" not in features_df.columns:
        print("  Not enough data")
        return

    X = features_df[feature_cols].fillna(0).values
    y = features_df["failure_label"].values

    class_counts = [int((y == value).sum()) for value in set(y)]
    if len(X) < 10 or len(class_counts) < 2 or min(class_counts) < 2:
        print("  Not enough labeled failure data")
        return

    if model.model is None:
        model.train(X, y)
        model.feature_cols = feature_cols
        model.preprocessing = {"missing_value": 0}
        model.save()

    y_prob = model.model.predict_proba(X)[:, 1]

    fig, axes = plt.subplots(2, 2, figsize=(12, 10))

    for label_val, label_name, color in [(0, "OK", "blue"), (1, "At Risk", "red")]:
        mask = y == label_val
        axes[0, 0].hist(y_prob[mask], bins=30, alpha=0.6, label=label_name, color=color, edgecolor="black")
    axes[0, 0].set_xlabel("Failure Probability")
    axes[0, 0].set_title("Failure Probability Distribution")
    axes[0, 0].legend()

    age = features_df["meter_age_years"].values
    axes[0, 1].scatter(age[y == 0], y_prob[y == 0], c="blue", alpha=0.5, s=8, label="OK")
    axes[0, 1].scatter(age[y == 1], y_prob[y == 1], c="red", alpha=0.7, s=12, marker="x", label="At Risk")
    axes[0, 1].set_xlabel("Meter Age (years)")
    axes[0, 1].set_ylabel("Failure Probability")
    axes[0, 1].set_title("Failure Risk vs Age")
    axes[0, 1].legend()

    importances = model.model.feature_importances_
    indices = np.argsort(importances)[-10:]
    axes[1, 0].barh(range(len(indices)), importances[indices])
    axes[1, 0].set_yticks(range(len(indices)))
    axes[1, 0].set_yticklabels([feature_cols[i][:25] for i in indices], fontsize=8)
    axes[1, 0].set_title("Top Feature Importances")

    axes[1, 1].axis("off")
    text = f"Total Meters:   {len(y)}\n"
    text += f"At Risk:        {y.sum()}\n"
    text += f"Risk Rate:      {100 * y.sum() / len(y):.1f}%\n"
    text += f"ROC-AUC:        {model.metrics.get('roc_auc', 'N/A')}\n"
    text += f"Accuracy:       {model.metrics.get('accuracy', 'N/A')}\n"
    text += f"Precision:      {model.metrics.get('precision', 'N/A')}\n"
    text += f"Recall:         {model.metrics.get('recall', 'N/A')}"
    axes[1, 1].text(0.1, 0.5, text, fontsize=12, fontfamily="monospace", verticalalignment="center")

    plt.tight_layout()
    path = os.path.join(OUTPUT_DIR, "meter_failure_evaluation.png")
    plt.savefig(path, dpi=150)
    plt.close()
    print(f"  Saved: {path}")


def main():
    parser = argparse.ArgumentParser(description="Evaluate ML models and generate visualizations")
    parser.add_argument("--org", type=int, required=True, help="PDAM organization ID")
    parser.add_argument("--config", type=str, default=None, help="Config file path")
    args = parser.parse_args()

    ensure_output_dir()

    from src.pipeline import Pipeline
    pipeline = Pipeline(config_path=args.config)

    evaluate_consumption(pipeline, args.org)
    evaluate_anomaly(pipeline, args.org)
    evaluate_churn(pipeline, args.org)
    evaluate_meter_failure(pipeline, args.org)

    print(f"\nAll evaluation plots saved to: {OUTPUT_DIR}")


if __name__ == "__main__":
    main()
