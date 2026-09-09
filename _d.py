import re
import subprocess
import sys

out = subprocess.run(['git', 'diff', 'a/b', '--stat', '--', 'mobile'], capture_output=True, text=True)
print(out.stdout)
