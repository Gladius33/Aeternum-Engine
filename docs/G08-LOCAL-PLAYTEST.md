# G08 — Laboratoire local privé (en qualification)

Prérequis : Docker Engine et Docker Compose v2. Aucun PHP, Node ou MariaDB installé sur l'hôte n'est nécessaire. Depuis le clone du SHA candidat :

```bash
docker compose -f compose.lab.yml up -d --build
docker compose -f compose.lab.yml ps
```

Ouvrir https://127.0.0.1:8443/play.html. Certificat autosigné de laboratoire, généré au premier démarrage du conteneur ; accepter uniquement cette exception locale après contrôle du nom/hôte. Ne jamais réutiliser ce certificat, les identifiants ou cette configuration en production.

| Monde | Alice | Bob |
|---|---|---|
| Médiéval | medieval-alice | medieval-bob |
| Industriel | industrial-alice | industrial-bob |
| Guild Caravans | guild-alice | guild-bob |

Mot de passe de démonstration commun : consultez la configuration jetable dans `compose.lab.yml` du dépôt source (non fournie dans le paquet de distribution). Les six comptes sont provisionnés par CLI, pas par un contournement de l'inscription. L'inscription publique est désactivée. Le registre épingle médiéval et industriel à 0.4.0, Guild à 0.5.0, avec les modules approuvés et de nouveaux identifiants `lab-*-f04` ; les mondes et octets historiques restent inchangés. MariaDB reste sans port publié, HTTPS est publié uniquement sur 127.0.0.1:8443. Le worker traite chaque monde toutes les quelques secondes ; le joueur n'exécute pas de commande CLI. Les données persistent dans un volume Compose. Réservé à un poste isolé : ne jamais connecter de données réelles ni changer le bind en 0.0.0.0.

```bash
docker compose -f compose.lab.yml logs -f worker
docker compose -f compose.lab.yml down
# Réinitialisation DESTRUCTIVE du seul volume de ce laboratoire :
docker compose -f compose.lab.yml down -v
```

Le workflow G08 construit exactement la même image, exécute les trois campagnes jusqu'à la victoire, attend le worker autonome, vérifie les reconnexions, les échanges, alliances, messages, blocage et modération avec acteurs indépendants, ainsi que le refus d'inscription publique. Il conserve les captures PC/mobile et les mesures de navigation. Les résultats doivent être verts avant fusion. Ce laboratoire n'est pas une qualification de production ou une release V1.0.

## Revue visuelle G07 et correction G08
Les captures authentiques des runs 45361585935 et 45361585980 montrent une scène identifiable mais un écran trop long (2364–2823 pixels en capture PC). Le mode normal affiche maintenant un seul panneau de travail, selon la navigation ; la scène et le HUD restent accessibles. Le mode classic conserve la totalité des panneaux pour les scénarios historiques. Les commandes de l'inspecteur ouvrent directement les nouveaux panneaux.

## Adversaires automatisés (G09)
Dix bots par monde, trente au total, avec identités et mots de passe indépendants conservés dans un volume privé. Quatre profils déterministes : bâtisseur, producteur, marchand, équilibré. Le service `bots` décide toutes les 30 secondes via les mêmes API et sessions que les humains. Construction, économie, progression médiévale/industrielle, troc Guild, recherche et expéditions. Les bots n'initient pas de défi hostile. Le tableau présente uniquement leurs synthèses volontaires, sans identifiants internes ni secrets. Pause : `docker compose -f compose.lab.yml stop bots` ; reprise : `docker compose -f compose.lab.yml start bots`. Rapport ancien après 90 secondes. Agents à règles, pas un LLM ni une IA auto-apprenante. Ne pas exposer le laboratoire sur Internet.
