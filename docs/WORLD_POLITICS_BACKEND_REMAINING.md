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
