# F06 — Portable distribution and operator handoff

## Requirements and deployment model

Use PHP 8.4 with PDO MySQL, Argon2id, JSON and standard PHP CLI functions;
MariaDB 11.4 is the qualified database. The deployment bundle includes
Composer autoload and compiled player JavaScript; Node, npm, Composer,
Git and test frameworks are build-time only. A database client,
mariadb-dump, Bash, GNU coreutils, gzip and tar are needed for the supplied
backup and archive tools. Where shared hosting restricts those tools,
use the operator's equivalent backup/restore facility and verify its
result against the same acceptance criteria. No root privileges are
needed for the application; the hosting operator creates databases and
runtime accounts.

Use separate paths (examples, replace with your actual installation):

    /srv/aeternum/releases/<source-sha>/       immutable package
    /srv/aeternum/releases/<source-sha>/public only web document root
    /srv/aeternum/private/                    private config, logs, backups

On shared hosting choose equivalent private paths outside any public
directory, then point the site's document root to the package public/
directory. Never upload the whole bundle beneath an existing public
document root, and do not rely on a deny rule to protect private files.
If the provider cannot change the document root or keep private code
and data outside it, that target does not meet the supported layout.
On a managed server the deploy owner owns immutable code; the PHP and
worker identity only needs read access. Private directories should be
0700 or 0750 with a dedicated trusted group, secret files 0600 or 0640.
No web-accessible .env, credential file, backup, registry, vendor tree
or operational console. Do not chmod 777. Do not give the application
the migration or backup account's DDL privileges.

### Apache or Nginx

Configure TLS with a trusted certificate, redirect HTTP to HTTPS before
the API, and ensure the actual web server sets HTTPS=on for PHP only on
trusted TLS ingress. Never accept a client's X-Forwarded-Proto as proof.
Apache 2.4 needs mod_rewrite and mod_headers, AllowOverride permitting the
distributed .htaccess (or equivalent vhost rules). Use DirectoryIndex
play.html and route non-file /v1 requests to public/index.php. The
application returns JSON with strict security headers; preserve the
Authorization header through FastCGI.

Illustrative Nginx location policy inside the HTTPS server block; replace
the PHP-FPM socket path, configure your own certificate and HTTP redirect:

```nginx
root /srv/aeternum/releases/REPLACE_WITH_SHA/public;
index play.html;
autoindex off;
location ~ /\. { deny all; }
location ~* \.(?:sql|sqlite|db|log|bak|ini|env|pem|key|p12|pfx)$ { deny all; }
location / { try_files $uri $uri/ =404; }
location /v1/ { try_files $uri /index.php?$query_string; }
location = /index.php {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    fastcgi_param HTTPS on;
    fastcgi_param HTTP_AUTHORIZATION $http_authorization;
    fastcgi_pass unix:/run/php/php8.4-fpm.sock;
}
location ~ \.php$ { return 404; }
```

These are configuration examples, not automatic modifications of any
server. Validate syntax, effective vhost, actual HTTPS and private-path
denials before exposing the application. For a reverse proxy only trust
specific peer addresses through AETERNUM_TRUSTED_PROXY_IPS; the outer
proxy must strip client-supplied forwarding headers and the origin must
not be directly reachable.

## Build and verify the package

From a clean reviewed checkout with the intended SHA:

```sh
composer dump-autoload --no-interaction
npm ci --prefix client --no-audit --no-fund
npm run build --prefix client
bash tools/m11-package.sh /absolute/private/distribution
```

The archive names the checkout SHA. It excludes tests, CI, lab setup,
Node modules, private configuration, database dumps and disposable lab
credentials. PACKAGE.json records the source and client lock digest;
PACKAGE.SHA256 inventories every other regular file. License and NOTICE
are mandatory and verified. Directory and file permissions, tar order,
timestamp and gzip metadata are normalized. Two independent client
builds from the same locked inputs are compared byte for byte in CI.

Obtain the archive hash from a trusted release record; a checksum stored
beside an untrusted archive alone cannot authenticate it. Run
`sha256sum -c aeternum-<sha>.tar.gz.sha256` in the archive directory,
inspect the archive inventory before extraction, extract to a new empty
private release directory, then run:

```sh
bash /absolute/release/aeternum/tools/f06-verify-package.sh /absolute/release/aeternum
```

Do not extract an untrusted archive as root. Never install over a live
release. The verifier compares the full extracted inventory, rejects
changed/missing/extra files, symlinks and forbidden paths; it is not a
digital signature. Re-run after transfer before switching the webroot.

## First installation

1. Create an empty dedicated database and a separate migration account.
   Provision a runtime account with the existing SELECT/INSERT/UPDATE/
   DELETE permissions on its dedicated database only; no global grants.
   Use separate restricted console and backup credentials. Test the
   runtime account cannot CREATE TABLE.
2. Prepare private environment for CLI and PHP-FPM with the same approved
   registry paths, DSN, DB user/password and TLS ingress configuration.
   Use your provider's secret store or protected environment file; never
   print secrets, shell-trace them or commit them. Explicitly keep
   AETERNUM_PUBLIC_REGISTRATION=0 initially. The preflight rejects test
   switches and unsafe unverified public registration. For verified
   email/invitation policies see M01_OPERATOR_ACCEPTANCE.md.
3. Create a data-only world registry using the installed immutable pack
   bytes and SHA-256. Add module registry entries from pack-inspect.php
   for reviewed modules only; keep the registry outside the release.
   See F01_SDK.md and F04_GAMEPLAY.md. Do not copy private game state into
   a world with different effective rules. Choose a new world ID for
   changed rule fingerprints. Verify with bin/registry-status.php and
   both absolute registry paths.
4. Using the temporary migration credentials run `php bin/migrate.php`
   then repeat and require applied=[]; stop on any dirty migration.
   Switch back to runtime credentials before starting the service.
   Run `php bin/m01-preflight.php --layout /absolute/release/aeternum/public /absolute/private`.
   The operator supplies actual paths; CLI cannot inspect the live
   vhost and does not itself prove TLS or file permissions of all ancestors.
5. Provision the first authorized player through `php bin/provision.php
   PACK WORLD LOGIN`, with a password on stdin (use an interactive
   no-echo read or protected secret, not a literal in shell history).
   Repeat with the same credentials only if needed; do not create fake
   world state. For scored worlds rebuild the derived score index if it
   has not yet been initialized. Use status, alerts and a private player
   smoke test before opening registration.

## Continuous operation

Run `php bin/ops-cron.php` once per minute from private CRON on shared
hosting; do not expose a web endpoint to invoke it. A dedicated server
can schedule the same bounded task more often using a systemd timer
(with one invocation per tick) when a reference game requires shorter
completion times. Avoid concurrent independent schedules for the same
database. The database advisory lease prevents overlapping cycles;
monitor skipped, deferred and failed runs. A one-minute CRON can add
up to a minute of completion delay, more under backlog; short game
durations are not a guarantee of equally short observed latency.

Run `php bin/ops-status.php`, `php bin/ops-alerts.php` and
`php bin/ops-metrics.php` with private monitoring credentials.
Metrics are Prometheus text format for a private collector/textfile
exporter. Exit 0 means no configured alert, 2 means attention required,
1 means execution failure. Use an atomic temporary-file rename on
successful collection and alert on stale collection timestamps or
nonzero exit. Do not expose metrics publicly. Rule mismatches and
uninitialized worlds fail health, migration checksums are verified,
due work and outbox backlog are reported with cap flags. Calibrate
thresholds against F02 evidence and your own workload. Do not configure
an alert sender containing tokens or player data in public paths.

## Upgrade, backup, restore and rollback

- Freeze writes: disable ingress mutations, provisioning, worker/CRON,
  admin writes and other consumers; record exact release, registries,
  database version, schema ledger and target configuration. Make a
  verified backup of DB and separately encrypt/protect configuration and
  secret backups. The operator must ensure no other writer or DDL runs;
  the tools cannot prove a globally quiescent database.
- Use tools/m11-backup.sh with an absolute private destination,
  M11_BACKUP_APPROVED=YES, M11_SOURCE_DATABASE and an external private
  M11_CREDENTIALS_FILE (chmod 600). Retain SHA-256 and off-instance
  encrypted copies with a documented retention policy. A backup is not
  verified until tested restoration.
- Restore only a verified archive into a precreated EMPTY disposable
  database named aeternum_restore or aeternum_restore_SUFFIX with
  M11_RESTORE_DISPOSABLE=YES, M11_RESTORE_DATABASE and matching private
  credentials. The tool refuses nonempty targets and checksum mismatch.
  Use a new schema for another attempt after partial import. Do not
  destroy a live database or restore a dump directly into it.
- Verify the restored database against the frozen source: schema
  ledger, canonical replay, projection, resource ledger, all row
  fingerprints, existing player access and current approved rules.
  Run AETERNUM_VERIFY_QUIESCED=YES php bin/ops-verify.php for approved
  worlds on both sides; compare JSON. Replay is bounded to 100,000
  entries per world; larger worlds need a separately engineered audit
  procedure. The command does not lock all writers for the whole audit.
- Deploy the candidate into a new immutable release directory. Perform
  additive migration on the isolated restored copy before live cutover;
  retain old binaries/registries and pre-upgrade backup. Inspect
  migration failure instead of replaying dirty DDL blindly.
- For rollback, restore the coherent pre-upgrade backup to a fresh
  database with matching historical code/registry. Before switching,
  verify it and reconcile any accepted writes after the backup.
  Switching to an older binary on a newer schema is not a generic
  rollback guarantee. Never silently discard subsequent player actions.
  Resume ingress and workers only after verification and authorized
  decision; track RPO/RTO and record any data loss.

## Cosmetic entitlement events

No actual payments are initiated. bin/admin-console.php test-event
requires the explicit test mode, an authenticated local operator
identity with a scoped manager role, and a unique event ID. Only
profile_badge and ui_theme are supported. Identical replay has no
second effect; conflicting replay is rejected. Revocation uses another
event ID and only changes cosmetic entitlement, never resources or
gameplay. Preserve the license and attribution in every distribution.

A future payment provider integration needs independent approval,
server-side signed webhook verification with replay windows, provider
event IDs, amount/currency/order reconciliation, transactional outbox,
refund/revocation and chargeback policy, sandbox tests, secret rotation
and privacy/legal review. Never accept client-side payment assertions,
do not store card data, and do not route this test console as a webhook.
No real provider adapter or payment credentials are included.

## CI evidence and limits

M11 preserves the existing actual populated pre-031 upgrade and
least-privilege/backup corruption tests, now comparing canonical replay
before/after migration. The added job builds the archive twice, extracts
it, and runs the installed CLI and public front controller against two
separate MariaDB schemas. Tests include two identities, actual guild
module completion, trade idempotence, private read isolation, simulated
grant/revoke/replay and restored login. Complete row fingerprints and
canonical state/projections must agree before reconnect. The HTTP
fixture is loopback-only with a test ingress TLS marker; it does not
prove a deployed certificate or vhost. Existing G08 tests use actual
local HTTPS; external target TLS remains an operator gate.
The package and sanitized evidence are CI artifacts, not a public
release. The independent M13 release acceptance and operator
authorization are not bypassed.
