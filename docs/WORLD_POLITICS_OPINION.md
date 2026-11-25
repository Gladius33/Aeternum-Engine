# WP35 — aggregate public opinion

Opt-in fr-opinion-v1 retains the seven-country political pilot and adds
a French opinion aggregate. All cohorts, initial intentions and coefficients
are fictional. Approval, institutional trust, social pressure, civic
dissatisfaction and electoral uncertainty are independent indices. The
intention distribution uses deterministic largest-remainder allocation and
conserves 10000 basis points; only undecided share changes relative to the
fixed fictional party mix, not individual or demographic targeting.

The fifth world-clock subsystem runs AFTER domestic and national clocks.
It reads verified snapshots through both existing stores, records their
hashes, checks matching world/date, and evolves one day at a time. Only
applied policies affect domestic pressure/economic indices; mere proposals
do not. Missing economic values remain null and are marked as unavailable.
An ordinary dissolution is an electoral transition, not a regime collapse.
A recorded constitutional deadline breach is separate from civic
dissatisfaction. No automatic regime transition, coercive action, election
result modification, or authority grant is derived from opinion.

Migration 021 adds optional pinned opinion participation and a replayable
state plus latest receipt. The existing world fence/checkpoints govern
the new subsystem. Old worlds remain unchanged; apply the reviewed migration
before serving the new scenario. GET /worlds/{world}/countries/FR/opinion
is membership-bound and read-only; there is no public write route.

The alpha still lacks actual enactment and WP28 parliamentary integration
for this scenario. Unit fixtures test enacted vs unapproved policy effects;
HTTP tests exercise proposals, daily steps, replay and access control.
Foreign opinion, real survey calibration, longitudinal party switching,
renewed elections and verified 49.3 event integration remain future work.
