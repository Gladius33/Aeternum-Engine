# M08 — Private barter history and indexed market discovery

## Authorised API

- `GET /v1/worlds/{world}/economy/market[/after]`: active offers, 50 per page.
- `GET /v1/worlds/{world}/economy/market/search/{give}/{want}[/after]`:
  pair filter and exclusive immutable offer-ID cursor, with no seller private data.
- `GET /v1/worlds/{world}/economy/market/history/{account}[/after]`:
  terminal offers (filled, cancelled, expired) involving an account owned by
  the authenticated, active world member. Foreign accounts return 409.
  Responses give offer terms, seller/buyer and terminal status. Cursor is
  exclusive, ordered by ASCII-binary offer ID, **not by date**. No legacy
  timestamp is fabricated; independently committed pages do not form a snapshot.
- `POST /v1/worlds/{world}/economy/commands`: create, accept, cancel and
  claim commands preserve actor authorization and original retry identities.

## Durability and compatibility

The existing canonical journal, market status, pending refund counters and
resource ledger remain authoritative; historical state formats and replay
semantics are untouched. Every escrow reserve/claim/release and settlement
is committed atomically with its receipt; cancellation and expiration at
capacity produce durable claimable credit. Closing an offer never reuses its
ID. The worker handles scheduled expiration; market reads hide expired offers
even before due processing, and writes obey the due-event barrier.

Migration 027 adds two indices only. Stop writes, take and validate a full
off-instance database backup, apply with the checksummed migrator, and run
replay and relational consistency checks before reopening. Because MariaDB
DDL may commit implicitly, a failed upgrade requires restoration of the
**complete backup**, not merely rolling back an SQL session.

PHP 8.4, MariaDB, concurrent accepts/claims, legacy migration and Chromium
E2E must all pass on the exact release SHA. Installation acceptance
and V1.0 certification are separate acceptance gates.

## Browser controls

A player can select exact give/want resource filters and browse the market
with a stable filter for each cursor page. The history panel is loaded only on
demand, is account-scoped, and clears its private contents on account/world
switch or logout. Missing history is displayed without fabricating receipts.
Translations are provided in the existing English/French catalogue.
