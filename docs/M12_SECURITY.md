# M12 — security/resilience/capacity qualification (IN PROGRESS)

## First real increment
- JSON-only response policy uses immutable reusable headers for all API routes,
  including exceptions and plaintext rejection.
- Negative loopback HTTP tests exercise cleartext denial on multiple endpoints,
  no HSTS without trusted TLS, no-store, nosniff and strict JSON CSP/CORP.
- Unit regression covers safe header names/values and TLS policy.
- Separate CI checks pinned npm dependencies including development toolchain.

## Second security hardening increment
- StrictJson validates UTF-8, JSON syntax and depth using the native PHP parser,
  then performs a bounded second lexical pass rejecting duplicate keys even when
  the duplicate has a different Unicode escape spelling.
- Public auth, social, economy payloads and trusted pack/world manifests share
  the same fail-closed parser. Existing API error responses remain 400 invalid_json.
- Regression covers nested objects, escaped braces, generated duplicate corpus,
  rejected ambiguous pack identity and a real MariaDB-auth HTTP negative case.

## Not yet passed
- Complete authorization/authentication/CSRF/CSP/chat/market security audit.
- Reproducible fuzzing, concurrent races, replay and crash-recovery validation.
- Measured load limits, failure thresholds, performance and capacity report.
- Dependency advisory review and remediation of any critical/high finding.
- Exact-head PHP, MariaDB, Chromium and M11 lab CI all green.
- Authorized staging installation and restore (not a production deployment).

Optional scoring, combat protection and bot capabilities are documented in
[OPTIONAL_GAMEPLAY_MODULES.md](OPTIONAL_GAMEPLAY_MODULES.md), **not implemented**.
