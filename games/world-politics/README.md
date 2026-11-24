# World Politics — back-end de simulation (branche isolée)

**Aucun développement d'interface graphique dans cette branche.**
Il s'agit du jeu World Politics, destiné ultérieurement à un dépôt dédié :
**ne jamais fusionner ce code dans le main du moteur Aeternum Engine**.

Le sous-système actuel implémente la création de personnages fictifs rapides
ou détaillés, un registre de profils réels sourcés ou fictifs, la composition
initiale d'un gouvernement, et les adaptations REST headless.

## Fichiers

- \`src/PoliticalProfileTemplate.php\` : spécification de saisie rapide/
  détaillée destinée aux futurs clients ;
- \`src/PoliticalRoster.php\` : règles métier de profils et nominations ;
- \`src/PdoPoliticalStore.php\` : état MariaDB, transactions et journal ;
- \`src/PdoWorldPoliticsApi.php\` : opérations REST sans serveur public ;
- \`src/WorldPoliticsApi.php\` : prototype en mémoire, réservé aux tests ;
- \`api/openapi.yaml\` : contrat OpenAPI 3.1 ;
- \`database/001_rosters.sql\` : première migration spécifique au jeu.

## Tests sur PHP 8.4

Depuis la racine du dépôt :

\`\`\`sh
php tests/wp01-political-roster.php
php tests/wp01-rest-contract.php
php tests/wp02-profile-template.php
\`\`\`

Les tests \`tests/wp03-store-db.php\` et \`tests/wp04-rest-db.php\`
nécessitent une base MariaDB **de test isolée** avec un DSN comportant
\`wp_test\` et \`WP_TEST_ISOLATED=YES\`. Le workflow CI fournit cet
environnement. **Ne pas les lancer sur une base de production**.

## Limites

Aucun serveur HTTP accessible, aucune authentification de production,
aucune IA gouvernementale ni données politiques réelles importées.
Aucune garantie de production n'est accordée par ces prototypes.

Voir \`../../docs/WORLD_POLITICS_BACKEND.md\` pour l'architecture et
les exigences de migration et de sécurité.
