# WP05–WP07 — Institutions nationales, pays jouables et moteur temporel

**Statut : prototype de back-end, non déployable.**
Aucun composant graphique n'est développé. Le jeu reste dans une branche
temporaire **à ne jamais fusionner** vers \`main\` d'Aeternum Engine.

## Distinction structurelle impérative

1. **Univers simulé mondial** : objectif de référence = 193 membres de
   l'ONU, avec présence distincte des acteurs observateurs et des
   territoires lorsque les données et statuts juridiques le justifient.
   L'ensemble des 193 entrées n'a **pas encore été importé**.
2. **Pays candidats au contrôle humain** : **27 États membres de l'UE**,
   **Suisse**, et une liste configurable de grandes puissances/États
   stratégiques hors UE. L'absence de module juridique complet rend un
   pays **non prêt en production** même s'il figure dans la sélection.
3. **Pays gérés par IA** : les États et acteurs non incarnés seront gérés
   par la simulation. Les véritables IA de gouvernement et la couverture
   automatique mondiale ne sont **pas encore implémentées**.
4. **Règles spécifiques à chaque pays** : chaque pays possède un
   régime, des chambres, des fonctions et des prérogatives propres,
   versionnés à la date du scénario. Aucune commande universelle
   \`president.dissolve_parliament\` n'existe.

L'Union européenne compte 27 membres au 24 novembre 2026.
L'UE n'est pas elle-même un pays jouable ; ses institutions internationales
seront simulées séparément des gouvernements nationaux.

## Premières règles constitutionnelles de démonstration

| Pays | Chef de l'État | Gouvernement | Fonctions contrôlées dans le pilote |
| --- | --- | --- | --- |
| FR | Président de la République | Premier ministre | initiative de loi par PM/parlementaire, consultations de dissolution, fenêtre législative de 20–40 jours |
| DE | Président fédéral | Chancelier | dépôt de projets par organes habilités ; dissolution conditionnelle **non encore simulée** |
| US | Président | Président et cabinet | initiative parlementaire au Congrès ; **aucune dissolution présidentielle** |

Sources originales :
- France, Constitution du 4 octobre 1958, art. 8, 12, 39 :
  https://www.legifrance.gouv.fr/loda/id/JORFTEXT000000571356/
- Allemagne, Loi fondamentale, art. 63, 64, 68, 76 :
  https://www.gesetze-im-internet.de/englisch_gg/
- États-Unis, Constitution :
  https://www.senate.gov/civics/constitution_item/constitution.htm
- 27 États de l'UE :
  https://eur-lex.europa.eu/EN/legal-content/glossary/member-states.html
- 193 membres ONU :
  https://ask.un.org/faq/14345

Les opérations disponibles sont **un pilote non exhaustif** des
institutions. Les autres pouvoirs, même légaux, sont refusés tant
qu'ils ne sont pas modélisés et testés.

## Exemples de décisions dans le moteur

- \`record_consultation\` : l'acteur institutionnel habilité inscrit
  un événement de consultation. Un booléen déclaratif du joueur ne
  peut pas remplacer ces événements.
- \`dissolve_lower_house\` : en France uniquement dans cette première
  version, le président peut dissoudre l'Assemblée après que les
  consultations du Premier ministre et des deux présidents des
  assemblées ont été **enregistrées**. Un délai de 20–40 jours est
  ouvert pour les élections. Le délai d'une année avant toute nouvelle
  dissolution court **à partir des élections postérieures à la
  dissolution**, et non de la date de dissolution.
- \`hold_election\` : l'autorité électorale désignée enregistre la tenue
  du scrutin dans les délais ; le résultat parlementaire n'est pas
  inventé. Le moteur le laisse « en attente de simulation ».
- \`submit_bill\` : initiative parlementaire/gouvernementale limitée
  aux rôles propres au pays. Une période de préparation au débat de
  sept jours est un **paramètre de gameplay fictif**, pas un délai légal.
- \`advance_clock\` : traitement déterministe des événements différés
  via une commande **réservée au serveur de simulation**.
- Un dépassement du délai électoral peut inscrire une **crise de
  constitutionnalité** plutôt que d'inventer une élection ou de
  déclarer légal un refus de scrutin.

Ce pilote ne résout pas encore le dépouillement, la formation de
majorités, les lois, les coups d'État, les mobilisations sociales,
les débats ni l'opinion publique. Les événements politiques illicites
et leurs réactions seront un domaine fictif distinct, sans méthodes
opérationnelles de coercition ou d'atteinte à des personnes.

## Persistance expérimentale

La migration de jeu \`database/002_institutions.sql\` complète la migration
\`001_rosters.sql\`, sans modifier la base du moteur générique.
Elle conserve état politique, empreinte, révision, reçus idempotents
et journal append-only au sein de **transactions InnoDB par pays/monde**.

Les modules de référence sont :
- \`CountryCatalog.php\` : provenance des pays, statuts et cible jouable ;
- \`InstitutionalRulebook.php\` : règles FR/DE/US et références ;
- \`InstitutionalSimulation.php\` : événements et horloge déterministes ;
- \`PdoInstitutionalStore.php\` : persistance et journal, avec rôle serveur ;
- \`PdoInstitutionalApi.php\` : contrat REST sans serveur HTTP public.

### Contrat API headless

| Méthode | Route | Fonction |
| --- | --- | --- |
| GET | /api/v1/worlds/{world}/countries/{country}/institutions | État institutionnel et ETag |
| GET | /api/v1/worlds/{world}/countries/{country}/institutions/rulebook | Règles nationales versionnées |
| POST | /api/v1/worlds/{world}/countries/{country}/institutions/decisions | Décision nationale autorisée |
| POST | /api/v1/worlds/{world}/countries/{country}/institutions/clock | Horloge **privilégiée serveur** |

Les POST exigent une \`Idempotency-Key\` et un \`If-Match\` correspondant
à la version courante. Les rôles et compétences ne proviennent pas du
JSON envoyé par le client.

## Blocages avant vraie production

- Liaison à l'authentification d'Aeternum et aux rôles réels des joueurs
  dans les mondes, sans accorder des pouvoirs par simple paramètre.
- Transaction mondiale coordonnée entre personnalités, gouvernements,
  élections, économie et diplomatie ; les agrégats spécialisés
  demeurent actuellement séparés.
- Modèles de gouvernance réellement approfondis **pays par pays**,
  sans affirmer que les prototypes FR/DE/US sont prêts à jouer.
- Ingestion sourcée des 193 pays, calibration historique et métadonnées
  de licence ; modèles des acteurs observateurs et territoriaux.
- IA automatisées des autres pays, décisions diplomatiques et
  économétriques soumises aux mêmes autorisations que les humains.
- Tests de concurrence et de montée en charge à échelle mondiale,
  politiques de sécurité et limitations de débit.
- Aucune interface graphique avant nouvelle décision expresse.

Cette branche est un espace de développement temporaire. **Jamais de
fusion dans Aeternum Engine main**, même avec toutes les CI vertes.
