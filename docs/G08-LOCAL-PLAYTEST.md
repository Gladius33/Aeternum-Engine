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

Mot de passe de démonstration commun : `aeternum-local-playtest-only`. Les six comptes sont provisionnés par CLI, pas par un contournement de l'inscription. L'inscription publique est désactivée. Le registre épingle médiéval et industriel à 0.3.0, Guild à 0.1.0 ; aucun fichier de règles n'est modifié. MariaDB reste sans port publié, HTTPS est publié uniquement sur 127.0.0.1:8443. Le worker traite chaque monde toutes les quelques secondes ; le joueur n'exécute pas de commande CLI. Les données persistent dans un volume Compose. Réservé à un poste isolé : ne jamais connecter de données réelles ni changer le bind en 0.0.0.0.

```bash
docker compose -f compose.lab.yml logs -f worker
docker compose -f compose.lab.yml down
# Réinitialisation DESTRUCTIVE du seul volume de ce laboratoire :
docker compose -f compose.lab.yml down -v
```

Le workflow G08 construit exactement la même image, exécute un vrai login et chantier dans chaque monde, attend le worker autonome, vérifie le refus d'inscription publique et l'absence d'accès au script de provisionnement, puis conserve les captures PC/mobile. Les résultats doivent être verts avant fusion. Ce laboratoire n'est pas une qualification de production ou une release V1.0.

## Revue visuelle G07 et correction G08
Les captures authentiques des runs 45361585935 et 45361585980 montrent une scène identifiable mais un écran trop long (2364–2823 pixels en capture PC). Le mode normal affiche maintenant un seul panneau de travail, selon la navigation ; la scène et le HUD restent accessibles. Le mode classic conserve la totalité des panneaux pour les scénarios historiques. Les commandes de l'inspecteur ouvrent directement les nouveaux panneaux.
