#!/usr/bin/env python3
"""Local-only CodeQL gate. Never uploads source, databases, or SARIF."""
import argparse
import json
import math
import os
from pathlib import Path, PurePosixPath
from urllib.parse import unquote, urlsplit
import shutil
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parents[2]
ARTIFACT_ROOT = ROOT / '.cache/security'  # Playwright cleans test-results/.
LOCK = json.loads(Path(__file__).with_name('codeql-lock.json').read_text())
LANGUAGES = ('javascript', 'python')

def verified_cli():
    cache = Path(os.environ.get('XDG_CACHE_HOME', Path.home() / '.cache'))
    installation = cache / 'ticketmailer/codeql' / LOCK['version']
    cli = installation / 'codeql/codeql'
    if not cli.is_file() or not os.access(cli, os.X_OK):
        raise RuntimeError('Pinned CodeQL missing; run python3 scripts/security/install-codeql.py')
    if (installation / 'bundle-sha256').read_text().strip() != LOCK['sha256']:
        raise RuntimeError('CodeQL installation does not match the pinned bundle')
    version = json.loads(subprocess.check_output([str(cli), 'version', '--format=json'], text=True))
    if version['version'] != LOCK['version']:
        raise RuntimeError('CodeQL version mismatch')
    return cli

def diagnostic_files(item):
    files = set()
    for location in item.get('locations', []):
        artifact = location['physicalLocation']['artifactLocation']
        uri = urlsplit(artifact['uri'])
        path = PurePosixPath(unquote(uri.path))
        if (uri.scheme or uri.netloc or uri.query or uri.fragment or path.is_absolute()
                or '..' in path.parts or not path.parts
                or artifact.get('uriBaseId') != '%SRCROOT%'):
            raise ValueError('Unsafe or unbased diagnostic source location')
        files.add(path.as_posix())
    if not files:
        raise ValueError('Missing diagnostic source locations')
    return files

def expected_sources(source, language):
    # Independent of CodeQL baseline heuristics: no generated/test path exclusions.
    suffixes = ({'.js', '.jsx', '.mjs', '.cjs', '.ts', '.tsx', '.mts', '.cts',
                 '.html', '.htm', '.vue'} if language == 'javascript' else {'.py'})
    return {p.relative_to(source).as_posix() for p in source.rglob('*')
            if p.is_file() and p.suffix.lower() in suffixes}

def evaluate_sarif(path, language, expected_files, coverage=None):
    report = json.loads(Path(path).read_text())
    if report.get('version') != '2.1.0' or not report.get('runs'):
        raise ValueError('Missing or invalid SARIF runs')
    prefix = {'javascript': 'js', 'python': 'py'}[language]
    findings = []
    expected = set(expected_files)
    if not expected:
        raise ValueError('No expected source files for ' + language)
    extracted, baseline = set(), set()
    for run in report['runs']:
        driver = run['tool']['driver']
        if driver['name'] != 'CodeQL' or not isinstance(run.get('results'), list):
            raise ValueError('Incomplete CodeQL SARIF')
        rules = {rule['id']: rule for rule in driver['rules']}
        if not rules:
            raise ValueError('No CodeQL rules in report')
        invocations = run.get('invocations')
        if not isinstance(invocations, list) or not invocations:
            raise ValueError('Missing successful CodeQL invocation')
        for invocation in invocations:
            if invocation.get('executionSuccessful') is not True:
                raise ValueError('CodeQL reported unsuccessful or incomplete execution')
            for key in ('toolExecutionNotifications', 'toolConfigurationNotifications'):
                for item in invocation.get(key, []):
                    did = item.get('descriptor', {}).get('id', '')
                    if (item.get('level') in ('error', 'warning')
                            or '/diagnostics/extraction-' in did):
                        raise ValueError('CodeQL reported analysis/extraction diagnostics: ' + did)
                    if did == prefix + '/diagnostics/successfully-extracted-files':
                        extracted.update(diagnostic_files(item))
                    if did == prefix + '/baseline/expected-extracted-files':
                        baseline.update(diagnostic_files(item))
        for result in run['results']:
            rule = rules[result['ruleId']]
            if '/diagnostics/' in rule['id']:
                raise ValueError('Unexpected extraction diagnostic in results')
            properties = rule.get('properties', {})
            score = properties.get('security-severity')
            if score is None:
                if 'security' in properties.get('tags', []):
                    raise ValueError('Security result has no severity')
                continue
            score = float(score)
            if not math.isfinite(score) or not 0 <= score <= 10:
                raise ValueError('Invalid security severity')
            # High >=7, critical >=9. Do not honor suppressions or baselines.
            if score >= LOCK['security_severity']:
                findings.append({'rule': rule['id'], 'security_severity': score})
    if not baseline or not extracted:
        raise ValueError('Missing CodeQL extraction coverage diagnostics')
    missing = (expected | baseline) - extracted
    if missing:
        raise ValueError('Incomplete ' + language + ' extraction: ' + ', '.join(sorted(missing)))
    if coverage is not None:
        coverage.update({'expected_source_files': sorted(expected),
                         'codeql_baseline_files': sorted(baseline),
                         'successfully_extracted_files': sorted(extracted)})
    return findings

def snapshot_project(destination):
    names = subprocess.check_output(
        ['git', 'ls-files', '--cached', '--others', '--exclude-standard', '-z'], cwd=ROOT
    ).split(b'\0')
    for raw in set(names) - {b''}:
        relative = Path(os.fsdecode(raw))
        source = ROOT / relative
        if relative.is_absolute() or '..' in relative.parts or source.is_symlink():
            raise ValueError('Unsafe source path')
        if not source.exists():
            continue  # Locally deleted tracked files are not part of this candidate.
        if not source.is_file():
            raise ValueError('Source path is not a regular file')
        target = destination / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(source, target)

def scan_tree(source, output, cli):
    findings = []
    counts = {}
    coverage = {}
    for language in LANGUAGES:
        suite = (cli.parent / 'qlpacks/codeql' / (language + '-queries') /
                 LOCK['query_packs'][language] / 'codeql-suites' /
                 (language + '-code-scanning.qls'))
        if not suite.is_file():
            raise RuntimeError('Missing pinned query suite: ' + language)
        database = output / (language + '-db')
        sarif = output / (language + '.sarif')
        with (output / (language + '.log')).open('w') as log:
            subprocess.run([str(cli), 'database', 'create', str(database),
                            '--language=' + language, '--build-mode=none',
                            '--source-root=' + str(source), '--threads=2', '--ram=3072'],
                           stdout=log, stderr=subprocess.STDOUT, check=True)
            subprocess.run([str(cli), 'database', 'analyze', str(database), str(suite),
                            '--format=sarif-latest', '--output=' + str(sarif),
                            '--threads=2', '--ram=3072'],
                           stdout=log, stderr=subprocess.STDOUT, check=True)
        coverage[language] = {}
        detected = evaluate_sarif(sarif, language, expected_sources(source, language),
                                  coverage[language])
        findings.extend({'language': language, **item} for item in detected)
        counts[language] = len(detected)
        print(language + ': completed, high/critical findings=' + str(len(detected)), flush=True)
    summary = {'codeql_version': LOCK['version'], 'bundle_sha256': LOCK['sha256'],
               'suite': LOCK['suite'], 'query_packs': LOCK['query_packs'], 'security_severity_minimum': LOCK['security_severity'],
               'counts': counts, 'coverage': coverage, 'findings': findings}
    (output / 'summary.json').write_text(json.dumps(summary, indent=2) + '\n')
    if findings:
        raise RuntimeError('CodeQL high/critical findings block this candidate')
    return summary

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path)
    args = parser.parse_args()
    cli = verified_cli()
    parent = ARTIFACT_ROOT
    parent.mkdir(parents=True, exist_ok=True)
    output = args.output.resolve() if args.output else Path(tempfile.mkdtemp(prefix='codeql-', dir=parent))
    if args.output:
        output.mkdir(mode=0o700, parents=True, exist_ok=False)
    output.chmod(0o700)
    print('Private local CodeQL artifacts: ' + str(output), flush=True)
    with tempfile.TemporaryDirectory(prefix='source-', dir=output) as snapshot:
        snapshot_project(Path(snapshot))
        scan_tree(Path(snapshot), output, cli)
    print('PASS: local CodeQL security gate (no uploads)', flush=True)

if __name__ == '__main__':
    try:
        main()
    except (OSError, ValueError, KeyError, TypeError, RuntimeError, subprocess.CalledProcessError) as error:
        detail = str(error) if isinstance(error, RuntimeError) else type(error).__name__
        print('FAIL: CodeQL gate: ' + detail + '; inspect local artifacts/setup', file=sys.stderr)
        sys.exit(1)
