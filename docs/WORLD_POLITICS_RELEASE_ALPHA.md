# World Politics — WP80 alpha release qualification

Scope: private headless alpha, canonical France investiture 2027. This is NOT a production release or a claim of comprehensive real-world simulation. No UI and no merge into generic engine.

## Acceptance gates

- Fresh dedicated MariaDB installation with migrations 001–026 (014 excluded: maintenance-only).
- All three GitHub jobs green on the exact revision: headless contract, isolated MariaDB, real HTTP.
- Score, deterministic twenty-year synthetic horizon, world-clock recovery, terminal freeze, session scope and SQL integrity regressions.
- Offline `WP_RELEASE_AUDIT=YES php games/world-politics/bin/audit-campaign.php WORLD_ID` using dedicated read-only database credentials; refuses pending/unranked worlds. It verifies domestic, diplomacy, opinion, organizations, economy and seven institutional states using their existing restore rules and recomputes the committed score. It is not a comprehensive journal/receipt, security or production-capacity audit.

## Additional qualification (WP87)

The read-only audit additionally recomputes the experimental household account on worlds with the pinned historical reference; the report includes its SHA-256 digest and committed window. This check does not validate economic calibration or authorize an official leaderboard. Existing worlds without a household reference are not backfilled.

## Bounded operator maintenance

With dedicated credentials limited to the session and HTTP quota tables, run
`WP_ALPHA_MAINTENANCE=YES php games/world-politics/bin/prune-alpha.php`.
At most 500 expired sessions and 500 conservatively obsolete rate-limit
buckets are deleted per invocation. The legacy mixed-duration quota schema
requires a conservative cutoff; this is not a storage capacity guarantee.
Never run with generic engine credentials. This does not prune world journals.
The private-alpha HTTP CI additionally takes a logical dump of its disposable
MariaDB database after tests, imports it into a distinct schema, and compares
the offline campaign audit before and after using a SELECT-only credential.
This is a CI restoration drill, not a disaster-recovery validation of the
target environment or proof of recovery-time objectives.

## Source handoff (WP97 partial)

From a Git checkout, run `bash games/world-politics/bin/package-alpha.sh FULL_COMMIT_SHA /tmp/world-politics.tar.gz`. The archive contains only the tracked game, WP tests, WP documentation and existing attribution/license files. Its embedded Git commit and deterministic gzip output are checked; CI runs selected tests against the extracted tree. This is not a dedicated repository, a versioned engine integration or a production installer. No release tag or official ranking is authorized by this script.

## Existing databases

Backup and restore-test first, stop writers and recover pending ticks; apply the missing reviewed migrations through 026 using a dedicated migration account. Do not use fresh installer against a populated schema. DDL is not globally atomic. Old saves retain score_enabled=0; no silent leaderboard backfill. Verify schema, reopen only after smoke tests; restore backup if migration fails. No database migration has been applied to any user system.

## Grouped finalization — partial delivery WP88–WP97

Rolling tax sensitivities and scenario extrema are diagnostics only; future commitment maturity buckets reconcile fictional outstanding amounts without booking them. Password rotation authenticates the current secret and revokes all existing sessions; recovery is now separately supported by a pre-enrolled one-time secret (WP98); MFA remains unsupported. No claim of full completion of the ten work packages.

## Outstanding release blockers beyond the private alpha

- Real household disposable income per UC calibration, uncertainty and fair official competition; current ranking experimental.
- Curated historical programme content and mechanical programme effects (creation-time candidate identity selection is implemented), playable foreign constitutional systems, independent upper chambers and presidential races, interim governments.
- Real international obligation/creditor schedules, guarantees, enforceable accounting and diplomatic calibration; synthetic fixtures cannot substitute for contract inventories.
- Corrective budgets and autonomous certified legislative path (ordinary annual series 2028–2047 implemented for new canonical worlds) (the 2028 legal-to-fiscal activation is covered by an isolated DB regression), national service law and administrative timing; no tactical security simulation.
- Capacity and multi-node stress, MFA, invite rotation, log/alerting, backup restoration in target environment, independent security review, dedicated repository and versioned engine dependency. No deployment or commercial readiness claimed.

## WP98 account recovery

An authenticated account can replace its optional recovery code after current-password verification. The 256-bit random secret is displayed once, only its SHA-256 digest is persisted, it expires after 365 days and is consumed atomically with password reset and session revocation. Ordinary password rotation deletes it. No email-based recovery, operator impersonation, or MFA. Apply migration 026 before starting this revision. HTTP tests cover replacement, account binding, replay, digest storage and session invalidation.

## WP99 limited corrective finance route

New canonical campaigns pin a separate in-year corrective catalogue (state health credits, fictional ±920 million EUR over the fourth quarter). The player requests a distinct bill; both chambers, final adoption, review and publication precede the fixed 1 October scenario effective date. It does not replace the annual budget. For 2028 onwards an annual published law must exist. Domestic authorization still requires the exact legal instrument and proposal. Old saves do not acquire the new catalogue or score cohort. This is a bounded finance-law pilot, not a full LOLF model. Reference: https://questions.assemblee-nationale.fr/synthese/fonctionnement-assemblee-nationale/travail-legislatif/l-examen-parlementaire-des-lois-de-finances .

The isolated MariaDB suite also replays a certified corrective law, injects a receipt failure to check atomic rollback, proves expenditure starts on 1 October, and verifies that the exact 92-day appropriation ends at the next fiscal year. This test uses synthetic state only.

## WP100 bounded multi-process qualification

CI starts two HTTP PHP processes sharing an independent MariaDB schema. It races identical world creation, performs 48 concurrent-client committed clock reads, then races one-time recovery and verifies token revocation on both processes. This is a shared-database concurrency smoke test, not a production load or distributed network benchmark.

## WP101 historical candidate programme subset

New campaigns pin a versioned 2022 reference with two concise, sourced measures per candidate, limited to employment, wages and retirement. All twelve candidates receive equal coverage. Source: Public Sénat, 22 March 2022 (updated 4 April 2022), https://www.publicsenat.fr/actualites/politique/emploi-retrouvez-les-principales-propositions-des-candidats-a-la-presidentielle. The official CNCCEP manifesto index remains linked. This is a partial secondary-source summary, not complete manifestos, not 2027 platforms and not automatic policy effects. Legacy reference packs remain replayable; new pinned hashes define a distinct leaderboard cohort.

## WP102 managed universe pilot

Migration 027 adds a disabled technical owner, serialized universe pool, unique country/account slots, append-only control periods and durable join receipts. A full pool creates a spare canonical world in the same transaction. Existing solo campaigns and scores are unchanged; managed worlds do not enter the solo leaderboard. Pilot slots cover FR, DE, ES, GB, IT, JP and US; foreign commands are limited to the existing diplomacy API. Members cannot advance the common clock or grant membership. Release and claim preserve all world state. The per-universe and global standings use best single control-period length, with composite score null, pending national tenure and comparable income data. No claim of full executive autonomy or real-world multiplayer readiness: server scheduler, AI delegation, successor continuity and comparable country scores are further acceptance gates.

## WP103 server clock and diplomatic delegation

Migration 028 pins a default game day per real hour for managed universes. Empty spare worlds remain at the opening day until first claim; activated worlds continue after all players leave. Run `WP_UNIVERSE_TICKS=YES php games/world-politics/bin/tick-universes.php` from a server scheduler (e.g. each minute), with dedicated credentials. The worker uses per-world MariaDB advisory locking, existing recoverable clock fencing and a persistent next-day checkpoint, one due day per world per invocation. No real cron was installed. Diplomacy fallback now handles incoming proposals to playable slots using a persisted cooperative/balanced/cautious fictional programme, with accepted manual diplomacy actions suppressing fallback for that country for the next tick. A claim/release inherits the programme; it never overwrites committed policy or government state. This does not yet implement autonomous legislation, every policy domain, or successor continuity after the French terminal campaign. Existing solo behaviour remains unchanged.

## WP104 player-specific composite ranking pilot

Migration 029 adds committed France income checkpoints and per-control-period scores. Every new French claim starts with score zero and its current rolling household proxy as the opening index. Each coherent world-day publication updates the checkpoint and the current human period transactionally, without attributing pre-claim control time or absolute income gains. The exact gain is floor(10000*current/opening)-10000; the tenure component is the existing 1000 points per mean Gregorian year, floor and nonnegative total. Former periods stop updating at release. Both universe and global rankings expose comparable France-only composite cohorts; one best period per player, no summing across worlds, no technical service account. Other countries are not awarded invented economic points. The all-country duration standings remain provisional. Legacy periods without an opening checkpoint are never backfilled. This is not calibrated real household income or official competition.

## WP105 national termination isolation

New managed universes pin continuity of social observations after the original French campaign ends. The former campaign and presidency are frozen, its human control period and score close atomically at the committed terminal day, its access is revoked, and other countries continue on the same world clock. The French slot is explicitly `succession_pending`, not falsely advertised as an elected successor. No diplomatic autopilot acts for that unresolved national slot. Full independent post-mandate succession and recurring playable French presidents remain open; pre-WP105 managed worlds are not silently rewritten. Solo campaign termination remains unchanged.


## WP106–WP110 grouped increment

Apply additive migration 031 before deploying this managed-world service; stop the tick worker and use the reviewed backup procedure. New universes pin electoral succession v2; existing v1 histories remain unchanged. An ordinary resolved fictional election closes the outgoing office at handover and installs the successor alias as a new AI-controlled office on the next published day. Optional French domestic presets create proposals only; certified legal activation is still required. Use the read-only CLI audit-universe.php UNIVERSE_NO with a dedicated SELECT-only connection and a coherent committed day. Composite rankings remain experimental and France-only. See the remaining-backend document for residual gates.


## WP111–WP113

Apply additive migrations 032 and 033 after 031 with workers stopped and a tested backup. Newly allocated universes pin 43 diplomatic slots and continuity v3; historical rosters and election rules are unchanged. France can reopen after an undisputed fictional vacancy election (the scenario's +21/+35/+36 dates, not actual official dates). Former rights and scores remain closed. New narrative events are tied to the incumbent mandate. Weekly fictional diplomatic initiatives are bounded and non-binding. National policy and economic scoring remain qualified only for France; foreign slots provide diplomatic control, with national institutional clocks for the original seven countries. See the backend remaining document for residual release gates.


## WP114

The expanded French programme catalogue requires no additional schema migration after 033. New universes and newly invested successors use the fictional governance profile; existing selected presets are unchanged. Executable profiles use the normal legal aggregates and domestic authorization ledger, never a client-supplied proof. GET /universes/{universe_no}/programme is membership-scoped and returns a coherent committed programme report. Manual and the older two proposal-only profiles remain available. Full semantic political programme execution is not qualified.


### WP114b

Apply migration 034 before deploying this increment, following the established stopped-worker and backup process. POST /universes/{universe_no}/programme accepts an exclusive France-only agenda object; GET returns its decoded fields and direct action receipts. The aid choice is a one-time discretionary scenario objective, not cancellation of existing obligations. Custom programmes remain deliberately bounded and do not infer real politicians' decisions.


### WP114c

Apply additive migration 035 after 034, using the documented stopped-worker backup procedure. Optional extended fields preserve legacy programme encoding; the fictional cabinet, revision, industrial and commitment objectives are explicit opt-ins. A request is not an appointment, enacted revision or accepted treaty amendment. GET programme exposes the relevant existing domain records and direct receipts. Historical measures without reviewed mappings are not automatically executed.


### WP114d

No new migration after 035. POST programme accepts measure_ids as an alternative to agenda or domestic. The executable_measure_catalog returned by the lobby and programme report is the authoritative list of reviewed objective IDs, not a claim that any historical manifesto can be executed. Unsupported IDs and conflicting objectives return 422. Exact historical candidate progress does not infer a measure from political affiliation or free text.


### WP114e

New managed universes provision 24 exact historical social policy entries and an independently pinned legal catalogue. No SQL migration is required. Existing universes without that pin reject social programme configuration with 409 rather than silently alter assumptions or historical hashes. The mixed objective IDs use the existing programme field. Historical measures remain fictional aggregate proxies; actual statutory entitlements and full manifestos are not qualified. WP114 remains open.


### WP114f–k grouped increment

Migration 036 adds the model_upgrade journal operation. With workers stopped and a verified backup, run php games/world-politics/bin/upgrade-social-universe.php UNIVERSE_NO for a replay-verified dry run, and explicitly append --apply only after reviewing the digest transition. No startup rewrite. Existing active legal proofs and past rows are retained; pending unconsumed proofs or an advancing fence refuse the upgrade. Newly provisioned worlds use the richer social scenario immediately. Source register and model assumptions remain versioned; this is not statutory or behavioral calibration. WP114 remains open.


### WP114 social law lifecycle

The social_lifecycle_catalog adds ten explicit repeal objectives to the 24 historical objectives. The same measure_ids contract accepts these IDs. Repeals use ordinary legislation and repeated instruments are uniquely identified, preserving original receipts and past rows. Old worlds without the social economy pin reject the new objectives. The existing institutional bill count limit still applies.


The social migration dry-run also reports the old and new ranking cohort hashes. Applied migrations reclassify the current French control period without changing its accumulated score, and retain all closed periods. No official cross-model comparison is claimed.


Migration 037 widens programme storage to 40 characters for extended canonical mixed profiles. The existing maintenance command adds primary quantitative entries to older pinned universes, verifies replay and isolates the score cohort. New scenario parameters are assumptions, not verified actual unit costs. No production universe has been migrated by this change.


Apply migration 038 after 037. Extended canonical masks retain old profile encodings; primary-family repeals require the corresponding pinned base catalogue entries. The wider catalogue bound is restricted to cohort-model scenarios. The quantitative routes remain uncalibrated scenario models.
