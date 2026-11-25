# F01 — Developer SDK and compatibility boundary

The independent `sdk-observatory@1.0.0` sample is an opt-in demonstration,
not a default fourth player world. It contains only data and typed schemas.
The reviewed survey command/event pair is selected explicitly through the
same module registry as the three reference games. No new thematic branch
is required in economic dispatch, scheduler, HTTP routing or provisioning.

## Developer commands

```sh
php bin/pack-sdk.php scaffold my-pack 1.0.0 'My Pack' > manifest.json
php bin/pack-sdk.php validate manifest.json
php bin/pack-sdk.php validate games/sdk-observatory/versions/1.0.0/manifest.json survey-v1
php bin/pack-sdk.php upgrade-plan old.json new.json --from=survey-v1 --to=survey-v1
```

Scaffold emits data-only JSON, validate uses the actual loader and composed
runtime rules, and upgrade-plan compares immutable IDs, manifest digests,
module selection and effective keys. It is read-only and does not grant
authority. Module IDs must be reviewed and compiled into the application
catalog; manifest strings are never executed. Use `pack-inspect.php` for a
configured world. Keep the reviewed artifact and exact private registry
consistent across HTTP, workers, CLI and replay.

## Authoring a module

- Declare the exact scalar command and event payload schemas in the manifest.
- Implement ApprovedPackCommand and ApprovedPackEvent in reviewed PHP; use
  ActorPackCommand only for explicit actor-owned account fields. The scheduler
  checks all declared accounts under the world lock. Engagement and diplomacy
  use the separate existing target policies; delayed hostile effects remain
  denied until an authoritative due-time policy exists.
- New private modules can opt into PrivatePackEffects: state mutations are
  restricted to their namespace and authorized accounts, no resource ledger
  movements, at most one delayed event per command, a strictly future delay
  of at most 3600 seconds, exact world, same namespace, approved event handler
  and declared payload. The event may only affect its own account. These
  checks run before durable effects are accepted and during event replay.
  The guard implementation participates in the effective fingerprint for
  opt-in modules; existing module fingerprints remain unchanged.
- Keep pure transitions deterministic, with server-provided logical time.
  Trusted PHP is not a sandbox; review code and artifact dependencies.
  Ordinary legacy and cross-account modules retain their reviewed policies.
- Survey v1 has a single pending slot per account, a ten-second completion
  and at most 100 completed surveys. It neither modifies another account nor
  creates economic resources. A durable retry returns the original receipt.

## Legacy compatibility and version migration

ReferenceGameplayCompatibility owns historical command aliases, actor
admission classification, HTTP rate category, homestead policy, capabilities
and the settlement/industry status adapters. Public API-1 constructor
properties, historical URLs, manifest fields and serialization remain frozen
for existing packs. They are compatibility surface, not the extension SDK.
No existing manifest bytes or migration SQL are changed.

For identical effective rules keys, continue serving the original world.
For changed keys, publish a new immutable manifest version where applicable
and retain all old artifacts; provision a separate world. Never rewrite
the stored rules key or copy a canonical snapshot into a different rules
history. Keep old worlds replayable. Before operational changes create a
coherent backup; rollback restores the prior matching code/configuration and
original world. A stateful transformation between incompatible gameplay
versions requires its own reviewed migration and replay proof; no generic
cross-version conversion is authorized.

## Acceptance

- Unit tests: scaffold, exact fingerprints, immutable version planning,
  strict schema, private state/event guards, delayed envelope, invalid
  completion and duplicate denial.
- MariaDB: independent actors, authenticated commands, ownership rejection,
  serialized identical receipts, one pending event, logical-time completion,
  four independent competing writers, exactly one winner, deterministic
  journal replay, and rejection of handler-less historical rules.
- Truce fixture compares serialized HTTP receipts rather than PHP object
  identity and checks that retry does not create another journal entry.
- Existing medieval/industrial/guild, security, migration, restoration and
  browser suites remain required on the exact candidate SHA.
