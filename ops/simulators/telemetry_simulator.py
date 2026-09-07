#!/usr/bin/env python3
"""Simulator telemetry IOT / PRODUCTION / DISTRICT untuk API PDAM Tier-3.

Perangkat keras (AMR/SCADA/telemetri DMA) belum tersedia; skrip mengisi
endpoint ingest pakai data sintetis, sama persis dengan kontrak yang diuji
`TierThreeTelemetryContractTest`, supaya integrasi & load bisa jalan
sebelum sensor nyata tiba.

    export PDAM_API=https://staging.pdam.local/api/v1
    export PDAM_TOKEN=<bearer akun service berpermission iot.ingest/prod.ingest/dist.ingest>

    # loop kontinu tiap 30 detik:
    python ops/simulators/telemetry_simulator.py --mode all

    # sekali + baca dashboard (smoke):
    python ops/simulators/telemetry_simulator.py --mode all --once --verify-read

    # id dma (WAJIB bila simulator --mode district tanpa --dma, ambil dari dashboard):
    python ops/simulators/telemetry_simulator.py --mode district --dma 1,2 --cycles 3

JANGAN arahkan --base ke production dengan data palsu.
"""

from __future__ import annotations

import argparse
import json
import math
import os
import random
import sys
import time
import urllib.error
import urllib.request
from datetime import datetime, timezone

__version__ = "1.0.0"


def request(method: str, url: str, token: str, body: dict | None) -> tuple[int, dict]:
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(
        url,
        data=data,
        method=method,
        headers={"Accept": "application/json", "Authorization": f"Bearer {token}"} | ({'Content-Type': 'application/json'} if data else {}),
    )
    try:
        with urllib.request.urlopen(req, timeout=30) as r:  # noqa: S310
            raw = r.read().decode("utf-8", errors="replace")
            return r.status, json.loads(raw or "{}")
    except urllib.error.HTTPError as e:
        raw = e.read().decode("utf-8", errors="replace")
        try:
            parsed = json.loads(raw or "{}")
        except Exception:
            parsed = {"raw": raw}
        return e.code, parsed
    except urllib.error.URLError as e:
        return 0, {"error": str(e)}


def iot_batch(n: int, t: datetime, rnd: random.Random) -> dict:
    out = []
    for i in range(1, max(1, n) + 1):
        base = 100 + i * 7
        low = i % 9 == 0  # bikin alert low_battery muncul kadang
        out.append({
            "device_id": f"AMR-{i:04d}",
            "value": round(base + rnd.uniform(-4, 9), 2),
            "flow_rate": round(max(0.0, 1.4 + rnd.uniform(-0.9, 2.2)), 2),
            "reading_at": t.isoformat(),
            "battery": round(rnd.uniform(12, 99) if low else rnd.uniform(60, 100)),
            "signal_strength": round(-95 - rnd.uniform(0, 9) if low else -55 - rnd.uniform(0, 30)),
            "source": rnd.choice(["lorawan", "nbiot", "wifi", "lorawan", "nbiot"]),
        })
    return {"readings": out}


def prod_batch(t: datetime, rnd: random.Random) -> dict:
    raw = round(12000 + rnd.uniform(-800, 800), 1)
    treated = round(raw * rnd.uniform(0.90, 0.965), 1)
    dist = round(treated * rnd.uniform(0.87, 0.97), 1)
    return {"readings": [{
        "production_date": t.date().isoformat(),
        "raw_water_m3": raw,
        "treated_water_m3": treated,
        "distributed_water_m3": dist,
        "pump_runtime_hours": round(20 + rnd.uniform(0, 4), 1),
        "power_consumption_kwh": round(raw * 0.32, 1),
        "turbidity_ntu": round(max(0.05, rnd.uniform(0.4, 2.6)), 2),
        "ph": round(rnd.uniform(6.7, 7.6), 2),
        "chlorine_residual": round(rnd.uniform(0.2, 0.7), 2),
    }]}


def dist_batch(ids: list[int], t: datetime, rnd: random.Random) -> dict:
    return {"readings": [{
        "dma_zone_id": int(z),
        "reading_at": t.isoformat(),
        "flow_rate_m3h": round(max(0.0, 320 + 90 * math.sin((t.hour + t.minute / 60) * math.pi / 12) + rnd.uniform(-25, 25)), 2),
        "pressure_bar": round(rnd.uniform(1.6, 3.4), 2),
        "reservoir_level_percent": round(rnd.uniform(35, 96), 1),
        "chlorine_residual": round(rnd.uniform(0.15, 0.75), 2),
    } for z in ids]}


def discover_dma(base: str, token: str) -> list[int]:
    for path in ("/distribution/dma-dashboard", "/dma"):
        st, body = request("GET", base.rstrip("/") + path, token, None)
        if st != 200 or not isinstance(body, dict):
            continue
        data = body.get("data")
        if isinstance(data, dict):
            for key in ("dma", "dmas", "zones", "items", "readings"):
                if isinstance(data.get(key), list):
                    data = data[key]
                    break
        if isinstance(data, list):
            ids: list[int] = []
            for row in data:
                if not isinstance(row, dict):
                    continue
                for key in ("dma_zone_id", "dma_id", "id", "zone_id"):
                    v = row.get(key)
                    if isinstance(v, int) and v > 0:
                        ids.append(v)
            if ids:
                return sorted(set(ids))
    return []


def main(argv: list[str] | None = None) -> int:
    ap = argparse.ArgumentParser(description="Simulator telemetry Tier-3 PDAM (stdlib only)")
    ap.add_argument("--base", default=os.getenv("PDAM_API", "http://localhost:8080/api/v1"))
    ap.add_argument("--token", default=os.getenv("PDAM_TOKEN", ""), help="bearer Sanctum akun service")
    ap.add_argument("--mode", choices=["iot", "production", "district", "all"], default="all")
    ap.add_argument("--devices", type=int, default=8)
    ap.add_argument("--interval", type=float, default=30.0, help="detik antar siklus")
    ap.add_argument("--cycles", type=int, default=0, help="0 = tak hingga")
    ap.add_argument("--once", action="store_true", help="sama dengan --cycles 1")
    ap.add_argument("--verify-read", action="store_true", help="GET dashboard setelah kirim")
    ap.add_argument("--dma", default="", help="id dma dipisah koma (cosong=detect dari dma-dashboard)")
    ap.add_argument("--version", action="version", version=f"%(prog)s {__version__}")
    args = ap.parse_args(argv)

    if not args.token:
        print("ERR: set --token / PDAM_TOKEN", file=sys.stderr)
        return 2
    if args.once:
        args.cycles = 1

    base = args.base
    dma_ids = [int(x) for x in args.dma.split(",") if x.strip()] or (
        discover_dma(args.base, args.token) if args.mode in ("district", "all") else [])
    rnd = random.SystemRandom()

    cycles = 0
    failures = 0
    while True:
        t = datetime.now(timezone.utc).replace(microsecond=0)
        jobs: list[tuple[str, dict]] = []
        if args.mode in ("iot", "all"):
            jobs.append(("/iot/ingest", iot_batch(args.devices, t, rnd)))
        if args.mode in ("production", "all"):
            jobs.append(("/production/ingest", prod_batch(t, rnd)) )
        if args.mode in ("district", "all") and dma_ids:
            jobs.append(("/distribution/ingest", dist_batch(dma_ids, t, rnd)))
        elif args.mode == "district":
            print("ERR: --mode district butuh --dma atau dma-dashboard kosong")
            failures += 1

        for path, payload in jobs:
            st, body = request("POST", base.rstrip("/") + path, args.token, payload)
            ok = body.get("data", {}).get("ingested") or body.get("data", {}).get("count")
            tag = "OK " if st and 200 <= st < 300 else "ERR"
            print(f"[{tag}] {path} HTTP {st} ingested={ok} keys={list(body.get('data', {}))[:3]}")
            if tag == "ERR":
                failures += 1
                print("   ", json.dumps(body.get('error', body))[:220])

        if args.verify_read and args.mode in ("iot", "all"):
            for path in ("/iot/dashboard", "/production/dashboard", "/distribution/dma-dashboard"):
                st, _ = request("GET", base.rstrip("/") + path, args.token, None)
                print(f"[{'OK ' if 200 <= st < 300 else 'ERR'}] GET {path} → {st}")

        cycles += 1
        if args.cycles and cycles >= args.cycles:
            break
        time.sleep(max(0.1, args.interval))

    return 1 if failures else 0


if __name__ == "__main__":
    raise SystemExit(main())
