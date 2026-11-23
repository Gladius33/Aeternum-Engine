# M09 — Social, alliances, privé, signalements et modération (travaux en cours)

**Statut : NON VALIDÉ / NON LIVRÉ sur `main`.** La macro M09 ne sera close
qu'après tous ses critères de sortie (dont E2E, migration, restauration,
concurrence et sécurité), et non à la simple création d'une PR.

## Données et frontières de confiance

- `028_social_alliances.sql` crée les alliances, leurs adhésions à rôles
  `owner/officer/member` et les invitations, avec clé de monde dans chaque
  clé primaire et étrangère. Les mutations sérialisent leurs transitions sous
  verrou de l'alliance ; les membres du monde sont revérifiés sous verrou.
- `029_social_messaging.sql` ajoute messages privés, blocages, rapports,
  notifications persistantes avec `read_at`, ainsi qu'un journal d'arbitrage
  opérateur. Aucune de ces tables ne remplace les comptes, ressources, reçus,
  règles épinglées ou journaux économiques existants.
- **Autorité :** l'acteur n'est jamais accepté depuis un champ de requête ;
  seul un bearer valide résolu côté serveur identifie l'émetteur. L'appartenance
  active au monde est revérifiée à chaque opération. Les IDs de monde sont
  explicitement admis par la configuration des packs.
- **Données textuelles :** corps de message UTF-8 ≤ 2 000 octets ; nom
  d'alliance ≤ 100 octets ; motif de signalement de 5 à 255 octets.
  Les sorties de joueurs sont rendues comme du texte, jamais du HTML.
- **Rejeux :** créer deux fois une même alliance avec nom/propriétaire
  identiques est un no-op ; les invitations `pending` sont stables ; un
  `message_id` répété avec contenu identique ne crée ni deuxième message
  ni deuxième notification ; un rejeu conflictuel est refusé.
- **Blocages :** tous deux peuvent bloquer la conversation. Envoyer
  des messages ou inviter le joueur bloqué est refusé ; les invitations
  antérieures sont masquées et ne peuvent être acceptées pendant le blocage.
  Les anciens messages sont masqués du destinataire qui a bloqué l'émetteur ;
  un déblocage ne détruit ni les messages ni les invitations en attente.
- **Notifications :** message, invitation et invitation acceptée sont écrits
  dans la transaction de l'événement pertinent. Leur accusé de lecture est
  privé et idempotent.
- **Classement :** calcul depuis les adhésions **actives** persistantes,
  décroissant par effectif et départagé par ID d'alliance binaire ; aucune
  victoire fictive ni statistique économique inventée.

## API des joueurs (HTTPS uniquement)

Toutes les routes ci-dessous ont le préfixe
`/v1/worlds/{world}/social`. Limitation par acteur : 120 lectures et
30 écritures par fenêtre de 60 secondes. Une session révoquée retourne 401,
une absence d'accès à un monde/ressource privée retourne 404, une écriture
interdite ou conflictuelle 409, une entrée malformée 400.

| Méthode et chemin | Corps JSON strict / réponse |
| --- | --- |
| GET `/alliances` | Répertoire public du monde, max. 50 éléments |
| POST `/alliances` | `{"id":"my-alliance","name":"Alliance"}` |
| GET `/alliances/{id}` | Informations privées des membres et rôles |
| POST `/alliances/{id}/invite` | `{"actor":"member-id"}` |
| POST `/alliances/{id}/respond` | `{"accept":true}` ou `false` |
| POST `/alliances/{id}/revoke` | `{"actor":"member-id"}` |
| POST `/alliances/{id}/role` | `{"actor":"member-id","role":"officer"}` |
| POST `/alliances/{id}/owner` | `{"actor":"member-id"}` |
| POST `/alliances/{id}/remove` | `{"actor":"member-id"}` |
| POST `/alliances/{id}/leave` | `{}` |
| GET `/invitations` | Invitations en attente du seul destinataire |
| GET `/messages` et `/messages/page/{createdAt}/{id}` | Messages privés, pagination descendante de 50 entrées, filtrage de visibilité |
| POST `/messages` | `{"id":"stable-request-id","recipient":"actor-id","text":"Hello"}` |
| GET `/blocks` | Joueurs bloqués par cet acteur |
| POST `/blocks` | `{"actor":"actor-id"}` |
| POST `/blocks/remove` | `{"actor":"actor-id"}` |
| GET `/notifications` et `/notifications/page/{createdAt}/{id}` | Notifications persistantes, pagination descendante de 50 entrées et statut de lecture |
| POST `/notifications/{id}/read` | `{}` |
| POST `/reports` | `{"id":"report-id","message":"message-id","reason":"..."}` |
| GET `/leaderboard` | Jusqu'à 50 alliances classées du monde actif |

Les réponses `messages` et `notifications` fournissent `nextAfter` (objet
`{createdAt, id}` ou `null`). Le client doit reprendre par
`/page/{createdAt}/{id}` jusqu'à `null`, sans inclure l'acteur dans l'URL.
La pagination est indexée par le couple (horodatage, identifiant) pour
éviter les doublons même lorsque plusieurs messages partagent la même
seconde. Chaque page est une lecture transactionnelle indépendante :
de nouvelles écritures entre pages ne forment pas un instantané figé.
Les messages masqués ou bloqués sont exclus avant chaque page.

**Limites à traiter avant clôture M09 :** pagination complète des autres boîtes
chargées, gestion d'alliances très grandes, audit de la révocation des
propriétaires et robustesse aux volumes, protections anti-spam plus fines,
qualifications E2E et restauration sur serveur autorisé. Les autres
contraintes G01–G09 de la roadmap restent inchangées.

## Opérations de modération : jamais une route publique

`bin/social-moderate.php` est destiné à une console opérateur séparée,
exécutée avec un compte système autorisé et les permissions SQL restreintes.
La variable `AETERNUM_SOCIAL_MODERATOR` désigne l'identité du responsable
**déjà authentifié par le système hôte** : ce simple identifiant ne fournit
pas à lui seul une authentification forte. Il ne faut jamais exposer cette
commande comme un endpoint web.

Exemples à utiliser **uniquement sur un environnement de recette** après
validation de l'identité du compte opérateur et configuration des variables
de base de données hors dépôt :

```sh
export AETERNUM_SOCIAL_MODERATOR=operator_recette
php bin/social-moderate.php demo-world queue
printf '%s\n' 'Contenu analysé et non conforme' |
  php bin/social-moderate.php demo-world report-id hide
printf '%s\n' 'Contenu jugé conforme' |
  php bin/social-moderate.php demo-world second-report dismiss
```

La consultation révèle des contenus privés et doit rester dans un terminal
contrôlé. L'arbitrage est transactionnel, ne peut être changé silencieusement
et écrit auteur, décision, motif et date dans
`engine_social_moderation_audit`. Une décision `hide` masque le message
concerné dans la boîte du destinataire sans le détruire.

## Mise à niveau, sauvegarde et restauration

1. **Geler les écritures**, vérifier version de PHP/MariaDB et schéma effectif.
   Prendre un dump transactionnel **complet** de la base depuis un compte
   autorisé, vers une destination privée hors webroot. Sauvegarder aussi les
   versions de code et manifests de packs et les réglages des environnements.
2. Restaurer cette sauvegarde dans une **base de recette distincte** et
   confirmer table par table les effectifs/empreintes, les comptes et la
   relecture d'un journal économique historique. Ne jamais appliquer M09
   directement à la seule copie des données réelles.
3. Après validation et en fenêtre de maintenance, exécuter le migrateur
   vérifiant les empreintes des fichiers 028 et 029 une seule fois
   (`php bin/migrate.php` avec la configuration autorisée). Vérifier la
   liste et les tables créées, les accès intermondes et un parcours complet
   multi-joueurs.
4. Si le migrateur s'interrompt ou marque une migration `completed=0`,
   **ne pas effacer le marqueur ni réexécuter aveuglément le SQL** :
   les DDL MariaDB valident implicitement leurs changements. Stopper
   l'application, inspecter l'état, puis restaurer la sauvegarde complète
   vers une base saine (et rétablir la configuration) avant un nouvel essai.
   Ne jamais annoncer un rollback réussi sans recette et empreintes comparées.

Le workflow CI comporte un dump MariaDB `--single-transaction`, la
restauration de toutes les tables dans une base de laboratoire et
`tests/restore-verify.php` qui vérifie les empreintes ligne par ligne et
les anciens rejouages. Ce test ne constitue **pas** une preuve de
restauration de production O2Switch/VPS.

## Preuves exigées avant la fusion

- PHP 8.4, migration sur base vide et base préremplie, opérations positives,
  négatives, accès révoqués, isolation par acteur/monde.
- MariaDB concurrence à quatre connexions, idempotence, notification unique,
  audit et décisions opérateur, dump/restore/rejeu.
- Chromium à deux comptes dans des contextes indépendants et vérification
  de l'absence d'interprétation HTML des contenus de joueurs.
- Logs, SHA exact des runs, absence de conflit GitHub, et examen des
  limitations ci-dessus avant de marquer M09 comme achevée.
