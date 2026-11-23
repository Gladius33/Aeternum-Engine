# M06 — Réseau industriel : état de développement

## Livraison initiale (non terminée)

Le pack historique `reference-industrial@0.1.0` demeure strictement inchangé. Le manifeste `reference-industrial@0.2.0` est un nouvel artefact immuable, à épingler par SHA-256 dans le registre des mondes. Il définit une progression d'infrastructures : centrale électrique, aciérie, entrepôt, terminal de fret. Les prérequis imposent un ordre de construction et les coûts associent acier et énergie. Les bâtiments utilisent le même modèle déclaratif `buildings` et les mêmes contrôles du moteur que le jeu médiéval.

**Limite essentielle :** les prérequis et les coûts ne constituent PAS une simulation de réseau énergétique ou de logistique. La centrale ne fournit pas encore de puissance réservée aux consommateurs, l'entrepôt ne limite pas les flux et le terminal ne réalise pas de livraison. Aucun objectif de victoire industrielle ni E2E de campagne n'est revendiqué.

## Compatibilité et vérifications

`tests/m06-industrial-pack.php`, exécuté depuis `tests/run.php`, contrôle les versions épinglées, le graphe des dépendances et les refus des prérequis impossibles, ressources absentes et durées invalides. Les tests de progression métier, transactions MariaDB, concurrence et Chromium doivent être ajoutés avant la sortie de M06.

## Critères de sortie restant ouverts

- Production industrielle conditionnée par la puissance disponible, avec arbitrage explicite et transactionnel en cas de pénurie.
- Chaîne de transformation, stockage et transport avec limites de capacité, livraison et reprise idempotente.
- Parcours de création d'une installation par un joueur, progression et objectif industriel validé côté serveur.
- Parties concurrentes, isolation intermondes, rejouage, annulation et échecs couverts sur MariaDB.
- Interface joueur et scénario Chromium comparant effectivement une campagne industrielle à une campagne médiévale.
- Validation des migrations sur base non vide et procédure de restauration, CI complète verte sur SHA exact.

Ne pas fusionner ni déclarer M06 achevée sur la seule base du manifeste et de ses tests de validation.
