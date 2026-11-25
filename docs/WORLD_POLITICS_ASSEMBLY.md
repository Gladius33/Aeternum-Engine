# WP42 — individual Assembly responsibility

Opt-in fr-assembly-v1 extends WP41 without changing older snapshots or
database schema. Reuses FrenchParliament (WP28) with 577 fictional seats,
three immutable fictional groups and deterministic individual AI ballots.
There are no real-person profiles and no player-selected pressure or
concession coefficients. GET /worlds/{world}/countries/FR/assembly
exposes the individual register, signatures and archived roll calls.

Certified 49(3) filing now takes individual signatory IDs instead of a
number; the vote takes only an instant and motion ID. The tally is
computed server-side, bound to the pre-vote constitutional hash and
applied through FrenchConstitutionalCrisis. The older WP41 scenario
retains its aggregate certified input. Independent 49(2) and 49(1)
events use bill_id null, and require the existing trusted legislation
clerk. The clerk certifies agenda, timestamps, Council deliberation and
individual signatures; the engine computes the result. The original
constitutional thresholds and signature quotas are reused. Independent
appointment following censure is supported. Independent proceedings
and a linked 49(3) are serialized in this conservative pilot.
The existing daily opinion projection handles cabinet status without
ending the presidential campaign.

No public certification endpoint is exposed. Local CLI legislation.php
certify accepts assembly_censure_file (at, motion_id, signatories),
assembly_censure_vote (at, motion_id), assembly_confidence_open
(at, statement_id, statement_kind, council_deliberated),
assembly_confidence_vote (at, statement_id), and assembly_appoint
(at, appointment_id, prime_minister, resignation_presented).
Individual ballot generation is automatic upon certified vote event,
not automatic agenda scheduling. Parliamentary sessions remain pinned;
legislative election seat redistribution, authentic sitting calendar,
changing coalitions, human-controlled deputy sessions, general bill
roll calls and a fully autonomous parliamentary scheduler remain open.
Tests exercise replay, legal thresholds, no fabricated tally, scope,
durability and HTTP isolation.