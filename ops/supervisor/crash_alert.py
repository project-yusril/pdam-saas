#!/usr/bin/env python3
"""Supervisord event listener: crashalert.

Protokol event-listener supervisor: tulis `READY\\n`, baca header event, balas
`RESULT 2\\nOK`. Versi bash `while read` tanpa handshake membuat process tidak
pernah RUNNING sehingga event PROCESS_STATE_* tidak pernah diterima (bug review).
Di-deploy sebagai `command=python3 /var/www/pdam/ops/supervisor/crash_alert.py`.
"""
import os
import subprocess
import sys
from datetime import datetime, timezone


def write(msg):
    sys.stdout.write(msg)
    sys.stdout.flush()


def read_cmd():
    data = sys.stdin.readline()
    if not data:
        raise EOFError('supervisord closed the pipe')
    payload = data + sys.stdin.read(int(dict(p.split(':', 1) for p in data.split())['len']))
    return dict(p.split(':', 1) for p in payload.split())


def log_alert(line, event):
    msg = f"[pdam-supervisor] {datetime.now(timezone.utc).isoformat()} {event} :: {line}"
    try:
        subprocess.run(['logger', '-t', 'pdam-supervisor', msg], check=False, timeout=10)
    except Exception:
        pass
    sys.stderr.write(msg + '\n')
    sys.stderr.flush()


def main():
    write('READY\n')
    while True:
        try:
            headers = read_cmd()
            event = headers.get('eventname', '')
            payload = headers.get('payload', '')
            line = f"{payload[:400]!r}".strip("'")
            if 'FATAL' in event or 'EXITED' in event or 'STOPPED' in event:
                log_alert(line, event)
        except Exception as exc:
            log_alert(f'crash_alert internal error: {exc}', 'LISTENER.ERROR')
        write('RESULT 2\nOK')


if __name__ == '__main__':
    try:
        main()
    except (EOFError, BrokenPipeError):
        os._exit(0)
