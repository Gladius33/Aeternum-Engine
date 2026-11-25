# WP33 — electoral and coalition pilot

The opt-in fr-electoral-v1 scenario pins seven national clocks, the previous
comparative modules, and one fictional election cycle per country. Prior
scenarios retain their snapshot shapes. No new SQL migration is needed:
the electoral subaggregate is stored and journaled by PdoInstitutionalStore.

Integer vote totals, turnout, blank/invalid ballots, district-level seat
allocation, majority threshold, pending runoffs and deterministic restoration.
France-inspired two-round single-seat core: absolute majority of valid votes
and at least 25% registered in round one; 12.5% registered qualification,
top-two fallback, plurality in round two. Source: Code électoral L126 and L162.
The fixture uses a seven-day gap, no withdrawal handling and lexical tie
resolution, not actual French tie law. GB/US use fictional plurality districts
(UK reference https://www.parliament.uk/about/how/elections-and-voting/voting-systems/).
DE/ES/IT/JP use illustrative D'Hondt proportional lists, NOT their complete
actual electoral laws. No claim of current parties, candidates or seat counts.
The electoral aggregates have 100 seats, compatible in count with the earlier
fictional lower-house fixture. They do not yet replace individual WP28 deputies
or WP32 vote evidence. Preset vote tables are public scenario configuration,
not an opinion forecasting system or secret ballots. A district has one
reported decisive ballot in final totals; do not interpret it as national
first-round popular vote.

Fictional party coalition negotiations evaluate each party's declared policy
tolerance and count only accepting seats. Majority agreements do not appoint
a government; US legislative majorities do not use the coalition-formation
route. A separate trusted ElectoralInstitutionalAuthorizer may provide a
scope/date/candidate-bound formation certificate. The result is a certified
scenario formation record, not an automatic legal appointment, replacement
of the comparative incumbent or activation of a policy instrument. This
provider is not yet implemented for public HTTP.

Private HTTP owner can negotiate the French fictional coalition via the
existing institutions/decisions route using electoral_coalition, current ETag
and idempotency key; no player can submit vote totals or certify formation.
Other countries remain read-only. The full French parliamentary model remains
a separate module. Remaining work: real electoral districts, electoral calendars,
party and candidate data, evolving electorate and multiple election cycles,
national electoral laws, minority cabinet procedures, authenticated clerk and
government office handover, integration with WP28/WP32 and cabinet roster.
