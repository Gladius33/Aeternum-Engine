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

## Migration et retour arrière

Appliquer `php bin/migrate.php` avec une sauvegarde SQL préalable. Aucune
ligne existante n'est supprimée, et aucune restauration n'est tentée
automatiquement. Pour une restauration d'un monde existant, restaurer
**l'ensemble** de la sauvegarde cohérente, pas uniquement les tables 021/022 :
les relations doivent rester synchronisées avec les membres et les lieux.
Ne jamais retirer 022 sur une base de production contenant des territoires
sans plan de migration et validation humaine.

## Sortie de macro NON acquise

Restent à développer les règles typées de bâtiments, la construction
multi-ressources, la planification et l'annulation, l'intégration aux
événements et au journal, le rejeu, l'API/UI des constructions et une
preuve navigateur de deux joueurs évoluant indépendamment.
