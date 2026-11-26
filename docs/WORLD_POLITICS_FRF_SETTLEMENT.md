# WP54r — Règlement des créances souveraines en monnaie native

Le module `FrancDebtSettlement` est un pilote déterministe EUR/FRF, distinct du registre de référence EUR. Il règle le principal et les intérêts dans leur monnaie contractuelle, avec arriérés conservés, reçus par créancier et conservation des stocks monétaires. Les EUR du Trésor sont utilisés avant les réserves officielles ; leur achat nécessite une autorisation explicite et un transfert de FRF au compte officiel, au cours courant arrondi au centime supérieur. Le plancher officiel est opposable. Un moratoire suspend les paiements sans effacer les dettes.

Hypothèses de simulation : priorité aux intérêts puis au principal, et ordre lexical des identifiants au sein de chaque catégorie. Ce n'est pas une règle de droit ou une priorité de marché calibrée. Les données de test sont fictives. Le module accepte exclusivement EUR et FRF ; aucun dollar ou franc suisse n'est assimilé au FRF.

Le résultat contient les comptes et créances de clôture à reporter, les arriérés et les flux de règlement. Le prochain appel doit ajouter uniquement les nouveaux coupons/échéances. Aucune création monétaire ni conversion forcée n'est exécutée.

**Limites :** ce pilote ne modifie pas encore le Trésor national de référence, n'est pas encore connecté au journal de réserves FX ni à la persistance et n'intègre ni avances de banque centrale, ni financement de marché natif. Il ne faut pas le comptabiliser en parallèle comme une seconde dette nationale. L'adaptateur de cohorte, l'autorisation persistée, les cotations journalières et la validation transactionnelle devront précéder l'activation dans la boucle nationale.

Validation : `php tests/wp54r-frf-settlement.php` (intégré à World Politics headless backend).
