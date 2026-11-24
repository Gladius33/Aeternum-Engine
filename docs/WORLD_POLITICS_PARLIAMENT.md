# WP28 — Assemblée nationale : députés, oppositions, coalitions et votes nominatifs

**État : code PHP, MariaDB et contrats REST in-process, validés en CI.** Aucune interface graphique et aucun serveur HTTP public ne sont fournis. Le développement se trouve exclusivement sur la branche isolée World Politics, **pas** dans le tronc générique d'Aeternum Engine.

## 1. Références juridiques et précision sur les données

Le moteur distingue strictement les procédures :

- **Article 49, alinéa 1** : le Gouvernement engage sa responsabilité sur son programme ou une déclaration de politique générale. Il obtient la confiance quand les votes **pour** sont strictement supérieurs aux votes **contre** parmi les suffrages exprimés, sans compter les abstentions dans ces suffrages.
- **Article 49, alinéa 2** : motion de censure spontanée, recevabilité par un dixième des députés ; majorité absolue des membres composant l'Assemblée, seuls les suffrages **en faveur de la censure** étant comptabilisés pour son adoption.
- **Article 49, alinéa 3** : un texte admissible suit la procédure constitutionnelle et, si une motion est déposée dans les 24 heures, sa censure est décidée selon la même majorité absolue. Les votes individuels viennent du même registre parlementaire nominatif.
- **Article 50** : démission gouvernementale à remettre au Président après une censure adoptée ou un refus de confiance. Ni la chute de la Ve République, ni une révolution ne sont déduites de ce vote.
- **Article 8** : le Président nomme le Premier ministre. La succession nécessite un acte présidentiel distinct et une démission formellement présentée ; **aucun vote d'investiture obligatoire n'est inventé**.

Sources françaises :
- Constitution, article 49 : https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000019241062/
- Constitution, article 8 : https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000006527467/
- Constitution, article 50 : https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000006527530/
- Assemblée nationale, fiche sur les votes et majorités : https://www.assemblee-nationale.fr/dyn/synthese/fonctionnement-assemblee-nationale/travail-legislatif/les-votes-a-l-assemblee-nationale
- Règlement de l'Assemblée (art. 65 et suivants, motions de censure, décompte et sièges effectivement pourvus) : https://www.assemblee-nationale.fr/dyn/17/divers/texte_reference/02_reglement_assemblee_nationale.html
- Présentation institutionnelle des groupes politiques : https://www.assemblee-nationale.fr/dyn/les-groupes-politiques

**Provenance :** les 577 députés, coalitions, programmes et scénarios utilisés dans les tests sont entièrement **fictifs** et nommés comme tels. Ils servent à démontrer la mécanique et à tester les propriétés du système. Le modèle \`FrenchParliament\` distingue l'occupation réelle des sièges, leur nombre maximal (577), la source HTTPS, la date et le statut \`fictional_scenario\` ou \`verified_official\`. Aucun fichier de la CI ne prétend constituer un annuaire électoral officiel contemporain.

## 2. Nouvelle simulation politique par député

Le fichier \`src/FrenchParliament.php\` introduit :

- **députés individuellement identifiés**, siège numéroté 1 à 577, groupe, titulaire humain ou IA, propension de présence, loyauté et indépendance, sans jamais dupliquer les sièges ;
- **groupes politiques** avec orientation vis-à-vis du Gouvernement, positions par domaine, discipline et identité propre ;
- **coalitions explicites** avec vérification d'appartenance et interdiction d'adhésions concurrentes d'un même groupe à deux accords actifs ;
- **dossiers parlementaires** liés à une preuve SHA-256 de l'état constitutionnel avant le vote, une politique évaluée, des concessions aux différents groupes et une date/scénario historique explicite ;
- **votes nominatifs** : pour, contre, abstention, absence. Un joueur ne peut voter que pour le député réellement contrôlé et ne peut pas fabriquer les votes des IA ;
- **IA individuelle déterministe** : les choix sont influencés par le programme de groupe, les alliances, les préférences propres du député, la loyauté, la présence, les concessions particulières et le contexte fictif. Les tirages sont stables pour permettre une relecture reproductible ;
- **scrutins scellés**, exportables dans les instantanés, avec tous les résultats par député et les quatre décomptes distincts.

Le modèle ne traite jamais « opposition » comme une volonté de renverser automatiquement le régime. Une coalition peut changer après un vote et déplacer la majorité lors du vote suivant sans altérer le premier. **Chaque matière parlementaire fige sa coalition de référence**, précisément pour éviter de réécrire l'histoire en cas d'accord ultérieur.

Les paramètres de popularité, de discipline et d'orientation sont des **hypothèses de simulation**, pas des observations empiriques. Aucun pouvoir de maintien de l'ordre n'est automatisé à partir d'une censure.

## 3. Persistance et source des décisions constitutionnelles

La migration \`database/015_fr_parliament.sql\` crée :

- \`wp_fr_parliament_states\` — instantané canonique, empreinte et révision ;
- \`wp_fr_parliament_receipts\` — reçus d'idempotence séparés par monde/principal ;
- \`wp_fr_parliament_journal\` — journal append-only des commandes, versions, empreintes et source constitutionnelle du scrutin.

Le service \`PdoFrenchParliamentStore.php\` prend ses décisions dans une transaction InnoDB et passe par **la barrière globale WP27**. Les signatures, alliances et paramètres politiques proviennent de capacités authentifiées d'un service, et non de champs JSON inventés par un joueur.

Le service n'ouvre une matière 49(1), 49(2) ou 49(3) que si elle correspond à une procédure **réellement pendante dans le moteur constitutionnel**. Il vérifie le nombre des sièges pourvus par rapport à la constitution du scénario et conserve une empreinte de l'état d'origine.

\`VerifiedFrenchParliamentPrincipal.php\` remplace les totaux de votes artificiellement injectés dans le simulateur juridique par les **577 votes nominatifs enregistrés et vérifiés**.

**Verrouillage de compatibilité :** à l'installation de ce registre, \`wp_fr_constitutional_states.parliament_required\` devient vrai. L'ancien principal constitutionnel qui fournirait arbitrairement 577 « pour » ne peut plus exécuter de décisions dans ce monde. Les mondes anciens ne sont pas convertis silencieusement.

L'intégrité de l'état de la loi est vérifiée avant l'application des votes 49.1, 49.2 et 49.3. Un résultat parlementaire préparé sur un état désormais modifié doit être revu par l'autorité ; le système ne peut pas silencieusement réutiliser le même scrutin contre une autre version de la Constitution/scénario.

La transition présidentielle 8/50 vérifie séparément le procès-verbal de remise de démission et le décret de nomination du nouveau Premier ministre. Le nom de ce dernier vient du service authentifié, non d'un champ contrôlé par le client. Les autres nominations ministérielles restent à développer.

## 4. Contrats REST versionnés (aucun serveur public)

\`FrenchParliamentApi.php\` propose des réponses transport-neutres :

| Action | Route |
| --- | --- |
| Lire l'Assemblée | \`GET /api/v1/worlds/{world}/france/assembly\` |
| Lire une matière et son scrutin | \`GET /api/v1/worlds/{world}/france/assembly/matters/{matterId}\` |
| Ouvrir un dossier constitutionnel | \`POST /api/v1/worlds/{world}/france/assembly/matters\` |
| Voter comme député contrôlé | \`POST /api/v1/worlds/{world}/france/assembly/ballots\` |
| Certifier l'ensemble des voix | \`POST /api/v1/worlds/{world}/france/assembly/roll-calls\` |
| Enregistrer un accord de coalition | \`POST /api/v1/worlds/{world}/france/assembly/coalitions\` |
| Sceller les signataires | \`POST /api/v1/worlds/{world}/france/assembly/signatures\` |
| Nommer le Premier ministre après démission | \`POST /api/v1/worlds/{world}/france/constitution/presidency/prime-minister-appointments\` |

Les mutations nécessitent \`If-Match\`, \`Idempotency-Key\` et des rôles vérifiés. Ni listes de groupes arbitraires, ni totaux des voix, ni candidats à Matignon choisis via JSON ne sont acceptés par ces contrats.

Les routes d'administration et de certification sont **réservées à des principaux authentifiés côté serveur**, même lorsqu'elles sont décrites dans l'OpenAPI. Un futur serveur public devra imposer les permissions correspondantes sans exposer une identité de greffier ou de Président au navigateur.

## 5. Validation de WP28

- \`tests/wp28-french-parliament.php\` : unicité des députés/sièges ; 577 voix ; variations de coalition ; votes individuels ; quorum ; neutralisation des votes réécrits ; restauration et détection d'archives modifiées.
- \`tests/wp28-french-parliament-db.php\` : véritables transactions MariaDB, droit de contrôle du député, refus d'usurpation, révocation, reçus, preuve de source constitutionnelle, vote de confiance, censure spontanée et 49.3, démission et nomination d'un autre Premier ministre, refus d'une preuve stale ou d'un décompte inventé.
- CI \`headless-contract\` (PHP 8.4) et \`mariadb-rosters\` (MariaDB 11.4).

## 6. Ce qui reste clairement hors WP28

Ce jalon ne signifie **pas** un modèle constitutionnel complet de la France : la composition réelle à une date historique (groupes, circonscriptions, suppléants, vacances, apparentés) reste à importer et sourcer. L'IA n'a pas encore d'identité/personnalité complète, de stratégie pluriannuelle, de mémoire politique ou de négociations multi-tours. Les calendriers de séances, commissions, amendements, navette et promulgation restent à étendre.

La composition ministérielle complète, les propositions du Premier ministre, la vérification des décrets, les réorganisations après dissolution et les élections nationales restent à relier au registre des personnalités politiques.

La couche HTTP réelle, l'authentification joueur, les mondes hébergés, les rôles SQL, les tests de charge et la supervision de travailleurs sont toujours **des blocages de production**. Les agrégats parlementaire et constitutionnel conservent chacun leur transaction : les empreintes et la barrière mondiale empêchent des corruptions de source et de dates, mais ne constituent pas une transaction atomique globale contre tout écrivain SQL externe.

**Prochaine priorité recommandée : WP29, mise en œuvre d'une boucle de politique publique qui relie chronologiquement réforme, coalition, vote constitutionnel, mesures budgétaires, réactions par profession et leurs effets économiques.**
