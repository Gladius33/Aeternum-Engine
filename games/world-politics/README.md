# World Politics — back-end autonome (branche temporaire)

**World Politics est un jeu distinct d'Aeternum Engine.**
NE JAMAIS fusionner cette branche dans le `main` du moteur générique.
Le développement graphique et les templates d'Aeternum sont exclus jusqu'à
approbation expresse. Les futurs clients web/mobile/bureau utiliseront
la même API RESTful JSON côté serveur.

## Modules métier

- `src/PoliticalProfileTemplate.php` : champs communs aux profils
  biographiques fictifs et réels, modes rapide et détaillé.
- `src/PoliticalRoster.php`, `src/PdoPoliticalStore.php` :
  personnages, gouvernements et nominations, stockage InnoDB.
- `src/InstitutionalRulebook.php`, `src/InstitutionalSimulation.php`,
  `src/PdoInstitutionalStore.php` : règles nationales testées France,
  Allemagne, États-Unis, horloge et révisions.
- `src/CountryCatalog.php` : pays potentiellement jouables versus
  acteurs contrôlés par l'IA, statut ONU et statut Palestine.
- `src/GeopoliticalActorRegistry.php` :
  acteurs diplomatiques distincts, sources et date d'observation.
- `src/DiplomaticSimulation.php` :
  propositions, réactions IA déterministes et délais de jeu.
- `src/PdoDiplomaticStore.php` :
  persistance mondiale transactionnelle, reçus et audit.
- `src/PdoDiplomaticApi.php` :
  contrat REST durable, non déployé en HTTP.
- `api/openapi.yaml` : contrat OpenAPI 3.1 validé automatiquement.
- `database/001_rosters.sql`, `002_institutions.sql`,
  `003_diplomacy.sql` : schémas propres au jeu.

## Validation

Dans la racine du dépôt, avec PHP 8.4 :

```sh
php tests/wp01-political-roster.php
php tests/wp01-rest-contract.php
php tests/wp02-profile-template.php
php tests/wp05-institutions.php
php tests/wp06-diplomacy.php
```

Les tests MariaDB (`wp03-store-db.php`, `wp04-rest-db.php`,
`wp06-institutions-db.php`, `wp07-institutional-rest-db.php` et
`wp08-diplomacy-db.php`) utilisent **exclusivement une base de test**
dont le DSN contient `wp_test`, avec `WP_TEST_ISOLATED=YES`.
Ils effacent et reconstruisent les tables du test ; ne jamais les
exécuter sur une base de production.

## Sécurité et maturité

L'API est un **adaptateur en PHP, pas un serveur HTTP public**.
Authentification effective, contrôle des autorisations Aeternum,
gestion de charge mondiale, import des 193 pays, résultats
électoraux, économie et IA gouvernementales calibrées restent à
développer. Les sources militaires réelles inconnues ou contestées
ne sont jamais inventées.

Documentation :
- `../../docs/WORLD_POLITICS_BACKEND.md`
- `../../docs/WORLD_POLITICS_DIPLOMACY.md`

Migration future : déplacer `games/world-politics/` et ses tests
vers un dépôt de jeu dédié, maintenir une intégration de moteur
générique versionnée, sans merger dans `main` d'Aeternum.

## Modules économiques et internationaux — WP10 à WP15

- PoliticalProfileTemplate.php : candidats fictifs rapides/détaillés et profils biographiques.
- PoliticalRoster.php / PdoPoliticalStore.php : cabinets et nominations.
- InstitutionalSimulation.php / PdoInstitutionalStore.php : règles institutionnelles selon le pays.
- GeopoliticalActorRegistry.php / DiplomaticSimulation.php / PdoDiplomaticStore.php : États, autorités, relations diplomatiques et IA.
- OrganizationSimulation.php / PdoOrganizationStore.php / AutonomousOrganizationPlanner.php : organisations, votes IA, procédures, préavis.
- EconomicReferenceRegistry.php / WorldBankOfflineImporter.php : sources économiques datées, manquantes ou hypothétiques.
- WorldEconomicSimulation.php / PdoEconomicStore.php / PdoEconomicApi.php : conséquences commerciales différées, audit MariaDB, REST.
- DiplomacyEconomicBridge.php : passage non contraignant d'une proposition diplomatique vers une décision économique.
- api/openapi.yaml : contrat OpenAPI 3.1, sans serveur HTTP ni client graphique.

Tests supplémentaires : php tests/wp12-economic-domain.php ; php tests/wp14-worldbank-import.php ; php tests/wp15-diplomacy-economic-bridge.php. Les tests WP10, WP11 et WP13 ont besoin d'une base wp_test isolée.

Le moteur reste expérimental : les données réelles, les lois de chaque État, les licences et la calibration économique doivent être vérifiées avant toute mise en production. Consulter docs/WORLD_POLITICS_ECONOMY.md.
