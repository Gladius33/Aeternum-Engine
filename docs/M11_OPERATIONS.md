# M11 — Exploitation, observabilité et restauration (G08)

**Statut : travail en qualification, et non autorisation de déploiement.** ROADMAP.md conserve les exigences normatives de la V1.0. Aucun hébergement O2Switch ou VPS n'a été modifié dans cette PR.

## Architecture à installer

Le moteur exige PHP 8.4 CLI/FPM, PDO MySQL natif, MariaDB/InnoDB, un registre explicite de mondes, les versions épinglées des packs et les migrations à empreintes contrôlées.

**Racine HTTP impérative : uniquement public/**. Ne jamais exposer la racine du dépôt, vendor, bin, tools, database, les configurations, les sauvegardes ou les secrets. Ne pas utiliser le serveur PHP intégré en production.

- **VPS :** placer les releases dans un emplacement privé (exemple /srv/aeternum/releases/...), pointer le document root Nginx vers le sous-dossier public du code activé, utiliser PHP-FPM 8.4 en socket locale, refuser tout fichier PHP autre que index.php, imposer TLS et vérifier les en-têtes proxy. Le paramètre HTTPS doit être défini par le serveur UNIQUEMENT après un terminateur TLS fiable ; ne jamais faire confiance à X-Forwarded-Proto envoyé directement par un client.
- **O2Switch mutualisé :** vérifier les versions PHP 8.4 CLI et web, l'accès PDO MySQL, les quotas mémoire/CPU, CRON, mod_rewrite et la possibilité d'avoir un document root pointant uniquement sur public/. public/.htaccess refuse les index et route les API via index.php. Si les sources ne peuvent pas être placées hors webroot, la configuration n'est PAS approuvée. L'hébergeur peut ne pas disposer de mariadb-dump ou du temps CRON requis ; dans ce cas utiliser ses procédures de sauvegarde officielles et en éprouver la restauration dans une base distincte.

Exemple indicatif de locations Nginx à valider pour votre VPS :

    root /srv/aeternum/current/public;
    index play.html;
    location / { try_files $uri $uri/ /index.php$is_args$args; }
    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param HTTPS on;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }
    location ~ \.php$ { return 404; }
    location ~ /\.(?!well-known/) { deny all; }

Attention : HTTPS on n'est légitime que dans un vhost réellement isolé derrière le terminateur TLS. Le déploiement et la modification DNS exigent un accord explicite de l'exploitant.

## Paquet autonome et vérification

Dans le clone Git de build disposant de Composer, Node.js 22, PHP 8.4 et GNU tar :

    composer dump-autoload --no-interaction
    npm ci --prefix client --no-audit --no-fund
    npm run build --prefix client
    bash tools/m11-package.sh /chemin/absolu/prive/paquets
    bash tests/m11-package.sh

Le constructeur prend UNIQUEMENT des fichiers applicatifs suivis par Git et le client TypeScript déjà compilé. Le paquet intègre les sources PHP, bin, les schémas/migrations, les packs, les documents, LICENSE.md, NOTICE et l'autoloader Composer. Il exclut tests, .github, client source, environnements locaux, clés, sauvegardes et Git. L'archive est ordonnée, horodatée de façon contrôlée et accompagnée de SHA-256. Le constructeur ne configure pas le serveur, les DNS ni les secrets.

## Installation initiale, montée de version et retour arrière

1. Identifier le SHA actuel, les mondes approuvés, la version MariaDB, les packs et les règles épinglées. Arrêter les écritures et les workers avant toute migration ; garder l'ancienne release.
2. Faire une sauvegarde cohérente de TOUTES les tables, y compris migrations, comptes, sessions, registres, soldes, journaux et reçus. Faire une COPIE HORS INSTANCE.
3. Restaurer dans une base de recette vide, vérifier empreintes, connexion d'un ancien compte, soldes, journal, rôles et décisions de modération, puis répéter la restauration sur un autre support dans le cadre d'un exercice périodique.
4. Tester php bin/migrate.php sur ce clone peuplé. Une seconde exécution doit répondre avec applied vide. Ne jamais effacer à la main un marqueur de migration sale, ni rejouer une DDL interrompue : MariaDB peut commettre des DDL implicitement.
5. Répéter en fenêtre autorisée seulement après succès de l'essai, puis exécuter les diagnostics, le contrôle du mode d'inscription et les parcours de deux acteurs avant ouverture.
6. En cas d'échec, geler le service, restaurer code/configuration correspondant au dump **et toute la base**, réconcilier les écritures postérieures au snapshot, vérifier les journaux avant réouverture.

Un compte séparé doit exécuter les migrations DDL. Le compte applicatif ne requiert PAS DROP/ALTER ; limiter SELECT, INSERT, UPDATE et DELETE aux seules tables nécessaires, avec permissions de sauvegarde spécifiques. Le contrôle effectif des GRANT, de l'identité OS opérateur et du coffre de secrets reste un critère d'acceptation sur hôte autorisé.

## Sauvegarde et restauration en laboratoire

Les deux scripts n'acceptent que des identifiants MySQL depuis un fichier d'options client **absolu, sans lien symbolique et mode 0600**. Ne jamais commiter le fichier ni le mettre sous public/. Arrêter toutes les DDL et de préférence les écritures : --single-transaction donne un snapshot InnoDB cohérent, mais ne protège pas contre une migration DDL concurrente.

    export M11_CREDENTIALS_FILE=/chemin/prive/mysql-client.cnf
    export M11_SOURCE_DATABASE=aeternum
    export M11_BACKUP_APPROVED=YES
    bash tools/m11-backup.sh /chemin/absolu/prive/backups

Transférer le .sql.gz et son .sha256 hors de l'instance. Créer séparément une base DISPOSABLE VIDE nommée aeternum_restore ou aeternum_restore_suffixe, avec un compte ayant les permissions minimales de création des tables.

    export M11_RESTORE_DISPOSABLE=YES
    export M11_RESTORE_DATABASE=aeternum_restore
    bash tools/m11-restore-lab.sh /chemin/absolu/prive/backups/fichier.sql.gz

Le fichier réel de sauvegarde est généré par le script, remplacez fichier.sql.gz. La restauration vérifie SHA-256 AVANT d'importer et refuse une base non vide. Elle ne supprime aucune base. Un import interrompu exige de jeter le clone partiellement restauré. Un retour disposableRestore=true confirme l'import seulement : vérifier ensuite les empreintes ligne par ligne, l'authentification, les comptes économiques et la relecture déterministe. La CI M11 exerce ces vérifications dans deux bases MariaDB de laboratoire, et refuse les sauvegardes altérées.

## CRON, maintenance et observabilité

Depuis une crontab privée, sous un compte restreint :

    php /chemin/prive/aeternum/bin/ops-cron.php
    php /chemin/prive/aeternum/bin/ops-status.php

La commande CRON prend un verrou MariaDB GET_LOCK dérivé du nom de la base, et la concurrence rend skipped=already_running sans mutation. Une terminaison brutale de la connexion relâche le verrou serveur. Seuls les mondes approuvés existants sont traités ; limite de 16 mondes et 50 événements par monde, budget coopératif de 9 s par défaut, limite de 15 s dans le service. Les délais de verrou SQL, les interruptions et les E/S peuvent dépasser ce budget ; limiter aussi la durée au niveau de la crontab. Chaque purge est bornée à 100 éléments : sessions expirées, buckets anciens, invitations inutilisées, activations non confirmées. Les invitations consommées et journaux utiles au replay restent intacts. Conserver un NTP synchronisé : une horloge reculant sous le plancher du rate limiter provoque un refus contrôlé.

La commande ops-status ne modifie jamais les données. Elle vérifie les empreintes des migrations et leurs marqueurs, les mondes initialisés, les événements échus, leur retard, l'outbox, les sessions actives/expirées et les inscriptions en attente. Les comptages sont plafonnés à 10 000 pour éviter une lecture non bornée ; un indicateur explicite signale ce plafonnement. healthy=false donne un code de retour 2, et une erreur de configuration/SQL un code 1. Ne pas exposer de route HTTP pour ces diagnostics.

Les deux commandes renvoient une ligne JSON sans données personnelles ni secrets ; stderr ne livre que les classes d'exception. Stocker logs et journaux dans un répertoire privé à droits restreints et rétention bornée, ou les transmettre à un collecteur dûment autorisé.

**Seuils d'alerte initiaux à étalonner** : migrations non vérifiées ou CRON en échec, plus de 1 000 événements échus ou événement le plus ancien en retard de 900 s, outbox durablement en croissance, maintenanceDeferred répété, restauration périodique trop ancienne. Une alerte réelle doit être configurée par l'exploitant ; ce projet ne prétend pas envoyer déjà des notifications externes. Surveiller en outre disque, erreurs FPM, latence p95, quota de connexions SQL et expiration TLS.

## Critères et preuves M11

| Parcours | Preuve technique | Limite |
| --- | --- | --- |
| Installation neuve | CI M11 crée 001..030 et un monde/joueur réel | Hôte externe non testé |
| Migration habitée | Migration 031 sur compte et journal existants, second passage no-op | Aucun patch manuel en prod |
| Sauvegarde/restauration | Dump InnoDB -> nouvelle base -> comparaison des tables, journal et états | Données de laboratoire uniquement |
| CRON concurrent | Verrou de deux connexions MariaDB, exécution bornée, reprise sans double effet | Charge réelle à étalonner |
| Horloge et échecs | Refus contrôlé de rollback d'horloge et libération du verrou | Arrêt brutal hôte à approfondir |
| Paquet déployable | Tarball sans secrets, contenu et autoload vérifiés, construction répétée | Ne déploie pas l'infra |
| O2Switch/VPS | Procédures séparées, préflight et restriction webroot | Validation humaine manquante |
| Permissions et alertes | Recommandations et métriques produites | GRANT/alertes réelles non prouvées |

Ne marquer M11 achevée que lorsque PHP 8.4, MariaDB, Chromium et la CI M11 spécifique sont verts sur **le même SHA**, qu'aucune anomalie critique n'est ouverte et que les limites non résolues sont explicitement indiquées sans diminuer les critères G08.
