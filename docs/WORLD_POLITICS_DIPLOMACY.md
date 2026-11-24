# World Politics — Diplomatie et acteurs non jouables (WP06, prototype)

**Dernière révision : 24 novembre 2026. Branche du jeu uniquement.**
**Aucune fusion dans le `main` d'Aeternum Engine. Aucun développement graphique.**

## Monde simulé, sélection de pays jouables

1. Référentiel mondial cible : les **193 États membres de l'ONU**.
2. **État de Palestine (PS)** : présence supplémentaire, en qualité d'État
   observateur non membre, **sans augmenter artificiellement le nombre de
   membres de l'ONU**. D'autres entités peuvent exister selon le scénario.
3. L'**Autorité palestinienne** est une entité institutionnelle distincte
   (`authority_pa`) rattachée au dossier `state_ps`. Elle n'est ni
   assimilée à l'État entier ni considérée comme exerçant une autorité
   uniforme sur Gaza, Jérusalem-Est et la Cisjordanie.
4. **Israël (IL), Iran (IR) et Palestine (PS) sont définitivement non
   incarnables** dans la configuration de ce jeu : acteurs IA seulement,
   indépendamment des listes de grandes puissances configurables.
5. Les 27 États de l'UE, la Suisse et les grandes puissances retenues
   restent les **cibles pour une jouabilité humaine ultérieure**, sous
   réserve de modules constitutionnels spécifiques complets.
6. Les États non jouables conservent leurs relations, paramètres,
   programmes et décisions autonomes ; ils ne sont pas des « décorations ».

Ces règles sont vérifiées dans `CountryCatalog.php`, et le contrôle de
couverture exige à terme une importation réellement sourcée des 193
membres **plus** de la Palestine. **Ce référentiel mondial n'est pas encore
importé** : les acteurs actuellement enregistrés sont une graine partielle,
pas les 194 États d'une simulation initialisée.

## Fiches d'acteurs distinctes et données sourcées

Le fichier `games/world-politics/data/2026-11-24-special-actors.json`
contient un **instantané daté** avec :

- `state_il` : Israël, État membre ONU, IA uniquement ;
- `state_ir` : Iran, État membre ONU, IA uniquement ;
- `state_ps` : État de Palestine, observateur non membre ONU, IA uniquement ;
- `authority_pa` : Autorité palestinienne, institution liée à la
  Palestine, IA uniquement, interlocuteur pour démarches administratives,
  aide et dialogue ; elle ne devient pas par défaut partie à un traité
  interétatique indépendant.

Les sources officielles et documents de référence figurent dans le
JSON. Les données de statut, les territoires, la reconnaissance de
certains États et les capacités administratives réelles ont des nuances
juridiques qui devront être attachées à la **date du scénario**.

La date `2026-11-24` indique l'**instantané du jeu** ; elle ne signifie
pas que chaque information sous-jacente a été observée ou mesurée ce
jour-là. Une future filière de curation ajoutera la date originale de
publication, la période mesurée, la licence et une qualité de preuve.

## Défense et Iran : ne pas inventer de renseignements

Les programmes **balistique** et **nucléaire** de l'Iran figurent comme
domaines stratégiques publiquement documentés. Les champs de capacités
quantifiées sont `null` tant qu'une série vérifiable, adéquatement
datée et réutilisable n'a pas été retenue. **Existence d'un programme
nucléaire ne signifie pas constat d'un arsenal nucléaire**.

La fiche présente le niveau de vérification et les sources, y compris
lorsque le contrôle international est limité. Elle ne prétend pas
exposer des stocks actuels non publics, des plans d'action ni des données
de ciblage. La même règle s'appliquera aux autres États.

## Commandes de diplomatie — première simulation abstraite

Le fichier `DiplomaticSimulation.php` propose pour l'instant :

- dialogue et aide humanitaire ;
- coopération commerciale et proposition de traité ;
- expression de soutien ou d'opposition dans **le monde fictif** ;
- proposition de sanctions **soumise à un examen institutionnel ultérieur**.

Une aide ou une proposition de traité reste une **offre**. Une
acceptation de l'IA est seulement un **accord de principe**, pas une
ratification ni une entrée automatique dans une alliance. Une sanction
proposée ne produit pas immédiatement d'effet réel ou mécanique.

Les échanges expirent après 14 jours de jeu dans le prototype, ce qui
est un **paramètre de gameplay**, non un délai juridique universel.
L'horloge est virtuelle, les événements sont déterministes et les
commandes sont idempotentes à l'intérieur de l'agrégat.

## Intelligence artificielle : neutralité et transparence

`AutonomousDiplomacyPlanner` contient trois politiques **fictives
paramétrables de scénario** : `cautious`, `open_dialogue` et
`humanitarian_open`. Elles sont appliquées par un principal de service
autorisé, avec la même frontière de commande que pour les humains.

Ces politiques illustrent un moteur d'automatisation : **elles
n'expriment pas les intentions véritables des gouvernements israélien,
iranien ou palestinien**. Dans une version plus avancée, les mécanismes
seront alimentés par les institutions, engagements publics, contraintes
économiques, alliances, crises et réactions accumulées dans la partie.

## Prochaines livraisons techniques

- Stockage MariaDB du journal diplomatique et des propositions, avec
  idempotence et reprise après panne, dans le schéma propre au jeu.
- Adaptateur API REST `/api/v1` et tests multi-acteurs/multi-pays,
  sans créer de frontend.
- Chaîne de données internationales sourcées, historiques et
  versionnées, avec obligations de licence.
- Système de négociation : traités bilatéraux/multilatéraux,
  adhésions et retraits institutionnels, conventions, reconnaissance
  et votes, **chacun avec des procédures propres**.
- Modélisation distincte des institutions palestiniennes, de la
  représentation internationale et des différentes zones territoriales,
  sans figer des hypothèses contestées en vérités juridiques.
- IA gouvernementale améliorée, avec contraintes économiques,
  diplomatiques et institutionnelles, et journalisation explicable.

Le prototype ne contient **ni serveur HTTP public ni IA politique
calibrée**. La suite de tests `tests/wp06-diplomacy.php` vérifie les
statuts, l'absence d'incarnation humaine des trois acteurs, le refus
d'usurpation d'autorité IA, la différence État/Autorité palestinienne,
les offres et réponses, les délais et l'absence de traité instantané.

## Sources principales

- ONU, États membres : https://www.un.org/en/about-us/member-states
- ONU, États observateurs : https://www.un.org/en/about-us/non-member-states
- ONU, résolution 67/19 : https://www.un.org/unispal/document/auto-insert-187149/
- CIJ, avis consultatif 2024 : https://www.un.org/unispal/document/advisory-opinion-icj-19jul24/
- CSIS, dossier public balistique iranien : https://missilethreat.csis.org/country/iran/
- Déclaration du Quad à l'AIEA, novembre 2026 :
  https://www.gov.uk/government/speeches/quad-statement-to-iaea-board-of-governors-november-2026
