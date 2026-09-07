"""Test contract simulator telemetry vs field validation pada controller Tier-3.

Lokasinya di ml/tests supaya jalan bersama pytest CI; import dari ops/ via path.
"""

import sys
from datetime import datetime, timezone
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO / "ops" / "simulators"))

import telemetry_simulator as sim  # noqa: E402

IOT_REQUIRED = {"device_id", "value", "reading_at"}
IOT_TYPES = {
    "device_id": str, "value": (int, float), "reading_at": str,
    "flow_rate": (int, float, type(None)), "battery": (int, float, type(None)),
    "signal_strength": (int, float, type(None)), "source": (str,), "meter_id": (int, type(None)),
}
SOURCE_ENUM = {"lorawan", "nbiot", "wifi"}
PROD_REQUIRED = {"production_date"}
DIST_REQUIRED = {"dma_zone_id", "reading_at"}


def test_iot_batch_contract() -> None:
    out = sim.iot_batch(4, datetime.now(timezone.utc), sim.random.SystemRandom())
    assert set(out) == {"readings"} and len(out["readings"]) == 4
    for row in out["readings"]:
        assert IOT_REQUIRED.issubset(row.keys()), row
        assert isinstance(row["source"], str) and row["source"] in SOURCE_ENUM
        assert float(row["value"]) >= 0


def test_production_batch_contract() -> None:
    body = sim.prod_batch(datetime.now(timezone.utc), sim.random.Random(42))
    r = body["readings"][0]
    assert PROD_REQUIRED.issubset(r)
    assert float(r["treated_water_m3"]) <= float(r["raw_water_m3"])
    assert float(r["distributed_water_m3"]) <= float(r["treated_water_m3"])


def test_district_batch_contract() -> None:
    body = sim.dist_batch([1, 2], datetime.now(timezone.utc), sim.random.Random(7))
    assert list(body) == ["readings"] and len(body["readings"]) == 2
    for r in body["readings"]:
        assert DIST_REQUIRED.issubset(r)
        assert 1.0 <= float(r["pressure_bar"]) <= 4.0
