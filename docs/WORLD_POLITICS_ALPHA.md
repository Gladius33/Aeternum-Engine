# WP30 — private alpha HTTP and accounts

New HTTP entrypoint public/index.php; only public/ is the document root.
Private enrollment secret (minimum 32 bytes), Argon2id, opaque 256-bit
bearer tokens (only SHA-256 digests stored), 12-hour sessions, revocation,
per-peer/account rate limits, body bound, JSON-only routes, TLS unless the
explicit PHP development loopback mode is enabled. No cookies or permissive CORS.

Install on an EMPTY dedicated MariaDB database using bin/install-alpha.php
with WP_ALPHA_INSTALL=YES and WP_DB_DSN, WP_DB_USER, WP_DB_PASSWORD. DDL is not
atomic; if installation fails, inspect and recreate the empty test database.
Set WP_ALPHA_ENABLED=YES and a strong WP_ALPHA_INVITE outside source control.
For local private testing only, WP_ALPHA_LOOPBACK=YES and bind:
`php -S 127.0.0.1:18730 -t games/world-politics/public games/world-politics/public/index.php`.

The fr-sandbox-v1 scenario seeds a national roster, basic FR institutions,
international diplomacy/organizations/economy, domestic assumptions and
world clock in a single MariaDB transaction. The scenario and coefficients
are fictional and pinned; no full real-world data set is claimed.
The owner may create fictional people, choose a scenario head, propose
diplomatic and domestic actions and request one coordinated day. Viewers
are read-only and revocable. Account IDs do not confer constitutional office.
No player access to direct subsystem clocks, legal publication, AI controller,
appointment resolution or constitutional 49.3 endpoints yet. The advanced
France constitutional/Assembly stores from WP28 are NOT mounted or seeded
by this first scenario. The full alpha political gameplay remains incomplete.

Save is automatic in durable state, journal and receipts, not a portable
backup/export. No live account creation on a public service is implied.
Known remaining operational work: independent authorization of governmental
offices, account recovery/password changes, MFA, invite lifecycle, token
rotation, bucket cleanup, reverse proxy trusted-IP configuration, concurrent
stress/backup tests, deployment monitoring, public release review. Rate
limits count all clients behind the same peer; database rate rows need a
scheduled retention process. A single coherent read across all domains is
not guaranteed while another HTTP request starts a tick.
