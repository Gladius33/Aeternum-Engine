# World Politics — Références économiques et conséquences (WP12–WP13)

Branche temporaire du jeu uniquement. Aucune fusion dans main d'Aeternum Engine.
Cette couche est 100 % headless : aucun graphique, aucune API HTTP publique.

## Principes

Le registre EconomicReferenceRegistry conserve séparément :

- les observations documentées avec leur année, source HTTPS et licence déclarée ;
- les hypothèses fictives de scénario, obligatoirement signalées comme telles ;
- les observations manquantes, qui demeurent null et ne valent pas zéro.

Le registre n'atteste pas à lui seul l'authenticité d'une publication : une filière d'ingestion et de curation administrative est indispensable. Les métadonnées de licence et de réutilisation doivent être vérifiées pour chaque série. Les fichiers de test n'utilisent que des données de scénario inventées et clairement signalées comme telles.

## Moteur WP12

WorldEconomicSimulation propose un premier mécanisme déterministe de politique commerciale :

1. L'État contrôlé peut proposer une restriction bilatérale fictive. Aucun effet n'est alors produit.
2. Un principal vérifié, disposant de la compétence nationale adéquate, confirme la régularité de la procédure constitutionnelle. Une approbation n'est pas une mise en application immédiate.
3. Un serveur d'horloge, et lui seul, atteint la date d'effet et matérialise un scénario de réduction hypothétique du commerce bilatéral.
4. Le modèle fournit valeur centrale, basse et haute, unités monétaires, date, année des observations, qualité et provenance ; il ne modifie JAMAIS le PIB réellement publié.
5. Si les échanges bilatéraux sont inconnus, les montants de projection restent explicitement null : aucune invention de données.

Le modèle travaille en points de base entiers pour garantir des résultats reproductibles et éviter les erreurs de flottants. Les hypothèses d'élasticité restent configurables et non calibrées.

Ce module n'est ni une prévision économique validée, ni un moteur de sanctions réelles. Il ne tient pas encore compte des substitutions commerciales, des effets de second tour, des taux de change, des chaînes d'approvisionnement ou des institutions complexes de chaque État.

## Persistance WP13

- database/005_economic_policies.sql : états nationaux de monde, reçus de commandes et journal transactionnel InnoDB.
- PdoEconomicStore.php : verrouillage par monde, décisions atomiques, reprise et détection de changements de références ou de pouvoirs.
- PdoEconomicApi.php : adaptateur REST JSON versionné /api/v1, sans serveur HTTP public.
- tests/wp12-economic-domain.php : tests de causalité, provenance, autorisations, montants et reprise.
- tests/wp13-economic-rest-db.php : tests REST/MariaDB de la procédure légale, rejouabilité, révisions, séparation de mondes et altération de snapshots.

Routes :

- GET /api/v1/worlds/{world}/economy
- GET /api/v1/worlds/{world}/economy/indicators
- POST /api/v1/worlds/{world}/economy/policies
- GET /api/v1/worlds/{world}/economy/policies/{policyId}
- POST /api/v1/worlds/{world}/economy/policies/{policyId}/authorization
- POST /api/v1/worlds/{world}/economy/clock

Toutes les écritures demandent Idempotency-Key et If-Match. Les permissions des acteurs et les actes institutionnels proviennent d'un principal fiable injecté côté serveur, jamais des requêtes de joueurs.

## Évolutions restant nécessaires

Le modèle économique doit être calibré, sourcé et validé contre des séries publiques historiques pour fonctionner comme un serious game quantitatif. La curation des véritables données de commerce bilatéral et leurs licences, l'importation des 193 États, le modèle de finances publiques et les relations entre décisions diplomatiques et mécanismes économiques seront les prochaines étapes. Le moteur ne doit pas promettre un résultat « réel » si ces validations n'ont pas été effectuées.

Sources pour la future ingestion :

- https://datahelpdesk.worldbank.org/knowledgebase/articles/889392
- https://datahelpdesk.worldbank.org/knowledgebase/articles/898581
- https://data.worldbank.org/summary-terms-of-use
- https://www.un.org/en/about-us/non-member-states
