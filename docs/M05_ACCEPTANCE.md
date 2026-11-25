# M05 — Matrice de recette du jeu médiéval de référence

**Périmètre** : M05 de `docs/MACRO_ROADMAP_V1.md`, G03 et G04 du
`ROADMAP.md`. M05 exige un jeu jouable du démarrage à une victoire
vérifiable ; la recette n'est pas une approbation de l'ensemble V1.0.

| Critère de sortie | Implémentation | Preuve automatisée |
| --- | --- | --- |
| Création de colonie autonome | Provisionnement du lieu, de l'entité, du terrain privé et des plans de bâtiments à l'inscription | `tests/m05-colony-onboarding.php`, `tests/browser/medieval.spec.cjs` |
| Compatibilité | Manifeste opt-in version 0.3.0 et digest inchangé des versions 0.1.0 / 0.2.0 | `tests/m05-medieval-pack.php`, registre de packs en CI |
| Bâtiments et territoire | Placement joueur, vérification acteur/terrain, tarifs multi-ressources et prérequis | `tests/m05-full-gameplay.php`, tests M04 d'isolation et concurrence |
| Population | Capacité tirée de la ferme et du grenier ; nourriture réellement débitée | `tests/m05-full-gameplay.php`, navigateur |
| Chaîne de ressources | Travail transformant du grain en bois en fonction de la scierie et de la main-d'œuvre | `tests/m05-full-gameplay.php`, navigateur |
| Économie et temps | Coups atomiques, cooldown, coûts, worker, remboursements M04, reçu idempotent | `tests/m05-full-gameplay.php`, `tests/m04-construction.php` |
| Objectif vérifiable | Donjon 2, ferme 2, scierie 2, grenier 1, 6 habitants ; verdict calculé côté serveur | `tests/m05-full-gameplay.php`, `tests/browser/medieval.spec.cjs` |
| Partie multijoueur privée | Deux comptes et terrains disjoints, refus de comptes étrangers et victoire séparée | `tests/m05-colony-onboarding.php`, `tests/browser/medieval.spec.cjs` |
| Retour et cohérence | Reconnexion, lecture de l'objectif et rejeu des commandes/événements | `tests/m05-full-gameplay.php`, `tests/browser/medieval.spec.cjs` |
| Installation isolée | Migrations 021–025 exécutées sur MariaDB jetable, anciens mondes testés | `.github/workflows/ci.yml`, `.github/workflows/browser.yml` |

## Preuve indispensable avant fusion

Les jobs `PHP core CI` (core + MariaDB) et `Browser E2E` (Chromium)
doivent tous réussir sur **le même SHA final de la PR**. Tout échec
ou toute annulation implique l'absence de validation. Un test utilisant
des données de démonstration peut prouver un comportement applicatif,
mais ne prouve pas un déploiement en environnement réel.

## Risques et réserves

- Les durées et stocks du pack 0.3.0 sont accélérés pour le test et
  devront être rééquilibrés pour une exploitation commerciale.
- La migration 025 change l'ENUM du grand livre. Sur une base persistante,
  prévoir fenêtre de maintenance, sauvegarde et restauration validées.
- Les services worker/CRON restent à déployer et à surveiller par un
  opérateur : une partie réelle nécessite leur disponibilité pour
  achever les constructions.
- Vue/TypeScript, internationalisation, mobile, tests de charge prolongés,
  installation mutualisé/VPS et qualification V1.0 appartiennent aux
  macros ultérieures M07, M11, M12 et M13.
- Aucune dépense, publication publique ni migration d'hébergement réel
  n'est entreprise ici.
