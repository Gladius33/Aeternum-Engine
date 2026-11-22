# M04 — Monde géographique (travail en cours)

Les migrations 021 et 022 sont **additives**. La 021 ajoute des liaisons
orientées entre lieux ; la 022 ajoute un registre territorial, relié aux
lieux et au monde par clés étrangères composites. Les mondes préexistants
conservent tous leurs lieux, entités et journaux. Aucun changement de licence.

## Invariants acquis et limites

- `PdoTopology::connect` conserve une liaison orientée immuable ; découverte
  autorisée uniquement aux membres actifs, pagination bornée et déterministe.
- `PdoTerritories::define` crée un territoire immuable (identifiant,
  lieu, type) ; `assign` attribue une propriété immuable à un membre actif.
  Ces deux écritures sont des opérations **de configuration de monde réservées
  à l'opérateur**, jamais des commandes exposées à un joueur.
- Chaque mutation verrouille le monde et son travail est transactionnel.
  Les conflits ne créent aucune propriété partielle. Un territoire libre
  n'accorde **aucun droit d'acquisition automatique**.
- Une projection de carte permet aux membres actifs de découvrir les
  territoires, paginés par ID ASCII. Le statut visible est uniquement
  `own`, `occupied` ou `free`, sans identifiant du propriétaire tiers.
  Une page représente un instantané commité ; plusieurs pages successives
  ne garantissent pas un instantané stable.
- Les attributions administratives ne sont **pas encore des commandes
  simulées** : elles ne sont pas dans le journal déterministe du moteur.
  Leur audit/rejeu et l'intégration de coûts/prérequis restent à construire
  avant de déclarer M04 terminée.

## Initialisation opérateur

Le CLI local `bin/m04-map.php` refuse les mondes non approuvés. Commandes :
`location WORLD LOCATION`, `edge WORLD FROM TO COST`,
`territory WORLD TERRITORY LOCATION KIND`, `owner WORLD TERRITORY ACTOR`,
`inspect WORLD ACTOR [AFTER]`. En pratique, passer le monde en premier :
`php bin/m04-map.php WORLD location LOCATION`.

Ce CLI n'est pas un contrôleur HTTP et exige les mêmes variables
`AETERNUM_DSN`, `AETERNUM_DB_USER` et `AETERNUM_DB_PASSWORD` que les
autres tâches administratives. En CI, deux processus indépendants tentent
une attribution contradictoire et un seul doit réussir.

## Types de bâtiments et fondations (préparation M04)

La migration 023 enregistre une liste immuable de coûts par niveau, chaque
étape comprenant une durée et des prérequis déclaratifs. Les coûts sont
normalisés puis validés : quantité entière strictement positive et au plus
16 ressources/prérequis par étape. Les plans sont épinglés à
`engine_worlds.rules_key`. Les bâtiments de niveau zéro sont liés à un
acteur actif, une entité et un territoire de même monde et de même lieu,
tous deux déjà possédés par l'acteur. Un identifiant de bâtiment est
immuable, les lectures sont réservées au propriétaire actif.

**Important :** `PdoBuildings::establish` reste réservé à l'opérateur ;
aucun coût n'est débité, aucune construction planifiée ni achevée et
aucun journal de commande généré. Il serait incorrect de présenter une
fondation comme une construction jouable. L'intégration au moteur de
commandes, à l'économie et au rejeu demeure une exigence M04 ouverte.

## Projection privée des bâtiments

L'endpoint de lecture seule `GET /v1/worlds/{world}/buildings/{id}`
demande un jeton valide, vérifie que le monde est approuvé et que l'acteur
possède le bâtiment et son appartenance active, puis renvoie le niveau
persistant et le prochain coût multi-ressources. Aucun endpoint joueur
ne peut établir ou améliorer un bâtiment.

## Migration et retour arrière

Appliquer `php bin/migrate.php` avec une sauvegarde SQL préalable. Aucune
ligne existante n'est supprimée, et aucune restauration n'est tentée
automatiquement. Pour une restauration d'un monde existant, restaurer
**l'ensemble** de la sauvegarde cohérente, pas uniquement les tables 021/022 :
les relations doivent rester synchronisées avec les membres et les lieux.
Ne jamais retirer 022 sur une base de production contenant des territoires
sans plan de migration et validation humaine.

## API et navigateur (lecture seule)

`GET /v1/worlds/{world}/map/territories[/{after}]` retourne 50 secteurs
au maximum et `nextAfter`. `GET /v1/worlds/{world}/map/locations/{id}/links[/{after}]`
fait de même pour les relations orientées. Les deux routes exigent une
session active et une appartenance au monde configuré, appliquent un quota
de lecture et n'exposent pas d'API de mutation. L'interface de référence
permet de visualiser les territoires et les routes, pas de construire.

### Recherche d'itinéraires

La route authentifiée `GET /v1/worlds/{world}/map/routes/{from}/{to}`
calcule un chemin orienté de coût total minimal. À coût égal, le
chemin lexicalement le plus petit est choisi ; les parcours sont bornés à
500 lieux développés et 100 arcs sortants par lieu. L'API retourne
`route: null` lorsqu'aucun chemin n'existe. L'interface affiche le trajet
et son coût ; **aucun mouvement de troupes n'est déclenché**.

## Sortie de macro NON acquise

Restent à développer les règles typées de bâtiments, la construction
multi-ressources, la planification et l'annulation, l'intégration aux
événements et au journal, le rejeu, l'API/UI des constructions et une
preuve navigateur de deux joueurs évoluant indépendamment.
