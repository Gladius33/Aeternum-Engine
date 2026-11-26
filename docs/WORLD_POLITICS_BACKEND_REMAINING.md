# World Politics — bilan du back-end et critères de sortie (24 novembre 2026)

## Portée et principe non négociable

World Politics est un jeu original autonome qui consommera Aeternum Engine comme dépendance générique. La branche actuelle
`feat/world-politics-wp01-identity-government` est un espace de travail TEMPORAIRE : **ne jamais la fusionner dans le main d'Aeternum**.
Pas de graphiques, pas de front-end, pas d'intégration à des templates Aeternum avant l'approbation expresse du projet client. Les clients web, Android, iOS et éventuellement bureau devront consulter le même back-end versionné.

La présence de tests unitaires et MariaDB verts ne signifie ni serveur HTTP déployé, ni jeu politiquement réaliste prêt à commercialiser, ni conformité constitutionnelle exhaustive.

## Avancées concrètement présentes

| Domaine | Code actuellement disponible | Limitation honnête |
| --- | --- | --- |
| Profils humains / fictionnels | `PoliticalProfileTemplate.php`, `PoliticalRoster.php`, `PdoPoliticalStore.php` | Modes « rapide / détaillé » décrits en JSON, pas de compte client complet ni d'interface ; données de personnalités réelles non validées à l'échelle mondiale |
| Univers pays | `CountryCatalog.php` | Catalogues de pays candidats : UE (27) + Suisse + puissances sélectionnées ; aucun pays automatiquement déclaré prêt à jouer ; référentiel complet des 193 États membres ONU non encore importé et vérifié |
| Statuts particuliers | `GeopoliticalActorRegistry.php` | Israel (IL), Iran (IR), Palestine/Autorité palestinienne (PS) doivent rester automatisés, avec identité diplomatique distincte ; scénarios diplomatiques pas encore calibrés sur l'actualité |
| Institutions | `InstitutionalRulebook.php`, `InstitutionalSimulation.php`, `PdoInstitutionalStore.php` | Débuts de règles propres FR, DE, US, très loin des constitutions complètes |
| France constitutionnelle | `FrenchConstitutionalCrisis.php`, `PdoFrenchConstitutionalStore.php`, `FrenchConstitutionalApi.php` | 49.1/49.2/49.3, délais minimaux, signatures, votes, 50 ; jours de séance, navette, constitutionnalité et formation du nouveau Gouvernement NON achevés |
| France sociale | `FrenchSocialDynamics.php`, `PdoFrenchSocialStore.php`, `FrenchSocialApi.php` | Mécontentement et perturbations économiques par secteur avec concessions explicites ; coefficients hypothétiques non calibrés |
| Diplomatie | `DiplomaticSimulation.php`, `PdoDiplomaticStore.php`, `PdoDiplomaticApi.php` | Acteurs, propositions, délais et journal, mais pas encore une diplomatie multinationale complète avec traités juridiquement et économiquement vérifiés |
| Organisations | `OrganizationSimulation.php`, `PdoOrganizationStore.php`, `AutonomousOrganizationPlanner.php` | Adhésion, décisions et votes IA en prototype ; pas encore toutes les organisations et procédures spécifiques de l'ONU, de l'UE, de l'OTAN, etc. |
| Économie | `EconomicReferenceRegistry.php`, `WorldBankOfflineImporter.php`, `WorldEconomicSimulation.php`, `PdoEconomicStore.php` | Import WDI hors ligne et politiques commerciales fictives ; ni véritable modèle macro-économique national, ni calibrage statistique |
| Passage interdomaines | `DiplomacyEconomicBridge.php`, `SanctionsOutboxDispatcher.php` | Propositions de sanctions dédupliquées, sans transaction globale ni ordonnanceur durable déployé |
| Horloge mondiale | `WorldClockCoordinator.php`, migrations 007, 008, 011 | Coordination récupérable économie/diplomatie/organisations/institutions FR/DE/US et, si activée, procédure France en heure de Paris. Ce n'est PAS une transaction distribuée atomique. |
| APIs | `api/openapi.yaml` et plusieurs adaptateurs `*Api.php` | Contrats JSON et réponses in-process uniquement : aucune passerelle HTTP publique, authentification des joueurs ou API hébergée de production |

## WP25 — horloge parlementaire française et synchronisation mondiale

La migration `database/011_fr_parliament_world_clock.sql` épingle, par monde de jeu, l'activation obligatoire du calendrier constitutionnel français et son fuseau `Europe/Paris`. `WorldClockCoordinator.php` refuse de démarrer un monde dans lequel une France constitutionnelle activée n'est pas incluse parmi les pays ayant un module institutionnel.

La clôture d'une journée de simulation est définie à **23:59:59 heure de Paris** avec calcul du véritable décalage UTC selon le jour (heure d'hiver / heure d'été), sans choix libre d'horodatage par le joueur. L'autorité parlementaire certifie séparément l'instant demandé. L'horloge mondiale n'enregistre sa date comme cohérente qu'après les autres calendriers nationaux, l'étape française, la diplomatie et l'économie.

Une interruption entre la validation de l'horloge française et l'inscription du checkpoint ne provoque pas un second calcul : la reprise vérifie le véritable état constitutionnel et restitue l'étape. Le registre de configuration empêche un processus redémarré de supprimer silencieusement la procédure française obligatoire. Les droits sont réévalués avant les reçus et les progressions.

Le fichier `tests/wp25-fr-parliament-world-clock-db.php` teste l'interruption après une étape française déjà enregistrée, la reprise, les autorisations, les fuseaux hiver/été, le rejet d'une heure non certifiée et l'impossibilité de « retirer » un module obligatoire du monde.

**Limite :** les jours de séance de l'Assemblée nationale et l'inscription obligatoire d'un débat de censure au plus tard le troisième jour de séance après les 48 heures constitutionnelles ne sont pas encore représentés. Les sauts temporels de plusieurs semaines ou mois sont une facilité de test ; tant que le calendrier parlementaire n'est pas complet, ils ne suffisent pas à simuler fidèlement une crise réelle. Les mutations concurrentes provenant d'autres modules doivent encore être bloquées à l'échelle de la partie pendant une saga en cours.

Références françaises :
- Constitution article 49 : https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000019241062/
- Fonctionnement du travail parlementaire : https://www.assemblee-nationale.fr/dyn/synthese/fonctionnement-assemblee-nationale/travail-legislatif/la-procedure-legislative
- Règlement de l'Assemblée nationale (notamment motion et délai des jours de séance) : https://www.assemblee-nationale.fr/dyn/16/divers/texte_reference/02_reglement_assemblee_nationale.html

## P0 — Bloquants pour une première alpha jouable avec back-end fiable

| Ordre | Chantier | Critère d'acceptation |
| --- | --- | --- |
| 1 | **Dépôt propre World Politics** et livraison reproductible | Dépôt dédié, architecture et historique séparés d'Aeternum ; moteur comme dépendance versionnée, aucune fusion du jeu vers `main` |
| 2 | **HTTP réel et comptes authentifiés** | Passerelle JSON REST exposant les contrats OpenAPI ; identité vérifiée côté serveur, inscription, connexion, sessions, rôles, anti-abus, CORS/CSRF selon clients, quotas et journalisation |
| 3 | **Création, reprise et sauvegarde de parties** | Monde persistant multijoueur ou solo, pays contrôlé, vitesse du temps, permissions pays/gouvernement, révisions, reprises et états consultables après panne |
| 4 | **Verrou de monde et traitement cohérent des commandes** | Pendant le passage de journée, aucune écriture concurrente ne peut invalider l'état ; récupération des sagas, outbox/inbox généralisées, agents de traitement persistants |
| 5 | **Boucle de jeu politique minimale** | Création rapide/détaillée du personnage fictif avec avatar générique, programme, biographies ; sélection des personnages réels sourcés ; gouvernement, actes, opposition, résultats et tours qui ont des conséquences |
| 6 | **Pays alpha et pouvoirs réellement fonctionnels** | Règles constitutionnelles nationales testées pour chaque pays effectivement proposé au lancement de l'alpha ; verrouillage explicite des pays seulement présents dans le référentiel |
| 7 | **IA autonomes et contrôle unifié** | Opposition, ministres, acteurs économiques et pays IA utilisent exactement les mêmes contrôles d'autorité et procédures que les joueurs ; résultats reproductibles, agents vérifiés et limités |
| 8 | **Économie intérieure jouable** | Budget, recettes, dépenses, impôts, inflation, chômage, marché du travail, production par secteur, dette, services publics, commerce extérieur et conséquences des politiques ; calculs testés et hypothèses identifiables |
| 9 | **Données officielles historisées et licences** | Référentiel ONU de 193 membres, acteurs observateurs pertinents, population/PIB/travail/budget et provenance ; date de validité, qualité, licence, manquants, vérification de redistribution |
| 10 | **Qualité et sécurité de livraison** | Tests unitaires, d'intégration, de sécurité et de charge ; migrations versionnées, restauration des sauvegardes, surveillance, gestion des incidents et confidentialité |

Les points ci-dessus sont des **jalons fonctionnels**. Ils ne correspondent pas chacun à une seule itération ni à une durée arbitraire. Ils sont à découper en tickets acceptables et testés par CI.

## P1 — Réalisme politique, juridique et diplomatique nécessaire à la version ambitieuse

### A. France, institutions et droit constitutionnel

- Registre des **577 sièges maximum** (variable selon la période et la composition du scénario) et des députés réellement investis ; groupes et alliances parlementaires, discipline, absences, votes nominatifs vérifiés, formation de majorités.
- Conseil des ministres, responsabilité du Premier ministre, formation et démission d'un gouvernement, nomination, continuité administrative, remaniements, impossibilités juridiques.
- Calendrier réel des **séances** (jours et heures), article 49 et calendrier du débat de censure : pas de motion laissée en suspens pendant plusieurs mois par un saut artificiel de temps.
- Motion 49.2 : contrôle individuel des trois signatures par session ordinaire / une en session extraordinaire. Motion 49.3 et article 50 distincts. Contrôle du caractère approprié d'une loi de finances et du recours au 49.3.
- Droit législatif : initiatives, commissions, amendements, recevabilité, navette Sénat/Assemblée, commission mixte paritaire, vote final et promulgation. Articles 34, 38, 39, 40, 42, 44, 45, 47, 47-1.
- Dissolution : article 12, consultation obligatoire, restrictions, élections subséquentes ; article 8 (Premier ministre). Contrôle constitutionnel : articles 61/61-1, décisions du Conseil constitutionnel. Régimes d'exception : articles 16 et dispositions applicables, sans raccourci.
- Mécanismes électoraux, présidentiels, législatifs, locaux et européens, cohérents avec les dates historiques du monde.

### B. Toutes les puissances et États européens jouables

- Modèles constitutionnels propres aux **27 États membres de l'UE + Suisse**, puis aux puissances hors UE sélectionnées, avec sources de droit vérifiées.
- Président français ≠ chancelier allemand ≠ président américain : aucune décision de dissolution, de guerre, d'impôt ou de nomination n'est permise par simple équivalence de titre.
- Gouvernement, parlement, tribunaux, administration, autorités territoriales et leurs compétences au cas par cas. Élections et transitions gouvernementales nationales.

### C. Hétérogénéité de la population et économie sociale

- Démographie par âge, territoire, revenu, profession, niveau d'éducation ; migrations et actualisation des statistiques d'importance.
- Organismes collectifs et partenaires sociaux, syndicats, branches professionnelles, réseaux de solidarité et associations ; négociations, concessions ciblées, droit de grève et continuité des services.
- Réactions qui s'inscrivent dans le temps (fatigue, apprentissage, confiance et événements) ; effets économiques non automatiques et corrélations non assimilées à des causalités. Calibration sur données historiques et publication de l'incertitude.
- Séparation stricte entre défaite parlementaire d'un gouvernement, crise sociale, continuité de l'État et éventuelle transition exceptionnelle de régime. Ni « toutes les manifestations renversent le régime », ni « les institutions sécuritaires rendent le régime invulnérable ».
- Police, armée et autorités civiles sous contraintes juridiques, modélisées au niveau institutionnel, sans fournir de tactiques réelles de répression.

### D. Géopolitique et IA

- Tous les États du monde présents comme acteurs automatisés si non jouables ; **Israël, Iran et Palestine/Autorité palestinienne uniquement automatisés**, mais pouvant être destinataires d'alliances, relations, mesures commerciales ou reconnaissances dans le jeu.
- Organisations ONU, UE, OTAN, alliances régionales, veto/majorités, ratification, obligations des traités, sanctions et effets économiques.
- Gouvernements et partis IA qui prennent des décisions plausibles selon des objectifs, des contraintes institutionnelles, l'information disponible et les ressources, sans omniscience.
- Actualité réelle importée sous forme de faits **sourcés et datés**, séparée des événements alternatifs du scénario. Aucun état de fait géopolitique vivant ne doit être présenté sans date et source.
- Stratégie de défense **à l'échelle politique**, accords, budgets, dissuasion et enjeux géopolitiques ; éviter de substituer un moteur opérationnel militaire détaillé à la simulation institutionnelle.

## P2 — Renforcement avant une large diffusion

- Outils de recherche et d'équilibrage des scénarios, tests de sensibilité, comparaisons historiques, tests de causalité et calibrations statistiques révisables.
- Économie multiannuelle (dette et taux, cycles, investissements, commerce bilatéral, secteurs, taux de change, politique monétaire selon pays).
- Modes « jeu accessible » et « simulation poussée » sur un même moteur sans modifier la vérité des règles.
- Déploiements haute disponibilité, partitionnement et isolation des mondes, reprise de crash multi-nœuds, quotas et budgets de calcul IA.
- API stable de notifications et synchronisation, versionnage de snapshots, migration d'anciens scénarios, import/export, données de conformité et outils d'administration.
- Banc de vérification de sécurité, évaluations d'équité, performances et audit des permissions, tests de charge avec de nombreuses parties simultanées.

## Ordre recommandé après WP25

1. **WP26 : calendrier parlementaire français à jours de séance réels**, motion votable et échéance légale, avec validation obligatoire avant de sauter des journées.
2. **WP27 : verrou mondial des décisions concurrentes**, preuve de cohérence des versions des agrégats, et ordonnanceur de reprise.
3. **WP28 : composition de l'Assemblée, majorité et formation/démission des gouvernements** ; suffrages par député et opposition IA.
4. **WP29 : mécanisme de politiques publiques et de conséquences nationales**, avec transitions économiques et sociales persistantes entre plusieurs jours.
5. **WP30 : service HTTP authentifié + gestion des comptes et des univers**, interfaces JSON partagées par les futurs clients, en environnement de test privé.
6. **WP31 : import contrôlé du référentiel mondial et de données historiques réelles**, avec suivi de licences et d'actualisation.
7. **WP32+ : extension constitutionnelle et IA diplomatique/économique**, chaque nouvelle nation devant passer les mêmes contrôles de cohérence.

La séquence n'est pas un nombre exact d'itérations restantes : chaque jalon peut exiger plusieurs développements et revues. Les interfaces visuelles sont **hors périmètre** tant que le socle back-end n'est pas suffisamment fiable.

## Verdict de sortie

**À ce stade : prototype back-end technique vérifié par tests, PAS une alpha réellement jouable et PAS un serveur de production.**
La prochaine démonstration de valeur sera une **partie France sur plusieurs mois** avec Gouvernement, Assemblée, majorité, 49.3, censure, opinion publique, concessions, économie et IA, sans contournement des délais parlementaires ni incohérence au passage des jours. La réussite de cette démonstration constitue un critère de réalité bien plus pertinent qu'un pourcentage arbitraire de « back-end terminé ».


## État après WP26 — échéances parlementaires enfin contrôlées

**WP26 livré :** moteur de jours de séance français explicitement datés, calcul du troisième jour après les 48 heures constitutionnelles, affectation de créneaux vérifiés, persistance de la conférence parlementaire, consultation REST en lecture seule, interdiction des sauts de temps qui ignorent un vote, régression du scénario mondial WP25.

Ce jalon ne signifie pas « calendrier officiel français complet ». Les jours réels doivent encore être importés, sourcés et versionnés ; des règles de débat, de séances exceptionnelles, de session et de programmation des scrutins restent à préciser, ainsi que l'automatisation parlementaire.

**Prochain jalon recommandé — WP27 :** verrou/ordonnanceur de monde et protections de cohérence transactionnelle pour les ordres concurrents des joueurs, des administrations et des IA pendant une saga d'avancement de date. Le jeu doit empêcher que des actes de diplomatie, des nominations ou des effets économiques surviennent au mauvais état de monde. Ensuite WP28 : assemblée, députés, opposition et coalitions IA.

L'état du back-end reste **prototype non déployé** : ces améliorations ne fournissent ni serveur HTTP public, ni authentification de comptes réels, ni monde complet de 193 États, ni IA et économie calibrées.

## WP27 livré — admission concurrente et reprise des horloges mondiales

Le verrou interdomaines n'est plus uniquement une priorité théorique. WP27 a été réalisé avec `WorldMutationFence.php`, la migration `013_world_mutation_fences.sql`, l'intégration dans les magasins du jeu, `WorldTickRecoveryWorker.php` et les tests de concurrence MariaDB.

Les écritures d'un monde dont le calendrier global est `advancing` sont refusées, sauf étape `clock` exécutée par le principal vérifié du coordinateur pour le jour précis en attente. L'écriture de l'intention d'avancement et la prise de barrière sont atomiques, tout comme sa libération et la publication du nouveau jour. Des verrous `SELECT FOR UPDATE` assurent l'exclusion entre les dernières décisions admises avant le changement de jour et sa prise d'effet.

La récupération depuis la base, sans clé brute de client, est disponible avec `resumePending` et la file bornée `WorldTickRecoveryWorker`. **Ce ne sont pas encore un daemon installé, un ordonnanceur supervisé ni des garanties de transactions globalement atomiques.**

Documentation détaillée : `docs/WORLD_POLITICS_WORLD_FENCE.md`.

### Prochain jalon prioritaire : WP28

Développer les députés, groupes parlementaires, majorités, abstentions, discipline de vote et coalitions, avec une opposition IA qui utilise les véritables procédures 49.1, 49.2 et 49.3 et leur calendrier. Ajouter la formation d'un nouveau Gouvernement après une censure, sans confondre démission et changement de régime.

### Renforcement WP27 à différer jusqu'à l'architecture de production

Rôles SQL minimaux et éventuels triggers d'audit, verrouillage de toutes les écritures hors adaptateurs, supervision des workers, logs et alertes, métriques de contention, tests de charge, migrations des parties antérieures et procédures d'exploitation. Ces points restent des **conditions de mise en production**, pas des fonctionnalités actuellement terminées.

## Statut après WP28 — parlement français et majorité politique

**WP28 livré :** modèle de députés et sièges individuellement vérifiés, groupes et coalitions, politique fictive par député, voix nominatives, scrutin 49.1/49.2/49.3 issu du même registre, archives et tests MariaDB, droits d'un élu humain, preuve des sources constitutionnelles, succession Premier ministre sous les articles 50 puis 8. Description technique : `docs/WORLD_POLITICS_PARLIAMENT.md`.

La France a désormais un premier scénario parlementaire cohérent de bout en bout, mais la mécanique reste un **prototype d'IA et de données fictives**. Aucun nom de parlementaire réel, mandat daté, composition officielle de groupe, projet réel de réforme ou statistique de comportement politique n'est encore importé et validé. Les nominations de ministres, la composition détaillée des cabinets, les élections et la dissolution restent à achever.

**Prochaine priorité : WP29 — boucle de politique publique multi-journées**, avec projet de loi, coûts/revenus, clauses et concessions ciblées, comportement parlementaire, réaction sociale graduelle et effets économiques durables. Ensuite, une alpha privée devra bénéficier d'un véritable serveur HTTP avec authentification et permissions.


## WP29A — domestic policy domain candidate

New headless domain and regression tests: `DomesticPolicySimulation.php`, `tests/wp29a-domestic-policy.php`. See `docs/WORLD_POLITICS_DOMESTIC_POLICY.md`. Full WP29 remains open pending durable storage, legislative proof and coordinated clock integration. CI must confirm this candidate before acceptance.


## WP29B — durable domestic policy candidate

Migration 017 and PdoDomesticPolicyStore add MariaDB persistence, transactional
state/journal/receipts, actor-scoped idempotency, strong ETags, permission
revalidation and deterministic restoration. Standalone world only; coordinated
world writes remain disabled until WP29D. Pending CI acceptance. Next WP29C:
real simulated legislative evidence and legally enacted instruments, not
mere Assembly adoption. WP29D: world-clock saga integration and REST contracts.


## WP29 final candidate — C/D/E in one commit

Simulated enactment registry, optional pinned domestic world-clock step,
REST facade, extended curated concessions, fiscal summaries and fictional
production/employment indices. Full real legislative procedures, validated
national macroeconomic modelling and interactive negotiation are still future
work. WP29 acceptance is contingent on final CI; WP30 remains the next
transport/authentication milestone.


## WP30 private alpha candidate

Real HTTP entry point, account enrollment, Argon2id credentials, hashed expiring
sessions, revocation, role-scoped worlds, atomic FR sandbox creation, durable
resume and authenticated selected REST routes. Dedicated HTTP/MariaDB CI.
Advanced constitutional roles, 49.3 gameplay mounting, portable save export,
public deployment hardening and full worldwide data remain open. See
WORLD_POLITICS_ALPHA.md. WP31 data curation remains the next planned package.


## WP31 global reference candidate

193 UN members, two observer states, one separate governing authority; new
versioned global scenario, optional content-addressed WDI archives, coverage
API and legacy-save regression. Raw WDI observations still require a reviewed
operator archive; no invented values or complete world institutions claimed.
See WORLD_POLITICS_GLOBAL_REFERENCES.md. CI acceptance pending.


## WP32 comparative constitutional candidate

Seven national clocks, six additional certified accountability routes,
versioned deterministic nested state, four added basic rulebooks and an
opt-in comparative alpha scenario. Player voting/office authorization,
full election and legislative systems, monarchies outside the pilot and
additional regimes remain open. See WORLD_POLITICS_COMPARATIVE.md.


## WP33 electoral candidate

Deterministic fictional election cycle with three counting methods, district
results, coalition negotiation and separate trusted formation evidence.
Opt-in scenario and private French coalition negotiation route. Actual electoral
laws/data, multiple cycles, authenticated appointment and individual deputy
integration remain future work. See WORLD_POLITICS_ELECTORAL.md.


## WP34 political AI candidate

Bounded fictional party coalition search, opposition projections and an opt-in
scenario with automatic foreign negotiation. Verified formation bridge to the
comparative incumbent; no public self-appointment or legal effects from vote
projections. See WORLD_POLITICS_POLITICAL_AI.md.


## WP35 public opinion candidate

Optional fifth clock subsystem for fictional French opinion, domestic and
institutional source hashes, gradual cohort approval and trust, conserved
intention distribution and independent institutional status. No direct client
writes or automatic regime transitions. See WORLD_POLITICS_OPINION.md.


## WP36 civic dialogue candidate

Opt-in fictional union and civic association episodes, consultation, abstract
collective action and observed relief, nested in the existing opinion checkpoint.
No direct client mutation, no legal bargaining certification or automatic
regime change. See WORLD_POLITICS_CIVIC_DIALOGUE.md.


## WP37 campaign candidate

Opt-in survival objective, pinned fictional president, distinct cabinet,
constitutional crisis and revolutionary outcome states, immutable curated
narrative queue, terminal publication and world gameplay lock. A separate
scripted case exercises revolutionary defeat. Actual automatic revolutionary
outcome generation and presidential election/succession integration remain
open. See WORLD_POLITICS_CAMPAIGN.md.


## WP38 presidential calendar candidate

Opt-in French presidential mandate and two-round fictional election cycle,
five-year terms, two consecutive term limit, result/transfer separation,
and replayed terminal outcome at expiry. Actual dynamic vote generation,
vacancy calendar, constitutional adjudication and roster succession remain
open. See WORLD_POLITICS_PRESIDENCY.md.


## WP39 constitutional revision candidate

Opt-in Article 89 pilot, reviewed fictional clause catalog, separate certified
initiative, identical chamber votes, referendum or Congress, suspension guards,
publication and versioned effective constitutional corpus. Trusted local clerk
and read/draft API only. Dynamic parliamentary/referendum evidence, full navette
and rule propagation remain open. See WORLD_POLITICS_REVISION.md.


## WP40 ordinary legislation candidate

Reviewed ordinary bill text versions, alternate chamber readings, CMP and
last word, constitutional review, promulgation and publication, verified
bridge to domestic enactment. Local certified evidence and operator apply;
full amendment tree, autonomous votes, 49(3) and all bill classes remain open.
See WORLD_POLITICS_LEGISLATION.md.


## WP41 government responsibility candidate

Existing 49(3) crisis aggregate linked to ordinary Assembly reading, certified
motions, censure, appointment and daily campaign/opinion projection. No
automatic law or presidential defeat. Single pinned session, certified
aggregate ballots only. See WORLD_POLITICS_RESPONSIBILITY.md.


## WP42 individual Assembly candidate

Pinned 577-deputy fictional roster, individual roll calls for linked and
independent censure and confidence votes, signed rolls, verified legal
outcomes and independent appointment. Certified agenda still required;
calendar and dynamic roster integration remain open. See WORLD_POLITICS_ASSEMBLY.md.


## WP43 parliamentary lifecycle candidate

Opt-in certified sitting slots and automatic scheduled censure roll calls,
ordinary session renewal, fictional 577-district electoral roster, archived
previous legislature and certified coalition changes. Repeated elections,
full session calendar, autonomous agenda and coalition bargaining remain
open. See WORLD_POLITICS_PARLIAMENT.md.


## WP44 cabinet candidate

Fictional named cabinet, certified voluntary and constitutional succession,
portfolio appointments/removals, cohabitation and current declared support,
incumbent prime-minister gates for selected legislative procedures, archive
and read API. Certified internal events only; autonomous formation and full
competence execution remain open. See WORLD_POLITICS_CABINET.md.


## WP45 executive acts candidate

Reviewed regulatory acts and delegated ordinance lifecycle, incumbent and
competent countersignature checks, publication, lapse/express ratification,
and verified regulatory domestic implementation. Clerk-certified law digests;
full delegated authority, litigation and autonomous agenda remain open.
See WORLD_POLITICS_EXECUTIVE.md.


## WP46 finance candidate

Annual fictional finance variants, Article 47 deadline guards, reviewed
publication and verified domestic enactment, coherent treasury projection
with fixed-rate interest and borrowing. Full LOLF, 49(3) finance pathway,
continuity and ordinance routes, recurring years and debt markets remain
open. See WORLD_POLITICS_FINANCE.md.


## WP47 macroeconomic candidate

Opt-in deterministic macro read projection from verified fiscal and trade
states; GDP expenditure identity, prices, unemployment, growth, tax proxy
and treasury reconciliation. Fictional coefficients; no feedback to the
underlying authoritative ledgers. See WORLD_POLITICS_MACRO.md.


## Décision de périmètre du 25 novembre 2026 — objectif WP80

WP80 est l'objectif final prévisionnel du back-end. France seule jouable
au lancement ; les autres acteurs restent présents et automatisés.
Ne pas interpréter les modules des autres pays comme l'ouverture de
leur contrôle joueur. Le développement de leurs IA est utile à la
France, pas un prérequis pour leur jouabilité humaine. Aucune fusion
dans main Aeternum, aucun front-end avant accord.

- WP48–50 : boucle macro budgétaire, monnaie, banques, dette, secteurs ;
  données françaises observées importées par un registre distinct.
- WP51–53 : démographie, services, partenaires sociaux.
- WP54–56 : crises, transitions politiques, continuité.
- WP57–60 : actions du joueur, IA, campagne française de référence.
- WP61–65 : interlocuteurs étrangers autonomes, institutions pertinentes
  pour la France et interactions transfrontalières.
- WP66–69 : diplomatie, traités, organisations, sortie UE et transition
  euro/nouvelle devise, liens avec WP48–50. Cas de maintien et retrait
  juridique distinct des calendriers opérationnels.
- WP70–73 : défense et sécurité au niveau stratégique.
- WP74–76 : enrichissement des données réelles françaises, personnalités,
  calibration, études de sensibilité. Collecter les sources lors des CI,
  pas de travail de fond asynchrone supposé.
- WP77–80 : sécurité, performances, tests longs, dépôt propre, API stable.

Première collecte France : games/world-politics/data/2026-11-25-france-observations-v1.json.
Ce lot est prêt pour étude de l'import, PAS encore en base de données,
et ne doit pas remplacer les fixtures des anciens scénarios. Voir
WORLD_POLITICS_FR_EU_EURO_EXIT.md pour l'étude initiale demandée.
WP80 est un objectif, pas un engagement de conformité exhaustive.


## France–Russia normalisation study (25 November 2026)

Added a France-first diplomacy/energy scenario: bilateral normalisation,
independent Russia–Ukraine peace negotiations, multilateral legal lifting,
separate EU gas phase-out law, France after effective EU withdrawal,
partial/failed agreements, lawful aggregate commodity logistics, capacity
constraints and independent foreign AI. No default political outcome or
sanctions-evasion mechanism. Implement across WP48–50, WP57–69 and
WP74–80. See WORLD_POLITICS_FR_RUSSIA_NORMALISATION.md and
games/world-politics/data/2026-11-25-france-russia-trade-staging-v1.json.
WP47 completed CI run 45711143046, all three jobs succeeded, 21 specific
assertions, no GitHub CI warnings. Study and reference staging are
documentation/data only, not a claim that the gameplay exists.


## Complement: systemic European and monetary consequences

See WORLD_POLITICS_EU_EURO_SPILLOVERS.md. All withdrawal and normalisation
branches must compute independent remaining-EU and euro-area responses,
fixed-geography versus membership aggregates, EU budget, central bank
balance sheets, financial spillovers and autonomous institutional choices.
No automatic collapse or forced secondary exits. WP80 acceptance.


## WP48 fiscal loop candidate

France observed-anchored opt-in world, 40 observations persisted in per-world
MariaDB assumptions, daily lagged tax/social feedback, prices, wages,
activity, employment and authoritative debt/cash financing. Prior scenarios
unchanged. Coefficients fictional; trade, energy, monetary policy and full
budget instruments remain open. See WORLD_POLITICS_FISCAL_LOOP.md.


## Sovereign debt and financial crisis scope for WP80

See WORLD_POLITICS_SOVEREIGN_DEBT.md and data/2026-11-25-fr-sovereign-debt-staging-v1.json. Daily instrument and creditor accounting; observed aggregate coverage and fictional residual ownership, maturity, cash constraints, default/restructuring, abstract nationalisation and contested transfers, autonomous support and systemic spillovers. No real creditor asset targeting, no automatic game over, no automatic unlimited financing. WP49–50 and WP66–80. Study only. WP48 HTTP failed on world creation; dedicated test account preserves runtime ten-world limit, new CI required.


## WP49 sovereign debt candidate

Observed debt pack pinned in MariaDB, synthetic nominal creditor cohorts,
120 monthly maturity buckets, finite financing, unpaid principal/interest/
primary outlays, fixed issue-rate buckets, daily/monthly/yearly stock-flow
journal. No actual ISIN schedule, player default, bank model or allied
credit yet. See WORLD_POLITICS_DEBT_LEDGER.md.


## WP50 financial crisis candidate

New scenario with owner-only fictional moratorium/resumption, one negotiated
overdue-principal reduction, one finite fictional support facility, bank
valuation/credit proxy and lagged macro feedback. No nationalisation,
central bank, actual alliance negotiation or currency conversion yet.
See WORLD_POLITICS_FINANCIAL_CRISIS.md. WP49 validated CI 45717816781.


## WP51 population candidate

51 Insee observations pinned, 28 age/sex cohorts, daily births, deaths,
net migration and aging, calendar aggregates and bounded lagged macro
feedback. Provisional January 2026 vintage; demographic assumptions
uncalibrated, no gross migration or service costs yet. See
WORLD_POLITICS_DEMOGRAPHY.md. WP50 CI 45768149991 all three jobs green,
23 specific assertions, no warnings.


## WP52 public services candidate

33 dated official observations, health/education/retirement demand,
capacity and funding proxies, demographic incremental fiscal adjustments,
lagged pressure and coherent read API. No duplicated baseline, not full
sector budgets, no individual pension rules or recruitment yet. See
WORLD_POLITICS_SERVICES.md. WP51 CI 45776279152 all green, 21 specific
assertions, no warnings.


## WP53 social partners candidate

Opt-in `fr-social-partners-v1`: five fictional representative bodies, differentiated
sectoral consultation from committed WP52 service and macro observations, bounded
hysteresis and replay-verified opinion journal. Read-only API; no legal agreement,
new fiscal charges or automatic regime outcomes. WP52 CI 45778973335 all three
jobs green. See WORLD_POLITICS_SOCIAL_PARTNERS.md.


## WP53b corporate alternative candidate

Opt-in France scenario with parallel corporations, contested exclusive system,
calendar reports, autonomous two-college votes, state approval or return, distinct
worker and employer response, and public ownership requests (not transfers).
See WORLD_POLITICS_CORPORATIONS.md. WP53 CI 45788382428 green.


## WP53c causal audit and policy packs

WP53b CI 45788792377 green. See WORLD_POLITICS_CAUSAL_AUDIT.md for cross-module
review, deficits and acceptance gates. Two corporate actions executable as packs;
other programmes explicitly unavailable until dependencies exist. 40 dated CAC40
identities; individual company legal/fiscal review requests, not ownership transfers.
New branch-wide requests rejected; historical replay preserved.


## WP54 civil mobilisation and continuity candidate

WP53c CI 45789774900 green. Opt-in fr-civil-crisis-v1, fictional daily attendance
estimates and city totals, public-service continuity and nonterminal certified
relocation/abroad/return events. No security-force deployment, weapons or
coercive tactics. See WORLD_POLITICS_CIVIL_CRISIS.md. Further autonomous
political resolution and legal recognition remain open.


## WP54r corrective tranche — before WP55

WP54 CI 45790745743 green. Corrected missing corporate wage-to-macro path and
fixed eligible-incumbent presidential outcomes for new corporation/civil-crisis
worlds. Versioned optional behaviour preserves old saves. No claim of closure of
all WP53c audit findings; see causal audit remediation checkpoint. WP55 deferred.

### WP54r firm capacity remediation

WP54r wage/election CI 45791373411 and trade CI 45791593618 green. Added pinned
fictional independent company agents and delayed project effects; WP55 remains
deferred. Nationalisation settlement and industrial programme still open.

### WP54r equity accounting remediation

Optional compensated voluntary fictional equity settlement, cash-limited, distinct
from statutory nationalisation. No automatic debt issue or GDP addition. CI
pending. WP55 remains deferred; statutory acquisition and financing open.

### WP54r industrial pilot remediation

Acquisition CI 45792401529 green. Cash-funded industrial pilot added; full
sector model and legal approvals remain open. WP55 deferred.

### WP54r foreign reply remediation

Party response CI 45793372187 green. Daily foreign replies now integrated in
pinned diplomatic clock; legal authorization and economic sanctions bridge remain
open. WP55 deferred.

### WP54r daily diplomatic-economic outbox

Pinned daily economic bridge now materializes pending proposals from verified
committed diplomatic intentions, atomically acknowledges source. Not legal
approval; actual trade sanctions and EU instruments remain open. WP55 deferred.

### WP54r daily legal reconciliation

Previous daily diplomatic-economic bridge green. New domestic clock matches
certified final publications to pending policies and records legal proofs
atomically. Does not bypass clerk, constitutional review or text scope.
Full programmes and trade legal authority remain open. WP55 deferred.

### WP54r European procedure pilot

Previous text/unit CI green. Article 50 state machine and player intent route
added; trusted evidence required for notification and negotiations. Trade regime,
monetary transition, legal proof issuance and EU economic spillovers remain open.

### WP54r post-exit trade remediation

Article 50 CI green. New pinned certified post-exit trade scenarios and
customs revenue integrated in daily macro and treasury. No automatic tariff
or currency conversion. Country-level and euro spillovers remain open.

### WP54r EU spillovers

Post-exit V2 CI green. Coherent read projection separates membership scope,
mirrored trade and counterfactual EU budget transfers; explicit missing country
exposure and unallocated flows. Actual EU/national fiscal settlement, foreign
GDP and independent monetary/central bank balance sheets remain open.

### WP54r euro stress sensitivity

Separate prospective certified FX and refinancing scenarios now expose reserve
valuation sensitivities and interest accrual on newly refinanced principal.
Not a currency market model, actual transfer or monetary transition; coverage
and accounting limitations explicit. WP55 remains deferred.

### WP54r multidevise contract ledger

Closed fictional EUR/FRF contract projection with explicit treatment, parity,
reciprocal FX and symmetric counterparty valuation. Actual debt/treasury
currency migration, bank and central-bank balance sheets, maturities and
legal certification issuance remain open. WP55 deferred.

### WP54r production-indexed Franc

New worlds pin a certified pilot monetary-base rule with lagged real GDP
and balanced issuance, explicit contraction and inflation safeguards. Franc
name exposed, historical code FRF reused. No fiscal spending, full bank money
or live FX peg until central-bank and treasury migration is implemented.

### WP54r Franc public funding

New pinned incremental four-sector funding ledger: certified public spending
within production ceiling, levy-funded repayment and destruction, no automatic
issuance or actual EUR fiscal booking. Full national currency migration,
central bank opening balance sheet, commercial banking solvency/credit and
macro demand effects remain open.

### WP54r Franc commercial banking

Pinned incremental consolidated-bank pilot with separate credit deposits,
capital/liquidity constraints, repayments, interest and losses. No new base
money from private credit; public deposits included once. Actual national
banks, interbank clearing, deposit runs, resolution, FX reserves and economic
feedback remain open. WP55 deferred.

### WP54r bank crisis

Pinned cash withdrawal/redeposit, collateral-limited prefunded liquidity
facility and aggregate prefunded deposit guarantee pilot. No extra base
issuance. Autonomous stress, depositor roster, resolution and multi-bank
clearing remain open; national EUR macro/treasury still separate.

### WP54r Franc FX reserves

Certified two-currency settlement and reserve valuation pilot, no extra base
issuance. Explicit unmet demand and reserve floor; dynamic exchange rate,
multi-asset reserves, bank FX exposures and macro feedback remain open.

### WP54r optional FX price formation

Lagged gross certified order pressure drives a bounded Franc exchange quote,
shared across the contract and reserve books. Autonomous bilateral demand,
price discovery/market clearing, reserve asset diversification and real macro
feedback remain open.

FRF replaces the provisional French-franc denomination across monetary and
FX projections. Persisted evidence unchanged; projection clients must adopt
frf-specific field names. CHF remains distinct.

### WP54r trade-driven FRF quote

Optional lagged nominal trade proxy and certified net private capital flow
pressure, shared quote across reserve and contract books. No invented actual
balance of payments, autonomous private capital behavior or FX transactions;
full macro price/credit feedback, actual national currency ledger remain open.

### WP54r FRF macro transmission

Pinned new-world v3 daily feedback from committed FX quote and prior domestic
history: one-off price level shock, lagged trade volumes, matched demand
shortfall, bounded export response, existing wage/fiscal feedback. Old v1/v2
worlds remain compatible. Calibration, actual currency migration, national
balance of payments, and realized rolling inflation remain open.

### WP54r FRF fiscal dual ledger

Committed per-day FRF fiscal reconciliation, certified opening cohort
conversion, new domestic FRF/external EUR issuance, native debt principal
and FX valuation bridge. Full market-rate settlement of retained EUR debt,
currency-specific interest/arrears and central bank fiscal integration remain
open. No reference EUR ledger migration yet.


## WP54r — échéances électorales et objectif de campagne (26 novembre 2026)

Les nouvelles campagnes avancées françaises épinglent les dates officielles de la présidentielle 2027 et la durée de cinq ans, avec une limite initiale de deux mandats consécutifs. L'incumbent fictif hérite du calendrier du mandat en cours mais est configuré à son premier mandat consécutif; le compteur du joueur commence uniquement à la prise de contrôle en jeu. Les campagnes de test antérieures et les sauvegardes existantes conservent leurs règles. La date ordinaire d'expiration de l'Assemblée est calculée selon LO121; les scrutins de juin 2029 sont des dates de scénario et non un décret officiel. La liste des autres scrutins français et leurs précisions de date est exposée en référence. Article 89: une révision effective peut supprimer le plafond d'Article 6 sans supprimer l'élection ni modifier la forme républicaine. Une rupture de régime ne passe pas par cette révision et doit être traitée dans un agrégat politique séparé.

### Objectif de jeu et classement mondial — exigence obligatoire WP80

Le joueur cherche la durée de pouvoir effectif maximale ET une amélioration durable du pouvoir d'achat des habitants. Une victoire électorale, une majorité parlementaire ou une modification du régime ne donne pas de points par elle-même. Le serveur mesure uniquement la durée effectivement passée en charge et le résultat économique. Perte du pouvoir = fin du compteur de tenure et score figé; la survie du régime n'est pas la survie du personnage. Une succession ou un retour ultérieur ne prolonge pas automatiquement la même série continue.

- WP55–56 : calendriers électoraux récurrents et anticipés, mandats, candidatures, vacances, dissolutions et transitions de régime au niveau abstrait. Juridiction et légitimité séparées. Tous les pays jouables doivent disposer d'un calendrier légal sourcé; aucun modèle national générique fictif présenté comme exact. Qualification de continuité au-delà de dix ans et extension des limites techniques au-delà de vingt ans avant classement illimité.
- WP57–60 : moteur de campagne, votes autonomes et accès réel du joueur à la procédure de révision (proposition PM, votes des deux chambres, ratification et publication par agents autorisés); aucun vote client fabriqué. Le maintien dans un régime alternatif doit être un état institutionnel certifié du jeu, pas une action qui efface un scrutin ou un bonus automatique.
- WP61–65 : échéances et règles nationales des 27 membres UE, Suisse et autres puissances jouables; adapter la notion de fonction dirigeante au régime.
- WP74–76 : données officielles et modèle de revenu disponible réel des ménages par unité de consommation (UC), emploi, transferts, impôts directs, revenus d'activité et du patrimoine, prix à la consommation, évolution des ménages, distribution et indicateurs de pauvreté. Insee https://www.insee.fr/fr/statistiques/2830244 et https://www.insee.fr/fr/statistiques/9006503 (2025 : -0,7 % par UC, 2024 : +2,2 %). Ne pas confondre salaire réel, PIB par tête et pouvoir d'achat. Les conversions monétaires, parité initiale et inflation ne doivent pas créer un gain fictif.
- WP77–80 : classement mondial persistant, API paginée, score versionné et vérifié exclusivement à partir du journal de simulation validé, contrôles anti-triche, limites de rechargement et duplications, compétitions comparables (version, difficulté, scénario et règles), classement tous joueurs et filtres pays. Tests de longue durée, reprise, égalités et audit avant extraction dans le dépôt autonome.

Proposition initiale du score v1 (à calibrer avant classement officiel): T = nombre de jours effectifs de pouvoir depuis le début de la campagne; G = variation en pourcentage du revenu disponible réel par UC, moyenne glissante sur les derniers 365 jours disponibles, rapportée à la référence des 365 jours avant prise de pouvoir ou baseline documentée. Score = max(0, floor(1000*T/365.2425 + 100*G)). Exemple : 10 ans et +20 % de pouvoir d'achat donnent environ 12000 points; 10 ans et -20 % environ 8000. Une baisse est pénalisée; aucun gain de conversion nominale. La moyenne réduit les manipulations de dernière journée; une période minimale et des tests d'équité seront requis. Départage: gain durable, durée, identifiant stable. Un seul meilleur résultat vérifié par compte dans un même classement. Pas de score officiel en cas de donnée économique manquante ou de scénario non comparable. Les valeurs 1000/100 sont une décision provisoire de design, non une observation économique. Cette formule est une spécification, pas encore un service de classement ni un indicateur RDB/UC implémenté.


### WP54r — premières échéances nationales comparatives

`ComparativeElectionCalendar.php` ajoute les six autres pays déjà présents dans les pilotes institutionnels (DE, ES, GB, IT, JP, US). Les nouvelles campagnes avancées ne déclenchent plus des scrutins étrangers au lendemain de la création : les dates sont prises dans des fenêtres légales publiées ou des conventions de scénario clairement identifiées (dates futures non annoncées). L'Espagne et les États-Unis utilisent une date ordinaire calculée à partir du texte applicable. Les mandats parlementaires sont distingués de la fonction exécutive : le plafond présidentiel français de deux mandats consécutifs ne s'applique pas aux chefs de gouvernement parlementaires; le plafond présidentiel américain, au contraire, porte sur le nombre total d'élections et comporte une règle spéciale en cas de succession. Le rapport d'audit donne les sources, règles et dates. Les résultats, circonscriptions, modes de scrutin et tailles des chambres des pilotes étrangers restent fictifs et incomplets ; les scrutins récurrents, la dissolution et la prise de fonctions ne sont pas encore entièrement raccordés. WP61–65 étendra les règles et données aux autres pays jouables. Les scénarios de test antérieurs et sauvegardes existantes ne sont pas modifiés.

## Décision utilisateur prioritaire — début de la campagne à l'investiture 2027

Le scénario canonique `fr-investiture-2027-v1` commence le 14 mai 2027 (convention journalière après l'expiration annoncée du mandat sortant le 13 mai, et non date de cérémonie confirmée). L'élection 2027 est un fait initial alternatif du scénario, pas une victoire réelle prédite. Le personnage du joueur exerce son premier mandat, le compteur de durée commence à zéro, première réélection en 2032, fin du second mandat en 2037 en l'absence de révision. La campagne hérite des modules avancés de crise civile, finance et institutions; le classement doit exclure les fixtures antérieures. Le référentiel économique disponible au 25/26 novembre 2026 demeure inchangé: ses stocks sont reportés comme hypothèses de démarrage, pas observations de mai 2027. Les maturités synthétiques débutent au premier mois complet suivant l'investiture (juin 2027). WP74–76 doit actualiser le millésime au lancement ou conserver explicitement le scénario historique; jamais inventer les résultats du scrutin ni des observations futures. Cette décision remplace les descriptions antérieures d'un titulaire en cours de mandat.


## WP54r — lot suivant prioritaire : état économique à l'investiture et aides à l'Ukraine

Exigence utilisateur du 26 novembre 2026. Avant la reprise normale de WP55, construire une **estimation reproductible** de l'état initial du scénario canonique `fr-investiture-2027-v1` au 14 mai 2027, à partir des données publiques disponibles au millésime de collecte. Ne pas attribuer au joueur la dette, la croissance ou le pouvoir d'achat accumulés avant sa prise de fonctions. Conserver les sources originales, leurs dates d'observation, les dates de publication, les unités, les périmètres et les hypothèses séparément; distinguer observé, prévu par une institution et projeté par le jeu. Le scénario historique doit rester reproductible après publication de nouvelles statistiques. Le nouveau millésime sera sélectionné explicitement; aucune réécriture des sauvegardes existantes.

### Projection de l'ouverture
- Dette Maastricht brute et nette et par sous-secteur, dette négociable de l'État, intérêts, échéances et encaissements/décaissements. Distinguer déficit et variation de dette avec rapprochement des ajustements stock-flux. Historique trimestriel, échéancier AFT disponible et trajectoires PLF/PLFSS; scénario central et sensibilités basse/haute, pas de fausse précision quotidienne. Conserver l'identité dette d'ouverture + flux + ajustements = dette de clôture.
- PIB nominal/réel, inflation et déflateur du PIB distincts, emploi, démographie, revenu disponible réel par UC, recettes/dépenses publiques, balance commerciale, réserves et capital bancaire; ne pas confondre les agrégats annuels avec des stocks datés.
- Patrimoine national net par secteurs (ménages, sociétés non financières, sociétés financières, APU), actifs produits/non produits, actifs et passifs financiers, richesse extérieure nette et trésorerie publique. Ne pas assimiler patrimoine national, patrimoine des APU, actifs de l'État et liquidités mobilisables; ne pas soustraire une seconde fois une dette déjà incluse dans un passif net. Réévaluations, épargne nette et autres changements de volume séparés.
- Sources déjà identifiées : Insee dette T2 2026 https://www.insee.fr/fr/statistiques/9053525 ; séries https://www.insee.fr/fr/statistiques/2830301 ; comptes de patrimoine 2025 https://www.insee.fr/fr/statistiques/9111734 et https://www.insee.fr/fr/statistiques/2830290 ; projections Banque de France septembre 2026 https://www.banque-france.fr/fr/publications-et-statistiques/publications/projections-macroeconomiques-intermediaires-septembre-2026 ; AFT, DG Trésor et documents budgétaires 2027 (vérifier leur statut et actualiser à la date d'exécution).
- Les valeurs de référence ci-dessous ne sont pas des stocks au 14 mai 2027 : patrimoine national net fin 2025, 20 196 Md EUR; patrimoine net APU 811 Md EUR; patrimoine non financier APU 2 945 Md EUR; patrimoine financier net APU -2 134 Md EUR (Insee, millésime 2025). Ce sont des chiffres publiés arrondis, non des entrées de trésorerie. Les données intermédiaires inconnues devront être projetées et étiquetées.

### Registre Ukraine — décision politique jouable
- Collecter séparément engagements et paiements bilatéraux civils/humanitaires, militaires (dons matériels en nature, coûts budgétaires réels, remplacements, formation), prêts (créance correspondante), garanties et risques, contributions européennes (FEP, Facilité pour l'Ukraine, service des prêts européens), dépenses d'accueil sur le territoire français. Comptabiliser les remboursements et éviter les doubles comptes entre UE, France, matériel valorisé, commandes et décaissements. Chaque flux doit avoir date, catégorie, source, bénéficiaire agrégé, caractère annulable et base juridique de scénario.
- Ancrages de collecte : rapport Sénat sur les déploiements extérieurs https://www.senat.fr/rap/r25-513/r25-5136.html : valeur brute hétérogène du soutien majoritairement militaire environ 6,5 Md EUR (depuis 2022, périmètres et dates variables), coûts directs Armées comptabilisés 114 M EUR en 2022, 397 M en 2023, 547 M en 2024, 588 M en 2025; engagements FEP 2,2 Md sur 2022–2027, remboursements effectifs/attendus près de 1 Md. Ces séries se recouvrent et ne s'additionnent pas. Rapport Sénat PLF 2027 Affaires européennes https://www.senat.fr/rap/l26-156-22/l26-156-227.html : prêt UE 90 Md EUR en 2026–2027, distinct d'un don national; Commission https://commission.europa.eu/topics/eu-solidarity-ukraine/eu-assistance-ukraine/eu-financial-support-ukraine_en et https://commission.europa.eu/topics/eu-solidarity-ukraine/eu-assistance-ukraine/ukraine-facility_fr ; compléter avec données DG Trésor, MEAE, budget exécuté et données Kiel (contrôler recouvrements).
- Autoriser le joueur dès l'investiture à décider un arrêt immédiat de toute nouvelle aide bilatérale discrétionnaire à l'Ukraine, avec date effective du jour de décision ou prochaine journée comptable, sans période de réduction imposée; prévoir aussi maintien, réduction, augmentation et reprise selon procédure nationale. La décision peut suspendre les nouveaux engagements et versements effectivement révocables. Les engagements contractuels déjà nés et obligations européennes ne disparaissent pas par simple annonce présidentielle: mécanismes distincts de résiliation/renégociation, autorisations budgétaires, impayés/litiges et conséquences explicites si le joueur refuse de les honorer. Aucun effet rétroactif sur les aides déjà payées; aucune confiscation automatique des prêts ou garanties.
- Raccorder le coût marginal réellement évité au budget/financement, au calendrier des paiements, au carnet de commandes industriel agrégé, aux relations avec Ukraine, Russie, États membres, UE, OTAN et autres partenaires autonomes. Réponses économiques et diplomatiques incertaines et conditionnelles, sans issue politique prédéterminée; aucune économie fictive égale à la valeur cumulée du matériel livré depuis 2022. Distinguer trésorerie immédiate, valeur du patrimoine, dépenses futures et risques contingents. Ne pas doubler des flux d'aide déjà compris dans les dépenses de base.
- Recette : import et provenance, ouverture cohérente au 14 mai 2027, compte de durée nul et référence RDB/UC fixée à l'investiture, aucun gain de score avant prise de pouvoir; scénario maintien contre arrêt immédiat avec mêmes conditions initiales, arrêt d'engagements nouveaux et conservation des passifs existants, non-duplication et réconciliation de chaque flux, répercussions différées, autorisations et rejeu exact en MariaDB, API et CI. Une projection future ou un indicateur manquant ne peut être présenté comme une observation certaine. Ce lot est une spécification de la prochaine itération, pas un module déjà réalisé.


### WP54r — projection d'ouverture et première politique Ukraine

Le pack `2026-11-26-fr-investiture-projection-v1.json` sépare données observées, prévisions institutionnelles et conventions de scénario. `InvestitureEconomicProjection` interpole les stocks de dette prévus fin 2026 et fin 2027; ventile la variation 2027 entre déficit et résidu stock-flux, et publie une sensibilité +/- 50 Md EUR annualisés (hypothèse de jeu). Les patrimoines nets sectoriels sont projetés avec les variations historiques nominales -3%, +1,2%, +3,1% comme sensibilités, non comme prévisions d'épargne ou de prix d'actifs. Les écarts d'arrondi sont explicites. Les nouveaux mondes d'investiture chargent la dette projetée, les flux PLF 2027 et un PIB annualisé nominal à prix de référence de départ; composition du PIB et prestations extrapolées à structure constante, chômage et démographie restent ancrés historiques. Les anciennes sauvegardes gardent leur pack. La dette nette n'est pas extrapolée faute de trajectoire d'actifs liquides, les réserves/bilans et patrimoine sectoriel restent des références non encaissables. Le taux de coupon initial est un proxy de charge annuelle, non le coupon exact des obligations.

`/countries/FR/ukraine-aid` expose le référentiel et le dernier flux; `POST /domestic/ukraine-aid` utilise le journal transactionnel de décisions domestiques (opération interne corporation, permission propriétaire existante) et accepte stop/reduce/maintain/increase/resume. Dans ce pilote, 588 M EUR annuels de coûts directs militaires 2025 sont reportés en 2027 comme hypothèse, avec moitié discrétionnaire et moitié protégée (convention, pas inventaire de contrats). Les variations de la part discrétionnaire réduisent/augmentent une seule fois la dépense primaire déjà incluse dans la baseline, à partir de la prochaine journée. Les obligations européennes et coûts protégés ne sont pas effacés. Les économies sont prévues et non présumées payées; les coûts civils, prêts, garanties et accueil non chiffrés ne sont pas supposés nuls. Les données brutes, FEP et remboursements restent séparés et non additionnés. Reste à collecter et modéliser les autres catégories, les engagements réels, la résiliation et les répercussions diplomatiques autonomes avant clôture complète du périmètre demandé. Les chiffres ne sont pas des observations de mai 2027.


### WP54r — aide Ukraine : ventilation civile et diplomatie

Le second pack conserve le guide DG Trésor (p.3, capture vérifiée), le MEAE du 6 octobre 2026 (471,6 M EUR humanitaire au 1er juillet), la nouvelle enveloppe civile 71 M EUR de 2026, les données OCDE 2025 et les prêts UE. Les garanties, prêts, plafond AFD, aide en nature et dépenses d'accueil ne sont pas additionnés aux coûts annuels. L'écart interne des projets de coopération (36 M annoncés contre 43 M détaillés) reste explicite. Pour nouveaux mondes projetés, l'aide v2 prend trois flux de scénario distincts : 588 M de coûts directs militaires, 71 M d'enveloppe civile et annualisation des 407,6 M d'aide ONG/organisations internationales sur la période 2022–juillet 2026. Ce ne sont pas des décaissements 2027 connus. Part révocable provisoire 50%, et part de demande intérieure des deltas défense/civil 50%, hypothèses non calibrées. Les réductions de transferts extérieurs ne sont pas comptées comme baisse mécanique de demande intérieure; les montants protégés sont conservés. Les nouveaux engagements discrétionnaires sont fermés au taux zéro; pas d'extinction automatique des contrats/prêts/garanties en cours. L'état diplomatique reçoit le taux de la veille depuis le registre engagé et ajuste de manière bornée les réponses des six agents partenaires; coefficients explicitement fictifs, ni prédictions ni sanctions automatiques. Les anciennes sauvegardes restent compatibles par activation épinglée du modèle v2 et du bridge. Le financement européen, les engagements juridiquement vérifiés et les flux de prêts/garanties restent sans échéanciers complets : ne pas les traiter comme zéro ni comme économies certaines.


### WP54r — complément d'ouverture et passage de relais

Le pack de complément du 26 novembre 2026 conserve les contributions Maastricht brutes et nettes par sous-secteur au T2 2026, les comptes de revenu disponible des ménages 2025 et les autorisations du fonds civil distinctes des paiements. Les nouveaux mondes projettent la dette nette en gelant les actifs déductibles Maastricht à 228,7 Md EUR et répartissent les stocks selon les contributions brutes publiées normalisées; les écarts d'arrondi restent visibles. La dette brute ventilée alimente les cohortes nominales existantes, sans inventer de créanciers. La population au 14 mai 2027 extrapole les flux physiques 2025 à structure d'âge et sexe constante, sans compter l'ajustement statistique comme naissance ou migration. L'indice initial de pouvoir d'achat réel par UC est 100, mais aucun classement n'est encore autorisé: la série dynamique RDB/UC reste à construire. Les snapshots antérieurs sont rejouables avec leurs versions et paramètres initiaux.

La tranche WP54r ferme le socle jouable et les régressions ciblées de l'investiture, des comptes publics et de l'arrêt des aides nouvelles. Le passage à WP55 n'attend pas de statistiques futures inexistantes. Les échéanciers contractuels réels, renégociation/impayés sur obligations existantes, paiements UE, prêts/garanties, calibration des comportements, complétude mondiale des observations, classement RDB/UC, et audit de bout en bout vingt ans restent des exigences ouvertes, non des fonctionnalités achevées. WP55–60 reprennent la continuité institutionnelle et les procédures joueur; WP66–69 consolident les engagements et négociations externes, WP74–80 la collecte, calibration, classement et validation complète. Ne pas fusionner la branche World Politics dans main du moteur générique.


## WP55–57 — premier lot de continuité institutionnelle

WP55 : nouveaux mondes d'investiture uniquement, scrutins législatifs français récurrents, échéance LO121, remplacement du calendrier après dissolution certifiée (deux dimanches dans la fenêtre Article 12), installation du résultat et archivage de l'Assemblée, délai de redissolution. Les ballottages restent des fixtures et le pilote suspend les décisions législatives pendant dissolution; une dissolution requiert les trois consultations certifiées, pas un bouton propriétaire. Les sessions sont journalisées pour le rejeu. WP56 : durée effective calendaire à partir de l'investiture, figée à la perte de fonction, état constitutionnel et reconnaissance séparés; fenêtre de succession Article 7 lors d'une vacance terminale, en lecture seule, puisque le jeu s'arrête. La transformation de régime avec maintien du titulaire reste à modéliser séparément. WP57 : le propriétaire peut déposer une demande de révision avec route référendum ou Congrès; le serveur progresse par étapes datées, observe l'opinion de la veille vérifiée, utilise les membres fictifs de l'Assemblée et 348 agents sénatoriaux synthétiques, puis les seuils Article 89 et publication. Les valeurs de vote et délais sont des hypothèses de jeu, non des données politiques réelles; l'accord PM est un proxy de cabinet en fonction, pas une procédure autonome complète. Les anciennes sauvegardes ne changent pas. Les votes réels simulés ne sont pas acceptés du client. Reste à compléter : accès joueur aux consultations et dissolution, législature intérimaire, Sénat réel et renouvellements, scrutins étrangers récurrents, changement de régime, qualification intégrale à long horizon et modèle de score. Sources : https://www.legifrance.gouv.fr/loda/id/JORFTEXT000000571356/ ; https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000006353288 .

### WP55 complément de jouabilité

Le propriétaire de la campagne d'investiture peut demander une dissolution via `request_dissolution` (pas de rôle juridique directement délégué). Les trois consultations de bureaux fictifs sont enregistrées par l'horloge le lendemain, la décision est réévaluée le jour suivant. Aucun consentement des personnes réelles n'est supposé. Le délai d'un an s'applique aux élections consécutives à une dissolution, et non à chaque renouvellement ordinaire. Les requêtes et résultats figurent dans l'état transactionnel. Pour la révision, le tirage des agents est épinglé à l'identité du monde, au texte et à la version constitutionnelle, non à l'identifiant choisi pour la requête; l'ID de preuve conserve son unicité. Les conditions du modèle restent fictives. L'étape de changement de régime avec maintien du titulaire est ouverte : elle exige un autre agrégat de droit et ne saurait seulement annuler la présidentielle républicaine.


## WP58–60 — Sénat, nominations, continuité du régime (tranche initiale)

WP58 : composition officielle du Sénat observée le 13/11/2026, 348 sièges, séries 170/178, mandats de six ans. Les identités des agents, la ventilation par série et les dates futures sont des conventions de simulation. Les renouvellements remplacent uniquement les agents de la série échue; la répartition agrégée reste constante dans ce pilote. Aucun vote d'une personne réelle n'est prédit. Les révisions autonomes utilisent ces agents et consignent leur empreinte. WP59 : demande de nomination d'un Premier ministre de catalogue fictif, exécution par service institutionnel après présentation certifiée de démission; ni révocation unilatérale ni démission implicite. Les autres compétences du gouvernement et le vote de confiance ne sont pas modifiés. WP60 : événements narratifs certifiés d'ouverture et de clôture d'une revue d'ordre constitutionnel alternatif, sans changement automatique des compétences, calendrier ou score. Un ordre alternatif complet reste à implémenter : ne pas confondre une revue de transition avec une abolition de la République ou une attribution de pouvoir. Aucun nouvel endpoint de publication d'événements narratifs. Nouveaux mondes d'investiture seulement. Les données et hypothèses sont séparées. Restent ouverts : modèle électoral indirect des sénatoriales, cohabitation et démissions autonomes, règles complètes des régimes alternatifs, API de procédures supplémentaires et qualification longue durée.

### WP59–60 complément

Le service de nomination enregistre également la présentation de démission due à une censure ou un refus de confiance déjà constaté, sans demande du joueur, lorsque les procédures sont closes. L'horloge ne nomme pas un successeur le même jour que la démission. `AlternativeOrder.php` est un noyau isolé de deux ordres constitutionnels **fictifs** (monarchie constitutionnelle élective à mandat de sept ans et république parlementaire à mandat de six ans limité à deux). Il accepte seulement des résultats constituants et de renouvellement provenant d'un service de scénario de confiance, épinglés au monde, au texte et au mandat précédent; aucune activation par API joueur, aucune attribution d'office automatique. L'expiration sans renouvellement termine le mandat, la sélection d'un autre titulaire termine la campagne. Le test démontre un parcours au-delà de dix ans avec renouvellement indépendant et rejeu. Ce noyau ne transfère pas encore les compétences, les horloges, les budgets, la diplomatie ou le droit du monde canonique; aucune partie intégrée n'est déclarée jouable sous ces régimes. La revue WP60 du monde canonique ne suffit pas à les installer. Le classement reste désactivé pour ce noyau.


## WP61–65 — tranche A : référentiel national et échéances récurrentes de planification

Le pack `2026-11-26-national-renewals-v1.json` couvre les 43 cibles (UE27, Suisse, 15 puissances), durées des chambres basses/uniques et mois attendus IPU. Les sources sont conservées, et l'Espagne utilise le scénario légal ordinaire existant plutôt que le mois IPU discordant. `NationalRenewalCalendar` calcule des échéances récurrentes de planification sur vingt ans, avec statut non contraignant, dates exactes uniquement pour le calendrier fédéral ordinaire américain et renouvellements indirects/nommés distincts. En Suisse, mandat collectif de quatre ans et présidence annuelle sont séparés. Le contrôle partiel des plafonds personnels FR/US/MX/KR ne vaut pas éligibilité complète, ne s'applique pas aux fonctions parlementaires et renvoie null pour les autres pays. Le nouveau GET authentifié `/api/v1/national-renewal-calendar` est un référentiel global, pas une mutation du monde; aucune sauvegarde modifiée. Tests de domaine et HTTP intégrés au workflow. Restent ouverts : règles constitutionnelles exécutives de tous les pays, calendriers électoraux certifiés, chambres hautes et renouvellements partiels, dissolutions, candidatures, autonomie des scrutins, formation des gouvernements et raccordement transactionnel des nouveaux cycles à l'horloge. Ne pas activer les 43 pays en sélection jouable sur cette seule base. WP61–65 restent ouverts après cette tranche.


### WP62 — renouvellements ordinaires des six pilotes étrangers

`ForeignRecurringElections` est activé uniquement pour les nouveaux mondes canoniques d'investiture, dans les états nationaux DE, ES, GB, IT, JP, US déjà transactionnels. Le serveur conserve le cycle précédent complet lorsque le scrutin suivant arrive, vérifie provenance et cohérence des archives et relance les négociations fictives au jour du résultat. Les dates américaines ordinaires sont calculées, celles des autres pilotes suivent une convention de récurrence conservant le jour de semaine du premier scrutin. Aucun scrutin anticipé, résultat réel, changement automatique d'exécutif ou présidentielle américaine jouable n'est introduit. Les sièges et bulletins demeurent les fixtures existantes et ne réagissent pas encore à l'opinion locale; les accords de coalition restent non constitutifs d'un gouvernement sans preuve indépendante. Les anciennes sauvegardes ne sont pas migrées. Les tests du domaine couvrent vingt ans, rejeu, passage par étapes, intégrité et absence de changement automatique du titulaire; le test HTTP vérifie l'activation et la persistance via l'horloge réelle. Les 36 autres cibles restent des références et ne sont pas activées comme pays jouables. WP63–65 restent ouverts.


### WP63–64 tranche A — formation nationale certifiée

Nouveaux mondes canoniques uniquement : `FormationProtocol` distingue l'accord politique et l'entrée en fonctions. DE : nomination, majorité absolue et nomination formelle; ES : consultations et investiture, deuxième vote du même candidat exactement 48 h après le premier si nécessaire; GB : appréciation de la confiance et nomination royale, sans vote d'investiture obligatoire inventé; IT : nomination puis deux votes distincts dans les dix jours. Le sous-modèle est conservateur (coalition majoritaire, quorum, étapes ordinaires; pas de procédure minoritaire/exceptionnelle complète). Les preuves sont fournies par un greffier de simulation autorisé, attachées au monde, candidat, coalition, scrutin et jour; l'état, les résultats et les archives de gouvernement sont recoupés au rejeu. JP ne peut pas passer ce nouveau contrôle tant que la Chambre haute n'est pas modélisée; US n'est pas une investiture parlementaire. Références supplémentaires CH, FI, SE, PL, PT dans le GET national; elles ne confèrent aucun droit ni jouabilité. Aucune migration des mondes antérieurs; pas de nouvel endpoint d'écriture joueur. Reste notamment à produire automatiquement les preuves indépendantes dans le monde alpha, à modéliser les cas de refus, les gouvernements intérimaires et les autres systèmes nationaux. Les dates des étapes de jeu ne sont pas des prédictions politiques.


### WP64–65 tranche B — autonomie partielle et intégration

`AutonomousForeignFormation` est épinglé uniquement dans les nouveaux mondes canoniques. Pour DE/ES/GB, cinq jours après un scrutin ordinaire, la simulation calcule une réponse individuelle des sièges synthétiques à la coalition : 97 % de soutien théorique dans la coalition, 5 % hors coalition, abstentions paramétrées; tirage déterministe par monde, scrutin, programme, parti, siège et tour. Ces paramètres ne sont ni une mesure ni une prévision. L'Espagne peut passer par le second vote; le Royaume-Uni conserve une appréciation de confiance et non un vote légal obligatoire. Une tentative bloquée est journalisée; un accord sans procédure certifiée ne nomme personne. Aucun contournement d'une motion en cours; aucun endpoint de certification joueur. Rejeu des votes, rapprochement des preuves et test de l'horloge quotidienne, plus régression réelle HTTP de l'activation. IT peut recevoir une preuve bicamérale vérifiée mais ne dispose pas d'une Chambre haute autonome indépendante; JP et US restent bloqués pour les formations parlementaires. Les étapes sont une abstraction évaluée au cinquième jour, pas une simulation temporelle exhaustive de chaque office; le premier vote et les nominations ne sont pas des mutations intermédiaires du monde. Les échecs ne relancent pas encore une procédure autonome; la gestion complète des intérims, refus, vacances, dissolution et autres pays reste ouverte. Aucun changement de la campagne FR ou des anciens mondes, ni fusion dans le moteur générique. WP63–65 ne sont pas exhaustivement clos.


Qualification WP65 : le test MariaDB `wp06-institutions-db.php` exécute le scrutin espagnol et les cinq journées suivantes par le véritable magasin transactionnel, compare à chaque étape le snapshot au moteur pur, lit sur une connexion PDO distincte, vérifie la nomination et le rejeu exact du reçu, le rejet d'un saut de jour et la conservation du journal. La préparation avant le scrutin est une fixture de domaine, non un test de l'ensemble de l'horloge mondiale de mai à août 2027.


WP65 complément : les nouveaux mondes épinglent `wp65-autonomous-formation-v2`. L'Italie dispose d'un Sénat synthétique distinct de 50 sièges, généré indépendamment du résultat de la Chambre; ses votes doivent eux aussi permettre la confiance avant certification. Les règles de la version v1 restent disponibles aux anciens mondes. Les sièges sont des fixtures non représentatives du Sénat réel. Les tentatives attendues manquantes sont rejetées au rejeu v2. Japon, élections présidentielles américaines, intérims et échecs d'investiture restent ouverts; l'Italie ne dispose pas encore d'un véritable cycle sénatorial indépendant ni d'une phase intérimaire juridiquement exhaustive.


## WP66–67 tranche A — engagements internationaux, échéances et négociations

`InternationalCommitments` est un registre versionné, épinglé aux nouveaux mondes d'investiture, transactionnel via la diplomatie existante. Trois contrats fictifs (aide civile Ukraine, fourniture Ukraine, contribution multilatérale) comportent des échéances en centimes EUR. Ils ne constituent PAS un inventaire réel, ni des dépenses additionnelles au modèle WP54r; `treasury_booked=false`, `budget_delta_minor=null`. Le joueur peut suspendre le paiement, reprendre, demander un report ou une résiliation bilatérale. La suspension ne libère pas juridiquement et crée des arriérés à échéance. Les réponses de partenaires sont fictives, déterministes, différées de sept jours et bornées aux échéances futures; pas de remise automatique d'arriérés ou de paiements déjà réalisés. Le canal multilatéral ne peut pas être résilié par cette procédure bilatérale. La conservation des montants, rejeu, droits, ETag et reçus sont raccordés au magasin diplomatique. Les références juridiques générales sont les articles 54, 57 et 70 de la Convention de Vienne (https://legal.un.org/ilc/texts/instruments/english/conventions/1_1_1969.pdf); cette convention et son applicabilité ne sont pas supposées identiques à des contrats commerciaux, à l'UE ou à la France. Pas de prétention de droit de résiliation unilatérale. API privée GET/POST `/worlds/{world}/diplomacy/commitments`. Restent ouverts : échéanciers réellement documentés, prêts, garanties, litiges, pénalités et effets budgétaires/diplomatiques quantifiés, autorisations nationales détaillées, mécanisme multilatéral, et qualification HTTP et MariaDB spécifique. Le registre ne change pas la décision immédiate WP54r sur les nouvelles aides discrétionnaires.


### WP68–69 tranche A — stress financier non comptabilisé et réponse diplomatique

`CommitmentRisk` distingue les arriérés ouverts, leur ancienneté et une sensibilité d'intérêts simples à 300 points de base annuels, explicitement hypothétique et non contractuelle. Aucun effet trésorerie, dette, FRF, intérêts réellement dus, sanction ou traité automatique; `budget_delta_minor=null` et `actual_penalties_minor=null`. Les arriérés du partenaire ukrainien ajoutent un signal borné à l'IA des réponses diplomatiques; la contribution multilatérale possède un signal séparé non distribué arbitrairement aux États membres. Le nouveau pin est activé uniquement pour les nouveaux mondes, les états précédents gardent leur comportement. Les tests comparent maintien et défaut, reprise, rapprochement et rejeu; les tests HTTP couvrent admission, idempotence, refus multilatéral et persistance après une journée coordonnée. Reste à qualifier les échéances en base et surtout à rapprocher les flux avec les véritables allocations budgétaires et créances/garanties avant tout effet sur les comptes nationaux; aucun montant fictif ne doit être additionné au budget de référence. WP66–69 restent ouverts au-delà de ce socle.


Qualification complémentaire WP66–69 : le test réel MariaDB `wp08-diplomacy-db.php` prépare une fixture au jour précédant la première échéance, exécute les commandes propriétaire et les ticks certifiés dans le magasin diplomatique, vérifie les arriérés et les signaux après reconnexion, la révocation des droits avant rejeu, le reçu exact, la reprise des paiements et quatre écritures atomiques sans double compte. L'observation d'aide est une fixture explicite, pas une preuve des flux réels de France.


### WP69 complément — rapprochement documentaire des engagements

GET authentifié `/countries/FR/international-finance` sur le monde canonique : huit montants historiques sourcés issus du pack Ukraine épinglé, séparant aides, prêts concessionnels, plafonds AFD, garanties et programmes UE. Les encours, passifs contingents, calendriers réels et quotes-parts françaises restent inconnus (null). Ces lignes ne sont pas additives. Le rapport combine l'état diplomatique et domestique sous verrou d'horloge, avec provenance et digests, et sépare explicitement les obligations fictives et les flux budgétaires déjà pris en compte. Aucun nouveau débit/crédit de trésorerie. Tests domaine et HTTP. Ne constitue pas une comptabilité exhaustive des traités ou créances réelles.


## WP70 — socle stratégique de planification de défense

Référence datée du 26 novembre 2026, PLF 2027 mission Défense 63,4 Md EUR, comparateur 2026 56,9 Md EUR, augmentation 6,5 Md EUR; objectifs LPM actualisée 2,5 % PIB en 2030 et 3,5 % en 2035, distincts des crédits annuels. Pack et digest persistés dans les nouveaux mondes canoniques; anciens mondes inchangés. `GET /countries/FR/defense-planning` expose trois variantes illustratives -10/0/+10 %, avec ventilation synthétique et conservation exacte des montants. Ni crédits votés ni dépenses constatées inférés, aucun impact additionnel sur les comptes. Appel sous les drapeaux suspendu, rétablissement par la loi (L112-2), aucune activation unilatérale. Tests de domaine et HTTP. Le lot ne comporte pas de simulation opérationnelle, de renseignement ou de déploiement. Restent ouverts : raccordement au cycle de loi de finances, coûts et calendrier des politiques de personnel au niveau agrégé, sécurité civile et résilience, contrôle démocratique et qualification longue WP71–73.


### WP71 — premier exercice budgétaire après l'investiture

Correction d'une incohérence du pilote WP46 : dans un monde démarrant en mai 2027, le dépôt d'un budget 2027 était impossible après la date limite de publication. Les nouveaux mondes canoniques épinglent `wp71-finance-2028-v1`, trois variantes de variation marginale de dépense défense à partir du repère PLF 2027, et l'exercice 2028 (366 jours). La variation annuelle est répartie en centimes/jour entiers, résidu inférieur à 366 centimes; elle n'est pas présentée comme un montant réel de crédits 2028 ou un remplacement de l'agrégat budgétaire. La procédure annuelle existante demeure obligatoire : ministre compétent, lectures, vote final, examen certifié, publication antérieure à l'entrée en vigueur. Le texte et la preuve sont liés à 2028 et à la proposition domestique, sans effet avant autorisation. Les anciens mondes gardent le catalogue 2027. Le test de domaine vérifie dépôt en octobre 2027, preuve exacte, rejeu et admission par DailyLegalActivation; le test HTTP vérifie le nouveau catalogue. Les variantes ne sont pas encore autonomes côté greffier/IA dans l'alpha, et l'activation effective est à qualifier en base. Les lois de finances rectificatives 2027, reconduction annuelle au-delà de 2028 et arbitrages multi-ministères restent ouverts. WP72–73 à poursuivre.


### WP72–73 tranche A — continuité civile et qualification

Rapport GET `/countries/FR/civil-continuity` pour les nouveaux mondes canoniques qui portent le référentiel défense : santé, éducation, pensions, financement primaire et arriérés, sur les trente dernières journées effectivement validées. Les ratios sont pondérés par la demande, les stocks séparés des flux, la fenêtre initialement vide reste inconnue et aucun indice arbitraire de préparation opérationnelle n'est inventé. L'état domestique et le référentiel défense sont lus sous verrou du calendrier, digest des sources et contrôle de cohérence. Les montants restent sur le registre de référence EUR, pas une valorisation en FRF. Aucun nouveau prélèvement, aucune décision automatique de sécurité, aucune activation de conscription. Tests domaine et HTTP. Restent ouverts : service national autorisé par une loi et calendrier administratif du scénario, qualification longue des finances 2028 en base, politique de sécurité civile et contrôles démocratiques, et WP74–80. WP72–73 ne sont pas exhaustivement clos.


## WP74 — données de revenu disponible et premier contrôle arithmétique WP76

Pack Insee base 2020 : six observations annuelles 2020–2025 (RDB, salaires nets, prestations monétaires, déflateur de consommation, UC, épargne et pouvoir d'achat réel par UC), quatre variations trimestrielles 2025-T3 à 2026-T2, vintages explicites. Épinglé dans les nouveaux mondes via le domaine diplomatique de références, sans changer les anciennes sauvegardes. GET `/countries/FR/household-reference`, digest, validation historique, identité nominal/prix/UC sur valeurs arrondies (tolérance 15 pb), sensibilité au déflateur. Ce n'est ni un backtest comportemental de la macro ni une calibration de ses coefficients. Aucun encours RDB/UC au 14 mai 2027 prétendu connu; aucun score. WP75 personnes/programmes, WP76 modèle de revenu par UC, calibration et WP77–80 restent ouverts. Les données de référence ne sont pas un flux budgétaire additionnel.


## WP75 tranche A — personnalités historiques

Douze candidatures de la présidentielle 2022, résultats proclamés et archive officielle des professions de foi. Rapprochement inscrits/votants/exprimés/voix par candidat, fiches compatibles PoliticalRoster avec source datée et avatar générique; programme non encore dépouillé. Épinglage dans les nouveaux mondes et GET authentifié `/countries/FR/historical-candidates`. Aucun résultat 2022 n'est transformé en intention de vote 2027; ni candidatures futures ni sélection joueur ni psychologie inférée. Le catalogue des personnalités contemporaines et programmes sourcés reste à enrichir. WP75 non clos.


## WP76 tranche A — indicateur expérimental de pouvoir d'achat

GET `/countries/FR/purchasing-power-proxy`, états persistés et cohérents sous verrou d'horloge. Pondérations de RDB, salaires nets et prestations 2025 issues de WP74; salaires simulés, chômage, population, prix et rétroaction de prestations du moteur. Deux hypothèses sur le résidu de revenu (constant nominal ou indexé sur les prix); population proxy des UC à composition fixe. Index initial conventionnel 100, historique uniquement depuis l'investiture, moyenne des 365 dernières journées validées; aucun score, aucun RDB réel par UC certifié, aucun effet budgétaire. Les impôts directs des ménages, patrimoine, transferts précis, participation et composition des ménages ne sont pas modélisés dans cette lecture. Registre EUR de référence, pas un indice nominal FRF. Tests de sensibilité, fenêtre, intégrité et HTTP. Pas de calibration comportementale empirique ni de qualification pour classement. WP76 reste ouvert, et WP77 doit refuser ce proxy pour un classement final.


## WP77 — score de campagne alpha versionné

`CampaignScore` calcule exclusivement côté serveur, à partir des états domestique, diplomatique et opinion rejoués et cohérents : max(0, floor(1000*T/365.2425) + G_pb). T jours continus depuis le 14 mai 2027, fin de mandat gelée au jour de perte du pouvoir, G variation d'indice de pouvoir d'achat expérimental (un point par point de base). Moyenne équipondérée des deux hypothèses WP76, moyenne jusqu'à 365 jours disponibles, fenêtre courte explicitement provisoire, ouverture 100 sans historique inventé. Cohortes par scénario, date, règles, difficulté standard, pack global, configuration domestique et références. L'alpha est classable dans une ligue expérimentale distincte; ni RDB réel par UC calibré ni classement officiel revendiqués. Pas de contrôle de score client. WP78 doit persister à la publication du jour, avec contrôle d'accès, séparation des cohortes et pagination. Calibrage empirique WP76 reste ouvert.


## WP78 — classement général alpha persistant

Migration additive 025, pin score_enabled uniquement pour nouvelles campagnes canoniques. Le score initial est créé dans la transaction de provisioning; chaque journée met à jour le score dans la transaction finale de publication du calendrier, après rejeu des domaines. Un échec empêche la publication et peut être repris par le mécanisme de récupération existant; l'ancien score reste visible au classement, pas un jour partiel. Contrôle cohorte, révision, terminal, empreinte et rapprochement index/rapport. Les anciennes sauvegardes restent non classées. GET `/worlds/{world}/score` privé et GET `/leaderboards`, `/leaderboards/cohorts/{page}`, `/leaderboards/{cohort}/{page}` authentifiés; aucune écriture publique du score. 50 par page, une meilleure campagne courante par compte actif, ordre score puis durée puis gain, rang partagé en cas d'égalité complète, pseudo dérivé stable sans publier identité ni monde privé. Fenêtres courtes provisoires, classement expérimental uniquement; pas de classement officiel au sens d'une calibration RDB/UC. Changement de règles ou de configuration = nouvelle cohorte, migrations de ligue à traiter explicitement. Installation neuve automatique; base existante nécessite migration 025 revue. Tests HTTP réels : création, plusieurs campagnes/comptes, classement, droits, révocation, pages, intégrité. WP79–80 : tests longs, coût du rejeu, qualification de reprise et audit de la migration, calibration WP76 encore à améliorer.
