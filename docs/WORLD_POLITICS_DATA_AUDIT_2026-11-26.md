# WP54r — audit de provenance et d'utilisation (26 novembre 2026)

Une donnée publiée n'est pas automatiquement une donnée active. Les sources sont datées, les valeurs manquantes restent inconnues et les paramètres de simulation sont distingués des observations.

| Domaine | État vérifié dans le code | Limite restante |
| --- | --- | --- |
| Pays ONU / acteurs spécifiques | 193 États membres, 2 observateurs et autorité palestinienne distincte | Définition de scénario, pas reconnaissance automatique |
| Population / PIB mondial | 194 populations et 55 PIB WDI, millésime 2024, 257 observations au total avec commerce | 140 PIB et population du Saint-Siège manquants ; dynamique des économies étrangères à étendre |
| France PIB, finances publiques | FrenchMacroBaseline / MacroFiscalLoop : 2 991,1 Md EUR PIB 2025, déficit 152,5 Md EUR, exportations 1 009,4 et importations 1 023,8 Md EUR | Paramètres comportementaux non étalonnés intégralement |
| Dette | SovereignDebtLedger, 3 595,5 Md EUR T2 2026, répartition agrégée, hypothèses de créanciers | Identités individuelles et encours exacts par pays non établis |
| Banques, réserves / FRF | FrancObservedFinance, FrancCommittedBudget : données ACPR/BdF avec activation explicite du SHA de référence ; FRF et émission adossée à la production | Modèle contrefactuel, pas une prévision ; calibrage longitudinal |
| Démographie / services | FrenchDemography, FrenchPublicServices, observations Insee/DREES chargées par les scénarios concernés | Régions et coefficients de réaction |
| Relations France-Russie | 1,89 Md EUR exportations et 4,1 Md EUR importations de biens 2025 effectivement présentes dans le registre et utilisables par WorldEconomicSimulation | Traités, gaz, substitution sectorielle et prix spécifiques non achevés |
| Commerce | 4 partenaires observés (DE/CN/US/RU), feedback pondéré par le volume annuel français total biens/services ; dédoublonnage par partenaire | Pas de matrice mondiale exhaustive ; pondération indicative, pas perte directe de PIB |
| UE, euro | Simulation de retrait, conversion, finances, chocs et reports | Contrefactuels non observables, calibration et droit complet |
| Constitution française | Modèles de procédure et de responsabilité ; référence des groupes parlementaires publiée séparément | Les groupes observés ne sont pas injectés dans des sauvegardes antérieures ; votes simulés |
| Organisations internationales | Références UE/OTAN, BRICS et effectifs OMS/OMC ; moteur générique | Les chartes réelles ne sont pas toutes exécutables |
| Élections, dirigeants étrangers | Modèles de scénario et identités | Résultats électoraux, mandats et composition à compléter ; pas de fausse observation |
| CAC 40 | 40 identités datées, mécanismes de comportement et d'acquisition | Paramètres, valorisations et actifs individuels non étayés ; pas de fausse précision |
| Défense / conscription | Référence de budget public 2026 et proposition de crédits défense hors pensions | Ne pas confondre PLF, LFI, exécution, effectifs ou capacité opérationnelle |
| Mobilisation et crise civile | Moteur de scénarios | Paramètres de réaction fictifs non validés empiriquement |

## Vérification complémentaire
- Insee comptes nationaux annuels 2025 : https://www.insee.fr/fr/statistiques/8988793 ; déficit et dette dans les packs datés déjà intégrés.
- Direction générale du Trésor, commerce bilatéral russe 2025 : https://www.tresor.economie.gouv.fr/Pays/RU/les-relations-commerciales-bilaterales-franco-russes-juin-2018
- Assemblée nationale, effectifs consultés le 26 novembre 2026 : 574 sièges pourvus, 3 vacants, 577 statutaires. Les majorités arithmétiques de 288 et 289 sont distinctes ; le seuil juridique dépend du scrutin.
- Sénat, scrutin du 24 novembre 2026 : 348 sièges répartis selon le relevé des groupes.
- Budget de l'État, LFI 2026 publiée le 27 février : recettes nettes 325,382 Md EUR, charges nettes 458,859 Md EUR, solde -134,627 Md EUR (périmètre budgétaire État, pas administrations publiques consolidées). Le PLF de défense 57,1 Md EUR hors pensions n'est pas une dépense exécutée.
- Les sources, dates, classifications et écarts figurent dans le rapport API en lecture seule. L'audit du dépôt ne prétend pas avoir vérifié chaque valeur de chaque fichier contre son fichier primaire.

## Non-régression
Tests : import et restitution du référentiel mondial, réconciliation des totaux parlementaires, dénominateur commercial, calcul réel de projections Russie/Allemagne à choc égal, dédoublonnage, conservation des anciennes règles pour les fixtures. Les CI ne certifient ni l'exhaustivité des sources ni une prédiction économique.
