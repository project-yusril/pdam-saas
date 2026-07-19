import os
import secrets


class ServiceAuthError(Exception):
    pass


class ServiceAuthNotConfigured(ServiceAuthError):
    pass


class InvalidServiceToken(ServiceAuthError):
    pass


class AiEntitlementInactive(ServiceAuthError):
    pass


def validate_service_token(provided_token, expected_token=None):
    expected = expected_token if expected_token is not None else os.getenv("ML_SERVICE_TOKEN", "")
    if not expected:
        raise ServiceAuthNotConfigured("ML service authentication is not configured")
    if not provided_token or not secrets.compare_digest(provided_token, expected):
        raise InvalidServiceToken("Invalid service token")


def validate_ai_entitlement(pdam_org_id, provided_token, connect, expected_token=None):
    validate_service_token(provided_token, expected_token)
    conn = None
    cursor = None
    try:
        conn = connect()
        cursor = conn.cursor()
        cursor.execute(
            """
            SELECT 1
            FROM subscription_modules
            WHERE pdam_org_id = %s
              AND module_code = 'AI'
              AND status = 'active'
              AND (expires_at IS NULL OR expires_at > NOW())
            LIMIT 1
            """,
            (pdam_org_id,),
        )
        if cursor.fetchone() is None:
            raise AiEntitlementInactive("AI module is not active for this tenant")
    finally:
        if cursor is not None:
            cursor.close()
        if conn is not None:
            conn.close()
