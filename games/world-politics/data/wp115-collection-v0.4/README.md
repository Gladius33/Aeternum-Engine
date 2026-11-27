# WP115 — documentation et limites

Archive de collecte hors dépôt, au 27 novembre 2026. Aucun changement GitHub. Périmètre: 42 cibles humaines hors France, conformément au registre de la branche World Politics. France reste la référence existante; les acteurs IA seuls ne sont pas le périmètre de cette collecte. L'archive ne certifie pas que les pays sont déjà jouables.

## Exploitation
- `countries/XX.json` : une fiche par pays, avec identité, institutions (brouillon), chambre(s), renouvellement, séries chiffrées, champs manquants.
- `data/` : tables JSON et CSV UTF-8, clé ISO2. JSON fait foi pour distinguer null et valeur. Les lignes CSV vides correspondent aux nulls JSON.
- `numeric_observations` : WDI 2024 issu du dépôt et FMI 2025 (historique/estimations). `numeric_projections` : prévisions FMI 2026–2027 et valeurs absentes; aucune valeur d'ouverture au 14 mai 2027 n'est déduite automatiquement.
- `numeric_all` : union des deux, ne pas additionner les trois fichiers lors d'un import.
- `documentation/collection_requests.csv` : pistes de téléchargement ciblées; pas des données collectées. Vérifier disponibilité, codes, version, dates et licences; éviter d'écraser une édition figée avec une API mobile.

## Raccordement ultérieur (aucun code produit)
1. Joindre ISO2 au registre effectif des acteurs du monde, sans déduire un identifiant interne de l'ISO. Éviter les doublons avec la France et les observations existantes.
2. Pour GDP/pop, le registre EconomicReferenceRegistry attend actor_id, partner_id=null, metric, value, unit, year, kind=observed, quality=published, source_url, license. Vérifier les droits et la provenance; les arrondis de publication doivent rester documentés. Ne pas réimporter si le même enregistrement est déjà présent.
3. Les taux signés et projections ne correspondent pas au contrat numérique actuel (seuls GDP, population, budget, commerce, entiers non négatifs). Ils nécessitent un futur contrat dédié ou une transformation contrôlée. Ne pas convertir une prévision en observation.
4. Les fiches institutionnelles ne doivent jamais autoriser une action à elles seules. Vérifier chaque article sur source officielle consolidée, date d'entrée en vigueur, exceptions, régimes transitoires. InstitutionalRulebook échoue de façon fermée pour les modules absents. Les dates électorales attendues ne prouvent ni tenue du scrutin ni résultat. Préserver la précision mensuelle.
5. Les sièges sont des références comparatives IPU, pas des sièges occupés ou par parti. Cas à vérifier: AU et CA évolutions de circonscription; GB Lords effectif fluctuant sans plafond fixe; CY 80 inclut sièges réservés; IN 545 ne signifie pas 545 sièges élus; IT sénateurs à vie; MT correctifs; ID DPD distinct de DPR; RU représentation fédérale et territoires; SA conseil consultatif; CH présidence annuelle distincte du Conseil fédéral. La famille électorale simplifiée ne suffit pas à calculer des résultats.
6. WDI 2024: population arrondie au millier, PIB au million USD; CY périmètre contrôlé par le gouvernement pour PIB, RU réserves territoriales du fournisseur. Le millésime 2025-12-15 n'est pas une nouvelle extraction. WEO octobre 2026: 2025 historique ou estimé, 2026–27 projections annuelles; vérifier notes pays, années budgétaires (notamment Inde), définitions de chômage et arrondis. Les 2 valeurs manquantes SA ne sont pas zéro.
7. Licences : archive de travail privée; WDI sous CC BY 4.0 avec conditions spécifiques; droits commerciaux du FMI, IPU, textes et traductions à vérifier avant intégration/diffusion. Aucun droit nouveau n'est accordé par ce paquet.

## Ce qui n'a pas été collecté de façon suffisante
Résultats électoraux par parti, gouvernements et titulaires actuels, manifestes primaires, lois consolidées article par article, barèmes fiscaux, dette/créanciers, budgets complets, revenus des ménages, indicateurs sociaux détaillés, commerce bilatéral, défense, engagements internationaux et projections d'ouverture spécifiques. Ces sujets sont listés pour chaque pays dans `gaps`. Les accès aux API en téléchargement massif n'ont pas abouti dans l'environnement de création de l'archive. Les liens candidats ne constituent pas une preuve de collecte. La comparaison juridique est une synthèse de travail, non une certification et non une implémentation. La spécification WP115 détaillée n'a pas été retrouvée dans la documentation consultée; le périmètre a été déduit des modules WP114 et du registre existant.


## v0.3 — collecte web complémentaire

Voir `documentation/SUPPLEMENT_V03.md`. 705 valeurs numériques supplémentaires non nulles, 42 profils TVA et 42 calendriers électoraux de référence. Collecte encore partielle; ne pas importer automatiquement.


## v0.4
Voir `documentation/SUPPLEMENT_V04.md`. Cette version comprend une serie fiscale additionnelle de 378 valeurs; la branche GitHub a recu le lot fiscal de preparation seulement. Les instructions historiques de la v0.3 ne decrivent pas les nouvelles ecritures.
