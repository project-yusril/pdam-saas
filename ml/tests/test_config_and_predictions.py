import os
import sys
import types
import unittest
from unittest.mock import MagicMock, patch

from src.db_config import resolve_database_config

mysql = types.ModuleType("mysql")
mysql.connector = types.ModuleType("mysql.connector")
mysql.connector.connect = MagicMock()
sys.modules.setdefault("mysql", mysql)
sys.modules.setdefault("mysql.connector", mysql.connector)
try:
    import pandas  # noqa: F401
except ImportError:
    sys.modules.setdefault("pandas", types.ModuleType("pandas"))

from src.data_loader import DataLoader


class ConfigAndPredictionsTest(unittest.TestCase):
    def test_environment_overrides_yaml_database_config(self):
        environment = {
            "DB_HOST": "db.internal",
            "DB_PORT": "3307",
            "DB_USERNAME": "service-user",
            "DB_PASSWORD": "",
            "DB_DATABASE": "tenant_data",
        }
        resolved = resolve_database_config(
            {
                "host": "yaml-host",
                "port": 3306,
                "user": "yaml-user",
                "password": "yaml-password",
                "database": "pdam_saas",
            },
            environment,
        )

        self.assertEqual(resolved["host"], "db.internal")
        self.assertEqual(int(resolved["port"]), 3307)
        self.assertEqual(resolved["user"], "service-user")
        self.assertEqual(resolved["password"], "")
        self.assertEqual(resolved["database"], "tenant_data")

    def test_data_loader_defaults_to_backend_database(self):
        with patch.dict(os.environ, {}, clear=True):
            loader = DataLoader()
        self.assertEqual(loader.database, "pdam_saas")

    def test_prediction_type_is_bound_and_limit_is_applied_in_database(self):
        loader = DataLoader()
        loader._query = MagicMock(return_value=[])
        malicious = "anomaly' OR 1=1 --"

        loader.get_ml_predictions(
            7,
            model_name="anomaly_model",
            period="2026-07",
            prediction_type=malicious,
            limit=25,
        )

        sql, params = loader._query.call_args.args
        self.assertIn("prediction_type = %s", sql)
        self.assertIn("LIMIT %s", sql)
        self.assertNotIn(malicious, sql)
        self.assertEqual(params, (7, "anomaly_model", malicious, "2026-07", 25))


if __name__ == "__main__":
    unittest.main()
