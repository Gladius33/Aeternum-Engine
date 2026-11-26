# WP38 — French presidential term and succession pilot

Opt-in fr-presidential-v1 inherits the WP37 campaign. The existing survival
and scripted revolution cases retain their previous state shapes. The new
scenario starts during a fictional first presidential term with an upcoming
national election. Immutable fictional ballots are configured at bootstrap;
they are neither actual election results nor dynamically forecast opinions.
The dedicated presidency endpoint does not expose the unpublished tally
configuration, although the full opinion journal/snapshot is still an
operator-facing scenario representation, not a secret ballot system.

Constitutional basis: articles 6 and 7 of the French Constitution
(https://www.legifrance.gouv.fr/loda/texte_lc/LEGITEXT000006071194/).
Five-year terms, no more than two consecutive mandates, absolute majority of
valid votes, top-two runoff 14 days later, first round 25 days before term end
(within the normal 20–35-day interval). Blank/invalid ballots and abstention
are separate. Candidate eligibility is enforced for the fictional incumbent.
A tie at the qualification boundary or a tied runoff remains unresolved,
not settled through an arbitrary lexical winner. Calendar uses game-day
anniversaries and simplified handover at term expiry.

An electoral defeat does not end the current mandate at result publication.
At the handover date, a re-elected player begins the next term, or the
campaign ends with the successor recorded. After two consecutive terms the
player cannot stand again. An unresolved election at expiry ends the player's
mandate in this pilot, without asserting a real interim legal officeholder.
Other terminal events can interrupt the mandate independently. Operator
narrative events cannot override electoral defeat or expiry in this scenario.
The existing world fence, terminal clock publication and opinion replay
persist outcomes without a new migration. The campaign and presidency
states are authoritative for player tenure; the original roster remains
an archival identity snapshot. No automatic appointment of a new real
official, early-election calendar for vacancies, sponsorship/withdrawals,
constitutional adjudication or constitutional amendments is implemented.
Future work includes dynamic fictional elections and actual succession
integration, and broader long-horizon performance/retention beyond the
existing 3650-day opinion and domestic ledger cap.


## WP54r — calendrier annoncé et réélection après révision

Pour les nouvelles campagnes corporations/civil-crisis, les dates métropolitaines annoncées 18 avril / 2 mai 2027 remplacent la fixture de novembre/décembre 2026. Le 13 mai 2027 est le dernier jour annoncé du mandat; le moteur modélise la transmission au 14 mai. Le titulaire fictif est à son premier mandat consécutif (ce n'est pas l'historique personnel du président réel). Les dates suivantes sont calculées dans la fenêtre de l'article 7, un dimanche, puis second tour 14 jours après. Le verrou de candidature utilise le 12 mars 2027 (clôture des parrainages), puis une convention de jeu 37 jours avant le premier tour. Ce verrou ne remplace pas le droit complet des candidatures ni une certification réelle. Une publication Article 89 effective reçue depuis l'état institutionnel engagé peut enlever le plafond de deux mandats; elle ne modifie ni une liste verrouillée rétroactivement ni le résultat électoral. Les élections dynamiques restent des scénarios agrégés. Les sauvegardes et fixtures historiques restent inchangées.
