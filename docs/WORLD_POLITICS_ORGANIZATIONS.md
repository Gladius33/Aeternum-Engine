# World Politics — Organisations internationales et alliances (WP09)

**Branche isolée. Ne jamais fusionner dans main d'Aeternum.**
**Back-end exclusivement. Aucune interface graphique.**

## Livré : moteur de procédure, sans charte réelle inventée

`src/OrganizationSimulation.php` est un agrégat PHP déterministe
d'adhésion et de retrait, destiné à être branché ultérieurement à
MariaDB et à la même API RESTful versionnée que la diplomatie.

Une organisation est définie par un document de scénario :

- identifiant et dénomination ;
- nature : `fictional_scenario` ou `curated_real_charter` ;
- sources HTTPS obligatoires pour toute organisation réelle ;
- règle de vote pour adhésion : unanimité ou majorité simple ;
- délai de formalisation ou ratification (jours) ;
- délai de préavis de retrait (jours) ;
- membres de départ, tous acteurs étatiques connus et distincts
  des autorités administratives.

Il ne faut **jamais** déduire les règles de l'OMS, l'OMC, l'ONU,
l'OTAN, des BRICS ou de l'Union européenne à partir de cet
exemple. Chaque organisation aura son régime juridique propre,
ses dates, ses conditions d'accès, ses actes constitutifs et
ses exceptions. Les régimes plus complexes (majorité qualifiée,
veto, statut associé/observateur, réserves, dénonciation, etc.)
sont **non implémentés** et doivent être modélisés séparément.

La présence de `state_ps` comme État observateur **n'implique aucune
éligibilité automatique** à toutes les organisations. Le statut
`authority_pa` reste une autorité administrative distincte,
pas un État membre par défaut.

## Transitions de l'agrégat

1. `apply` : demande de l'acteur étatique autorisé
   → `voting`.
2. `vote` : chaque membre électeur autorisé exprime sa voix
   → `voting`, `rejected` ou `approved_pending_formalization`.
3. `formalize` : uniquement par service institutionnel de confiance,
   après le délai prévu et les consentements requis
   → `member`.
4. `requestWithdrawal` : demande de retrait par l'État membre
   → `pending`.
5. `advanceTo` : horloge virtuelle autorisée
   → `effective` à la date prévue, sans effet rétroactif.

Un vote favorable ne crée pas immédiatement une alliance.
Un retrait annoncé ne supprime pas immédiatement l'adhésion
si un préavis existe.

Toutes les commandes utilisent une frontière d'autorisation
`DiplomaticPrincipal`. Les acteurs marqués `ai_only` doivent disposer
d'un principal de service autorisé ; un joueur humain ne peut pas
voter à la place de l'Iran ou d'Israël.

## Limites

- **Tests exclusivement avec une organisation fictive**, sans
  revendication d'exactitude juridique à propos d'organisations réelles.
- Pas encore de stockage persistant, de journal MariaDB ou d'API REST
  pour les adhésions. L'agrégat expose pour l'instant son état et
  son empreinte déterministe.
- Pas de sanctions économiques effectives, de traité automatique,
  d'adhésion d'une autorité administrative comme État, ni de
  commande militaire.
- Les règles d'admission des organisations supranationales complexes
  devront être correctement vérifiées et modélisées avant tout
  jeu de données historique.

## Tests

`php tests/wp09-organizations.php` couvre :
admission multilatérale, vote d'IA autorisée, interdiction
d'usurpation, protection du statut de l'Autorité palestinienne,
validation de provenance, ratification non instantanée,
préavis, horloge virtuelle et rejeu déterministe.

## Suite

WP10 devra définir les objets de référence
`OrganizationCharter`, `OrganizationMembership` et
`TreatyLifecycle` avec des sources, dates de validité,
statuts d'observateur, procédures d'approbation spécifiques
et validation juridique. Puis le moteur pourra être raccordé
au journal MariaDB et à l'API REST sans dépendre d'un client
graphique.
