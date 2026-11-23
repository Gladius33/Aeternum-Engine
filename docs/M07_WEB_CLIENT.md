# M07 — Client web Vue/TypeScript, accessibilité et i18n

## Architecture de transition

Le client de référence existant conserve les mêmes routes authentifiées, formulaires, reçus et commandes. Une enveloppe Vue 3 et TypeScript (`client/src/main.ts`) offre une navigation réactive et une aide accessibles tenant compte de la session, du jeu, de la langue et de la connexion. Aucun secret n'est stocké par Vue et aucune requête extérieure n'est effectuée.

La route reste `/play.html`, avec CSP `self` et ressources locales. Le bundle est compilé sous `public/client-dist/aeternum-shell.js`. Le gestionnaire historique `public/play.js` assure encore le traitement des API : cette première intégration Vue ne représente **pas** une réécriture complète et ne satisfait pas, seule, tous les critères de sortie M07.

## Compiler et tester

```sh
cd client
npm ci
npm run build
```

Le code TypeScript est vérifié puis Vue et le client sont assemblés en un script statique. Publier uniquement les ressources compilées du dossier `public`, jamais `node_modules`, les sources privées ni les tests.

## Invariants et recette

- Conserver PHP 8.4, MariaDB, isolation des comptes, autorisation transactionnelle, idempotence et rejeu ; Vue n'est pas une frontière de sécurité.
- Vérifier en Chromium les deux jeux, le marché, les transferts, la reconnexion, l'accessibilité clavier, le basculement français/anglais et un viewport mobile de 375 px.
- Aucune migration SQL n'est introduite ici. En cas de régression, restaurer `play.html`, scripts et CSS depuis la précédente version après sauvegarde du dossier public.
- Les CI exactes et la validation sur O2Switch/VPS restent des portes de sortie distinctes. Aucune publication externe n'est autorisée sans accord.
