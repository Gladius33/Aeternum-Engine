#!/usr/bin/env python3
"""Verify a WP125 archive against an exact Git commit, without extracting it."""
import argparse
import hashlib
import re
import subprocess
import tarfile
from pathlib import PurePosixPath


OMITTED = re.compile(
    r'^games/world-politics/data/wp115-(collection-v0\.4(/|\.zip$)|'
    r'fiscal-staging-v1\.json$|wdi-2026-11-27-v1/|'
    r'wdi-economic-social-2026-11-27-v2/|ipu-party-seats-2026-11-27-v[12]\.json$|'
    r'national-election-supplement-v1\.json$)'
)


def verify(commit, archive, full):
    if not re.fullmatch(r'[0-9a-f]{40}', commit):
        raise ValueError('an exact 40-character commit SHA is required')
    resolved = subprocess.check_output(['git', 'rev-parse', f'{commit}^{{commit}}'], text=True).strip()
    if resolved != commit:
        raise ValueError('commit mismatch')
    tree = subprocess.check_output(['git', 'ls-tree', '-rz', commit, '--',
                                    'games/world-politics', 'docs', 'tests', 'LICENSE.md',
                                    'NOTICE', 'WORLD_POLITICS_BRANCH_ONLY.md'])
    expected = {}
    for entry in tree.split(b'\0'):
        if not entry:
            continue
        metadata, raw_path = entry.split(b'\t', 1)
        mode, kind, oid = metadata.decode('ascii').split()
        path = raw_path.decode('utf-8')
        if not (path.startswith('games/world-politics/') or
                re.fullmatch(r'docs/WORLD_POLITICS.*\.md|tests/wp.*\.(php|py)|LICENSE\.md|NOTICE|WORLD_POLITICS_BRANCH_ONLY\.md', path)):
            continue
        if not full and OMITTED.match(path):
            continue
        if kind != 'blob' or mode not in ('100644', '100755'):
            raise ValueError(f'unsupported source entry: {path}')
        expected[f'world-politics/{path}'] = (oid, mode == '100755')
    if len(expected) <= 10:
        raise ValueError('source inventory is unexpectedly small')
    directories = {str(parent) for name in expected for parent in PurePosixPath(name).parents} - {'.'}
    seen = set()
    with tarfile.open(archive, 'r:gz') as bundle:
        if bundle.pax_headers.get('comment') != commit:
            raise ValueError('archive commit mismatch')
        for member in bundle:
            name = member.name
            if name in seen:
                raise ValueError(f'duplicate archive entry: {name}')
            seen.add(name)
            if member.isdir() and name in directories:
                continue
            if not member.isfile() or name not in expected:
                raise ValueError(f'unexpected archive entry: {name}')
            oid, executable = expected.pop(name)
            if bool(member.mode & 0o111) != executable:
                raise ValueError(f'executable mode mismatch: {name}')
            digest = hashlib.sha1(f'blob {member.size}\0'.encode('ascii'))
            with bundle.extractfile(member) as handle:
                for block in iter(lambda: handle.read(1024 * 1024), b''):
                    digest.update(block)
            if digest.hexdigest() != oid:
                raise ValueError(f'Git blob mismatch: {name}')
    if expected:
        raise ValueError(f'missing archive entries: {len(expected)}')
    print(f'PASS exact commit {commit}: {len(seen - directories)} verified files ({"full" if full else "reduced"})')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('commit')
    parser.add_argument('archive')
    parser.add_argument('--with-references', action='store_true')
    args = parser.parse_args()
    try:
        verify(args.commit, args.archive, args.with_references)
    except (ValueError, OSError, tarfile.TarError, subprocess.CalledProcessError) as error:
        parser.exit(1, f'FAIL: {error}\n')
