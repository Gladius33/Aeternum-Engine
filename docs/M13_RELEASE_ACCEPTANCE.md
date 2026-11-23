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

## Reste à faire

Confronter les critères ouverts dans `docs/M12_SECURITY.md` à des preuves
réelles, exécuter la matrice G01–G09, réviser API/SDK/guide utilisateur,
établir les rapports de charge et de restauration, puis seulement envisager
un tag V1.0 sous réserve de validation explicite.
