# WP32 comparative constitutional pilot

New fr-comparative-v1 scenario provisions seven clock-pinned national
institutional stores. France retains its previous institutional rules.
DE, ES, GB, IT, JP and US additionally have an optional durable government
accountability subaggregate. The old scenarios remain unchanged.

- Germany: Article 67 constructive no-confidence, absolute majority of
  Bundestag membership and 48-hour delay, appointment of the elected successor.
- Spain: Articles 113–115 constructive motion, one tenth signatures, five-day
  minimum and absolute majority, candidate appointment.
- UK: Commons confidence convention, majority of votes cast; resignation
  or certified dissolution as distinct follow-up actions.
- Italy: Article 94, either chamber, one tenth signatures and three-day
  minimum, loss of confidence and resignation.
- Japan: Articles 56 and 69, Representatives vote and ten-day response
  window; late response records a breach, not an automatic regime change.
- US: House impeachment and distinct Senate trial requiring two thirds
  of senators present; no ordinary parliamentary no-confidence route.

Sources are stored in ComparativeGovernment::rules and InstitutionalRulebook.
The scenarios use fictional chamber sizes and a fictional initial officeholder.
The clerk must certify applicable quorum, candidacy eligibility, notice,
trial procedure and other rules not computed by the aggregate. The clock
uses UTC game days, with minimum waits expressed conservatively as full
24-hour periods. The response window for Japan is a game-day approximation.
These are partial procedural models; the UK conventions, Spanish competing
motions, Italian chamber procedural differences, full US trial/succession,
election procedures and actual officeholding need further detail.

The durable store accepts only evidence IDs from a ComparativeInstitutionalAuthorizer;
the game server must implement a verified evidence service. The private HTTP
gateway exposes comparative countries as read-only; it does not delegate offices
or accept vote totals, appointment decisions or dissolution commands. The advanced
French WP28 parliamentary engine remains separate from this alpha scenario.
The comparative status election_pending is not a full election simulation.
No automatic conversion of confidence defeat into regime collapse.
