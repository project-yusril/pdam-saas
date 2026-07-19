import hashlib
import json
import os
import pickle
import platform
import re
import tempfile
from datetime import datetime, timezone


ARTIFACT_SCHEMA_VERSION = 1


class ArtifactContractError(RuntimeError):
    pass


def validate_training_data(X, y=None):
    import numpy as np

    values = np.asarray(X, dtype=float)
    if values.ndim != 2 or values.shape[1] == 0:
        raise ValueError("Training features must be a non-empty two-dimensional array")
    if not np.isfinite(values).all():
        raise ValueError("Training features must contain only finite values")
    if y is not None:
        targets = np.asarray(y, dtype=float)
        if targets.ndim != 1 or len(targets) != len(values):
            raise ValueError("Training targets must be one-dimensional and match feature rows")
        if not np.isfinite(targets).all():
            raise ValueError("Training targets must contain only finite values")


def manifest_path(model_path):
    return f"{model_path}.manifest.json"


def _validate_features(feature_names):
    if not isinstance(feature_names, list) or not feature_names:
        raise ArtifactContractError("Artifact feature metadata must be a non-empty list")
    if any(not isinstance(name, str) or not name for name in feature_names):
        raise ArtifactContractError("Artifact feature names must be non-empty strings")
    if len(set(feature_names)) != len(feature_names):
        raise ArtifactContractError("Artifact feature names must be unique")


def _atomic_write(path, content, binary=False):
    directory = os.path.dirname(os.path.abspath(path))
    os.makedirs(directory, exist_ok=True)
    mode = "wb" if binary else "w"
    kwargs = {} if binary else {"encoding": "utf-8"}
    fd, temporary_path = tempfile.mkstemp(prefix=".artifact-", dir=directory)
    try:
        with os.fdopen(fd, mode, **kwargs) as output:
            output.write(content)
            output.flush()
            os.fsync(output.fileno())
        os.replace(temporary_path, path)
    finally:
        if os.path.exists(temporary_path):
            os.unlink(temporary_path)


def save_artifact(model_path, model_type, feature_names, payload):
    if payload.get("model") is None:
        raise ArtifactContractError("Cannot save an untrained model")
    _validate_features(feature_names)

    contract = {
        "schema_version": ARTIFACT_SCHEMA_VERSION,
        "model_type": model_type,
        "feature_names": list(feature_names),
    }
    artifact_payload = dict(payload)
    artifact_payload["artifact_contract"] = contract
    serialized = pickle.dumps(artifact_payload, protocol=pickle.HIGHEST_PROTOCOL)
    manifest = {
        **contract,
        "artifact_format": "python-pickle",
        "checksum": {
            "algorithm": "sha256",
            "value": hashlib.sha256(serialized).hexdigest(),
        },
        "size_bytes": len(serialized),
        "provenance": {
            "created_at": datetime.now(timezone.utc).isoformat(),
            "python": platform.python_version(),
        },
    }

    _atomic_write(model_path, serialized, binary=True)
    try:
        _atomic_write(
            manifest_path(model_path),
            json.dumps(manifest, indent=2, sort_keys=True) + "\n",
        )
    except Exception:
        os.unlink(model_path)
        raise
    return manifest


def load_artifact(model_path, expected_model_type):
    sidecar_path = manifest_path(model_path)
    if not os.path.isfile(model_path):
        raise FileNotFoundError(f"Model not found at {model_path}")
    if not os.path.isfile(sidecar_path):
        raise ArtifactContractError(f"Artifact manifest not found at {sidecar_path}")

    try:
        with open(sidecar_path, "r", encoding="utf-8") as manifest_file:
            manifest = json.load(manifest_file)
    except (OSError, ValueError) as exc:
        raise ArtifactContractError("Artifact manifest is unreadable") from exc

    if not isinstance(manifest, dict):
        raise ArtifactContractError("Artifact manifest must be an object")
    if manifest.get("schema_version") != ARTIFACT_SCHEMA_VERSION:
        raise ArtifactContractError("Unsupported artifact schema version")
    if manifest.get("model_type") != expected_model_type:
        raise ArtifactContractError("Artifact model type does not match predictor")
    if manifest.get("artifact_format") != "python-pickle":
        raise ArtifactContractError("Unsupported artifact format")
    feature_names = manifest.get("feature_names")
    _validate_features(feature_names)
    checksum = manifest.get("checksum")
    if not isinstance(checksum, dict) or checksum.get("algorithm") != "sha256":
        raise ArtifactContractError("Artifact checksum metadata is invalid")
    if not isinstance(checksum.get("value"), str) or not re.fullmatch(r"[0-9a-f]{64}", checksum["value"]):
        raise ArtifactContractError("Artifact checksum value is invalid")
    provenance = manifest.get("provenance")
    if not isinstance(provenance, dict) or not all(
        isinstance(provenance.get(key), str) and provenance[key]
        for key in ("created_at", "python")
    ):
        raise ArtifactContractError("Artifact provenance metadata is incomplete")

    with open(model_path, "rb") as artifact_file:
        serialized = artifact_file.read()
    if manifest.get("size_bytes") != len(serialized):
        raise ArtifactContractError("Artifact size does not match manifest")
    if hashlib.sha256(serialized).hexdigest() != checksum.get("value"):
        raise ArtifactContractError("Artifact checksum verification failed")

    try:
        payload = pickle.loads(serialized)
    except Exception as exc:
        raise ArtifactContractError("Artifact payload is unreadable") from exc
    if not isinstance(payload, dict) or payload.get("model") is None:
        raise ArtifactContractError("Artifact payload is incomplete")
    if payload.get("artifact_contract") != {
        "schema_version": ARTIFACT_SCHEMA_VERSION,
        "model_type": expected_model_type,
        "feature_names": feature_names,
    }:
        raise ArtifactContractError("Artifact payload contract does not match manifest")
    model_feature_count = getattr(payload["model"], "n_features_in_", len(feature_names))
    if model_feature_count != len(feature_names):
        raise ArtifactContractError("Artifact model feature count does not match manifest")
    return payload, manifest
