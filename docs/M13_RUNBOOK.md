# M13 — procès-verbal opérationnel de recette

Cette procédure est réservée à un laboratoire jetable et à une
infrastructure explicitement autorisée. Elle ne donne aucun accès
à un serveur tiers et ne doit pas être appliquée aveuglément
à une base contenant des données réelles.

## Préconditions contrôlables

1. Établir la version exacte du commit candidat, la liste des mondes,
   les versions SHA-256 des manifests et la version PHP/MariaDB.
2. Obtenir l'autorisation explicite de l'exploitant pour le staging
   concerné, les comptes techniques, les données et les fenêtres
   de maintenance. Ne jamais utiliser les identifiants d'exemple.
3. Séparer l'utilisateur MariaDB de migrations (DDL) de l'utilisateur
   applicatif (DML), les secrets et le répertoire web `public/`.
4. Préparer une base de recette **vide** distincte ; ne pas importer
   ou écrire sur un serveur en production pour satisfaire la CI.

## A. CI reproductible sur le candidat

Consulter le SHA exact et les dix workflows requis : PHP Core, Chromium, G08, M10, M11, M12, M13, M14, M15 et M16. Si l'un manque ou est encore en cours,
la recette est bloquée. Après terminaison, exécuter depuis un clone
ayant accès à l'API GitHub :

```bash
php tools/m13-actions-audit.php SHA_COMPLET_DU_CANDIDAT
```

Les rapports doivent conserver le SHA, l'identifiant de run, la
conclusion et la date. La simple couleur verte de la liste globale
GitHub n'est pas une preuve : un run d'un autre SHA ne compte pas.

## B. Installation neuve et migration

Installer PHP 8.4 et PDO MySQL sur la cible de recette, configurer
un compte MariaDB jetable privé et le registre strict des mondes.
Garder les secrets hors du dépôt et du webroot. Dans un environnement
approuvé seulement, le migrateur est `php bin/migrate.php`.
Le premier passage doit appliquer les migrations manquantes, le
second doit n'en appliquer aucune. Vérifier l'absence de migration
sale et le maintien des empreintes de tous les fichiers SQL.

Confronter ensuite un **monde historiquement peuplé** avant/après
migration : utilisateurs, appartenances, soldes, reçus, grand livre,
version de règles et capacité de rejeu doivent rester identiques
lorsque le scénario ne crée aucun événement. Ne pas corriger une
DDL partiellement commise en effaçant un marqueur.

## C. Sauvegarde et restauration sur une cible différente

Suivre les commandes et garde-fous officiels de
[M11 Operations](M11_OPERATIONS.md). Les scripts M11 refusent la
restauration sur base non vide et exigent les fichiers client privés
et les validations d'environnement. Conserver l'archive et son
SHA-256 hors instance. Restaurer sur une base distincte, vérifier
les effectifs, les empreintes ligne à ligne, l'authentification
d'un ancien acteur, le grand livre et les projections avant
d'accepter la restauration.

Consigner les preuves M11 et appliquer les critères G08.

## D. Deux parcours de jeu réellement complets

Exécuter les campagnes de
[M05](M05_MEDIEVAL_GAMEPLAY.md) et
[M06](M06_INDUSTRIAL_GAMEPLAY.md) avec des identités indépendantes.
Observer toutes les opérations via Chromium et l'API PHP réelle,
atteindre la condition d'objectif de chaque pack, se déconnecter,
se reconnecter, puis refuser explicitement les lectures et
transactions sur un compte d'un autre acteur ou monde.

Vérifier en plus un échange multijoueur, une reprise après
réponse perdue, un message, blocage et signalement, un droit
premium TEST ONLY et sa révocation ; aucun paiement réel.

## E. Sécurité et capacité

Rassembler une revue contradictoire des contrôles HTTPS,
authentification, CSRF, sessions, XSS/encodage, autorisations,
dépendances, registre de packs, administration et rétention.
Consigner les vulnérabilités hautes/critiques, leurs correctifs et
la réexécution des tests ; ne pas signer en présence d'un risque
grave connu sans résolution.

La [mesure concurrente préliminaire](M13_CAPACITY_LAB.md)
n'est pas une recette de charge dimensionnante. Publier les
profils graduels, CPU/RAM/IO, p95/p99, erreurs, saturations,
scénarios de panne/récupération et budgets pour la cible autorisée.

## F. Décision, signature et publication

Pour chaque G01–G09 : statut, preuve GitHub liée au candidat, limite,
responsable de revue et résultat de la recette. Consigner séparément
toute non-conformité ; une preuve partielle ne transforme jamais la
gate en `passed`.

Le manifeste versionné
[release-evidence-v1.json](release-evidence-v1.json)
est **un exemple volontairement incomplet**, destiné à rester
non autorisant. À la fin d'une véritable recette, construire
un manifeste *externe au commit candidat*, conservé de façon
immuable par l'exploitant et pointant vers son SHA : incorporer
dans un commit un manifeste contenant l'empreinte de ce même
commit serait une référence circulaire insoluble en pratique.

Exécuter sur ce manifeste externe :

```bash
php tools/m13-release-gate.php /chemin/prive/evidence-revue.json --enforce
```

**Même un code de sortie 0 ne constitue qu'un contrôle déclaratif.**
Les URLs et les rapports doivent être vérifiés indépendamment,
les autorisations formalisées et la signature opérateur contrôlée
hors de ce script. Seul un visa explicite puis un contrôle final de
l'ensemble des CI peuvent précéder un tag ou une release V1.0.

## Décision de recette

Le visa opérateur est lié au dossier de preuves et au SHA candidat.
