# M13 — premier rapport de capacité concurrente, environnement jetable

## Identification et provenance

- Dépôt : `Gladius33/Aeternum-Engine`.
- Commit testé : `bd427cc991d4c46055842ced576c910483631b36`.
- GitHub Actions : [PHP Core CI / MariaDB, run 45233736695](https://github.com/Gladius33/Aeternum-Engine/actions/runs/45233736695).
- Source des métriques : ligne `M13_CAPACITY_JSON` du job `mariadb`.
- Environnement déclaré par le workflow : Ubuntu 24.04, PHP 8.4,
  MariaDB 11.4 via service Docker GitHub Actions.
- Type de résultat : **mesure de CI jetable**, non SLA, non test
  d'hébergeur mutualisé/VPS et non qualification de production.

## Méthodologie

La suite d'intégration crée d'abord une base MariaDB jetable, ses
schémas et ses données de marché. Le test `tests/m13-capacity.php`
lance ensuite **quatre processus PHP distincts**, chacun ouvrant
sa connexion PDO/MySQL et attendant la barrière de départ commune.
Chaque processus effectue 50 lectures de marché autorisées et
50 lectures de solde autorisées : 200 requêtes de marché et 200
requêtes de solde au total. Les lectures ne modifient pas le stock.

Données préexistantes : 1 001 offres de marché et 1 002 soldes.
Toutes les requêtes contrôlent les éléments attendus, et les mesures
utilisent `hrtime()`, en millisecondes. Le percentile p95 est le
rang `ceil(0,95 * N)` après tri croissant des observations.

## Résultat réellement observé

| Mesure | Valeur |
|---|---:|
| Processus indépendants | 4 |
| Lectures de marché | 200 |
| Lectures de solde | 200 |
| Total des lectures autorisées | 400 |
| Erreurs / réponses contradictoires | 0 |
| Latence p95 marché | **3,294 ms** |
| Latence p95 solde | **4,713 ms** |
| Temps total observé | **416,918 ms** |
| Garde-fou par p95 | 3 000 ms |
| Garde-fou temps total | 90 000 ms |

**Interprétation limitée :** cette exécution a respecté des seuils
volontairement très généreux pour détecter une régression flagrante.
Ces seuils n'ont PAS la valeur d'objectifs de performance en production.
Les mesures indiquées appartiennent uniquement au commit ci-dessus ;
une CI sur un nouveau SHA doit être contrôlée séparément.

## Lacunes restant obligatoires pour G09

- Profils 1/4/16/32 concurrents, écritures réelles, mix lecture/écriture,
  profils de requêtes HTTP et effets des verrous transactionnels.
- Répétitions sur plusieurs exécuteurs et équipement de capacité connue,
  avec moyenne, dispersion, p95/p99, CPU, RAM, I/O, connexions SQL et
  saturation explicite.
- Scénarios de panne en charge (processus tués, perte de connexion,
  reprises idempotentes, cohérence des écritures et grand livre).
- Seuils d'acceptation définis **avant** l'essai et validés dans
  l'environnement autorisé cible ; incident/retour arrière prévus.
- Revue du dimensionnement propre à l'hébergement mutualisé et au VPS, non déductible
  du matériel GitHub Actions.

La case `load_limits` de
[la matrice de release](M13_RELEASE_ACCEPTANCE.md) reste **false**
jusqu'à ces validations. Aucune publication V1.0 n'est autorisée
sur la base de ces seules mesures.
