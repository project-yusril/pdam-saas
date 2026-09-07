#!/usr/bin/env python
"""Gate §13 #6 — produksi artifact production_calibrated dari dataset export.

Langkah penuh (lihat docs/ML_CALIBRATION.md):
  1. Di server produksi: php artisan pdam:ml-export-training-data --org=1 --months=24 --out=ml-dataset
  2. salin folder ml-dataset ke mesin pipeline tepercaya (read-only)
  3. python scripts/calibrate_production.py --org 1 --export-dir ml-dataset [--promote]

--promote MENIMPA ml/models/ (artefak ter-publish lewat pipeline tepercaya
+ git history = bukti audit). Tanpa flag, hasil hanya di ml/models_candidate/.
"""

import argparse
import json
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from src.calibration import CalibrationError, run_calibration  # noqa: E402


def main():
    parser = argparse.ArgumentParser(description="Production ML calibration gate")
    parser.add_argument("--org", type=int, required=True)
    parser.add_argument("--export-dir", required=True)
    parser.add_argument("--config", default=None)
    parser.add_argument("--promote", action="store_true",
                        help="salin kandidat lolos ke models/ (produksi nyata)")
    args = parser.parse_args()

    try:
        summary = run_calibration(args.export_dir, args.org, config_path=args.config, promote=args.promote)
    except CalibrationError as exc:
        print(json.dumps(exc.report, indent=2, default=str))
        print(f"CALIBRATION DITOLAK: {exc}", file=sys.stderr)
        raise SystemExit(3)

    print(json.dumps(summary, indent=2, default=str))
    if args.promote:
        print("PROMOTED: artefak production_calibrated=true aktif di models/")
    else:
        print("DRY-RUN lolos — jalankan ulang dengan --promote untuk publish.")


if __name__ == "__main__":
    main()
