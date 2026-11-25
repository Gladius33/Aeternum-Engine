# F01 — Reviewed module catalog and version pins

The application now accepts an optional operator-owned module registry via
`AETERNUM_MODULE_REGISTRY`. This file is separate from the existing world
registry (`AETERNUM_WORLD_REGISTRY`). Both must reside outside the public
document root, be private to the operator and be identical for HTTP, CLI,
CRON and workers. Unset the module variable to retain default composition.

## Reviewed module selection

Only code explicitly included in ReviewedModuleCatalog may be selected.
The first registered module is `bilateral-truce-v1`, compatible with packs
that explicitly declare the two diplomacy schemas and enable diplomacy.
The registry cannot select PHP paths, class names, arbitrary callbacks or
executable manifest content. Each world has a unique entry with the exact
pack SHA-256, each reviewed module SHA-256 and the effective rules key.
Unknown fields, duplicates, wrong hashes and incompatible packs fail closed.

Inspect a configured new world before provisioning:

```sh
php bin/pack-inspect.php guild-new bilateral-truce-v1
```

The output is read-only metadata and candidate fingerprints, not a review
or authorization. Verify the module artifact through the release review.
For the private JSON registry, use the output fields `world`,
`pack_sha256`, `rules_key` and `modules` (omit `pack` and `version`).
Wrap these entries in an object with `module_api` equal to string "1"
and a `worlds` array. No registry entry is necessary for a world without
extensions. Deploy the same reviewed code and registry across entrypoints.
Do not regenerate fingerprints automatically to bypass a mismatch.

## Compatibility and rollback

Existing manifests, version directories, rule keys and legacy command
names remain unchanged. ReferenceGameplayCompatibility now owns the
frozen thematic adapter table rather than generic economic routing.
New namespaced modules use the reviewed extension contracts.

A change to a pack or executable digest changes the effective rules key.
Never edit a populated world's pin to enable, disable or upgrade a module.
Provision a new world for a different key; retain historical artifacts
for the original world. A rollback to the previous code/configuration
must restore matching pins and, if persistence was changed by a future
migration, use a rehearsed coherent backup. The scheduler rejects a
mismatching key. In-place cross-version state migration is outside this
increment and remains subject to explicit compatibility/replay design.

## Verification

Unit cases cover exact hashes, default/selected worlds, unknown module,
duplicate entries, incompatible pack and actual private registry bootstrap.
MariaDB cases cover selected truce module, authenticated ownership,
durable idempotency, canonical replay and rejection of removed modules.
The existing medieval and industrial campaigns exercise unchanged aliases.

## Remaining F01

Complete generic theme boundaries (including data model and status API
adapters), independent SDK scaffolding and additional command/event
examples, side-effect/concurrency policies, explicit migration tooling
and exact-SHA qualification. See [the active plan](FINALIZATION_ROADMAP.md).
