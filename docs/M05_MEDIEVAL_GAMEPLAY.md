# M05 — Jeu médiéval de référence (version jouable opt-in)

## Principe et versionnement

Le jeu `reference-medieval@0.3.0` est un monde de colonie réellement
jouable à partir d'une inscription en navigateur. Les anciennes versions
`0.1.0` et `0.2.0` restent intégralement inchangées. Chaque monde est
épinglé à un manifeste exact (identifiant, version, SHA-256). Il est
**interdit de changer un monde existant de version en modifiant son registre** :
une migration applicative explicite et sa validation seront nécessaires.

La V0.3.0 est volontairement accélérée pour la démonstration et la
qualification en laboratoire, avec ressources initiales et production
adaptées à un cycle complet en une courte session. Elle ne constitue pas
un équilibrage commercial définitif.

## Démarrage et tutoriel (sans commandes techniques côté joueur)

1. Créer un compte depuis la page web dans un monde approuvé de version
   `reference-medieval@0.3.0`. Les contrôles d'identité restent ceux de
   M01 : invitation ou activation email en production, dispense **uniquement**
   pour le serveur localhost jetable des tests.
2. Le moteur crée automatiquement un compte de ressources, une entité,
   un lieu et un territoire `homestead` privés. Il installe les plans
   typés du monde sans créer un bâtiment fictif ni débiter des unités.
3. Choisir `Find construction sites`, un terrain et un type. Placer une
   fondation dans la propriété de son compte, puis `Begin construction`.
4. Ériger une scierie (`lumberyard`) et une ferme (`farm`), puis utiliser
   `Feed and welcome one resident` pour développer la population et
   `Organize lumber work` pour convertir une consommation de grain en
   production complémentaire de bois. Le moteur applique un temps de repos
   logique et un plafond physique de stockage.
5. Développer les bâtiments jusqu'aux niveaux requis, utiliser un grenier
   (`granary`) pour agrandir la capacité de population et achever le
   donjon (`keep`) niveau deux.
6. Consulter `Review your colony`. La victoire n'est annoncée que par
   une projection serveur de l'état canonique rejouable. Quitter, revenir,
   constater la même condition de victoire.

Le processus CLI `bin/worker.php` reste un service d'exploitation exécuté
par l'hébergeur ou la CI pour achever les événements différés : **le joueur
n'a jamais à l'exécuter**, ni à envoyer de JSON à l'API.

## Règles déclaratives et objectif

Le manifeste embarque `settlement`, champ opt-in vérifié strictement,
qui référence des types de bâtiment et ressources du même pack. Dans la
version de jeu livrée :
- 1 résident au départ ; 3 places supplémentaires par niveau de ferme et
  2 par niveau de grenier ;
- accueillir un résident coûte 4 grains, exige une ferme et de la place ;
- un chantier de bûcheronnage exige 2 résidents et une scierie de niveau 1,
  consomme 2 grains, produit 15 bois par niveau de scierie et respecte
  un repos de 10 secondes ;
- la victoire demande `lumberyard 2`, `farm 2`, `granary 1`,
  `keep 2` et **6 résidents**. Le donjon requiert lui-même les chaînes
  de bâtiments déclarées dans `buildings[].stages[].requires`.

Les coûts de construction, les ressources consommées ou gagnées, les
reçus de commande, l'horloge logique, la progression démographique et le
journal sont calculés et stockés atomiquement. Les erreurs de fonds
insuffisants, temps de repos, capacité et accès à un compte tiers
n'appliquent aucun mouvement partiel.

Le moteur fournit :
- `settlement.feed` et `settlement.work` comme commandes authentifiées
  et idempotentes du même endpoint économique existant ;
- `GET /v1/worlds/{world}/settlement/status/{account}` comme vue privée
  du peuplement, des besoins, de la main-d'œuvre et de l'objectif ;
- les endpoints M04 de terrain, bâtiments, progression, marché et transfert,
  réutilisés sans mécanisme d'authentification parallèle.

Le nouveau motif de grand livre `settlement_food` exprime une
consommation ; `settlement_work` exprime un crédit. La migration 025
élargit l'ENUM existant sans le supprimer. Comme la migration 024, elle
peut reconstruire une table MariaDB et exige une sauvegarde vérifiée,
une fenêtre de maintenance et une répétition de restauration sur copie
avant toute installation persistante.

## Critères de recette et preuve

La matrice exhaustive figure dans
[M05_ACCEPTANCE.md](M05_ACCEPTANCE.md). La suite PHP vérifie
les invariants de règles, la création idempotente de colonies, les
débits/crédits de main-d'œuvre, le rejet de comptes étrangers, les
prérequis, le parcours jusqu'au donjon, les seuils de population,
la reconnexion et le rejeu déterministe. Le scénario Chromium
`tests/browser/medieval.spec.cjs` s'inscrit avec deux identités,
place réellement des fondations, construit, nourrit et fait travailler
les colons, atteint la victoire avec l'un des joueurs, reste perdant
avec l'autre, puis confirme la victoire après reconnexion.

Les CI exigées sont **PHP 8.4**, **MariaDB 11.4** et **Chromium** sur le
SHA exact de la PR avant fusion. Aucun succès d'un SHA précédent ne
constitue une preuve pour le SHA final.

## Limites volontairement reportées

Le client web Vue/TypeScript, l'i18n et l'accessibilité mobile de
production relèvent de M07. Les réseaux logistiques industriels relèvent
de M06 ; l'administration et la restauration d'une base persistante réelle
relèvent de M11 ; la sécurité et les essais de charge de longue durée
relèvent de M12. Le respect de ces dépendances n'autorise aucune
publication V1.0 anticipée. Aucun hébergement externe n'a été modifié.
