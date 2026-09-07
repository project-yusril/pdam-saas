# PDAM ML Prediction Pipeline

Machine learning prediction service for PDAM water utility management.

> **Authoritative current status:** [`../temuan2.md`](../temuan2.md) (canonical six production approval gates; gate #6 = ML calibration). Calibration runbook: [`../docs/ML_CALIBRATION.md`](../docs/ML_CALIBRATION.md). Related: [`../README.md`](../README.md) · [`../PRD.md`](../PRD.md), [`../02_flow.md`](../02_flow.md) · archives [`../task.md`](../task.md), [`../temuan.md`](../temuan.md) · [`../SECURITY_CHECKLIST.md`](../SECURITY_CHECKLIST.md) · [`../backend/DEPLOY.md`](../backend/DEPLOY.md) · [`../HANDOVER.md`](../HANDOVER.md) · tools [`../ops/README.md`](../ops/README.md).

> **Tautan wajib:** [`README.md`](../README.md) · [`temuan2.md`](../temuan2.md) · [`02_flow.md`](../02_flow.md) · [`HANDOVER.md`](../HANDOVER.md) · [`SECURITY_CHECKLIST.md`](../SECURITY_CHECKLIST.md) · [`docs/DOC_MAP.md`](../docs/DOC_MAP.md) · [`docs/COUNTS.json`](../docs/COUNTS.json) · [`ops/README.md`](../ops/README.md) · [`backend/README.md`](../backend/README.md) · [`backend/DEPLOY.md`](../backend/DEPLOY.md) · [`backend/SEED_DATA.md`](../backend/SEED_DATA.md) · [`ml/README.md`](../README.md) · [`docs/ML_CALIBRATION.md`](../docs/ML_CALIBRATION.md) · [`docs/BUSINESS_DECISIONS.md`](../docs/BUSINESS_DECISIONS.md)
> <!-- doc-sync:links -->
<!-- doc-sync:start verifikasi 7 Sept 2026 -->
> **Verifikasi terintegrasi:** 117/117 (117 test backend, 812 assertions) · Vitest 13 · Flutter analyze 0 issue + 46/46 · ML 32 (CI) · 369 method /api/v1 (293 path registry) · 65 migration · 28 seeder · 21 command · 167 tabel statis · 136 model · 2026-09-07. Kanoni angka: [`docs/COUNTS.json`](../docs/COUNTS.json) · status resmi: [`temuan2.md`](../temuan2.md) §13 · peta dokumen: [`docs/DOC_MAP.md`](../docs/DOC_MAP.md) · tooling: [`ops/README.md`](../ops/README.md) · CI: [`.github/workflows/ci.yml`](../.github/workflows/ci.yml) + `nightly-ops.yml`.
<!-- doc-sync:end -->

## Models

| Model | Type | Description |
|-------|------|-------------|
| `consumption` | XGBoost Regressor | Predicts next-month water consumption per customer |
| `anomaly` | Isolation Forest | Detects anomalous meter readings (ML + rule-based) |
| `churn` | XGBoost Classifier | Predicts customer churn/disconnection risk |
| `meter_failure` | XGBoost Classifier | Predicts meter hardware failure probability |

## Setup

### 1. Install dependencies

Python 3.11 is required (supported runtime: `>=3.11,<3.12`).

```bash
cd ml
pip install -r requirements.txt
```

### 2. Configure database

Copy `.env.example` to `.env` and set your MySQL credentials:

```bash
cp .env.example .env
```

Edit `.env`:
```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=root
DB_PASSWORD=your_password
DB_DATABASE=pdam_saas
ML_SERVICE_TOKEN=replace-with-a-long-random-secret
```

`DB_DATABASE` harus sama dengan database backend. Default source sudah diselaraskan ke `pdam_saas`,
tetapi nilai tetap harus diatur eksplisit pada deployment yang memakai nama database berbeda.

### 3. Adjust model hyperparams (optional)

Edit `config.yaml` to tune model parameters.

## CLI Usage

### Train models

```bash
# Train a specific model
python -m src.pipeline train --org 1 --model consumption

# Train all models for an organization
python -m src.pipeline train-all --org 1
```

### Generate predictions

```bash
# Predict for a specific period
python -m src.pipeline predict --org 1 --period 2026-07

# Predict all models
python -m src.pipeline predict-all --org 1 --period 2026-07
```

### Train all models (script)

```bash
python scripts/train_all.py --org 1
python scripts/train_all.py --org 1 --models consumption anomaly
```

### Evaluate models (with visualizations)

```bash
python scripts/evaluate.py --org 1
```

Plots saved to `output/` directory:
- `consumption_evaluation.png`
- `anomaly_evaluation.png`
- `churn_evaluation.png`
- `meter_failure_evaluation.png`

## API Service

### Start API server

```bash
python -m src.api
```

Server runs on `http://0.0.0.0:8100` by default.

Service ini untuk jaringan internal. Semua endpoint selain `/health` memerlukan header
`X-Service-Token`; endpoint tenant juga memverifikasi entitlement modul `AI` aktif. Token kosong
fail-closed dan tidak boleh digunakan dari client web/mobile langsung.

### Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/health` | Alias liveness; tidak memeriksa dependency |
| GET | `/health/live` | Liveness process/event loop |
| GET | `/health/ready` | Readiness token, DB, artifact enabled, dan model storage |
| POST | `/train` | Trigger model training; token + AI entitlement wajib |
| POST | `/predict` | Generate predictions; token + AI entitlement wajib |
| GET | `/models` | List model files/metrics; service token wajib |
| GET | `/predictions/{pdam_org_id}` | Get predictions; token + AI entitlement wajib |
| GET | `/predictions/{pdam_org_id}/{period}` | Get predictions by period; token + AI entitlement wajib |

### API Examples

```bash
# Train consumption model
curl -X POST http://localhost:8100/train \
  -H "X-Service-Token: $ML_SERVICE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"pdam_org_id": 1, "models": ["consumption"]}'

# Generate predictions for July 2026
curl -X POST http://localhost:8100/predict \
  -H "X-Service-Token: $ML_SERVICE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"pdam_org_id": 1, "period": "2026-07"}'

# Get predictions
curl -H "X-Service-Token: $ML_SERVICE_TOKEN" \
  http://localhost:8100/predictions/1/2026-07
```

## Verification Status (gate §13 #6: [`../docs/ML_CALIBRATION.md`](../docs/ML_CALIBRATION.md))

```bash
python -m compileall -q src scripts
python -m unittest discover -s tests -v
```

Python 3.11 verification passes compile and **32 tests** (CI; + `test_calibration.py` gate §13 #6 & contract `test_telemetry_simulator`) (25 legacy + `test_calibration.py` gerbang produksi 4
test di job CI `ml`; runner Win-ARM64 lokal tanpa wheel xgboost → cukup `compileall`). The ML contract rejects
dummy training, keeps a consistent 5-tuple, persists/aligned feature order, handles enabled/unknown/disabled
models explicitly, and returns a nonzero CLI status on failure. `CsvDataLoader` (ml/src/csv_dataset.py) memungkinkan
kalibrasi `production_calibrated=true` dari CSV yang diekspor via `php artisan pdam:ml-export-training-data`
tanpa credential DB di pipeline.

### Deterministic validation artifacts

```bash
python scripts/generate_validation_artifacts.py
```

The generator creates four local Git-ignored artifacts under `models/`, manifest/checksum sidecars, and
`fixture_validation_summary.json`. The fixture is deterministic: 40 customers x 12 periods, seed 42.
These artifacts are labeled `artifact_kind=fixture_validation` and `production_calibrated=false`:

| Artifact | SHA-256 |
|---|---|
| `consumption_xgboost.pkl` | `d0a356449ed7c64bfd5139dfef997a22c90a468db16722cb323d3c05ea2c2cc6` |
| anomaly | `ff8581a7d46590b74594455d18fe0534e564ada1de7702de1c4d06c0ed070be9` |
| churn | `fd7660ceb7f0b97ea5ab6d029f506024addd9d6e8f1fee3670445908d60e9667` |
| meter failure | `92f4de90db2d18f39e87001e10f313301aa1429a492a7d79228680b8d9ed77b6` |

Writes are atomic. Manifests include schema, checksum, feature metadata, and provenance; loading validates
the contract before pickle deserialization and runs a finite-value smoke check. `check_artifacts` passes
for the generated set. Binary artifacts must not be committed: generate and publish artifact+manifest+
checksum through a trusted deployment pipeline. Production-calibrated models still require representative 1-2 year historical data, explicit acceptance
thresholds, and trusted publication — all enforced by `scripts/calibrate_production.py` (dataset representativeness
>=12 months + min rows/customers, per-model `max_/min_` thresholds, candidate → `--promote`). See runbook
`../docs/ML_CALIBRATION.md`. This is gate #6 of the six canonical production approval gates in `../temuan2.md`.

## Docker

### Build and run API

```bash
docker compose -f docker/docker-compose.ml.yml up -d ml-api
```

### Run training job

```bash
docker compose -f docker/docker-compose.ml.yml --profile scheduler run ml-scheduler
```

## Database

Predictions are written to the `ml_predictions` table. Each record includes:
- `predicted_value` — the model's prediction
- `confidence_min` / `confidence_max` — confidence interval bounds
- `actual_value` — ground truth (populated retroactively for evaluation)
- `features` — JSON with feature values used for the prediction
- `status` — `predicted` | `actualized` | `archived`

## Project Structure

```
ml/
  config.yaml              # DB, model, API configuration
  requirements.txt         # Python dependencies
  .env                     # DB credentials (git-ignored)
  .env.example
  models/                  # Git-ignored trained/fixture-validation artifacts + manifests
  output/                  # Evaluation plots
  src/
    data_loader.py         # MySQL database connector
    preprocessing.py       # Feature engineering, scaling, encoding
    pipeline.py            # Training & prediction orchestrator
    api.py                 # FastAPI service
    models/
      consumption_predictor.py
      anomaly_detector.py
      churn_predictor.py
      meter_failure_predictor.py
  scripts/
    train_all.py           # Train all models script
    evaluate.py            # Evaluation with visualization
    generate_validation_artifacts.py # Deterministic fixture artifact generator
  docker/
    Dockerfile
    docker-compose.ml.yml
```
