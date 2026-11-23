# M13 — Qualification et recette V1.0 (en cours)

**Base de M13 :** `main` au SHA `b9b7c2ca878d4b8b785753b4d9e691d29ca8fabd` (PR #52 fusionnée).
Les cinq CI de PR ont réussi sur `a8de29fb5bf98b76cbbeb8202a2c6dc03203ea35`.
La CI après fusion est un contrôle distinct.

**V1.0 NON validée. Aucun tag, aucune release ou installation externe.**
La fusion de M12 a intégré des tests et correctifs ; elle n'a pas clôturé les
réserves M12 sur l'audit exhaustif, la charge, les pannes et le staging.

## Matrice de recette obligatoire

| Gate | Critère de release | Preuves à vérifier | Statut final |
|---|---|---|---|
| G01 | Ordre, idempotence et replay | Transactions, processus concurrents, reprise après crash | En attente |
| G02 | Isolation acteur/monde et versions | Packs épinglés, migrations monde peuplé, autorisation | En attente |
| G03 | Économie et progression cohérentes | Grand livre, courses, conservation et limites | En attente |
| G04 | Deux jeux distincts et SDK générique | Parcours complets et troisième pack configurable | En attente |
| G05 | API, auth, UI, clavier et i18n | Tests négatifs et navigateurs en français/anglais | En attente |
| G06 | Marché, social et modération | Plusieurs acteurs, rapports, invitations et reprise | En attente |
| G07 | Premium optionnel, paiements simulés | Contrôle des droits et attribution ACCL-1.0 | En attente |
| G08 | Installation, migration, restauration | Backup/restore sur base jetable, préflight et staging autorisé | En attente |
| G09 | Sécurité, résilience, capacité, feu vert | Audit haute/critique, charges publiées, CI et visa opérateur | En attente |

*En attente* qualifie la **preuve de sortie finale**, sans nier l'existence
des fonctionnalités ni les tests intermédiaires déjà exécutés.

## Politique « aucune release implicite »

- CI PHP 8.4, MariaDB, M10, M11, Chromium, audit M12 et M13 sur SHA exact,
  puis contrôle `main` post-fusion.
- Corriger toute vulnérabilité critique ou haute connue ; établir la portée
  et les limites de l'audit auth, CSRF, CSP, sessions, contenus et marché.
- Documenter un essai de charge reproductible (plateforme, jeu de données,
  simultanéité, p95, erreurs, ressources, seuil d'arrêt) et des pannes/replays.
- Exécuter la restauration et l'upgrade d'une base de recette peuplée.
- Faire jouer deux parties complètes avec acteurs indépendants et rejets
  intermondes depuis la véritable interface navigateur.
- Staging uniquement sur environnement **explicitement autorisé** ; aucun
  hébergement O2Switch/VPS n'est réputé testé à ce jour.
- Vérifier licence et attribution ; approbation opérateur liée au SHA du
  candidat. Aucun vrai paiement ni publication sans autorisation.

## Vérification externe des CI sur le SHA exact

La commande `php tools/m13-actions-audit.php SHA_COMPLET` interroge
directement l'API GitHub Actions publique du dépôt (optionnellement avec
`GITHUB_TOKEN` privé). Elle exige six workflows distincts : PHP Core,
Chromium E2E, M10, M11, M12 et le contrat M13, tous terminés avec
`conclusion=success` sur **le même SHA**, sans emprunter de preuves à
un commit antérieur. Toute exécution manquante, annulée, défaillante ou
encore en cours entraîne un code de sortie 2, une erreur de collecte
entraîne 1. Ne jamais recopier de jeton dans un fichier ou un journal.

Cette vérification doit être exécutée **après la fin des CI** : elle ne
doit pas exiger que sa propre exécution encore en cours soit verte.
Le déclencheur M10 couvre désormais les fichiers M13 afin de fournir
une preuve isolée sur chaque changement de qualification. Une preuve
CI positive ne remplace ni l'audit des vulnérabilités, ni l'exercice
de restauration, ni la recette externe autorisée.

## Contrôle de cohérence des preuves

```bash
php tests/m13-release-gate.php
php tools/m13-release-gate.php
php tools/m13-release-gate.php --enforce
```

Les preuves sont consignées dans `docs/release-evidence-v1.json`.
Initialement, aucune gate n'est acceptée : `result=pending`,
`checks=false`, SHA candidat et signature opérateur absents.
Le mode audit fournit les blockers ; `--enforce` renvoie le code **2**
pour un manifeste incomplet, le code **1** pour une erreur de lecture,
et **0** seulement si la structure et les déclarations sont complètes.

**Important :** le vérificateur contrôle la cohérence *déclarative* des
preuves et l'égalité des SHA. Il ne télécharge pas les journaux, ne vérifie
pas les URLs à distance, ne remplace pas une revue humaine et ne certifie
jamais la sécurité par lui-même. Les fixtures synthétiques de test ne
constituent jamais des preuves de release.

## Épreuve de charge concurrente reproductible (CI MariaDB)

`tests/m13-capacity.php` lance **quatre processus PHP réels et quatre
connexions MariaDB** derrière une barrière de départ commune. Chacun effectue
50 lectures du marché et 50 lectures de solde sur le jeu de données préexistant
de 1 001 offres et 1 002 soldes. Le total est de **400 lectures autorisées**,
avec vérification des résultats, zéro erreur et temps de réponse p95 par type.

La CI consigne `M13_CAPACITY_JSON` avec le nombre de workers, les mesures,
le temps total et les limites de régression très larges (p95 3 000 ms,
temps mur 90 s). Le scénario ne réalise pas d'écritures, ne mesure pas les
connexions externes et ne certifie **ni une charge utilisateur en production,
ni le nombre maximal de joueurs simultanés**. Il complète sans remplacer
une qualification à paliers, incluant saturation, écritures et restauration.

## Reste à faire

Confronter les critères ouverts dans `docs/M12_SECURITY.md` à des preuves
réelles, exécuter la matrice G01–G09, réviser API/SDK/guide utilisateur,
établir les rapports de charge et de restauration, puis seulement envisager
un tag V1.0 sous réserve de validation explicite.

## Documents de recette complétés

- [Guide des joueurs M13](M13_PLAYER_GUIDE.md) : parcours médiéval/industriel, multijoueur, sécurité et limites.
- [Procès-verbal d'acceptation opérateur](M13_RUNBOOK.md) : installation sur base jetable, upgrade, restauration et critères de décision.
- [Rapport de charge concurrente](M13_CAPACITY_LAB.md) : 400 lectures réelles, résultats datés et limites non certifiées.

La CI M13 vérifie que les liens Markdown locaux de ces guides et de la documentation principale ne sont pas brisés ; ce contrôle de cohérence n'est ni une vérification du contenu juridique ni une validation externe de release.
