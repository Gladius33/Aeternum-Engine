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
