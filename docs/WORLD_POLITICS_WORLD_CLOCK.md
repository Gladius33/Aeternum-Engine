# World Politics WP17 — horloge mondiale coordonnée et récupérable

## Séparation de projets

World Politics est un jeu indépendant provisoirement développé dans une branche d'Aeternum-Engine. **Ne jamais fusionner dans main.** Cette itération ne comprend ni frontend, ni graphiques, ni serveur HTTP public.

## Problème traité

Les trois domaines déjà implémentés disposaient chacun de leur horloge virtuelle : organisations internationales, diplomatie et économie. Une succession de commandes sans coordination pouvait laisser les domaines à des dates différentes après une panne.

WP17 ajoute une coordination **saga persistante**. Le système inscrit d'abord l'intention d'avancer à une date précise dans wp_world_clock, puis exécute les trois horloges par des commandes idempotentes et journalise chaque étape dans wp_world_clock_steps. La date mondiale officiellement validée n'est modifiée que lorsque les trois domaines ont atteint la même date.

### Ordre du passage de date

1. Organisations internationales : préavis et retraits arrivés à échéance.
2. Diplomatie : expiration des offres et réponses différées du monde.
3. Économie : activation des politiques préalablement autorisées et parvenues à leur date d'effet.
4. Publication de committed_day uniquement lorsque tous les points précédents ont été confirmés.

L'ordre est un **choix technique de scénario WP17**, non une règle juridique applicable universellement. Les institutions nationales n'y sont pas encore intégrées. Les échéances et dépendances interdomaines plus complexes exigeront un calendrier à événements ordonnés.

## Récupération après une interruption

Une reprise doit fournir le même monde, la même date cible et la même clé d'idempotence. Le coordinateur vérifie chaque domaine : si la date voulue est déjà atteinte mais que le point d'étape n'a pas été enregistré, il reconstruit le checkpoint au lieu de répéter l'opération. Si un domaine est à une troisième date inattendue ou si un checkpoint déjà signé ne correspond plus à son état, l'exécution est refusée.

Une autre date ou une clé distincte ne peut pas dépasser une opération partiellement exécutée. L'horloge refuse également les retours dans le passé et les sauts de plus de 366 jours de jeu en une seule commande.

La coordination est **récupérable mais pas atomique sur tous les domaines** : lorsque la date est en cours d'avancement, les trois agrégats peuvent transitoirement afficher des dates différentes. Le statut `advancing` doit empêcher un client d'afficher le nouveau jour comme officiellement cohérent. Avant un multijoueur à grande échelle, il faudra imposer un verrou de monde aux autres commandes et une vue de lecture cohérente pour tous les clients.

## Sécurité

- WorldClockCoordinator impose un principal de service vérifié et la même identité serveur dans les trois magasins de domaine.
- Les domaines eux-mêmes recontrôlent les capacités `organizations.clock`, `diplomacy.clock` et `economy.clock`.
- L'infrastructure de production devra retirer ces capacités à tous les autres services pour éviter le contournement de l'horloge centrale.
- Un reçu déjà acquis ne contourne jamais une révocation de la capacité `world.clock.advance`.
- Aucun pouvoir de pays, décret présidentiel, vote de parlement ou sanction juridique ne peut être créé par l'horloge.
- Chaque magasin de domaine et le coordinateur doivent utiliser des connexions PDO distinctes pour permettre le verrouillage et la récupération de transactions indépendantes.

## Fichiers

- `games/world-politics/database/007_world_clock.sql` : intention persistante, date officielle, derniers reçus et checkpoints par domaine.
- `games/world-politics/src/WorldClockCoordinator.php` : planification, progression, validation et récupération.
- `games/world-politics/src/WorldClockApi.php` : contrat REST versionné sans exposition HTTP effective.
- `games/world-politics/api/openapi.yaml` : nouvelles routes et schémas.
- `tests/wp17-world-clock-db.php` : test sur MariaDB réelle, avancée des trois domaines, panne après deux étapes, reprise d'une écriture économique sans checkpoint, idempotence, révisions et révocation.

### Routes expérimentales

- `GET /api/v1/worlds/{world}/clock`
- `POST /api/v1/worlds/{world}/clock/advances` : corps `{ "target_day": "2026-12-31" }`, en-têtes `Idempotency-Key` et `If-Match`.

Les routes constituent pour l'instant **un adaptateur PHP de contrat REST, pas un serveur déployé**. Les clients web, mobiles ou bureau pourront partager ce contrat après réalisation du gateway, de l'authentification des comptes et du système de rôles.

## Étapes suivantes

1. Intégrer les horloges institutionnelles par pays et leurs délais constitutionnels.
2. Garantir la lecture cohérente d'un monde en cours de progression, suspendre les commandes concurrentes ou faire appliquer un verrou commun.
3. Implémenter les dépendances entre organisations, sanctions, diplomatie et marché national à partir de données et règles vérifiées.
4. Extraire World Politics vers son propre dépôt Git, sans modifier le moteur générique.

## WP18 — échéances constitutionnelles nationales coordonnées

Le coordinateur WP17 peut désormais être configuré avec une liste **immuable** de pays dont les règles constitutionnelles ont déjà un prototype validé : FR, DE et US.

Le fichier `database/008_world_clock_countries.sql` ajoute un registre des calendriers nationaux obligatoires et un checkpoint durable par pays et par commande. Aucun pays ne peut être supprimé silencieusement de ce registre lorsqu'un service redémarre.

L'ordre de calcul d'un jour est : organisations internationales ; institutions nationales activées triées par code ISO ; diplomatie ; économie ; publication de la date mondiale cohérente.

Un projet de loi en France, en Allemagne ou aux États-Unis continue de suivre le modèle institutionnel distinct de son pays, y compris ses événements d'agenda. Un calendrier national retardé, inaccessible ou non autorisé empêche la publication du nouveau jour mondial.

Le coordinateur national emploie le principal de service institutionnel vérifié, et jamais le rôle revendiqué par un joueur. Les pays non couverts par un module constitutionnel ne sont pas ajoutés fictivement à la liste des pays à calendrier pilotable.

Le test `tests/wp18-world-clock-institutions-db.php` vérifie sur MariaDB : trois rôles législatifs distincts, agenda des débats, reprise après deux refus successifs de calendrier, restauration d'une échéance américaine déjà écrite sans checkpoint, absence de double exécution, contrôle d'accès, et impossibilité de retirer un pays de l'univers de simulation.

**Limite importante :** les calendriers FR/DE/US ne constituent encore que des modèles partiels de constitution. Les dizaines d'autres pays restent dans le référentiel diplomatique, mais ne disposent pas de calendriers constitutionnels pilotés à ce stade. Cette extension requiert les migrations de tables 001 et 002 pour les pays configurés.

Après mise à jour WP18, les installations WP17 doivent aussi appliquer `008_world_clock_countries.sql` avant d'utiliser le coordinateur, y compris lorsqu'aucun pays n'est encore activé : l'absence de calendrier ne doit pas être confondue avec une panne de configuration.


## WP25 — raccordement effectif de l'article 49 au calendrier mondial

À la différence des premiers prototypes WP17–WP18, l'horloge commune peut maintenant intégrer **le magasin constitutionnel français distinct**, en plus des institutions générales FR/DE/US. Cette option est activée explicitement dans la configuration du monde et épinglée en MariaDB par la migration `011_fr_parliament_world_clock.sql`.

L'heure de fin de journée est exactement **23 h 59 min 59 s à Paris**, calculée avec le décalage UTC réel du jour concerné, y compris les changements d'heure. La France constitutionnelle garde un journal et des reçus propres, et son autorité de service doit être identique à celle du coordinateur mondial. Le coordinateur exige une heure certifiée par ce service et vérifie ensuite la date, l'empreinte de l'état et le checkpoint.

L'heure mondiale ne devient cohérente qu'après cette étape : une panne après écriture constitutionnelle mais avant checkpoint est récupérable. Une nouvelle instance du serveur ne peut ni oublier la configuration française épinglée, ni réutiliser les droits d'un principal non autorisé.

**Limites conservées :** cette avancée n'apporte pas encore les jours de séance réels, la discussion/votation de censure inscrite au plus tard au troisième jour de séance ni le verrou mondial contre toutes les mutations concurrentes. Le prototype autorise encore des sauts de calendrier de plusieurs mois dans les jeux de test ; cela n'est pas suffisant pour une simulation constitutionnelle de production. La suite du back-end est détaillée dans `docs/WORLD_POLITICS_BACKEND_REMAINING.md`.

Test d'intégration : `tests/wp25-fr-parliament-world-clock-db.php`. Les jeux sans activation France continuent de fonctionner après application de la migration, mais un monde configuré avec cette obligation ne peut pas être repris sans le magasin constitutionnel correspondant.


## WP26 — garde parlementaire intégrée au passage du temps

La France peut désormais activer un calendrier **vérifiable et persistant des séances de l'Assemblée nationale**. Lorsqu'il est épinglé, le magasin constitutionnel utilisé par l'horloge mondiale contrôle les motions 49.2 et 49.3 en attente avant chaque progression de la journée.

Si une motion n'a pas encore reçu de créneau de vote approuvé et que le délai de 48 heures est dépassé, la progression est refusée. Si la date de vote certifiée est atteinte mais que la motion n'a pas été votée, la progression est refusée également. L'application doit d'abord obtenir le résultat institutionnel dans le modèle de vote vérifié. Cela empêche les sauts de plusieurs semaines qui, dans le prototype précédent, pouvaient ignorer une crise parlementaire.

Le référentiel reste daté et source-labellisé. Les sources fictives du jeu ne deviennent jamais de fausses dates historiques. La protection persiste après redémarrage grâce au marqueur `sitting_calendar_required` inscrit sur le monde constitutionnel.

`database/012_fr_sitting_calendar.sql` complète la migration 009 et ajoute les journaux du calendrier. Les tests WP26 vérifient les nouvelles interdictions ; le test mondial WP25 s'exécute aussi avec cette option activée.
