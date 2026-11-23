# M05 — Medieval settlement progression (work in progress)

This is **not** a completed playable reference game. The opt-in `reference-medieval@0.2.0` manifest introduces a first declarative construction progression; the original `0.1.0` bytes remain unchanged and worlds remain pinned by their manifest SHA-256.

## Progression model

| Building | Level 1 (wood/grain; seconds) | Level 2 | Prerequisites |
| --- | --- | --- | --- |
| Lumberyard | 12 / 4; 15 | 45 / 12; 45 | Farm 1 for level 2 |
| Farm | 18 / 8; 20 | 50 / 20; 60 | Lumberyard 1 for level 2 |
| Granary | 28 / 15; 30 | — | Farm 1 |
| Keep | 60 / 25; 60 | 120 / 65; 120 | Lumberyard 1 + Farm 1 for level 1; Lumberyard 2 + Farm 2 + Granary 1 for level 2 |

Costs use the existing atomic construction debit and scheduled worker completion from M04. The keep at level 2 is a **candidate objective**, not yet a server-authoritative win condition. The manifest does not yet attach building levels to population, food consumption, production rates or territorial expansion.

## Operator-controlled opt-in

Bind a disposable world to pack `reference-medieval`, version `0.2.0` and the **SHA-256 of the exact manifest bytes** in the private registry. Never repoint an existing pinned world to this version in place. Existing `0.1.0` worlds must remain on their original digest. Apply and verify migrations in a disposable MariaDB database before any persistent-world experiment.

## Evidence and outstanding work

`tests/m05-medieval-pack.php` exercises manifest loading, deterministic stage prerequisites, malformed-rule rejection and side-by-side version pinning. It runs in `tests/run.php` as part of PHP core CI. The strict JSON schema now documents the existing runtime building extension.

**Not yet proven:** new player settlement provisioning and geography, player-driven building type registration for an approved world, UI onboarding/tutorial, meaningful production/population mechanics, server-authoritative victory objective, full two-player MariaDB and Chromium gameplay, migration rehearsal on populated medieval worlds, and reconnection/replay acceptance. The M05 PR must remain draft until these are implemented and tested; no production deployment is authorized.
