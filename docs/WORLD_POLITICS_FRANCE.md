# World Politics — Constitution française, crise gouvernementale et réactions sociales

**État : prototype de logique métier WP19–WP21, sans interface graphique.**
**Portée : règles françaises de l'article 49, alinéa 3, et de l'article 50. Il ne s'agit pas encore d'un moteur exhaustif du droit constitutionnel français.**

## 1. Sources juridiques contrôlables

- Constitution du 4 octobre 1958 : https://www.legifrance.gouv.fr/loda/id/JORFTEXT000000571356/
- Article 49, texte et limitations depuis la réforme constitutionnelle de 2008 : https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000019241062/
- Article 50, démission du Gouvernement après censure : https://www.legifrance.gouv.fr/loda/article_lc/LEGIARTI000006527530/
- Fiche de l'Assemblée nationale sur la procédure législative et le 49.3 : https://www.assemblee-nationale.fr/dyn/synthese/fonctionnement-assemblee-nationale/travail-legislatif/la-procedure-legislative
- Rapport parlementaire expliquant la répétition du 49.3 sur un même texte à différents stades de la navette : https://www.assemblee-nationale.fr/dyn/docs/RAPPANR5L16B2407.raw
- Droit de grève et restrictions de service public : Conseil constitutionnel, décision 87-230 DC : https://www.legifrance.gouv.fr/cons/id/CONSTEXT000017667541

Ces liens servent de références aux règles ; ils ne valident pas les coefficients des réactions sociales. Version de travail au 24 novembre 2026. Toute modification législative ou constitutionnelle exigera une revue du modèle.

## 2. Mécanique constitutionnelle WP19

### 2.1 L'engagement de responsabilité sur un texte

- Il appartient au **Premier ministre**, et requiert une délibération préalable du Conseil des ministres. Le client ne transmet ni preuve d'habilitation ni décision ministérielle : ces éléments sont vérifiés par une autorité de confiance injectée côté serveur.
- La classe du projet (loi de finances, loi de financement de la sécurité sociale, autre projet ou proposition de loi) est certifiée côté serveur. Un joueur ne peut pas déguiser un texte ordinaire en projet financier.
- Pour un projet financier ou de financement de la sécurité sociale, il n'existe pas dans l'article 49.3 le plafond d'un autre texte par session.
- Pour les textes ordinaires, **un seul projet ou proposition** est autorisé par session parlementaire. Le même projet peut toutefois être examiné et soumis au 49.3 à plusieurs lectures : ce n'est pas un seul engagement valable une seule fois.
- Une nouvelle session ouvre de nouveau ce quota. L'ouverture de session est une décision du calendrier parlementaire autorisé, pas une déclaration libre du joueur.

### 2.2 Motion de censure consécutive au 49.3

- Une motion peut être déposée dans les **24 heures** suivant l'engagement.
- Les signataires vérifiés doivent être au moins un dixième des membres de l'Assemblée. Dans la configuration de test à 577 membres, le seuil vaut 58 ; cette valeur doit être dérivée du nombre de membres du scénario.
- Le vote ne peut pas se tenir moins de **48 heures** après le dépôt de la motion.
- Seuls les suffrages favorables à la motion sont pris en compte et la majorité exigée est celle des membres composant l'Assemblée, non la majorité des seuls votants. Pour 577 membres, le seuil est 289.
- Si aucune motion n'est déposée à temps ou si toutes sont rejetées, le texte est **considéré comme adopté par l'Assemblée nationale** ; ce n'est pas la promulgation de la loi ni l'achèvement de toute la navette parlementaire.
- Si la motion est adoptée, le Gouvernement doit remettre sa démission au président de la République en application de l'article 50. Le moteur utilise l'état `resignation_required`, **pas** `republic_overthrown`.

Le Règlement de l'Assemblée impose également une contrainte de calendrier de discussion tenant compte des **jours de séance** (en principe au plus tard le troisième jour de séance après l'expiration des 48 heures). Cette échéance n'est pas encore implémentée : le prototype garantit le délai minimal de 48 heures, mais pas encore toute la procédure parlementaire.

### 2.3 Ce que WP19 ne prend pas encore en charge

Les articles 49 alinéas 1 et 2, 50-1, 12, 16, 38, 40, 44, 45, 47, 47-1, 61, 61-1, 68, 89 ; les votes par député, les groupes parlementaires et leurs disciplines ; la composition des assemblées ; le calendrier des jours de séance ; les amendements et différentes versions des textes ; le contrôle du Conseil constitutionnel ; les relations entre Gouvernement, Parlement, président et collectivités territoriales.

Il faudra donc une constitution française organisée en **règles sourcées et versionnées**, des organes distincts, des pouvoirs autorisés et des événements contrôlés, et non une série de bonus ou pénalités numériques.

## 3. Réactions sociales WP20

`FrenchSocialDynamics.php` accepte entre 2 et 32 groupes professionnels distincts. Chaque groupe dispose d'un poids démographique explicite, d'un grief de départ, d'une capacité d'organisation collective, d'une dépendance économique, de contraintes de mobilité et d'une sensibilité à la désorganisation du travail. La somme des poids vaut exactement 100 %.

Lors d'un engagement de responsabilité réellement enregistré par le module constitutionnel, la simulation peut calculer séparément :

- le mécontentement de chaque groupe au regard des effets de la réforme, de l'exposition et des concessions ;
- la propension à la **manifestation pacifique visible** ;
- les indices de retrait du travail, de ralentissement et de rupture de services ;
- les pertes de production possibles, présentées comme **intervalles hypothétiques** et non comme des euros ou points de PIB vérifiés ;
- les tensions sur la majorité et la cohésion gouvernementale, et un indice plus large de tensions institutionnelles.

Les **concessions peuvent être sectorielles**. Une négociation avec les soignants peut modifier les conséquences dans la santé sans résoudre nécessairement la situation dans l'industrie, l'enseignement ou les services. Une même réforme peut même générer des appréciations positives pour certains groupes et négatives pour d'autres.

La diminution de l'espace civique n'est pas une solution automatique au mécontentement : le prototype différencie manifestations visibles et contestation sociale moins visible. Les coefficients sont **des hypothèses de conception non étalonnées**, et ne représentent pas des sondages français ni des prédictions de la réaction de syndicats déterminés.

### Le droit de grève n'est pas un interrupteur gouvernemental

En France, le droit de grève a valeur constitutionnelle, sous les conditions fixées par le législateur. Des limitations peuvent exister selon les fonctions et pour assurer la continuité de certains services essentiels. Une interdiction générale par une simple action politique ne doit donc pas être affichée comme une décision constitutionnellement valable.

Le futur moteur devra différencier exercice licite du droit de grève, restrictions spécifiques, désobéissance civile, ralentissement de production et dommages illégaux. L'utilisateur ne doit pas recevoir de procédures de sabotage ou de coercition réelle : ce sont des catégories de conséquences abstraites, pas des tactiques opérationnelles.

## 4. Survie du gouvernement, stabilité du régime, police et armée

Il faut distinguer cinq mécanismes : (1) survie d'un cabinet à une motion ; (2) perte de majorité et démission ; (3) crise de légitimité institutionnelle ; (4) continuité ou désorganisation de l'appareil administratif et de la production ; (5) changement de régime exceptionnel, qui exige un module historique et constitutionnel distinct.

Le moteur social dispose actuellement de paramètres agrégés de cohésion institutionnelle et de confiance dans les forces de sécurité et l'armée. **Ce ne sont pas des variables tactiques de maintien de l'ordre.** Une forte cohésion de l'État ne constitue ni une garantie d'immunité aux crises, ni une justification de répression ; une faible cohésion ne déclenche pas mécaniquement une révolution.

Ni des manifestations importantes ni un score social ne peuvent renverser directement un régime. Le prototype produit uniquement des indicateurs de tensions et de perturbations. Une future simulation de transition politique devra prendre en compte, avec incertitude, les institutions, les choix des élites, les forces de sécurité, les dynamiques constitutionnelles, l'économie et les possibilités de sortie de crise.

## 5. Back-end REST et MariaDB (WP21)

- `src/FrenchConstitutionalCrisis.php` : machine d'état juridique déterministe.
- `src/FrenchSocialDynamics.php` : modèle social par secteurs, avec preuves de l'événement 49.3 source.
- `database/009_fr_constitutional_49_3.sql` : états, journal append-only et reçus d'idempotence MariaDB.
- `src/PdoFrenchConstitutionalStore.php` : verrouillage transactionnel, authentification, contrôle des échéances, protection des snapshots.
- `src/FrenchConstitutionalApi.php` : adaptateur REST versionné pour mobile, web et bureau, non exposé publiquement.
- `tests/wp19-france-49-3.php`, `tests/wp20-france-social.php`, `tests/wp21-france-constitutional-rest-db.php` : tests du droit, du comportement social et du stockage.

Les opérations du client ne peuvent pas attribuer au Parlement un nombre de signatures ou un résultat de scrutin. La date et la catégorie du texte sont vérifiées par un service fiable. Les requêtes de modification requièrent `Idempotency-Key` et `If-Match`.

Le simulateur social reste pour le moment un **moteur de domaine en mémoire** et ses prévisions de scénarios ne sont pas persistées dans une base partagée. Son raccordement au calendrier WP18 et au journal global figure dans les prochaines étapes ; il est interdit d'affirmer que ces intégrations sont achevées.

## 6. Priorités pour la suite

1. Ajouter les procédures de confiance de l'article 49.1 et de la motion spontanée de l'article 49.2 avec le registre de députés et leurs quotas de signature.
2. Construire le vrai calendrier parlementaire des jours de séance, la navette, l'amendement, le Conseil constitutionnel et les délais financiers.
3. Synchroniser l'horloge française constitutionnelle **avec** WP18, sans franchissement de délai arbitraire ni décision anticipée.
4. Persister les scénarios sociaux avec leurs hypothèses, leurs versions et leur chronologie.
5. Calibrer les réactions par données historiques françaises agrégées et études publiques, sans prétendre prédire les comportements individuels.
6. Distinguer juridiquement changements de gouvernement, élections, dissolution, transition institutionnelle et changements de régime sans automatisme.

**Toutes ces évolutions restent sur la branche indépendante World Politics. Aucune fusion dans main d'Aeternum Engine. Aucune interface graphique.**

## WP22 — article 49, alinéa 1, et censure spontanée 49, alinéa 2

Le moteur `FrenchConstitutionalCrisis` a été étendu **dans le même agrégat de responsabilité gouvernementale** : les décisions 49.1, 49.2 et 49.3 ne créent plus trois gouvernements indépendants.

**49.1 — Question de confiance.** Après délibération authentifiée du Conseil des ministres et habilitation du Premier ministre, un programme ou une déclaration de politique générale est soumis à l'Assemblée. Le résultat est obtenu sur les **suffrages exprimés** : le soutien doit être strictement supérieur à l'opposition, les abstentions ne sont pas comptées parmi les suffrages exprimés. Le rejet oblige le Gouvernement à remettre sa démission en vertu de l'article 50. Le régime politique ne s'effondre pas mécaniquement.

**49.2 — Censure spontanée.** La motion émane des députés, sans engagement préalable sur un projet législatif ; elle nécessite au moins un dixième des membres de l'Assemblée parmi des **signataires individuellement identifiés et authentifiés côté serveur**. Aucun vote n'a lieu avant l'écoulement de 48 heures. Le succès requiert la majorité absolue des membres composant l'Assemblée, et seuls les votes favorables à la censure sont décomptés. Une motion adoptée entraîne la démission du Gouvernement, sans changement de régime automatique.

**Quotas par député.** Un député peut signer jusqu'à trois motions spontanées dans une session ordinaire et une dans une session extraordinaire. Les motions consécutives à un 49.3 ne sont pas comprises dans ces quotas. Les compteurs sont conservés dans l'instantané et réinitialisés à l'ouverture d'une session autorisée. Une motion non encore votée empêche une clôture de session qui en effacerait silencieusement l'existence.

Sources : Constitution de 1958 (Légifrance, article 49) https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000019241062/ ; Assemblée nationale, fiche de synthèse n°64 https://www.assemblee-nationale.fr/dyn/synthese/fonctionnement-assemblee-nationale/evaluation-politiques-publiques-controle-gouvernement/la-mise-en-cause-de-la-responsabilite-du-gouvernement ; règlement de l'Assemblée nationale https://www.assemblee-nationale.fr/dyn/17/divers/texte_reference/02_reglement_assemblee_nationale.pdf.

**Limite de droit parlementaire :** la date maximale de discussion des motions (troisième jour de séance après l'expiration des 48 heures), la gestion réelle du calendrier des séances, les groupes politiques, les délégations et les évolutions des effectifs sont encore à développer. Les décomptes par député reposent sur des identifiants vérifiés injectés par un service externe, qui devra être raccordé à un registre électoral historique.

**Migration :** l'agrégat porte désormais `VERSION=wp22-fr-constitutional-responsibility-1`. Les anciens instantanés WP19–WP21 ne sont pas convertis automatiquement et doivent bénéficier d'une migration auditée, ou d'une réinitialisation volontaire d'un scénario de test. Le moteur refuse délibérément un instantané versionné non compatible.

## WP23 — REST et journalisation des nouveaux actes de responsabilité

Les opérations 49.1 et 49.2 passent désormais par `PdoFrenchConstitutionalStore.php` et `FrenchConstitutionalApi.php`, en utilisant les mêmes verrous InnoDB, reçus d'idempotence, contrôles de délibération, habilitations et vérifications d'horloge que pour le 49.3. Aucune liste de signatures ni aucun nombre de votes ne peuvent être fournis par le client.

Routes additionnelles :

- `POST /api/v1/worlds/{world}/france/constitution/confidence/engagements` : annonce du programme ou de la déclaration de politique générale.
- `POST /api/v1/worlds/{world}/france/constitution/confidence/{statementId}/votes` : vote de confiance certifié.
- `POST /api/v1/worlds/{world}/france/constitution/spontaneous-motions` : dépôt de motion spontanée avec signatures serveur.
- `POST /api/v1/worlds/{world}/france/constitution/spontaneous-motions/{motionId}/votes` : vote de censure à la majorité absolue.

Tests : `tests/wp22-france-49-1-49-2.php` et `tests/wp23-france-accountability-rest-db.php`.

## WP24 — rapports sociaux persistants à hypothèses immuables

`FrenchSocialDynamics.php` possède une restauration d'instantané cohérente ; `PdoFrenchSocialStore.php` et `FrenchSocialApi.php` conservent maintenant les évaluations sociales avec une empreinte de leur source constitutionnelle, une empreinte des paramètres du scénario, un journal de révisions MariaDB et des reçus d'idempotence.

Le catalogue des **paramètres socioprofessionnels est approuvé côté serveur à la création du monde**, jamais recalculé librement à partir des déclarations d'un joueur. Le client transmet uniquement le `bill_id` d'un texte dont l'usage du 49.3 a déjà été enregistré dans le monde. Les résultats sont des indices heuristiques et intervalles fictifs, pas des sondages historiques ni des prédictions de PIB.

Routes :

- `GET /api/v1/worlds/{world}/france/social` : lire les cohortes et rapports persistants.
- `GET /api/v1/worlds/{world}/france/social/assessments/{billId}` : consulter un rapport lié à une décision constitutionnelle datée.
- `POST /api/v1/worlds/{world}/france/social/assessments` : déclencher une évaluation sur une hypothèse déjà validée, avec `Idempotency-Key` et `If-Match`.

Migration : `database/010_fr_social_scenarios.sql`, après `009_fr_constitutional_49_3.sql`. Tests MariaDB : `tests/wp24-france-social-rest-db.php`.

Le stockage social et le stockage constitutionnel restent **deux agrégats transactionnels distincts** ; l'empreinte du document légal source garantit la traçabilité mais pas une transaction globale atomique. La prochaine étape nécessitera un calendrier constitutionnel intégré à WP18 et une orchestration de simulation cohérente pour les décisions publiques et leurs effets sociaux.

## Ce qui ne peut toujours pas être revendiqué

Les coefficients sociaux ne sont pas calibrés empiriquement ; les changements de gouvernement ne valent pas changements de régime ; les mécanismes relatifs à la police et aux armées restent des indicateurs institutionnels abstraits, sans plan de répression ni simulation opérationnelle ; aucun moteur HTTP public n'est déployé ; aucun module graphique n'a été introduit.


## WP25 — calendrier français synchronisé avec les décisions mondiales

Le coordinateur mondial dispose désormais d'une étape française optionnelle et **non contournable une fois inscrite dans le monde**, distincte de l'institution générale française. Son calcul utilise les fuseaux horaires réels `Europe/Paris`. Un événement constitutionnel 49.3 enregistré en France est donc mis à jour lorsqu'un jour est définitivement achevé, sans présumer d'une motion adoptée et sans changement de régime automatique.

Les preuves d'avancement et les reçus restent persistants, la récupération après interruption est testée et les droits d'accès sont revérifiés. L'absence du calendrier de séance réel impose cependant de considérer cette mécanique comme **provisoire** pour toute simulation des délais de censure.

Pour les autres étapes nécessaires, consulter `docs/WORLD_POLITICS_BACKEND_REMAINING.md` : France parlementaire complète, 27 pays de l'UE et Suisse, statistiques, IA, économie et véritable serveur REST.


## WP26 — calendrier des jours de séance de l'Assemblée nationale

**Le module d'échéances parlementaires est désormais codé, persisté et vérifié par tests**, indépendamment des autres règles de l'article 49.

Le droit applicable impose :
- une attente constitutionnelle de **48 heures consécutives** après le dépôt de la motion de censure avant le vote (Constitution, article 49) ;
- une **discussion au plus tard le troisième jour de séance suivant l'expiration** des 48 heures (Règlement de l'Assemblée nationale, article 154), et non le troisième jour calendaire ;
- la date de discussion doit être fixée par la Conférence des présidents de l'Assemblée.

Sources : https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000019241062/ et https://www.assemblee-nationale.fr/dyn/17/divers/texte_reference/02_reglement_assemblee_nationale.html .

Le nouveau modèle `FrenchSittingCalendar.php` exige une **liste explicite et datée des jours de séance**, un statut de provenance (`verified_official` ou `fictional_scenario`) et une URL de source. Il ne suppose pas que tous les jours ouvrés sont des séances officielles. Une motion déposée peut recevoir un créneau précis de vote, vérifié par rapport à sa date de dépôt et à la troisième séance suivant l'expiration des 48 heures. Ni un jour hors calendrier ni une programmation après le terme réglementaire ne sont acceptés.

`PdoFrenchSittingCalendarStore.php` consigne ce calendrier et les décisions de programmation en MariaDB avec empreintes SHA-256, journal et identité de l'autorité parlementaire. La décision de créneau est fournie par une autorité de simulation vérifiée côté serveur, **jamais par un champ de date choisi par le joueur**.

`PdoFrenchConstitutionalStore.php` accepte une dépendance optionnelle à ce calendrier. **Lorsqu'un monde l'active, son obligation est inscrite dans `wp_fr_constitutional_states.sitting_calendar_required`**. Après reconnexion, un service sans calendrier ne peut donc pas faire croire que les délais ne sont plus applicables. Les votes des motions 49.2 et 49.3 doivent correspondre à leur créneau approuvé. Une tentative de faire avancer le temps au-delà de ce créneau avant le vote est refusée : **aucune censure fictive automatique, ni adoption de loi fictive en présence d'une motion pendante**.

`FrenchSittingCalendarApi.php` offre uniquement des routes REST de lecture :
- `GET /api/v1/worlds/{world}/france/calendar`
- `GET /api/v1/worlds/{world}/france/calendar/motions/{motionId}`

Les programmations sont pour le moment des commandes internes de service authentifié, sans route client autorisant l'invention de séances.

Migration `database/012_fr_sitting_calendar.sql` : ajoute, lorsqu'il manque, le marqueur obligatoire sur le registre constitutionnel et crée les tables du calendrier et du journal. Elle doit être appliquée **après 009** ; les anciennes parties existantes ne sont pas automatiquement remplies de jours de séance.

Tests :
- `tests/wp26-fr-sitting-calendar.php` — délai constitutionnel, troisième jour de séance, week-end sans séance, date trop tardive, interdiction de sauter le vote et intégrité.
- `tests/wp26-fr-sitting-calendar-db.php` — calendrier épinglé, calendrier omis au redémarrage, programmation certifiée, refus de votes hors créneau, reprise MariaDB, révocation, détection de corruption.
- `tests/wp25-fr-parliament-world-clock-db.php` — horloge multinationale fonctionnant avec le calendrier de séance français épinglé.

**Limites restantes :** les jours de séance réels n'ont pas encore été importés depuis une source officielle vérifiée ; les jeux de tests utilisent un calendrier fictif explicitement étiqueté. Les débats et scrutins ne sont pas encore planifiés automatiquement par l'IA parlementaire ; ils sont déclenchés par une autorité vérifiée. Le modèle actuel ne représente pas encore toutes les subtilités de clôture ou suspension des sessions, séances exceptionnelles, ordres du jour et amendements. Les scénarios anciens n'ayant pas opté pour ce calendrier restent en mode constitutionnel partiel et doivent être migrés expressément.

## WP28 — votes nominatifs et sortie constitutionnelle d'une crise gouvernementale

Après WP26, les délais de censure et les séances étaient pris en compte, mais un greffier vérifié devait encore fournir un nombre global de suffrages. Avec WP28, la confiance, les motions spontanées et la procédure du 49.3 peuvent toutes être décidées à partir de **577 voix individuelles, contrôlées et persistantes**. La formation de coalitions change le rapport de forces sans rendre les votes des députés automatiquement uniformes.

L'installation d'un registre parlementaire enregistre `parliament_required=1` dans le monde constitutionnel ; un ancien service qui voudrait injecter arbitrairement un total de voix est alors refusé. Les élus contrôlés par un joueur sont soumis à une vérification nominative, et les votes IA sont produits selon des paramètres fictifs, documentés et déterministes. Chaque résultat est lié à l'empreinte de l'état constitutionnel qui l'a autorisé.

Après un refus de confiance ou une censure, l'article 50 entraîne la démission gouvernementale, **pas la disparition de la République**. Le jeu exige ensuite une remise de démission vérifiée et un décret authentifié de nomination d'un nouveau Premier ministre sous l'article 8, sans inventer d'investiture obligatoire. La liste des autres membres du Gouvernement reste à modéliser.

Sources : Constitution, articles 8, 49 et 50 sur Légifrance, et fiches de l'Assemblée nationale sur les votes et les groupes. Détails, nouvelles routes et migrations : `docs/WORLD_POLITICS_PARLIAMENT.md`.
