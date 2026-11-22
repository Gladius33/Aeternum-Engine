# Reference game packs

- reference-medieval: settlement economy (wood and grain).
- reference-industrial: industrial economy (steel and energy).

Both remain immutable data-only API 1 manifests, validated in unit,
MariaDB and Chromium CI. See ../schemas/game-pack-v1.schema.json.
Keep exact manifest bytes and compatible engine code for existing pinned worlds.

New operator-approved packs and worlds are configured through the strict
registry described in ../docs/M02_SDK_AND_REGISTRY.md.
The third SDK pack lives only under tests/fixtures, not as a playable game.
