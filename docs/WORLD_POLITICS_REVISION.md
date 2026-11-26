# WP39 — Article 89 procedural pilot

Opt-in fr-revision-v1 inherits the presidential campaign and adds a
replay-verified French constitutional revision aggregate. Prior worlds retain
their state shapes. No new SQL migration is required.

Legal references: French Constitution articles 7 and 89:
https://www.legifrance.gouv.fr/loda/article_lc/LEGIARTI000019240655/
and https://qpc360.conseil-constitutionnel.fr/constitution-4-octobre-1958 .
Government projects require presidential initiative on a Prime Minister's
proposal; parliamentary proposals require a certified parliamentary sponsor.
Both chambers must approve identical text. The Congress option is restricted
to government projects; its threshold is three fifths of votes expressed.
Referendum approval requires an absolute majority of votes expressed.
Blank/invalid ballots and abstentions are excluded. No casting vote.
Territorial-integrity infringement, presidential vacancy and definitive
incapacity suspend the legal process. The republican form is protected.

The reviewed pilot catalog contains only two fictional public reporting
clauses. Drafting is not formal initiative. The text and SHA are immutable;
a failed chamber vote ends that draft in this simplified procedure.
Real navette amendments, parliamentary reading delays, quorum, sponsorship,
legislative procedure, the actual electorate and constitutional adjudication
are not exhaustively modelled; the trusted clerk certifies these facts.
The clerk also supplies the world context; this is not automatically derived
from all national modules. Adoption does not change the effective corpus
until certified publication. New clauses are versioned and journaled; they
do not yet change other gameplay mechanics or the presidential term limit.

GET /worlds/{world}/countries/FR/constitution returns revision state and
catalog. The owner can submit only revision_draft with a reviewed template,
through institutions/decisions and its ETag/idempotency protections.
No HTTP certification or vote-writing route is mounted. A local trusted
scenario operator can use bin/certify-revision.php with WP_REVISION_CLERK=YES,
dedicated DB credentials and a JSON evidence file. Evidence includes world_id,
country FR, day, evidence_id, revision_id (null for context), kind,
procedure_certified and data. The clerk is explicitly responsible for
validating fictional procedural facts. The store obtains evidence through
the scoped RevisionInstitutionalAuthorizer. The shared world fence blocks
writes after game over or during a pending transition. The existing
institutional journal and receipts preserve the changes. Domain and HTTP/DB
tests cover the procedure, rejected routes, context suspension, publication,
replay, access and clock coherence. A future worker must produce verified
evidence from actual simulated votes.


WP54r ajoute la clause fictive `remove-presidential-term-limit` : suppression du seul deuxième alinéa de l'article 6, maintien du quinquennat, de l'élection et de la République. La publication après ratification est la seule preuve acceptée par le nouveau calendrier via l'observation du registre institutionnel rejoué; adoption, brouillon et modification tardive d'une candidature verrouillée ne suffisent pas. La certification autonome de la procédure depuis les vrais votes simulés est un jalon WP57–60; le clerk existant reste nécessaire. La forme républicaine demeure protégée par l'article 89.
