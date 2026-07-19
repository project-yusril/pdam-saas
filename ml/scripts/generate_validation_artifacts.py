import argparse
import json
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from src.validation_artifacts import generate_validation_artifacts


def main(argv=None):
    parser = argparse.ArgumentParser(description="Generate fixture-validation model artifacts")
    parser.add_argument("--config", default=None, help="Config file path")
    parser.add_argument("--output-dir", default=None, help="Override the configured model directory")
    parser.add_argument("--summary", default=None, help="Override the summary JSON path")
    args = parser.parse_args(argv)
    summary = generate_validation_artifacts(args.config, args.output_dir, args.summary)
    print(json.dumps(summary, indent=2, sort_keys=True))
    return summary


if __name__ == "__main__":
    main()
