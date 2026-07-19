import os


def resolve_database_config(config, environ=None):
    environ = environ if environ is not None else os.environ
    password = environ.get("DB_PASSWORD")

    return {
        "host": environ.get("DB_HOST") or config.get("host") or "127.0.0.1",
        "port": environ.get("DB_PORT") or config.get("port") or 3306,
        "user": (
            environ.get("DB_USER")
            or environ.get("DB_USERNAME")
            or config.get("user")
            or "root"
        ),
        "password": password if password is not None else config.get("password", ""),
        "database": environ.get("DB_DATABASE") or config.get("database") or "pdam_saas",
    }
