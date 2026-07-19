import os
import unittest
from unittest.mock import MagicMock

from src.security import (
    AiEntitlementInactive,
    InvalidServiceToken,
    ServiceAuthNotConfigured,
    validate_ai_entitlement,
    validate_service_token,
)


class SecurityTest(unittest.TestCase):
    def setUp(self):
        self.original_token = os.environ.get("ML_SERVICE_TOKEN")
        os.environ["ML_SERVICE_TOKEN"] = "test-service-secret"

    def tearDown(self):
        if self.original_token is None:
            os.environ.pop("ML_SERVICE_TOKEN", None)
        else:
            os.environ["ML_SERVICE_TOKEN"] = self.original_token

    def test_missing_service_token_is_rejected(self):
        with self.assertRaises(InvalidServiceToken):
            validate_service_token(None)

    def test_unconfigured_service_token_fails_closed(self):
        os.environ.pop("ML_SERVICE_TOKEN")
        with self.assertRaises(ServiceAuthNotConfigured):
            validate_service_token("anything")

    def test_inactive_ai_entitlement_is_forbidden(self):
        connection = MagicMock()
        cursor = connection.cursor.return_value
        cursor.fetchone.return_value = None

        with self.assertRaises(AiEntitlementInactive):
            validate_ai_entitlement(
                7,
                "test-service-secret",
                lambda: connection,
            )

        cursor.execute.assert_called_once()
        self.assertEqual(cursor.execute.call_args.args[1], (7,))
        cursor.close.assert_called_once()
        connection.close.assert_called_once()

    def test_active_ai_entitlement_is_allowed(self):
        connection = MagicMock()
        cursor = connection.cursor.return_value
        cursor.fetchone.return_value = (1,)

        validate_ai_entitlement(
            9,
            "test-service-secret",
            lambda: connection,
        )

        cursor.execute.assert_called_once()

    def test_wrong_token_does_not_open_database_connection(self):
        connect = MagicMock()

        with self.assertRaises(InvalidServiceToken):
            validate_ai_entitlement(9, "wrong", connect)

        connect.assert_not_called()


if __name__ == "__main__":
    unittest.main()
