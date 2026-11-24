# World Politics — back-end exclusivement, contrat REST (24 novembre 2026)

## Règles de séparation, sans exception

- World Politics est un **jeu distinct**, Aeternum est un moteur de jeux générique.
- **NE JAMAIS fusionner le jeu ni cette branche dans main d'Aeternum.**
- La branche du jeu n'est qu'un espace provisoire jusqu'à son dépôt dédié.
- **Aucun graphique**, aucune interface Vue/HTML/CSS, aucune dépendance aux
  templates génériques d'Aeternum tant qu'ils ne sont pas stabilisés.
- Le navigateur, Android, iOS et un éventuel logiciel de bureau consommeront
  la **même API RESTful JSON versionnée**, sans règles métier dans les clients.

## Travail réellement présent dans la branche

| Domaine | Implémentation |
| --- | --- |
| Profils politiques | PoliticalRoster.php |
| Création rapide / détaillée (formulaire décrit en JSON) | PoliticalProfileTemplate.php |
| Contrat API en mémoire, pour comparaison | WorldPoliticsApi.php |
| Stockage MariaDB / InnoDB transactionnel | PdoPoliticalStore.php |
| REST adossé à MariaDB, transport non exposé | PdoWorldPoliticsApi.php |
| Moteur constitutionnel et persistance nationale | InstitutionalSimulation.php, PdoInstitutionalStore.php |
| Diplomatie, acteurs Palestine/Israël/Iran, règles IA fictives | GeopoliticalActorRegistry.php, DiplomaticSimulation.php |
| Diplomatie mondiale transactionnelle | PdoDiplomaticStore.php, PdoDiplomaticApi.php |
| Journal et reçus diplomatiques MariaDB | games/world-politics/database/003_diplomacy.sql |
| Contrôles d'intégration et de restauration diplomatique | tests/wp08-diplomacy-db.php |
| Migration SQL du jeu, indépendante du noyau | games/world-politics/database/001_rosters.sql |
| Contrat OpenAPI 3.1 | games/world-politics/api/openapi.yaml |
| Tests PHP et MariaDB | tests/wp01-*.php à tests/wp04-rest-db.php |

Les profils de la base de code actuelle sont des **fixtures synthétiques**.
Aucun import complet de personnalités politiques réelles ni calcul
économique n'est encore intégré. Les premiers agents de diplomatie sont
des règles déterministes **fictives**, et non des IA gouvernementales calibrées.

## Éditeur de personnages pour les futurs clients

Le serveur publie une spécification de champs pour que les interfaces
mobile, bureau et navigateur puissent être construites **plus tard** sans
dupliquer les règles de validation.

- Mode **rapide** : seul le **nom** est nécessaire ; le serveur produit un
  identifiant stable associé à l'acteur, au monde et à la commande.
- Mode **détaillé** : les mêmes rubriques qu'une fiche biographique réelle :
  présentation, date et lieu de naissance, études, diplômes, parcours
  professionnel, fonctions publiques, parti, courant revendiqué, mandats,
  expertise, langues, priorités et mesures programmatiques.
- Toutes les rubriques autres que le nom sont **facultatives**.
- L'avatar est, pour cette première version, **toujours** \`generic-v1\`.
  Aucune image n'est chargée ni générée par le back-end.
- Les profils réels sont importables uniquement par une filière de curation
  de confiance, avec dates et sources vérifiables. Un client ne peut pas
  déclarer son personnage fictif comme étant une personne réelle.
- Les propositions et les biographies fictives ne deviennent jamais des
  déclarations authentiques d'une personnalité réelle.

### Métadonnées pour les formulaires

\`GET /api/v1/worlds/{world}/countries/{ISO2}/politics/person-template\`

Cette ressource fournit un contrat décrivant deux modes de saisie, des
sections, des contraintes et des libellés français. **Ce n'est pas un
formulaire HTML** ; les clients en feront ce qu'ils veulent ultérieurement.

### Exemple de création immédiate

\`POST /api/v1/worlds/{world}/countries/FR/politics/people\`

\`\`\`json
{"name":"Camille Moreau","mode":"quick"}
\`\`\`

Un programme détaillé peut être fourni dès la création, ou laissé vide.
Une fonctionnalité d'édition ultérieure, réservée au créateur authentifié,
reste à développer : le modèle actuel ne doit pas laisser un autre joueur
modifier un personnage fictif existant.

### Autres endpoints

- GET national roster et GET fiche \`/people/{id}\`;
- POST initialisation d'une présidence alternative ;
- POST nomination proposée ;
- POST résolution (acceptation/refus) de la nomination.

Tous les POST utilisent \`Idempotency-Key\` et \`If-Match\` (ETag fort).
Le stockage durable verrouille la ligne nationale, revalide l'autorisation
de l'acteur fourni côté serveur, conserve l'état, les reçus de commande et
les événements append-only dans **une seule transaction**.
Les accusés de réception restent disponibles après reconnexion ou reprise
du processus. Les tests négatifs couvrent les divergences d'état et les
accès intermondes.

## Ne pas exposer en production pour le moment

Le service REST durable est un **adaptateur applicatif**, non un point
d'entrée HTTP actif. Il n'implémente ni validation de véritables jetons,
ni contrôles de sessions Aeternum, ni politiques constitutionnelles
nationales, ni limitation des requêtes, ni autorisation fine de propriété
des personnages. L'identité et les autorisations du \`PoliticsAuthorizer\`
proviennent encore d'un composant injectable pour les tests.

Une mise en production nécessite d'abord un adaptateur d'authentification
fiable, l'autorisation des fonctions/nomination, les sauvegardes et
migrations, le contrôle de charge, le rejeu d'événements et des tests de
concurrence multi-acteurs.

Le prototype historique \`WorldPoliticsApi.php\` reste **en mémoire**
et ne doit pas être confondu avec \`PdoWorldPoliticsApi.php\`.

## Validation continue

Le workflow \`.github/workflows/world-politics.yml\` exécute :
- les régressions de domaine et du contrat REST ;
- les profils rapides/détaillés et tests de provenance ;
- une véritable MariaDB isolée avec transactions, reprise, journal,
  conflits, contrôle d'accès et appels API de bout en bout.

Le passage en CI ne constitue **pas** une homologation de production ni
une autorisation de fusion dans le moteur générique.

## Étapes suivantes — exclusivement back-end

1. Distinguer définitivement \`person_id\` et identité de compte ;
   rattacher et autoriser les créations/modifications de profils fictifs.
2. Intégrer l'authentification/les capacités de jeu d'Aeternum à travers
   un adaptateur stable, sans importer le frontend.
3. Importer les données publiques réelles, historisées, avec provenance,
   autorisations de réutilisation et versions de scénario immuables.
4. Formaliser les constitutions nationales, nominations, votes et crises
   institutionnelles dans le moteur d'événements versionné.
5. Économie, défense, conscription, mobilisation, alliances, diplomatie,
   religions et IA gouvernementales respectant les commandes autorisées.
6. Tests avancés de replay, de concurrence et de charge avant tout client.

Consulter \`docs/WORLD_POLITICS.md\` pour le périmètre de serious game.

## Contrat REST diplomatique durable — WP08

Les modules spécifiques au jeu fournissent `PdoDiplomaticStore` et
`PdoDiplomaticApi`, avec les routes suivantes :

| Verbe | Route | Objet |
| --- | --- | --- |
| GET | `/api/v1/worlds/{world}/diplomacy` | état diplomatique versionné |
| GET | `/api/v1/worlds/{world}/diplomacy/actors` | acteurs publics sourcés |
| GET | `/api/v1/worlds/{world}/diplomacy/proposals` | propositions en cours ou terminées |
| POST | `/api/v1/worlds/{world}/diplomacy/proposals` | demande/dialogue/position fictive |
| POST | `/api/v1/worlds/{world}/diplomacy/responses` | réponse du destinataire autorisé |
| POST | `/api/v1/worlds/{world}/diplomacy/ai/decisions` | service d'IA vérifié seulement |
| POST | `/api/v1/worlds/{world}/diplomacy/clock` | horloge simulée vérifiée seulement |

L'ensemble reste un **contrat applicatif interne**, pas encore un endpoint
HTTP public. Le principal d'authentification injecté doit être déterminé
par une authentification fiable ; aucune donnée du corps JSON ne lui
attribue le contrôle d'un pays.

Le stockage garantit l'atomicité de la mise à jour, du journal et du
reçu d'idempotence. Le moteur de stratégie diplomatique n'accorde pas
à une autorité administrative la personnalité d'un État souverain.

**Important :** les snapshots sont réécrits en entier dans une ligne
par monde. Avant la production multijoueur, il faudra segmenter ce
stockage et ajouter le rejeu indépendant depuis le journal, les
quotas/pagination et les contrôles de concurrence à grande échelle.


## WP25 et bilan général du back-end

Le calendrier français constitutionnel est désormais raccordé de manière facultative mais **persistante et obligatoire lorsqu'activée** à la saga de l'horloge mondiale. L'autorité horaire est indépendante des données de requête, les échéances de jour sont calculées en heure de Paris avec DST, et la reprise après panne est vérifiée en CI sur MariaDB.

Le calendrier des jours de séance de l'Assemblée, la résolution obligatoire des motions en temps voulu et le verrou contre toute mutation concurrente ne sont toujours pas achevés. **L'évaluation complète des domaines encore absents, des risques de production et des critères d'acceptation est consignée dans `docs/WORLD_POLITICS_BACKEND_REMAINING.md`.**
