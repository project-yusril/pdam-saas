import numpy as np
import pandas as pd
from sklearn.preprocessing import StandardScaler, LabelEncoder
from sklearn.impute import SimpleImputer


class Preprocessor:
    def __init__(self):
        self.scaler = StandardScaler()
        self.label_encoders = {}
        self.feature_columns = None
        self.imputer_median = SimpleImputer(strategy="median")

    def _compute_consumption_from_readings(self, readings_df):
        df = readings_df.copy()
        df = df.sort_values(["customer_id", "period"])
        initial_readings = df.groupby("customer_id")["initial_reading"].first()
        consumption_rows = []
        for customer_id, group in df.groupby("customer_id"):
            initial = initial_readings.get(customer_id, 0)
            prev = initial
            for _, row in group.iterrows():
                val = int(row["reading_value"])
                cons = val - prev
                if cons < 0:
                    cons = (val + 100000) - prev
                consumption_rows.append({
                    "customer_id": customer_id,
                    "period": row["period"],
                    "consumption": cons,
                    "reading_type": row.get("reading_type", "actual"),
                    "is_rollover": row.get("is_rollover", False),
                })
                prev = val
        return pd.DataFrame(consumption_rows)

    def build_consumption_features(self, billing_df):
        df = billing_df.copy()
        df = df.sort_values(["customer_id", "period"])
        features = []

        for customer_id, group in df.groupby("customer_id"):
            group = group.sort_values("period")
            cons_series = group["consumption"].astype(float)
            if len(cons_series) < 2:
                continue
            latest = group.iloc[-1]
            row = {
                "customer_id": customer_id,
                "period": latest["period"],
                "consumption": latest["consumption"],
                "water_charge": float(latest.get("water_charge", 0)),
                "amount_due": float(latest.get("amount_due", 0)),
                "zone_id": int(latest.get("zone_id", 0)),
                "tariff_category_id": int(latest.get("tariff_category_id", 0)),
                "tariff_group": latest.get("tariff_group", ""),
                "status": latest.get("status", ""),
            }

            row["lag_1"] = cons_series.iloc[-2] if len(cons_series) >= 2 else float(cons_series.iloc[-1])
            row["lag_3"] = cons_series.iloc[-4] if len(cons_series) >= 4 else np.nan
            row["lag_6"] = cons_series.iloc[-7] if len(cons_series) >= 7 else np.nan

            if len(cons_series) >= 3:
                row["rolling_mean_3"] = cons_series.iloc[-3:].mean()
                row["rolling_std_3"] = cons_series.iloc[-3:].std()
            else:
                row["rolling_mean_3"] = cons_series.mean()
                row["rolling_std_3"] = cons_series.std() if len(cons_series) > 1 else 0.0

            if len(cons_series) >= 6:
                row["rolling_mean_6"] = cons_series.iloc[-6:].mean()
                row["rolling_std_6"] = cons_series.iloc[-6:].std()
            else:
                row["rolling_mean_6"] = cons_series.mean()
                row["rolling_std_6"] = cons_series.std() if len(cons_series) > 1 else 0.0

            if len(cons_series) >= 2:
                row["trend"] = cons_series.iloc[-1] - cons_series.iloc[-2]
            else:
                row["trend"] = 0.0

            row["month"] = int(latest["period"].split("-")[1]) if "-" in str(latest["period"]) else 1
            row["month_sin"] = np.sin(2 * np.pi * row["month"] / 12)
            row["month_cos"] = np.cos(2 * np.pi * row["month"] / 12)

            if len(cons_series) >= 2:
                row["pct_change"] = (cons_series.iloc[-1] - cons_series.iloc[-2]) / (cons_series.iloc[-2] + 1e-6)
            else:
                row["pct_change"] = 0.0

            row["cumulative_consumption"] = cons_series.sum()
            row["n_periods"] = len(cons_series)

            features.append(row)

        result = pd.DataFrame(features)
        return result

    def build_churn_features(self, billing_df, payment_df, customer_df, churn_labels_df):
        if billing_df.empty:
            return pd.DataFrame()

        billing = billing_df.sort_values(["customer_id", "period"])
        payment = payment_df.copy() if not payment_df.empty else pd.DataFrame()
        customers = customer_df.copy() if not customer_df.empty else pd.DataFrame()

        feature_rows = []
        customer_ids = billing["customer_id"].unique()

        for cid in customer_ids:
            cust_bills = billing[billing["customer_id"] == cid].sort_values("period")
            cust_payments = payment[payment["customer_id"] == cid] if not payment.empty else pd.DataFrame()
            cust_info = customers[customers["customer_id"] == cid] if not customers.empty else pd.DataFrame()

            if cust_bills.empty:
                continue

            recent = cust_bills.tail(1).iloc[0]

            row = {"customer_id": cid}

            total_bills = len(cust_bills)
            unpaid = (cust_bills["status"].isin(["unpaid", "pending", "partial"])).sum()
            row["unpaid_ratio"] = unpaid / total_bills if total_bills > 0 else 0.0

            cons_values = cust_bills["consumption"].astype(float)
            row["avg_consumption"] = cons_values.mean()
            row["std_consumption"] = cons_values.std() if len(cons_values) > 1 else 0.0
            row["max_consumption"] = cons_values.max()
            row["min_consumption"] = cons_values.min()

            if len(cons_values) >= 3:
                row["recent_avg"] = cons_values.iloc[-3:].mean()
                row["older_avg"] = cons_values.iloc[:-3].mean() if len(cons_values) > 3 else cons_values.iloc[-3:].mean()
                row["consumption_drop"] = row["older_avg"] - row["recent_avg"]
                row["consumption_drop_pct"] = row["consumption_drop"] / (row["older_avg"] + 1e-6)
            else:
                row["recent_avg"] = cons_values.mean()
                row["older_avg"] = cons_values.mean()
                row["consumption_drop"] = 0.0
                row["consumption_drop_pct"] = 0.0

            if len(cons_values) >= 2:
                row["trend"] = cons_values.iloc[-1] - cons_values.iloc[-2]
            else:
                row["trend"] = 0.0

            row["zero_count"] = (cons_values == 0).sum()

            if not cust_payments.empty:
                if "days_late" in cust_payments.columns:
                    row["avg_days_late"] = cust_payments["days_late"].mean()
                    row["max_days_late"] = cust_payments["days_late"].max()
                    row["late_count"] = (cust_payments["days_late"] > 0).sum()
                    row["late_ratio"] = row["late_count"] / len(cust_payments) if len(cust_payments) > 0 else 0.0
                else:
                    row["avg_days_late"] = 0.0
                    row["max_days_late"] = 0.0
                    row["late_count"] = 0
                    row["late_ratio"] = 0.0
            else:
                row["avg_days_late"] = 0.0
                row["max_days_late"] = 0.0
                row["late_count"] = 0
                row["late_ratio"] = 0.0

            if not cust_info.empty:
                ci = cust_info.iloc[0]
                row["days_since_install"] = float(ci.get("days_since_install", 0) or 0)
                row["tariff_category_id"] = int(ci.get("tariff_category_id", 0))
                row["zone_id"] = int(ci.get("zone_id", 0))
                row["tariff_group"] = ci.get("tariff_group", "")
                row["meter_condition"] = ci.get("meter_condition", "")
                row["tamper_status"] = ci.get("tamper_status", "")
            else:
                row["days_since_install"] = 0.0
                row["tariff_category_id"] = 0
                row["zone_id"] = 0
                row["tariff_group"] = ""
                row["meter_condition"] = ""
                row["tamper_status"] = ""

            period_str = str(recent["period"])
            row["month"] = int(period_str.split("-")[1]) if "-" in period_str else 1
            row["month_sin"] = np.sin(2 * np.pi * row["month"] / 12)
            row["month_cos"] = np.cos(2 * np.pi * row["month"] / 12)

            if churn_labels_df is not None and not churn_labels_df.empty:
                label_row = churn_labels_df[churn_labels_df["customer_id"] == cid]
                row["churned"] = 1 if not label_row.empty else 0
            else:
                row["churned"] = 0

            feature_rows.append(row)

        return pd.DataFrame(feature_rows)

    def build_meter_features(self, meters_df, anomaly_df):
        df = meters_df.copy()
        if df.empty:
            return df

        features = []
        for _, meter in df.iterrows():
            row = dict(meter)

            row["meter_age_days"] = float(row.get("days_since_install", 0) or 0)
            row["meter_age_years"] = row["meter_age_days"] / 365.25
            row["days_since_calibration"] = float(row.get("days_since_calibration", 0) or 0)

            row["is_calibration_overdue"] = 1 if row["days_since_calibration"] > 365 * 3 else 0
            row["is_meter_old"] = 1 if row["meter_age_years"] > 5 else 0

            meter_id = row.get("meter_id", row.get("customer_id", 0))
            if not anomaly_df.empty:
                meter_anomalies = anomaly_df[anomaly_df["customer_id"] == row.get("customer_id", -1)]
                row["anomaly_count"] = len(meter_anomalies)
                row["high_severity_count"] = (meter_anomalies["severity"] == "high").sum() if "severity" in meter_anomalies.columns else 0
            else:
                row["anomaly_count"] = 0
                row["high_severity_count"] = 0

            row["diameter"] = float(row.get("diameter", 0) or 0)
            row["tamper_flag"] = 1 if str(row.get("tamper_status", "")).lower() in ("tampered", "1", "yes", "true") else 0

            row["has_meter"] = 1 if row.get("meter_id") else 0

            features.append(row)

        return pd.DataFrame(features)

    def fill_missing(self, df, method="ffill"):
        df = df.copy()
        numeric_cols = df.select_dtypes(include=[np.number]).columns
        df[numeric_cols] = self.imputer_median.fit_transform(df[numeric_cols])
        return df

    def remove_outliers_iqr(self, df, columns, factor=1.5):
        df = df.copy()
        for col in columns:
            if col not in df.columns:
                continue
            Q1 = df[col].quantile(0.25)
            Q3 = df[col].quantile(0.75)
            IQR = Q3 - Q1
            lower = Q1 - factor * IQR
            upper = Q3 + factor * IQR
            df = df[(df[col] >= lower) & (df[col] <= upper)]
        return df

    def encode_categorical(self, df, columns):
        df = df.copy()
        for col in columns:
            if col not in df.columns:
                continue
            if col not in self.label_encoders:
                le = LabelEncoder()
                df[col] = df[col].astype(str)
                df[col] = le.fit_transform(df[col])
                self.label_encoders[col] = le
            else:
                le = self.label_encoders[col]
                df[col] = df[col].astype(str)
                known = set(le.classes_)
                df[col] = df[col].apply(lambda x: x if x in known else "unknown")
                if "unknown" not in le.classes_:
                    le.classes_ = np.append(le.classes_, "unknown")
                df[col] = le.transform(df[col])
        return df

    def categorical_metadata(self, columns):
        return {
            col: self.label_encoders[col].classes_.tolist()
            for col in columns
            if col in self.label_encoders
        }

    def apply_categorical_metadata(self, df, categorical_classes):
        df = df.copy()
        for col, classes in (categorical_classes or {}).items():
            if col not in df.columns:
                continue
            mapping = {str(value): index for index, value in enumerate(classes)}
            df[col] = df[col].astype(str).map(mapping).fillna(-1).astype(int)
        return df

    @staticmethod
    def align_features(df, feature_columns):
        return df.reindex(columns=feature_columns, fill_value=0).fillna(0)

    def scale(self, df, columns=None, fit=True):
        df = df.copy()
        if columns is None:
            columns = df.select_dtypes(include=[np.number]).columns.tolist()
        columns = [c for c in columns if c in df.columns]
        if not columns:
            return df
        if fit:
            df[columns] = self.scaler.fit_transform(df[columns])
        else:
            df[columns] = self.scaler.transform(df[columns])
        return df

    def prepare_consumption_data(self, billing_df):
        features = self.build_consumption_features(billing_df)
        if features.empty:
            return None, None, None

        categorical_cols = ["tariff_group", "status"]
        features = self.encode_categorical(features, categorical_cols)

        numeric_cols = features.select_dtypes(include=[np.number]).columns.tolist()
        exclude_cols = ["customer_id", "consumption"]
        numeric_cols = [c for c in numeric_cols if c not in exclude_cols]

        features = self.remove_outliers_iqr(features, ["consumption"])
        features = self.fill_missing(features)

        target = features["consumption"].values if "consumption" in features.columns else None
        customer_ids = features["customer_id"].values
        periods = features["period"].values if "period" in features.columns else None

        feature_cols = [c for c in features.columns if c not in ["customer_id", "consumption", "period", "water_charge", "amount_due"]]
        feature_cols = [c for c in feature_cols if c in features.select_dtypes(include=[np.number]).columns]

        X = features[feature_cols].fillna(0).values

        self.feature_columns = feature_cols

        return X, target, customer_ids
