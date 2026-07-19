import os
import mysql.connector
import pandas as pd
from dotenv import load_dotenv

load_dotenv()


class DataLoader:
    def __init__(self, host=None, port=None, user=None, password=None, database=None):
        self.host = host or os.getenv("DB_HOST", "127.0.0.1")
        self.port = int(port or os.getenv("DB_PORT", "3306"))
        self.user = user or os.getenv("DB_USER") or os.getenv("DB_USERNAME", "root")
        self.password = password if password is not None else os.getenv("DB_PASSWORD", "")
        self.database = database or os.getenv("DB_DATABASE", "pdam_saas")
        self.connect_timeout = int(os.getenv("DB_CONNECT_TIMEOUT", "5"))

    def _connect(self):
        return mysql.connector.connect(
            host=self.host,
            port=self.port,
            user=self.user,
            password=self.password,
            database=self.database,
            connection_timeout=self.connect_timeout,
        )

    def _query(self, sql, params=None):
        conn = self._connect()
        try:
            return pd.read_sql_query(sql, conn, params=params)
        finally:
            conn.close()

    def get_billing_history(self, pdam_org_id, months=24):
        sql = """
            SELECT
                b.customer_id,
                b.period,
                b.consumption,
                b.water_charge,
                b.amount_due,
                b.status,
                b.due_date,
                c.zone_id,
                c.tariff_category_id,
                c.installation_date,
                tc.group_type AS tariff_group
            FROM bills b
            JOIN customers c ON b.customer_id = c.id
            JOIN tariff_categories tc ON c.tariff_category_id = tc.id
            WHERE b.pdam_org_id = %s
              AND c.status = 'active'
              AND b.period >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL %s MONTH), '%%Y-%%m')
            ORDER BY b.customer_id, b.period
        """
        return self._query(sql, (pdam_org_id, months))

    def get_meter_readings(self, pdam_org_id, months=24):
        sql = """
            SELECT
                mr.customer_id,
                mr.period,
                mr.reading_value,
                mr.reading_type,
                mr.reading_date,
                mr.is_rollover,
                c.zone_id,
                c.tariff_category_id,
                c.initial_reading
            FROM meter_readings mr
            JOIN customers c ON mr.customer_id = c.id
            WHERE mr.pdam_org_id = %s
              AND c.status = 'active'
              AND mr.period >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL %s MONTH), '%%Y-%%m')
            ORDER BY mr.customer_id, mr.period
        """
        return self._query(sql, (pdam_org_id, months))

    def get_customer_features(self, pdam_org_id):
        sql = """
            SELECT
                c.id AS customer_id,
                c.customer_number,
                c.zone_id,
                z.name AS zone_name,
                c.tariff_category_id,
                tc.code AS tariff_code,
                tc.group_type AS tariff_group,
                c.installation_date,
                c.initial_reading,
                c.meter_serial_number,
                DATEDIFF(NOW(), c.installation_date) AS days_since_install,
                CASE
                    WHEN m.id IS NOT NULL THEN 1
                    ELSE 0
                END AS has_meter,
                DATEDIFF(NOW(), m.last_calibration_date) AS days_since_calibration,
                m.diameter AS meter_diameter,
                m.brand AS meter_brand,
                m.condition AS meter_condition,
                m.status AS meter_status,
                m.tamper_status
            FROM customers c
            LEFT JOIN zones z ON c.zone_id = z.id
            LEFT JOIN tariff_categories tc ON c.tariff_category_id = tc.id
            LEFT JOIN meters m ON c.meter_serial_number = m.serial_number
            WHERE c.pdam_org_id = %s
              AND c.status = 'active'
        """
        return self._query(sql, (pdam_org_id,))

    def get_customer_churn_labels(self, pdam_org_id, window_days=180):
        sql = """
            SELECT
                csh.customer_id,
                MAX(csh.created_at) AS disconnection_date,
                1 AS churned
            FROM customer_status_history csh
            JOIN customers c ON csh.customer_id = c.id
            WHERE c.pdam_org_id = %s
              AND csh.to_status IN ('disconnected', 'terminated', 'inactive')
              AND csh.created_at >= DATE_SUB(NOW(), INTERVAL %s DAY)
            GROUP BY csh.customer_id
        """
        df = self._query(sql, (pdam_org_id, window_days))
        if df.empty:
            return pd.DataFrame(columns=["customer_id", "churned"])
        return df

    def get_active_customers(self, pdam_org_id):
        sql = """
            SELECT id AS customer_id
            FROM customers
            WHERE pdam_org_id = %s AND status = 'active'
        """
        return self._query(sql, (pdam_org_id,))

    def get_payment_history(self, pdam_org_id, months=24):
        sql = """
            SELECT
                p.bill_id,
                p.customer_id,
                p.amount,
                p.payment_date,
                p.payment_method,
                b.period,
                b.due_date,
                DATEDIFF(p.payment_date, b.due_date) AS days_late
            FROM payments p
            JOIN bills b ON p.bill_id = b.id
            WHERE p.pdam_org_id = %s
              AND b.period >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL %s MONTH), '%%Y-%%m')
            ORDER BY p.customer_id, b.period
        """
        return self._query(sql, (pdam_org_id, months))

    def get_anomaly_history(self, pdam_org_id, months=24):
        sql = """
            SELECT
                ma.customer_id,
                ma.period,
                ma.rule_code,
                ma.severity,
                ma.actual_value,
                ma.expected_value,
                ma.status AS anomaly_status
            FROM meter_anomalies ma
            WHERE ma.pdam_org_id = %s
              AND ma.period >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL %s MONTH), '%%Y-%%m')
            ORDER BY ma.customer_id, ma.period
        """
        return self._query(sql, (pdam_org_id, months))

    def get_meters_for_failure_prediction(self, pdam_org_id):
        sql = """
            SELECT
                m.id AS meter_id,
                m.serial_number,
                m.customer_id,
                m.diameter,
                m.brand,
                m.condition,
                m.install_date,
                m.install_year,
                DATEDIFF(NOW(), m.install_date) AS days_since_install,
                DATEDIFF(NOW(), m.last_calibration_date) AS days_since_calibration,
                m.status,
                m.tamper_status,
                c.tariff_category_id,
                tc.group_type AS tariff_group
            FROM meters m
            LEFT JOIN customers c ON m.customer_id = c.id
            LEFT JOIN tariff_categories tc ON c.tariff_category_id = tc.id
            WHERE m.pdam_org_id = %s
              AND m.status IN ('installed', 'terpasang', 'active')
        """
        return self._query(sql, (pdam_org_id,))

    def get_ml_predictions(
        self,
        pdam_org_id,
        model_name=None,
        period=None,
        prediction_type=None,
        limit=100,
    ):
        sql = """
            SELECT *
            FROM ml_predictions
            WHERE pdam_org_id = %s
        """
        params = [pdam_org_id]
        if model_name:
            sql += " AND model_name = %s"
            params.append(model_name)
        if prediction_type:
            sql += " AND prediction_type = %s"
            params.append(prediction_type)
        if period:
            sql += " AND period = %s"
            params.append(period)
        sql += " ORDER BY created_at DESC LIMIT %s"
        params.append(limit)
        return self._query(sql, tuple(params))
