#!/usr/bin/env python3
"""Real negative analysis of synthetic GPL fixtures. Never executes fixture code."""
import argparse
import importlib.util
import json
from pathlib import Path
import shutil
import tempfile

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('security_scan', ROOT / 'scripts/security/scan.py')
scan = importlib.util.module_from_spec(spec)
spec.loader.exec_module(scan)

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, required=True)
    output = parser.parse_args().output.resolve()
    output.mkdir(mode=0o700, parents=True, exist_ok=False)
    cli = scan.verified_cli()
    with tempfile.TemporaryDirectory(dir=output) as tmp:
        source = Path(tmp)
        for fixture in (ROOT / 'tests/security/fixtures').iterdir():
            shutil.copyfile(fixture, source / fixture.name.removesuffix('.fixture'))
        try:
            scan.scan_tree(source, output, cli)
        except RuntimeError:
            report = json.loads((output / 'summary.json').read_text())
            expected = {('javascript', 'js/command-line-injection'), ('python', 'py/command-line-injection')}
            detected = {(item['language'], item['rule']) for item in report['findings']}
            if not expected.issubset(detected):
                raise AssertionError('Missing expected command-injection findings')
            print('PASS: real high/critical findings block both fixture languages')
        else:
            raise AssertionError('Negative fixtures unexpectedly passed')

if __name__ == '__main__':
    main()
