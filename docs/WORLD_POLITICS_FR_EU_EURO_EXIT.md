# World Politics — étude préalable France : retrait de l'UE et transition monétaire

Date de référence : 25 novembre 2026. Document de conception et étude de
sensibilité, non prévision, non recommandation politique. Aucun départ
français contemporain de la zone euro n'est observé. Le Brexit n'est pas
une sortie monétaire : le Royaume-Uni utilisait déjà la livre sterling.
Les coefficients et durées ci-dessous sont des paramètres de simulation
à éprouver, jamais des observations ou des probabilités.

## Périmètre et voies juridiques
Deux horloges distinctes : délai entre notification et retrait juridique
de l'UE, puis préparation/transition monétaire et commerciale avant et
après la date de retrait. Article 50 TUE : notification selon les règles
constitutionnelles, négociation, accord de retrait ou expiration du délai
de deux ans sauf prolongation unanime. Date anticipée possible par accord,
pas unilatéralement dans la procédure ordinaire. Révocation avant retrait
selon les conditions Wightman. Ne pas confondre membre UE, marché unique,
union douanière, zone euro, utilisation de l'euro, Schengen, accès SEPA
et autres accords. Sortie de l'euro seule en restant membre : aucune
procédure ordinaire prévue ; voie négociée/modification des traités ou
hypothèse de rupture juridique explicitement signalée. Après retrait UE,
utilisation unilatérale de l'euro distincte de la participation aux
institutions de l'Eurosystème. Le moteur doit gérer aussi annulation,
accord non ratifié, prolongation et litiges.

## Plan factoriel
Préparation/transition opérationnelle 0, 3, 6, 12, 18, 24, 36, 48 mois
(indépendante des échéances de l'article 50). Les durées 0/3/6 ne
constituent pas un droit au retrait immédiat. Paramètres croisés, pas de
lien mécanique entre durée et bénéfice/coût :
- accès commercial : accord large, libre-échange partiel, règles OMC
  applicables en l'absence d'accord préférentiel ; tenir compte de services,
  règles d'origine, conformité, douane, TVA, accords tiers, mobilité ;
- monnaie : maintien d'usage euro, conversion avec parité initiale,
  flottement, arrangement de change négocié ; ne jamais supposer un accès
  garanti à la liquidité BCE ou à une contrepartie étrangère ;
- valeur de la monnaie face à l'euro après conversion : +10%, 0%, -10%,
  -20%, -30%, -40% (stress, non prédiction) ; vitesse indépendante ;
- surcoût d'importation, élasticités de commerce et substitution,
  part des intrants importés, délais de répercussion des prix ;
- part des dettes restant en euros : 0/5/10/25/50% par secteur et
  juridiction (hypothèses, non parts françaises observées) ;
- refinancement : choc de taux +0/+100/+300/+500 points de base,
  maturité instrument par instrument, liquidité, garanties, provisions,
  accès au crédit, solvabilité, réserves en devises ;
- scénario de confiance : stabilité, anticipation, report des projets,
  stress bancaire, reprise, chocs externes ; pas de probabilité assignée.
Comparer chacun à un scénario de maintien UE/euro avec les mêmes chocs
externes. Mesurer écarts à 1/3/6/12/24/36/60/120 mois. Tenir un journal
de chaque hypothèse et graine. Une longue préparation peut améliorer
la continuité mais prolonger l'incertitude, et une date de change unique
ne signifie pas que tous les prix ou contrats évoluent simultanément.

## Transmissions économiques
1. Change : une dépréciation d de la valeur externe de la monnaie
   implique un multiplicateur 1/(1-d) des engagements en euros, toutes
   choses égales. Un recul de 20% donne +25% sur le montant en monnaie
   locale. L'effet sur le panier de consommation dépend de la part
   importée, des intrants, des marges, de la concurrence et de la politique
   monétaire ; la transmission est variable et retardée (FMI 2023).
2. Dette : séparer loi applicable, monnaie de paiement, clauses,
   échéances, couverture, détenteur et actifs dans la même devise.
   Non-résident ne signifie pas dette en euros non convertible.
   Renégociation et contentieux distincts de défaut. OFCE 2016/2017 et
   étude sur entreprises françaises 2015 : risque de discordance
   actif-passif, surtout si les engagements restent dans une autre monnaie.
   Appliquer les taux nouveaux au refinancement, pas instantanément à
   tout l'encours existant. Maintenir une équation stock-flux.
3. Commerce : les exportations vers l'UE en 2025 étaient de 515,0 MdEUR,
   les importations de 521,9 MdEUR (Insee, données provisoires). Coûts
   de frontière, services, certification, chaînes de valeur ; la baisse
   du change peut favoriser la compétitivité-prix si coûts importés et
   obstacles commerciaux ne l'annulent pas. Courbe en J possible,
   non garantie. Une perte brute d'exportations n'est pas une perte
   équivalente de PIB ; tenir compte des importations et de la valeur ajoutée.
4. Banque : bilans par devise, actifs/passifs, risques de crédit, paiements,
   disponibilité de monnaie et continuité des services, résolution bancaire
   et coût budgétaire. Dépôts, contrats, salaires, prestations, loyers,
   prix et impôts possèdent des règles de conversion explicites. Ne pas
   supposer conversion universelle, disponibilité des fonds ou défaut certain.
5. Budget : contributions UE évitées moins dépenses remplacées (PAC,
   cohésion, recherche, etc.), engagements et règlement financier,
   recettes douanières, coûts de transition, stabilisateurs sociaux et
   intérêts. Séparer administration centrale, collectivités, sécurité
   sociale ; ne jamais compter deux fois les transferts. Importer les
   données Commission de recettes/dépenses par pays.
6. Réactions privées : investissement, consommation réelle, emploi,
   salaires, défaillances, immobilier, énergie, alimentation, santé,
   transports. Distribution des impacts par catégories de ménages et
   secteurs, et non uniquement une moyenne.
7. Moyens monétaires : taux, gestion de liquidité, réserves et crédibilité,
   contraintes budgétaires, transmission et inflation. Autonomie
   monétaire accrue ne signifie ni autonomie économique totale, ni
   financement sans coût. Toute mesure de transition financière doit
   rester abstraite, légale, et modélisée par ses effets agrégés.

## Calculs de sensibilité (pas des prévisions)
A parité initiale 1:1, import facturé 100 EUR : monnaie locale nécessaire
à -10/-20/-30% = 111,11 / 125 / 142,86 unités. A -20%, part de
l'exposition de dette publique non convertie supposée de 10% de
3 595,5 MdEUR (T2 2026), revalorisation mécanique en monnaie locale :
89,8875 milliards d'unités, avant couverture et autres flux.
A -20%, part directe importée supposée de 20% du panier, répercussion
supposée de 50% : contribution directe illustrative au niveau des prix
= 2,5%, non inflation totale, ni estimation de ces paramètres pour France.
Si volumes d'exportations UE baissent de 5/10/20% à prix 2025 inchangés :
25,75 / 51,5 / 103 MdEUR de ventes brutes en moins, non pertes de PIB.
Pour une tranche hypothétique de 300 MdEUR refinancée +200 bps :
6 MdEUR d'intérêt annuel supplémentaire une fois pleinement refinancée.
Les cas doivent également inclure appréciation de monnaie, effet change
faible, reprise des exportations et accord commercial robuste. Le modèle
ne doit jamais associer une orientation politique à un résultat imposé.

## Durées
0 mois : bascule opérationnelle en rupture, risques de discontinuité
élevés si aucune préparation ; juridiquement scénario distinct d'un
retrait convenu. 3 mois : adaptation accélérée, contrats et systèmes
incomplètement adaptés possibles. 6 mois : fenêtres d'adaptation pour
secteurs et accords provisoires, forte dépendance à la coopération.
12 mois : un exercice budgétaire, contrats et renouvellements en partie
adaptés, coût d'incertitude potentiellement prolongé. 18/24 mois : intégrer
le cycle article 50, plusieurs échéances financières et négociation
commerciale, sans supposer un accord. 36/48 mois : prolongations ou
transition négociées, davantage de cycles d'investissement, restructuration
et substitution possibles, coûts d'attente et d'incertitude toujours présents.

## Références
- Article 50 : https://eur-lex.europa.eu/legal-content/FR/ALL/?uri=celex%3A12012M050
- CJUE Wightman C-621/18 : https://eur-lex.europa.eu/legal-content/fr/ALL/?uri=CELEX%3A62018CJ0621
- Athanassiou, ECB Legal Working Paper 10 (2009), analyse juridique
  d'auteur et non décision contraignante : https://ideas.repec.org/p/ecb/ecblwp/200910.html
- Insee comptes nationaux : https://www.insee.fr/fr/statistiques/8988793
- Insee échanges géographiques : https://www.insee.fr/fr/statistiques/2381418
- Insee dette T2 2026 : https://www.insee.fr/fr/statistiques/9053525
- FMI, State-Dependent Exchange Rate Pass-Through, 2023 :
  https://www.elibrary.imf.org/view/journals/001/2023/086/article-A001-en.xml
- OFCE, Balance sheets after the EMU, 2016/2017 :
  https://ideas.repec.org/p/fce/doctra/1631.html
- Dette privée française, 2015 : https://ideas.repec.org/p/hal/wpaper/hal-01117019.html
- CEPII, Sortie de l'euro et compétitivité française, 2017 :
  https://www.economie-internationale.fr/BLOG/bi/post.asp?IDcommunique=508
- OBR Brexit analysis : https://obr.uk/forecasts-in-depth/the-economy-forecast/brexit-analysis/
  hypothèse britannique de long terme, non transposable directement.
- Commission européenne, dépenses et recettes UE :
  https://commission.europa.eu/strategy-and-policy/eu-budget/long-term-eu-budget/2021-2027/spending-and-revenue_en
- AFT, dette négociable et maturité : https://www.aft.gouv.fr/fr/principaux-chiffres-dette

## Critères d'acceptation
Avant activation : données datées, droits et unités vérifiés, modèle
multi-devises, bilan consolidé, horloge réversible avant sortie légale,
transitions négociées, états terminaux légaux, pas de perte ou création
artificielle d'actif au changement d'unité, tests de non double comptage,
sensibilité et stress sur 10 ans, comparaison contrefactuelle, absence de
biais par défaut. Ce document n'est pas le moteur livré.
