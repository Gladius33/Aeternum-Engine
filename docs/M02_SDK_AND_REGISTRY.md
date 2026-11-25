# M02 — Registre des mondes, SDK typé et règles immuables

## Frontière de confiance

Les packs restent des manifestes JSON déclaratifs. Ni une requête publique, ni
un manifeste ne choisit un fichier PHP à exécuter, un chemin système libre, un
serveur externe, un handler arbitraire ou une autorisation d'acteur. Les
métadonnées de composants/actions/hook n'activent pas automatiquement de
commandes publiques. Seul le code applicatif de confiance exécute les règles.

Le registre WorldPackRegistry associe un identifiant de monde à une version
exacte de pack, identifiée par une empreinte SHA-256 de ses octets. Le
contrôleur HTTP, la découverte des comptes, les inscriptions, les commandes
opérateur et les workers passent par ConfiguredWorlds. Ainsi, l'ajout d'un
pack approuvé et d'un monde n'exige pas d'éditer public/index.php.

## Installation d'un pack approuvé

L'opérateur place lui-même le manifeste dans games/ID_DU_PACK/manifest.json,
hors de toute interface d'upload et après revue des données. Pour installer
une nouvelle version *sans remplacer l'ancienne*, il utilise
`games/ID_DU_PACK/versions/VERSION/manifest.json`. Le registre sélectionne
ce fichier lorsqu'il existe ; les mondes historiques restent reliés aux
anciens octets. Les chemins symboliques vers un autre fichier et les
références ../ sont rejetés.
Le troisième pack utilisé par les tests M02 est situé sous
tests/fixtures/m02-packs/sdk-fixture/manifest.json : c'est un artefact de
qualification, non un troisième jeu distribué aux utilisateurs.

Exemple de registre privé, à stocker en dehors de public/ :

    {
      "registry_api": "1",
      "worlds": [
        {
          "world": "monde-medieval",
          "pack": "reference-medieval",
          "version": "0.1.0",
          "sha256": "INSERER_ICI_64_CARACTERES_HEXADECIMAUX"
        }
      ]
    }

Pour calculer l'empreinte :
    sha256sum games/reference-medieval/manifest.json

L'empreinte doit être la valeur hexadécimale de 64 caractères, et non
l'exemple ci-dessus. Valider avant mise en service :
    php bin/registry-status.php /srv/aeternum-private/worlds.json

Puis définir pour les processus HTTP et CLI :
    AETERNUM_WORLD_REGISTRY=/srv/aeternum-private/worlds.json

Les deux anciennes variables AETERNUM_MEDIEVAL_WORLD et
AETERNUM_INDUSTRIAL_WORLD restent prises en charge lorsque le nouveau registre
n'est pas défini. Pour éviter toute ambiguïté, ne pas mélanger les deux
modes : le nouveau registre prend toute la configuration des mondes.
Un registre vide, non conforme, contenant un monde répété, une version
incompatible ou une empreinte fausse provoque un refus explicite.

Les commandes opérateur existantes conservent leur syntaxe :

    php bin/provision.php PACK WORLD LOGIN
    php bin/worker.php PACK WORLD

Elles exigent désormais une correspondance exacte PACK/WORLD dans le registre
approuvé. La commande provision reçoit le secret par stdin. Aucune migration
de base de données n'est ajoutée en M02.

## SDK déclaratif API 1

Les champs facultatifs du manifeste sont :

- components : [{ "id":"outpost", "fields":{"owner":"string","level":"integer"} }]
- actions : [{ "type":"economy.open", "payload":{"account":"string","resource":"string"} }]
- event_hooks : [{ "type":"economy.upgrade.complete", "payload":{"account":"string","resource":"string","level":"integer"} }]

Les types exacts autorisés sont integer, string, boolean, nullable_integer,
nullable_string et nullable_boolean. Les champs surnuméraires, les types
coercibles non exacts et les textes comportant des caractères de contrôle
sont refusés. La taille et le nombre de déclarations sont bornés.

Exemple d'intégration dans une extension PHP de confiance :

    $pack = GamePackLoader::load(file_get_contents($trustedManifest));
    $sdk = $pack->sdk();
    $component = $sdk->component('outpost', ['owner'=>'alice','level'=>1]);
    $cmd = $sdk->command('world-1','request-42','economy.open',
        ['account'=>'alice','resource'=>'wood']);

Le SDK valide les enveloppes, mais ne se substitue pas aux contrôles de
propriété et de permissions du scheduler. Les événements déclarés dans le
manifeste sont typés par TypedPackEventHandler, y compris pendant le rejeu.
Le traitement est délégué au handler applicatif déjà approuvé. Un hook
déclaré pour un événement non implémenté par un handler de confiance ne
devient pas exécutable par magie : il échoue de manière sûre.

Les manifestes API 1 historiques sans champs facultatifs restent inchangés
et conservent exactement leurs empreintes et identités de règles.

## Protocole de versionnement et de migration des mondes

Un monde existant possède une clé de règles basée sur l'identifiant, la
version et l'empreinte des octets du manifeste. Une modification, même
cosmétique, d'un pack épinglé entraîne un refus à la relecture. C'est une
protection de l'historique déterministe, pas une erreur à contourner.

Avant une nouvelle version :

1. Arrêter les écritures et workers du monde concerné. Conserver les octets
   exacts du pack, sa version, sa clé de règles et la configuration d'origine.
2. Prendre une sauvegarde transactionnellement cohérente hors instance et
   vérifier sa restauration sur une base isolée.
3. Vérifier le rejeu et les soldes avec les anciens handlers sur la copie.
4. Installer le nouveau pack sous une identité de version distincte, le
   référencer pour un *nouvel* identifiant de monde, et effectuer ses tests
   API, MariaDB, permissions, concurrence et rejeu.
5. Ne jamais réécrire engine_worlds.rules_key pour migrer un monde peuplé :
   une vraie procédure de migration avec checkpoint, audit et retour arrière
   devra être construite puis validée avant une telle opération.
6. Pour revenir à l'ancienne version, restaurer la configuration et les
   octets d'origine. Si la base a changé, restaurer la sauvegarde vérifiée.

Ce protocole est conservateur : M02 n'implémente pas une migration en ligne
d'une simulation déjà peuplée. Les tests M02 prouvent le refus d'un
changement silencieux, le maintien d'une version historique et le rejeu
d'un troisième pack sur une base de laboratoire. 

## Critères testés et limites

- Registre strict, détection de modifications et mise en correspondance
  d'un troisième pack non livré comme jeu : tests unitaires.
- Construction de commandes et événements typés, rejet de schémas invalides :
  tests unitaires positifs et négatifs.
- Nouveau monde, action programmée, fin d'événement, ressources et rejeu :
  MariaDB réelle en CI, avec verrou de version.
- Routes publiques sur registre configuré, mondes existants et deux jeux :
  navigateur Chromium en CI.
- Rétrocompatibilité des anciens mondes épinglés : tests de non-régression.

La gestion de composants mutables, l'exécution de nouvelles mécaniques
stratégiques, les packs médiéval et industriel pleinement jouables et une
migration de versions sur monde vivant relèvent de M04–M06 et nécessitent
des handlers de confiance et des preuves supplémentaires.
