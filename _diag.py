import re
import subprocess
import sys

p = subprocess.run(['git', 'diff', '--', 'mobile/lib'], capture_output=True, text=True)
print(p.stdout[:6500] if p.stdout else 'no diff mobile')
d = {f: s for f, s in zip(p.stdout.split('diff --git ')[1:], p.stdout.split('diff --git ')[1:])}

# repository call-site signature check
import os
import glob

repos = glob.glob('mobile/lib/features/**/data/repositories/*.dart', recursive=True) or glob.glob('mobile/lib/features/**/repositories/*.dart', recursive=True)
for f in repos:
    with open(f, encoding='utf-8', errors='ignore') as fh:
        src = fh.read()
    for m in re.finditer(r'_remote\.(fetch|get|submit|upload|mark|initiate|create|update|pay)[A-Za-z]*', src):
        pass
