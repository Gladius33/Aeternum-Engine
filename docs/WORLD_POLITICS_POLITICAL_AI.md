# WP34 — deterministic political AI pilot

fr-political-v1 is opt-in. It retains the seven-country clock and fictional
WP33 party and district data. Existing worlds are not silently upgraded.
The planner enumerates up to 4095 subsets of at most 12 represented parties.
For each it intersects policy tolerance intervals, computes an integer
seat-weighted compromise, and ranks viable majorities ahead of minorities.
Tie breaks are deterministic. Up to four alternatives are exposed.
France receives advice, never automatic coalition commands. Foreign
parliamentary pilots negotiate once at the election-result boundary, but
the United States does not use parliamentary executive formation.

The opposition projection classifies parties by coalition alignment and
computes hypothetical group yes/no/abstention counts on two illustrative
programmes. It is not individual roll-call evidence, a real voting forecast,
or a legal legislative result. Neither an AI agreement nor a projection
authorizes a cabinet or policy. A trusted formation certificate, validated
through the existing ElectoralInstitutionalAuthorizer interface, can update
the comparative incumbent if the constitutional state has no unresolved
procedure. The bridge is journaled and replayable. The private HTTP gateway
still cannot certify formation or supply clerk evidence. France's distinct
constitutional roster is not yet wired to this bridge.

Remaining: verified clerk implementation and full legal formation per
country, French WP28 individual-deputy integration, durable party evolution,
repeated elections, policy outcomes and governing performance feedback.
