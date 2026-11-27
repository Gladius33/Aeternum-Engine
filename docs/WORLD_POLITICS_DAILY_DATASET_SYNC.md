# Daily Game → Research synchronization

## Policy and scope

All supported game worlds may contribute synthetic simulation observations,
irrespective of whether their human participants use a free or Premium game
account. The game subscription is never checked for dataset enrollment.
Set WP_DATASET_AUTO_CAPTURE=YES on the game clock processes **only after**
the applicable data-use notices, lawful processing basis, commercial reuse
terms and release safeguards have been approved. It is disabled in the
template. An explicit disabled world record takes precedence over automatic
enrollment. Existing world exclusions are preserved. The legacy table
wp_game_dataset_consent represents a technical release flag, not proof of
individual legal consent. No historical backfill is performed by this change.

The existing signed numeric game aggregate schema covers supported French EUR
domestic observations only; it is not a complete international, individual
player, or decision-level game dataset. Private account identifiers, emails,
IP addresses, credentials, messages, payments and free-text biographies
are not exported. Pseudonymous world aliases still require a disclosure-risk
review before commercial publication. Research Standard sees its own
research data; Pro may access the eligible game feed through the existing
metered /research/v1/datasets/game-worlds endpoint. No direct game database
access for research customers.

## Installation and scheduling

Apply gameplay migration 044 and add the Research checkpoint table from the
updated fresh-install schema before enabling automatic capture. Existing
installations require reviewed migration; pause writers while introducing
the sequence fence. The clock appends observations in the same transaction
as the committed day. A singleton lock orders outbox sequence allocation
across worlds, preventing missed earlier uncommitted sequences.

Use a dedicated synchronization service environment (not the web service
environment), with source SELECT-only access to wp_game_dataset_outbox and
wp_game_dataset_consent, Research write credentials for the destination,
independent bridge/evidence keys, stable WP_DATASET_SOURCE_ID, and
WP_DATASET_SYNC_MAX_PAGES (default 100, up to 1000). Source IDs must not be
reused for rebuilt source databases. Keep the environment file outside web
roots and readable only by the dedicated account.

Example daily cron at 03:15 server local time (adjust path/timezone):
```cron
15 3 * * * /usr/local/sbin/wp-dataset-sync
```
Example wrapper /usr/local/sbin/wp-dataset-sync:
```sh
#!/bin/sh
set -eu
umask 077
set -a
. /etc/world-politics/dataset-sync.env
set +a
export WP_DATASET_OPERATOR=YES
exec /usr/bin/php /opt/world-politics/games/world-politics/bin/sync-game-dataset.php
```

The job imports signed batches of at most 100 rows, records a durable
Research cursor only after a successful batch, and resumes after interruption.
A crash after import but before checkpoint repeats the batch safely.
One MariaDB advisory lock per source prevents concurrent sync workers.
Withdrawn aliases are skipped in automatic ingestion, never reinserted.
An exhausted batch budget exits 75 with backlog_remaining, allowing an
operator to rerun or schedule an additional catch-up. Other errors exit 1
without advancing past the failed batch. Monitor exit status, backlog and
last_success_epoch; do not log payloads or credentials. A disabled source
world is not exported; any already imported rows require the existing
withdraw-game-dataset operator command. No automatic cross-database
withdrawal propagation or retention cleanup yet.

No cron is installed by GitHub CI; deploy and schedule on the actual server.
