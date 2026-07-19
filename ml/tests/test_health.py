import os
import unittest
from unittest.mock import MagicMock, patch

from src.health import check_artifacts, check_database, collect_readiness, enabled_predictors


class HealthTest(unittest.TestCase):
    def test_disabled_models_are_excluded_and_all_disabled_is_ready(self):
        pipeline = MagicMock()
        pipeline.predictors = {"consumption": MagicMock(), "anomaly": MagicMock()}
        pipeline.config = {"models": {
            "consumption": {"enabled": False},
            "anomaly": {"enabled": False},
        }}

        self.assertEqual(enabled_predictors(pipeline), [])
        self.assertTrue(check_artifacts(pipeline))

    def test_all_readiness_probes_can_pass(self):
        pipeline = MagicMock()
        logger = MagicMock()
        with patch.dict(os.environ, {"ML_SERVICE_TOKEN": "service-secret"}, clear=True), \
             patch("src.health.check_database", return_value=True), \
             patch("src.health.check_artifacts", return_value=True), \
             patch("src.health.check_model_storage", return_value=True):
            checks = collect_readiness(pipeline, logger)

        self.assertEqual(set(checks.values()), {"ok"})
        logger.exception.assert_not_called()

    def test_database_probe_always_closes_resources(self):
        connection = MagicMock()
        cursor = connection.cursor.return_value
        cursor.fetchone.return_value = (1,)
        loader = MagicMock()
        loader._connect.return_value = connection

        self.assertTrue(check_database(loader))
        cursor.execute.assert_called_once_with("SELECT 1")
        cursor.close.assert_called_once()
        connection.close.assert_called_once()

    def test_missing_auth_and_probe_exception_both_fail_readiness(self):
        pipeline = MagicMock()
        logger = MagicMock()
        with patch.dict(os.environ, {}, clear=True), \
             patch("src.health.check_database", side_effect=RuntimeError("secret-host")), \
             patch("src.health.check_artifacts", return_value=True), \
             patch("src.health.check_model_storage", return_value=True):
            checks = collect_readiness(pipeline, logger)

        self.assertEqual(checks["service_auth"], "failed")
        self.assertEqual(checks["database"], "failed")
        logger.exception.assert_called_once()


if __name__ == "__main__":
    unittest.main()
