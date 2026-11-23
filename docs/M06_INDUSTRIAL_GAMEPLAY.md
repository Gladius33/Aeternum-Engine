# M06 — Jeu industriel de référence, énergie et logistique

**Mise à jour documentaire M13 :** la [PR #46](https://github.com/Gladius33/Aeternum-Engine/pull/46) a été fusionnée (`45e1247a52d3ca8601804dff43056c26a736c0fc`). Les mentions de « code proposé » ci-dessous décrivent le contexte historique de la validation M06, et ne constituent pas l'état actuel de la branche M13.

**Périmètre normatif :** M06 de `docs/MACRO_ROADMAP_V1.md`, sans modification de `ROADMAP.md`.
Cette page décrit le code proposé dans la PR #46 ; une macro n'est acceptée
qu'après CI PHP 8.4/MariaDB/Chromium intégralement verte sur le SHA final,
absence de conflit, fusion dans `main` et vérification post-fusion.

## Versions et architecture

Les manifestes `reference-industrial@0.1.0` et `@0.2.0` restent immuables.
Le nouveau `@0.3.0` active explicitement le bloc `industry`, contrôlé
par `IndustryRules::load`. Son digest SHA-256 est épinglé dans le registre
de mondes ; les mondes historiques n'acquièrent ni commandes ni règles
industrielles. La mécanique partage `GamePackLoader`, `RuleSet`,
`EconomyCommandHandler`, `PdoScheduler`, le journal canonique,
`engine_resource_ledger`, les lieux, territoires et bâtiments de M04.
Aucune logique économique n'est fournie par un script arbitraire du pack.

## Parcours joueur

Une inscription dans un monde `@0.3.0` provisionne un terrain privé et
les plans immuables des quatre structures : centrale électrique, aciérie,
entrepôt et terminal de fret. Le joueur place lui-même leurs fondations,
paie les étapes multi-ressources et attend leur achèvement par le worker.

Les commandes authentifiées sur `POST /v1/worlds/{world}/economy/commands` :

- `industry.generate` : une centrale achevée convertit du stock de
  matière brute en énergie, en fonction du niveau de la centrale.
- `industry.refine` : une aciérie et un entrepôt achevés consomment
  matière et énergie, produisent des marchandises et refusent le
  dépassement de stockage ; la demande totale de puissance ne doit pas
  dépasser la fourniture installée.
- `industry.ship` : un terminal et un entrepôt achevés consomment
  marchandises et énergie, incrémentent le total de livraisons persistantes
  et vérifient également la capacité du réseau.

Chaque opération utilise le reçu de commande, les mouvements signés du
grand livre, des délais d'opération et les verrous transactionnels
existants. Aucune répétition avec le même identifiant ne crée de
ressources ni de livraisons supplémentaires. Le refus d'une commande
insuffisamment approvisionnée ne doit rien débiter.

L'API `GET /v1/worlds/{world}/industrial/status/{account}` est
authentifiée, réservée au compte du joueur, et calcule à partir d'un
snapshot transactionnel l'offre, la charge et le surplus réseau, la
capacité d'entrepôt, les délais, les bâtiments et les livraisons.
La victoire dépend des quatre objectifs d'infrastructure et d'au
moins 45 marchandises livrées. Le client public propose un tutoriel,
trois actions et un affichage du verdict côté serveur.

## Recette et preuves attendues

| Critère | Implémentation | Test de preuve |
| --- | --- | --- |
| Épinglage/compatibilité | `@0.3.0` opt-in, anciennes versions inchangées | `tests/m06-industrial-pack.php` |
| Construction d'infrastructure | Sites privés et moteur M04 | `tests/m06-full-gameplay.php`, `tests/browser/industrial.spec.cjs` |
| Réseau, électricité, usine | Production et transformation en journal | `tests/m06-full-gameplay.php` |
| Stockage et fret | Entrepôt et livraison atomique | `tests/m06-full-gameplay.php` |
| Concurrence | Verrous, cooldown, quatre écrivains PDO | `tests/m06-freight-worker.php` et suite MariaDB |
| Isolation et rejeu | Contrôle d'acteur et journal cohérent | `tests/m06-full-gameplay.php` |
| Web et comparaison | Campagne M06 et M05 avec Chromium, reconnexion | `tests/browser/industrial.spec.cjs`, `tests/browser/medieval.spec.cjs` |

Ces fichiers sont des tests programmés, **pas une preuve de réussite tant
que les workflows correspondant au SHA final ne sont pas verts**.
Ne pas annoncer la macro terminée sur la seule présence du code.

## Migration et restauration avant utilisation persistante

La migration `026_industry_ledger.sql` élargit l'ENUM des motifs du journal.
Elle ne modifie pas les unités ni les clés de tables, mais son `ALTER
TABLE` peut reconstruire un grand tableau InnoDB et mobiliser des verrous.

1. Arrêter les écritures et workers sur la base cible, définir une fenêtre
   de maintenance et collecter les compteurs de comptes, mondes, commandes,
   lignes de journal et livraisons.
2. Sauvegarder de manière cohérente le schéma, les données et la
   configuration des mondes (avec chiffrement et rétention appropriés).
3. Restaurer d'abord sur **une base distincte non vide**, exécuter
   `php bin/migrate.php` sur cette copie, puis lancer les scénarios
   de vérification de rejeu et comparer compteurs et stocks.
4. Tester le **retour arrière par restauration complète** de cette
   sauvegarde sur la copie : ne jamais tenter de supprimer les valeurs
   d'ENUM sur une table recevant déjà des écritures industrielles.
5. Ne procéder sur le serveur réel qu'après validation humaine du plan,
   des sauvegardes et de l'environnement. Aucune migration réelle sur
   O2Switch/VPS, publication ni paiement n'a été fait dans M06.

## Réserves

Le gameplay est accéléré pour les scénarios de recette et devra être
rééquilibré. Le client Vue/TypeScript, l'accessibilité/i18n complète,
la qualification de charge et l'installation opérationnelle appartiennent
aux jalons ultérieurs et ne sont pas assimilés à une validation acquise.
La CI d'une base jetable n'est pas une validation de restauration en
production.
