import numpy as np
import pandas as pd


def make_synthetic_frames(customer_count=40, periods=12, seed=42):
    rng = np.random.default_rng(seed)
    billing_rows = []
    reading_rows = []
    payment_rows = []
    customer_rows = []
    meter_rows = []
    anomaly_rows = []
    churned_ids = set(range(1, customer_count + 1, 4))

    for customer_id in range(1, customer_count + 1):
        at_risk = customer_id % 4 == 1
        reading = 1000.0 + customer_id * 10
        for month in range(1, periods + 1):
            period = f"2025-{month:02d}"
            consumption = max(0.5, 18 + customer_id % 7 + month * 0.2 + rng.normal(0, 0.4))
            if at_risk and month > periods - 3:
                consumption *= 0.15
            reading += consumption
            status = "unpaid" if at_risk and month > periods - 4 else "paid"
            billing_rows.append({
                "customer_id": customer_id, "period": period, "consumption": consumption,
                "water_charge": consumption * 2, "amount_due": consumption * 2,
                "zone_id": customer_id % 3, "tariff_category_id": customer_id % 2,
                "tariff_group": "B" if customer_id % 2 else "A", "status": status,
            })
            reading_rows.append({
                "customer_id": customer_id, "period": period, "reading_value": reading,
                "reading_type": "estimated" if month % 7 == 0 else "actual",
                "is_rollover": False,
            })
            payment_rows.append({
                "customer_id": customer_id,
                "days_late": 20 if status == "unpaid" else customer_id % 3,
            })

        broken = customer_id % 5 == 1
        customer_rows.append({
            "customer_id": customer_id, "days_since_install": 4500 if broken else 800,
            "tariff_category_id": customer_id % 2, "zone_id": customer_id % 3,
            "tariff_group": "B" if customer_id % 2 else "A",
            "meter_condition": "broken" if broken else "good",
            "tamper_status": "clear",
        })
        meter_rows.append({
            "meter_id": 1000 + customer_id, "customer_id": customer_id,
            "serial_number": f"M-{customer_id:04d}",
            "days_since_install": 4500 if broken else 800,
            "days_since_calibration": 1500 if broken else 200,
            "diameter": 20, "tamper_status": "clear",
            "tariff_category_id": customer_id % 2,
            "condition": "broken" if broken else "good", "tariff_group": "A",
        })
        if broken:
            for index in range(6):
                anomaly_rows.append({
                    "customer_id": customer_id, "severity": "high",
                    "rule_code": "spike" if index % 2 else "zero_streak",
                })

    return {
        "billing": pd.DataFrame(billing_rows),
        "readings": pd.DataFrame(reading_rows),
        "payments": pd.DataFrame(payment_rows),
        "customers": pd.DataFrame(customer_rows),
        "churn_labels": pd.DataFrame({"customer_id": sorted(churned_ids)}),
        "meters": pd.DataFrame(meter_rows),
        "anomalies": pd.DataFrame(anomaly_rows),
    }
