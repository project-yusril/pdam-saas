import os
import sys
import argparse

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from src.pipeline import Pipeline


def main():
    parser = argparse.ArgumentParser(description="Train all ML models for a PDAM organization")
    parser.add_argument("--org", type=int, required=True, help="PDAM organization ID")
    parser.add_argument("--config", type=str, default=None, help="Config file path")
    parser.add_argument("--models", type=str, nargs="+", default=None,
                       help="Specific models to train (consumption anomaly churn meter_failure)")
    args = parser.parse_args()

    pipeline = Pipeline(config_path=args.config)
    results = pipeline.run_training(args.org, models=args.models)

    print("\nTraining Results:")
    print("=" * 60)
    for model_name, metrics in results.items():
        print(f"\n[{model_name}]")
        if isinstance(metrics, dict):
            for k, v in metrics.items():
                if isinstance(v, float):
                    print(f"  {k}: {v:.4f}")
                else:
                    print(f"  {k}: {v}")
    print("\nDone.")
    if any(isinstance(result, dict) and "error" in result for result in results.values()):
        raise SystemExit(1)


if __name__ == "__main__":
    main()
