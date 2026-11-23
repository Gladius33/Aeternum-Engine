# M10 — Administration par monde et premium strictement simulé (en cours)

**Statut : PR #50 en brouillon ; aucune fusion.** Le périmètre normatif
demeure M10 (G05/G07) dans MACRO_ROADMAP_V1.md et ROADMAP.md.
M10 est développée dans une branche distincte basée sur le HEAD M09
dd55fe1, afin de conserver l'ordre des migrations 028, 029, 030, puis
031_admin_test_entitlements.sql. Une fusion de M10 sans qualification de
M09 est interdite.

## Autorité et frontières de sécurité

Il n'existe aucun endpoint d'administration HTTP. La console
bin/admin-console.php est uniquement exécutable en CLI depuis un terminal
dont le compte système et les permissions SQL sont déjà administrés.
AETERNUM_ADMIN_OPERATOR est un identifiant d'attribution, jamais une
preuve d'identité ou un secret à lui seul.

Deux rôles s'appliquent seulement à un monde :
- viewer : status du monde et consultation des actions auditables ;
- manager : mêmes lectures, appartenance des acteurs et événements TEST.

La commande members fournit des entrées bornées à 50 par page, avec un
curseur d'identifiant ; identity examine un acteur et ses comptes
exclusivement dans le monde autorisé, sans divulguer d'autres mondes.
Les acteurs hérités sans identité applicative restent recensés sans
fabriquer de login.

Les permissions sont revérifiées en base dans engine_admin_scopes pour
chaque commande ; aucune session joueur ne peut attribuer un rôle.
Le provisionnement initial des portées est volontairement hors API et
doit être réalisé par l'administrateur SQL déjà autorisé, sur une
connexion privée. Exemple pour une BASE DE RECETTE :

    INSERT INTO engine_admin_scopes
      (world_id,operator_id,role,active,changed_at)
    VALUES
      ('world-recette','operateur_local','viewer',1,UNIX_TIMESTAMP());

La révocation initiale d'une portée se fait par une procédure
d'administration SQL distincte, jamais par la console exposée aux joueurs.
Les opérations bootstrap doivent être attribuées dans le registre de
l'hôte, car elles précèdent volontairement le journal de M10.

Pour l'exploitation réelle, séparer le compte SQL destiné à la consultation
des règles, le compte capable de modifier les appartenances et l'accès
administratif aux portées. Les secrets et autorisations de l'hôte ne sont
ni codés en dur ni présents dans le dépôt.

## Mondes, règles et identités

La commande world montre uniquement le monde auquel l'opérateur est
autorisé, la clé de règles épinglée, le watermark et les membres actifs.
Le changement des règles et des manifests ne passe jamais par M10 :
il relève du registre de packs versionné et d'une migration approuvée,
afin de préserver les journaux et les mondes historiques.

La commande membership peut accorder ou révoquer l'appartenance d'une
identité existante à un seul monde autorisé. La transaction verrouille la
ligne du monde avant de vérifier le rôle et d'appliquer la modification.
Elle ne supprime aucune identité, session, économie ou propriété.
Une relecture identique de request_id n'effectue aucune nouvelle mutation,
tandis qu'un rejeu conflictuel est rejeté. Toute intervention produit
un audit attribué dans engine_admin_audit.

## Premium strictement TEST-ONLY

Le mode test est imposé par la variable d'environnement
AETERNUM_PAYMENT_MODE=test, en plus du rôle manager.
Seules les fonctionnalités cosmétiques suivantes sont admises :

- profile_badge : insigne visuel de soutien ;
- ui_theme : apparence alternative du panneau cosmétique.

Aucune vente, aucun prix, aucune monnaie, aucune carte, aucune facture,
aucun PSP, aucune recharge réelle et aucun droit d'accélération de jeu
n'existent dans M10. La table engine_test_payment_events ne décrit que
des transitions simulées grant/revoke. Ces événements ne touchent pas
les ressources, les achats, les soldes, les classements ou les moteurs
de production.

Le service conserve une clé de déduplication (monde, event_id) et un
digest de la charge logique. Tous les objets (événement, audit,
projection) sont validés atomiquement. Répéter un événement ancien
ne peut jamais ressusciter un droit révoqué ensuite. Les effets
simulés sont limités aux acteurs membres actifs du monde.

## Commandes d'administration (uniquement en laboratoire autorisé)

Après avoir configuré hors du dépôt la connexion PDO/MariaDB et un
opérateur effectivement autorisé :

    export AETERNUM_ADMIN_OPERATOR=operateur_local
    php bin/admin-console.php world world-recette
    php bin/admin-console.php members world-recette
    php bin/admin-console.php identity world-recette ACTOR_ID
    php bin/admin-console.php audit world-recette
    php bin/admin-console.php membership world-recette ACTOR_ID revoke m10-op-001
    php bin/admin-console.php membership world-recette ACTOR_ID grant m10-op-002
    export AETERNUM_PAYMENT_MODE=test
    php bin/admin-console.php test-event world-recette ACTOR_ID profile_badge grant m10-test-001
    php bin/admin-console.php test-event world-recette ACTOR_ID profile_badge revoke m10-test-002

Les commandes sans scope, avec rôle viewer lors d'une écriture,
sans identité opérateur, avec monde étranger, ou avec paiement hors
mode test, sont refusées. Les journaux de la console ne doivent jamais
contenir de secrets ni de justificatifs financiers.

## API et interface du joueur

Une route premium, exclusivement en lecture :

    GET /v1/worlds/{world}/premium/me

Elle vérifie HTTPS via le front controller, le bearer, la présence
effective du monde dans le registre, l'appartenance active, puis une
limite de débit. La réponse contient seulement les droits cosmétiques
du porteur de session, triés, et l'indicateur testModeOnly=true.
Aucune route POST ne permet d'accorder ou d'acheter des droits.
Une requête sur un autre acteur ne révèle rien.

L'interface HTML/JS affiche le badge et le thème uniquement si
la projection personnelle autorisée les contient, sans prétendre
à une capacité premium de production. La sélection d'un autre monde
ou la déconnexion efface les états précédemment affichés.
Les textes du panneau sont traduits français/anglais.

La mention requise « Powered by Aeternum Engine — Created by
Sébastien TOUILLEUX. » et le lien vers la licence ACCL-1.0 figurent
dans l'interface. Les fichiers LICENSE.md et NOTICE restent inchangés.

## Migration et restauration

031_admin_test_entitlements.sql ajoute des tables sans toucher
aux tables préexistantes. Avant toute application sur une base
durable, arrêter les écritures et prendre une sauvegarde complète
dans un répertoire privé, puis la restaurer sur une base distincte.
Le migrateur vérifie les empreintes de toutes les migrations.
Une DDL MariaDB interrompue ne doit jamais être « réparée » par
l'effacement manuel d'un marqueur incomplet : examiner puis restaurer
une sauvegarde cohérente.

La CI reproduit une mise à niveau sur monde prérempli, puis vérifie
la conservation du grand livre historique, les droits opérateur,
les refus intermondes, les rejouages, la concurrence entre quatre
connexions PDO, les signaux de paiement simulés et la restauration
bit à bit des tables, accompagnée des projections premium.
Chromium teste deux sessions de joueur et l'évolution de leurs
droits après des commandes locales, sans transaction réelle.

## Critères de sortie encore à acquérir

M10 ne peut être déclarée complète que lorsque la CI PHP 8.4,
MariaDB/dump/restauration et Chromium est verte sur le MÊME SHA,
et que les permissions minimales, le comportement en échec et la
suffisance des outils de gestion sont qualifiés. L'authentification
forte des opérateurs dépend de la configuration de l'hôte et n'a
pas été éprouvée sur O2Switch ou VPS. Aucune communication à un PSP
réel n'a été exécutée ni demandée.

Une PR ouverte n'est pas une macro terminée. Maintenir la PR #50
en brouillon et ne pas fusionner sans validation explicite de M09
et des critères de sortie de M10.
