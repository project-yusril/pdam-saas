import os
import shutil


def check_service_auth():
    return bool(os.getenv("ML_SERVICE_TOKEN", "").strip())


def check_database(data_loader):
    connection = None
    cursor = None
    try:
        connection = data_loader._connect()
        cursor = connection.cursor()
        cursor.execute("SELECT 1")
        return cursor.fetchone() is not None
    finally:
        if cursor is not None:
            cursor.close()
        if connection is not None:
            connection.close()


def enabled_predictors(pipeline):
    model_config = pipeline.config.get("models", {})
    return [
        predictor
        for name, predictor in pipeline.predictors.items()
        if model_config.get(name, {}).get("enabled", True)
    ]


def check_artifacts(pipeline):
    predictors = enabled_predictors(pipeline)
    if not predictors:
        return True
    for predictor in predictors:
        if not os.path.isfile(predictor.model_path):
            return False
        predictor.load()
        if predictor.model is None:
            return False
    return True


def check_model_storage(pipeline, minimum_free_bytes=100 * 1024 * 1024):
    predictors = enabled_predictors(pipeline)
    if not predictors:
        return True
    directories = {os.path.dirname(predictor.model_path) for predictor in predictors}
    for directory in directories:
        if not os.path.isdir(directory) or not os.access(directory, os.R_OK | os.W_OK):
            return False
        if shutil.disk_usage(directory).free < minimum_free_bytes:
            return False
    return True


def collect_readiness(pipeline, logger):
    probes = {
        "service_auth": check_service_auth,
        "database": lambda: check_database(pipeline.data_loader),
        "artifacts": lambda: check_artifacts(pipeline),
        "model_storage": lambda: check_model_storage(pipeline),
    }
    checks = {}
    for name, probe in probes.items():
        try:
            checks[name] = "ok" if probe() else "failed"
        except Exception:
            logger.exception("Readiness check failed: %s", name)
            checks[name] = "failed"
    return checks
