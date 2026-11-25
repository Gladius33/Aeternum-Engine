# API V1 — developer reference for the current engine

The public front controller is the authoritative routing contract. Same-origin
HTTPS; GET for reads and POST for mutations; application/json for JSON writes.
No cookies are required. Do not place bearer credentials in URLs or logs.
A successful response is a JSON object with a data member. Request scope is
determined by authenticated actor and configured world, never a caller-supplied
actor override. IDs are bounded ASCII; URL-encoded alternate path spellings
are rejected. Historical API-1 URLs remain supported.

## Identity and discovery

| Method | Path | Purpose |
|---|---|---|
| POST | /v1/auth/login | login and password; returns opaque bearer |
| POST | /v1/auth/logout | revoke presented bearer |
| POST | /v1/auth/logout/all | revoke actor sessions |
| POST | /v1/auth/register | only approved public invitation or activation policy |
| POST | /v1/auth/activate | verified mailbox enrollment |
| POST | /v1/auth/activation/resend | request another activation |
| POST | /v1/auth/recover | offline recovery code and new password |
| POST | /v1/auth/recovery/rotate | rotate recovery code |
| POST | /v1/auth/password/change | authenticated password change |
| GET | /v1/me/worlds | own worlds, accounts, resources, reviewed packActions/packViews |
| GET | /v1/worlds/W/recipients | bounded active recipient directory |

See [player guide](M13_PLAYER_GUIDE.md) and [security](F03_SECURITY_RESILIENCE.md).
Identity writes must not be blindly replayed following a lost response.

## World and economy

All paths below start with /v1/worlds/W.

| Method | Suffix | Purpose |
|---|---|---|
| GET | /map/territories | world map projection |
| GET | /map/locations/L/links | route graph |
| GET | /map/routes/L/D | bounded shortest route |
| GET | /construction/options | owned sites and permitted types |
| GET | /construction/progression | confirmed stages |
| GET | /buildings/B | own building |
| GET | /economy/balances/A/R | own resource balance |
| GET | /economy/overview/A/R | balance, capacity and upgrade |
| GET | /economy/market | current offers (bounded pages) |
| GET | /economy/market/search/G/R | filter by offered/requested resource |
| GET | /economy/market/history/A | own trade history |
| POST | /economy/commands | durable actor-scoped command |
| GET | /settlement/status/A | historical medieval rules |
| GET | /industrial/status/A | historical industry rules |
| GET | /progression/A | own score when enabled |
| GET | /progression/A/rank | own rank and participant count |
| GET | /economy/pack-view/campaign/A | own confirmed reviewed campaign |
| GET | /premium/me | own test-only cosmetic entitlements |

W is configured world, A account, R resource, B building, L and D map
locations, G offered resource. Never use a directory result as proof of
ownership or permission. All write conditions are independently checked.

Example body on a reviewed campaign world (replace the account and ID):

```json
{"id":"example-research-001","type":"pack.campaign.research","payload":{"account":"example-account"}}
```

The receipt contains requestId, effectiveAt and scheduledIds. Preserve the
exact ID and complete payload across uncertain delivery; changing the ID
may execute another action. A scheduled event is not a completed effect.
The worker alone processes due events. A GET may return a temporary barrier
rather than an inconsistent projection.

See [economy](ECONOMY.md), [market](M08_MARKET.md), [gameplay](F04_GAMEPLAY.md)
and [SDK](F01_SDK.md) for exact types, compatibility and versioned schemas.
No generic raw state or arbitrary code endpoint exists.

## Social and operational limits

The /social/ family provides alliance create/invite/respond, membership,
private messages, blocks, reports and bounded notifications. Refer to
[M09](M09_SOCIAL.md) for payloads, pagination and moderator boundaries.
Administrative commands are local CLI operations, not public HTTP routes.
No actual payments are accepted by this engine.

| Status | Handling |
|---|---|
| 400/413/414/415 | correct malformed, oversized or wrong-media request |
| 401 | discard session; sign in again |
| 403/404 | respect origin/access/missing-resource denial |
| 405 | use the declared method |
| 409 | conflict; only retry explicitly retryable responses with original identity |
| 429 | respect quota window; do not evade |
| 503 | temporary database/identity contention; reconcile ambiguous mutations |
| 500 | generic failure; no raw SQL or secret diagnostics |

Default economy quota 120/minute per actor; writes for market, construction
and reviewed packs have separate 20/minute buckets. Social reads 120/minute
and writes 30/minute; rank 12/minute; auth peer 10/minute plus additional
login/global and enrollment budgets. These are admission limits, not SLA.
Use bounded backoff, never parallelize to evade quotas. Modern client retries
explicitly transient reads and exact durable commands; session-local pending
identity must be reconciled from the server after logout. See [operations](F06_DISTRIBUTION.md).
