# World Politics — audit transversal des données et registre de collecte

Date : 28 novembre 2026. SHA audité : b16e3c86482dd9d21dcf4e9cad723721233c3a97. Branche World Politics uniquement.

## Méthode et limites

Revue statique de l'arborescence (1 172 entrées), référentiels WP115/117/130, audits WP54r/WP53c, documents de qualification et classes institutionnelles/référentiels. La CI atteste du logiciel, pas de la causalité ni de la complétude. Ceci est un inventaire transversal, pas une vérification ligne à ligne de tous les PHP ni une seconde vérification de chaque source primaire. Les sources proposées sont des pistes à vérifier. Aucune admission de données ni modification des sauvegardes.

États distincts : absent du pack ; staging ; documentaire incomplet ; non admis au runtime ; paramètre fictif/non calibré ; futur non observable ; indisponible/non applicable. Une valeur nulle n'est jamais zéro. Les registres se recouvrent : leurs nombres ne s'additionnent pas pour estimer les données uniques.

## Registre exact par fichier

Voir [CSV](WORLD_POLITICS_DATA_GAPS_2026-11-28.csv) : 1458 lignes de suivi, avec recouvrements entre couches.

- domain_open: 924

- runtime_reference_null: 302

- staging_null: 7

- election_party_table_gap: 12

- global_runtime_reference_absent: 141

- national_rulebook_absent: 36

- programme_pending: 22

- person_programme_pending: 14

WP130 : 42 × 52 = 2 184 emplacements, 1 882 renseignés, 302 nuls, 86,17 %. WP115 v0.4 : 924 exigences ouvertes, dont 195 partiellement documentées et 729 non collectées/non validées dans ce millésime ancien ; les suppléments ultérieurs améliorent cette documentation. IPU : 114 tableaux sur 126 références, 12 sans tableau de partis (parfois non applicable). Le pack mondial gelé couvre 194 populations et 55 PIB des 195 États ONU/observateurs. 7 pays ont un rulebook partiel (FR DE ES GB IT JP US), 36 n'en ont pas. 32 entrées documentaires françaises ne sont pas 32 programmes complets.

## 19 indicateurs WP130 avec cellules nulles
| Indicateur | Code | Nombre | Pays ISO2 |
|---|---|---:|---|
| agriculture_value_added_share | NV.AGR.TOTL.ZS | 2 | CA, US |
| manufacturing_value_added_share | NV.IND.MANF.ZS | 3 | BG, CA, US |
| industry_value_added_share | NV.IND.TOTL.ZS | 2 | CA, US |
| service_value_added_share | NV.SRV.TOTL.ZS | 2 | CA, US |
| government_education_expenditure_share | SE.XPD.TOTL.GD.ZS | 37 | AT, AU, BE, BG, BR, CA, CH, CY, CZ, DE, DK, EE, ES, FI, GB, GR, HR, HU, IE, IN, IT, JP, KR, LT, LU, LV, MT, MX, NL, PL, PT, RO, SE, SI, SK, TR, US |
| external_debt_public_publicly_guaranteed | DT.DOD.DPPG.CD | 35 | AT, AU, BE, BG, CA, CH, CY, CZ, DE, DK, EE, ES, FI, GB, GR, HR, HU, IE, IT, JP, KR, LT, LU, LV, MT, NL, PL, PT, RO, RU, SA, SE, SI, SK, US |
| external_debt_service_public_publicly_guaranteed | DT.TDS.DPPG.CD | 33 | AT, AU, BE, CA, CH, CY, CZ, DE, DK, EE, ES, FI, GB, GR, HR, HU, IE, IT, JP, KR, LT, LU, LV, MT, NL, PL, PT, RO, SA, SE, SI, SK, US |
| domestic_credit_private_sector | FS.AST.PRVT.GD.ZS | 3 | CA, RU, SA |
| research_development_expenditure | GB.XPD.RSDV.GD.ZS | 4 | AU, ID, IN, ZA |
| central_government_debt | GC.DOD.TOTL.GD.ZS | 30 | AT, AU, BE, BG, CN, CY, CZ, DE, DK, EE, FI, GR, HR, ID, IE, IN, IT, JP, LT, LU, LV, MT, NL, PL, PT, RO, SA, SE, SI, SK |
| revenue_excluding_grants_central_government | GC.REV.XGRT.GD.ZS | 4 | AU, ID, IN, JP |
| tax_revenue_central_government | GC.TAX.TOTL.GD.ZS | 4 | AU, ID, IN, JP |
| interest_payments_central_government_share_revenue | GC.XPN.INTP.RV.ZS | 6 | AU, CN, ID, IN, JP, RU |
| expense_central_government | GC.XPN.TOTL.GD.ZS | 14 | AU, BE, CN, CZ, DE, HU, ID, IE, IN, JP, RO, SE, SK, ZA |
| total_natural_resource_rents | NY.GDP.TOTL.RT.ZS | 42 | AT, AU, BE, BG, BR, CA, CH, CN, CY, CZ, DE, DK, EE, ES, FI, GB, GR, HR, HU, ID, IE, IN, IT, JP, KR, LT, LU, LV, MT, MX, NL, PL, PT, RO, RU, SA, SE, SI, SK, TR, US, ZA |
| hospital_beds_density | SH.MED.BEDS.ZS | 25 | AT, AU, BR, CA, CY, CZ, FI, GB, GR, HU, IN, IT, JP, KR, LT, MT, MX, NL, PL, PT, RO, SE, SI, US, ZA |
| physicians_density | SH.MED.PHYS.ZS | 34 | AU, BE, BG, CH, CN, CY, CZ, DE, DK, EE, ES, FI, GR, HR, HU, IN, IT, JP, KR, LT, LU, LV, MT, MX, NL, PT, RO, RU, SE, SI, SK, TR, US, ZA |
| poverty_international_line | SI.POV.DDAY | 11 | AU, CA, CN, DE, GB, HU, JP, KR, NL, SA, ZA |
| gini_index | SI.POV.GINI | 11 | AU, CA, CN, DE, GB, HU, JP, KR, NL, SA, ZA |

## 22 exigences WP115 pour chacun des 42 pays étrangers
| Champ | Preuve attendue | Source candidate |
|---|---|---|
| constitutional_primary_text_verified | texte consolidé, articles, dates, exceptions, procédure de révision et validation juridique | CONSTITUTION_RESEARCH |
| executive_election_and_succession | modalités de scrutin, seuils, éligibilité, intérim, mandat, destitution | IDEA |
| legislative_election_algorithm | circonscriptions, sièges, seuils, méthode de répartition, bonus et exceptions | IPU_ELECTORAL |
| current_officeholders_and_government | gouvernement, chef effectif, ministères, entrée en fonction, sources officielles datées | CONSTITUTION_RESEARCH |
| party_and_coalition_register | partis, alliances, programmes primaires, idéologies sourcées, sièges | IDEA |
| election_results_historical | résultats certifiés, participation, voix, sièges, dates | IDEA |
| public_opinion_and_social_movements | sondages, grèves, manifestations, participation, séries datées; source ILO pour grèves seulement | ILO |
| government_debt_and_creditors | dette brute/nette, périmètre, créanciers, maturité, charges, devises | IMF_FISCAL |
| government_revenue_expenditure_balance | recettes, dépenses, solde primaire/global, budget national voté | IMF_FISCAL |
| national_tax_and_social_security | barèmes, assiettes, TVA, cotisations, pensions, prestations, éligibilité | OECD |
| household_disposable_income_wages | revenu disponible, salaires, déciles, niveau des prix, épargne | OECD |
| population_structure_and_migration | âge, sexe, fécondité, mortalité, migration, projections | UN_POP |
| labour_market_sectoral | emploi par secteur, activité, chômage par âge, salaires | ILO |
| defense_budget_and_service_law | dépense militaire publiée, loi de service national à vérifier séparément | SIPRI |
| bilateral_trade_and_energy | flux par partenaire, produits, dépendances énergie, unités et années | UN_COMTRADE |
| foreign_reserves_currency_policy | réserves, change, banque centrale, politique monétaire | IMF_API |
| education_health_housing_services | capacités, dépenses, couverture, prix et accès | WDI_API |
| international_treaty_obligations | adhésions et engagements juridiques, clauses de retrait, coûts; autres sources par traité | WTO |
| political_programme_costing | programmes historiques, chiffrage propre à chaque mesure, sources et élasticités | REPO_RULEBOOK |
| investiture_2027_opening_balance | état au démarrage 2027; projection spécifique à construire, pas une observation | IMF_WEO |
| corporate_and_industrial_structure | entreprises, secteurs, production, capacités, propriété | WDI_API |
| legal_emergency_and_security_powers | état d’urgence, pouvoirs civils, contrôle judiciaire et parlementaire | CONSTITUTION_RESEARCH |

## Cahier de collecte transversal — France, pays étrangers, acteurs non jouables

Les variables ci-dessous définissent la couverture à compléter, même si des observations partielles existent déjà. Décliner par pays applicable, secteur, période, unité, périmètre et source. P0 bloque la boucle jouable ou sa qualification ; P1 enrichit le réalisme ; P2 approfondit la recherche.
| ID | Lots historiques | Données à compléter / vérifier | Sources candidates | Priorité |
|---|---|---|---|---|
| D01 | WP01–05,31–33,61–65,118–119,127,130 | Constitutions consolidées, articles et exceptions, compétences, initiative, promulgation/veto, contrôle juridictionnel, révision, intérim, destitution, confiance/censure, dissolution, urgence, niveaux fédérés, jurisprudence ; chefs d'État/gouvernement, exécutifs collégiaux, ministres et mandats datés. | Journaux officiels, IDEA, IPU | P0 |
| D02 | WP19–28,38–45,61–65,127 | 577 députés et 348 sénateurs FR, occupants, groupes, circonscriptions, vacances, suppléants ; autres chambres, commissions, seuils de scrutin, séances, votes nominatifs, navette, élections anticipées et renouvellements, habilitations par mandat. | AN, Sénat, Légifrance, parlements nationaux | P0 |
| D03 | WP33,37,75,106–119,127–128 | Scrutins historiques par circonscription, liste et tour : inscrits, abstention, exprimés, blancs/nuls, voix, sièges, candidats, coalitions, algorithmes de répartition, éligibilité, mandat, dates légales. Résultats 2027 inconnus. | Autorités électorales, IDEA, IPU | P0 |
| D04 | WP34–37,75,106–119,128 | Partis, mouvements extraparlementaires, syndicats, patronat, associations, médias, ONG ; identifiants, statuts, dates, dirigeants publics, coalitions, scissions, correspondances académiques, programmes primaires. Party Facts/CNCCFP non exhaustifs ; catalogue Manifesto Project 5 330 manifestes, aucun import dans le supplément WP117. | Registres nationaux, CNCCFP, Party Facts, Manifesto Project | P0 |
| D05 | WP75,106–114,121,128 | Programmes 2027 complets et chiffrés : mesures, version, cible, assiette, bénéficiaires, coûts, financement, calendrier, autorisations, dépendances et impacts. Distinction 2022/2027. Quatre éditions provisoires incomplètes ; maintien de leur non-jouabilité. | Sites officiels, Conseil constitutionnel, chiffrages publics | P0 |
| D06 | WP35–37,53–54,128 | Sondages agrégés (méthode, date, échantillon, incertitude), confiance institutionnelle, participation, abstention, grèves par secteur/jours perdus, manifestations agrégées, négociation sociale, élasticités et délais. Aucune loyauté personnelle inventée. | Commission des sondages, Dares, ILO, ESS | P1 |
| D07 | WP12–16,29,46–48,71,81–82,99,129–130 | PIB nominal/réel, déflateur, RNB, consommation, investissement, dépenses, import/export, stocks, tableaux ressources-emplois et entrées-sorties, comptes de secteurs, balance des paiements ; séries trimestrielles/annuelles et révisions. | Insee, Eurostat, OCDE, WDI, FMI | P0 |
| D08 | WP46–49,66–71,81–82,99,129–130 | Budgets votés/exécutés par mission, AE/CP, solde primaire/global, recettes fiscales/non fiscales, transferts, dépenses obligatoires/discrétionnaires, comptes État/APU/local/social, échéances. Fiscal Monitor 378 valeurs staging : 126 estimations, 252 projections ; ne pas substituer au périmètre WDI administration centrale. | Lois de finances, Trésor, Eurostat EDP/GFS, FMI | P0 |
| D09 | WP49–50,54r,66–69,129–130 | Dette brute/nette, instruments/ISIN publics, devise/droit, échéancier principal/coupons, taux fixe/variable/indexé, maturités, porteurs par secteur/pays, garanties et hors bilan, financement ; créanciers nominatifs uniquement lorsque publiquement établis, actifs sous juridiction, distinction dépositaire/bénéficiaire. | AFT, BdF, BCE, BIS, FMI CPIS, agences nationales | P0 |
| D10 | WP46–48,53–54,74–76,83–85,114,129 | Barèmes IR/IS, assiettes, crédits, TVA locale/fédérale, accises, taxes patrimoine/production, cotisations et plafonds, exonérations ; pensions, chômage, famille, logement, santé, minima, indexation, éligibilité, non-recours ; coût par catégorie et décile. | Administrations fiscales, OCDE TaxBEN, MISSOC, DREES | P0 |
| D11 | WP47–48,74–85,120,122,129–131 | RDB nominal/réel par UC, déciles, salaires nets/bruts par secteur, prestations, impôts directs, patrimoine, déflateur, panier par décile, loyers, énergie, alimentation, épargne, dette, composition ménages ; historique hors échantillon. France : six annuels 2020–25, quatre trimestres dans le pack actuel. | Insee, EU-SILC, enquêtes budget, LIS/OCDE | P0 |
| D12 | WP47,51,53,84,114,129–130 | Population âge simple, sexe, région, ménages, naissances/décès, fécondité, mortalité, migrations, actifs/inactifs, projections/cohortes ; emploi/chômage BIT, participation, heures, vacance, salaires, productivité et compétences par secteur/âge/région. | Insee, ONU WPP, ILOSTAT, Eurostat | P1 |
| D13 | WP52,74,114,121,129–130 | Santé : lits, personnels, recours, attente, coûts, couverture ; éducation : élèves, enseignants, classes, diplômes, formations, dépenses ; logement : stock, vacance, permis, chantiers, prix/loyers, HLM, rénovation ; transports, eau, énergie, justice, services par territoire. | DREES, DEPP, SDES, OMS, UNESCO, offices nationaux | P1 |
| D14 | WP53–54r,114,129–130 | Entreprises stratégiques/CAC40 : comptes consolidés et filiales, chiffre d'affaires, valeur ajoutée, emplois, actifs localisés, dettes, actionnariat, valorisations, compensation légale, capacité, investissement, délais, dépréciation, sous-traitants, énergie, intrants, délocalisations et élasticités. | Rapports annuels, AMF, registres, Insee Ésane | P1 |
| D15 | WP12–16,54r,65–69,130 | Commerce bilatéral directionnel par produit/secteur, biens/services, volumes/prix/devises, droits et préférences, sanctions et exceptions, intrants, énergie, substitutions et transports ; quatre partenaires FR dans le pack mondial ; autres couples et chaînes de valeur. | UN Comtrade, BACI, WTO, Comext, OCDE TiVA, IEA | P0 |
| D16 | WP50,54r,129–130 | Change, inflation sous-jacente, taux directeurs, courbes de taux, masse monétaire, bilans banques/banque centrale, fonds propres, dépôts/crédits, réserves par devise, exposition, maturité, garanties et liquidité ; FRF adossé à production : hypothèses de conversion et transmission, pas d'observation future. | BdF, BCE, BIS, FMI IFS/COFER, superviseurs | P0 |
| D17 | WP54r,55–69,129–130 | UE/euro : contribution nette, dépenses par pays/programme, engagements, actifs/passifs, commerce intra/extra, expositions bancaires, capital BCE, réserves, devises/droit des contrats, règles de retrait, accords transitoires, propagation contrefactuelle. | EUR-Lex, Commission, BCE, ESM, Eurostat, BIS | P0 |
| D18 | WP06–11,15–18,54r,65–69,130 | Adhésions, membres, organes/votes/veto/quorum, chartes, obligations par traité, retraits/délais, contributions, engagements financiers, sanctions, alliances, positions diplomatiques publiques, dates et paramètres de réactions. | Dépositaires, ONU, UE, OTAN, OMC, OMS | P0 |
| D19 | WP54r,66–72,130 | Ukraine : engagements/décaissements/garanties/prêts/dons, UE vs bilatéral, aide en nature, échéances, clauses de révocation ; Russie : commerce, énergie, prix, sanctions et accords ; IL/IR/PS : données diplomatiques, économiques, humanitaires agrégées non jouables. | Trésor, MEAE, Sénat, UE, Kiel, ONU | P1 |
| D20 | WP54,70,72,84,130 | Défense et sécurité civile : crédits votés/exécutés, pensions, effectifs agrégés, règles recrutement/service national, coût/délai agrégé de formation, secours et continuité, contrôle civil et juridique, statistiques agrégées des crises ; aucun détail sensible ou loyauté individuelle inventée. | Budgets, lois, SIPRI, autorités publiques | P1 |
| D21 | WP77–80,102–105,116,120,122,131 | Score : panier/UC comparable par pays, réel/nominal, temps au pouvoir, crédit préprésidentiel, cohorte, dates, incertitude, baseline de reprise, validation 365 jours/pluriannuelle, biais par rôle et pays. | Comptes ménages, benchmarks, audit score | P0 |
| D22 | WP115,126–134 | Ouverture 14 mai 2027 : projection datée de PIB/dette/trésorerie/population/prix/emploi/budget/mandats/alliances/obligations ; distinguer faits, hypothèses, incertitudes ; futurs résultats non observables, mises à jour non rétroactives. | Sources nationales et prévisions séparées | P0 |
| D23 | WP121–125,132–134 | Recherche : séries calibration/holdout indépendantes par pays et modèle, métadonnées, erreurs, sensibilités, domaines de validité, coefficients justifiés, chocs versionnés, conditions de réutilisation ; preuves charge/restauration/sécurité distinctes. | Données publiques, littérature scientifique, benchmarks | P1/P2 |

## Clôture

Priorité : règles nationales, élections, comptes fiscaux, dette ; puis ménages, fiscalité, monnaie, commerce et engagements ; programmes complets, ouverture 2027 et score ; services, entreprises, démographie, défense et calibration. Chaque ligne exige source primaire datée, couverture, unités/périmètre, distinction observation/estimation/projection, cohérence, non-applicabilité justifiée, revue et mapping vers le modèle avec tests si active. Les données non publiques restent inconnues. Aucune promesse d'exhaustivité absolue avant validation détaillée de chaque pays.
