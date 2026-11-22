# M04 — matrice de recette de la macro « Monde stratégique »

**Portée** : jalons G02 et G03 de [ROADMAP.md](../ROADMAP.md), macro
M04 du plan [M01–M13](MACRO_ROADMAP_V1.md).
Cette recette **n'est pas** une validation de la V1.0 ni une recette
d'hébergement O2Switch/VPS.

## Parcours fonctionnels

| Exigence M04 | Fonction réelle | Preuves automatiques |
| --- | --- | --- |
| Géographie structurée | Lieux, liens orientés pondérés, territoires, affectation immuable, itinéraires bornés | `tests/entities.php`, `tests/m04-territories.php`, `tests/m04-map-http.php`, `tests/browser/economy.spec.cjs` |
| Règles de bâtiments génériques | Types déclaratifs du pack épinglé, catalogue SQL, coûts multi-ressources, durées, prérequis | `tests/m04-buildings.php`, `tests/m04-construction.php` |
| Fondation par un joueur | `building.place` journalisée, insertion relationnelle atomique, terrain et entité appartenant au même acteur, refus de vol | `tests/m04-player-placement.php`, `tests/m04-placement-race.php` |
| Paiement/planification | `building.start`, mouvements du grand livre, événement de fin, worker, idempotence | `tests/m04-construction.php`, `tests/browser/construction.spec.cjs` |
| Annulation et stockage saturé | `building.cancel`, restitution immédiate, crédits persistants, `building.claim`, traitement no-op de l'événement annulé | `tests/m04-construction.php`, `tests/m04-construction-race.php` |
| Composant évolutif | `/entities/{id}/development` recalculé à partir des niveaux canoniques et des bâtiments du même acteur ; le niveau atteint déverrouille les prérequis | `tests/m04-player-placement.php`, `tests/m04-building-http.php`, `tests/browser/construction.spec.cjs` |
| Frontière public/privé | Carte publique épurée ; carte et fondations privées, sessions, membres révoqués et quota | `tests/m04-public-map.php`, `tests/m04-map-http.php`, `tests/m04-building-http.php` |
| Reprise et concurrence | Rejeu du journal, reçus immuables, courses indépendantes PHP, deux joueurs Chromium | `tests/m04-placement-race.php`, `tests/m04-construction-race.php`, `tests/browser/construction.spec.cjs` |
| Compatibilité migrations | Migrations 021–024, conservation d'un monde SQL alimenté, sauvegarde/restauration et contrôles de rejeu sur base jetable | `tests/integration.php`, `.github/workflows/ci.yml`, `tests/restore-verify.php` |

## Invariants

1. Aucune écriture de joueur ne franchit la paire `(world_id, actor_id)`.
2. Le débit de plusieurs ressources, l'état et le journal sont atomiques.
3. Un identifiant de demande rediffusé a le même reçu et le même effet.
4. Un autre identifiant ne crée pas deux bâtiments au même ID ou deux
   achèvements d'une construction déjà engagée.
5. La fin logique et l'annulation sont exclusives ; un remboursement
   saturant la capacité n'entraîne jamais une perte ni un double crédit.
6. Les versions de packs existantes restent épinglées et la composante
   `development` est calculée depuis l'état canonique rejouable.
7. Deux acteurs peuvent créer puis faire évoluer leurs fondations
   indépendamment et ne peuvent lire ou modifier celles de l'autre.

## Validation et limites hors laboratoire

Les références CI doivent porter sur **le SHA exact** fusionné ou soumis
à revue ; les succès d'un commit précédent ne valident pas un nouveau
commit. Les jobs requis sont PHP 8.4, MariaDB 11.4 et Chromium.

La migration 024 modifie un ENUM du grand livre. Sur une base existante,
cette opération peut reconstruire une table et requiert une fenêtre de
maintenance, une sauvegarde cohérente hors instance, la vérification de
son empreinte et une restauration répétée sur copie avant toute
exécution en production. **Aucune migration ni installation sur un
hébergement réel n'est effectuée dans cette macro**.

Des essais de charge longs, des tests de panne sur infrastructure réelle,
un déploiement O2Switch/VPS et une recette complète des jeux médiéval et
industriel appartiennent aux jalons M05–M13. Ils restent ouverts pour la
V1.0 et ne doivent pas être décrits comme accomplis par la CI M04 seule.
