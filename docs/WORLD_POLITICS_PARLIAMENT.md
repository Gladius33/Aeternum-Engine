# WP43 — parliamentary lifecycle

Opt-in fr-parliament-v1; previous scenarios and schema unchanged. The
initial fictional Assembly remains in place until the simulated two-round
legislative election completes. The French election fixture now covers 577
single-seat districts (other scenarios retain 100-seat fixtures). The
replayed district results determine each new deputy's group and seat.
The old roster, individual roll calls and signatures are archived with a
hash. No automatic prime minister appointment or confidence is inferred.
Installation occurs on the first coherent daily tick at/after final results
with no unresolved responsibility proceedings. This is a pilot deferral,
not an assertion about the actual end of French parliamentary mandates.
One election only; dissolution/repeated elections and vacancy scenarios
remain open. Historical archive and electoral input are replay checked.

The existing FrenchSittingCalendar validates the clerk-certified fictional
sitting days and exact UTC vote slot against Article 154 and the 48-hour
interval, including Paris civil dates. Both spontaneous and linked censure
filing require vote_at and sitting_days. Only one scheduled censure at a time
in this pilot. The clock resolves it from the existing 577 individual AI
ballots at the slot (published on the first subsequent daily tick).
No client-provided tally or separately certified manual vote. The filing and
slot are one journal operation; invalid slot rolls back filing. A motion
already moot is archived as such. Confidence still uses clerk-certified
agenda events.

Ordinary session boundaries follow Article 28 with a simplified weekday
working-day calendar (not actual public holidays or parliamentary sittings).
The October session is renewed automatically during daily advancement,
resetting session quotas through the existing constitutional aggregate.
An extraordinary session requires a certified opening via assembly_session,
a bounded interval outside ordinary session and no pending proceedings;
the twelve-day cap is conservative and does not cover every Article 29
exception. assembly_coalition records a certified fictional agreement
for existing groups, with immutable historical votes. No public write
route for these events; existing legislation clerk CLI. Read assembly
endpoint includes lifecycle, archived roster and scheduled slots. Game
clock remains daily; administrative multi-year jumps are not a supported
calendar workflow. The pilot does not yet handle the 120 sitting-day limit,
statutory holidays, repeated legislatures, actual deputy election data,
automated coalition bargaining, or all extraordinary session rules.
Sources: https://www.legifrance.gouv.fr/loda/article_lc/LEGIARTI000006527495
and https://www.assemblee-nationale.fr/dyn/synthese/fonctionnement-assemblee-nationale/travail-legislatif/la-procedure-legislative
