# M07 — Client web Vue/TypeScript, accessibilité et i18n

## Périmètre livré dans la branche de qualification

L'application de navigation Vue 3 (`client/src/main.ts`) est construite avec TypeScript en strict, le verrou NPM `client/package-lock.json`, et esbuild. Le shell suit les changements d'authentification, monde/compte, jeu, langue et connectivité. Il affiche des indicateurs de travail et de reprise d'une action ambiguë, puis un libellé de confirmation **uniquement après réponse acceptée par le serveur**.

Les formulaires de production, transferts, marché, constructions et campagnes médiévale/industrielle utilisent encore `public/play.js` : c'est un adaptateur de compatibilité conservé intentionnellement pour maintenir tous les invariants transactionnels et les tests E2E hérités. Cette livraison est un **client hybride Vue/TypeScript + adaptateur JavaScript** et non une réécriture totale. L'ensemble des parcours doit néanmoins être utilisable dans les deux langues, au clavier, sur ordinateur et sur mobile avant d'accepter la macro.

Le catalogue d'interface est local, sans injection HTML ni traduction des identifiants de joueur. Les champs de compte, mots de passe, jetons, noms de monde et charges utiles API ne sont jamais modifiés au basculement de langue. L'anglais reste la valeur par défaut pour la rétrocompatibilité et le français est sélectionnable sans rechargement.

## Construction reproductible

```sh
cd client
npm ci --no-audit --no-fund
npm run build
npm test
```

Le build vérifie les types puis produit `public/client-dist/aeternum-shell.js` ; seul ce bundle statique et les fichiers nécessaires de `public` sont destinés à l'hébergement. Ne jamais publier `node_modules`, les tests, des secrets ou des dossiers internes. Les workflows `.github/workflows/ci.yml` et `browser.yml` exécutent respectivement compilation/tests unitaires et compilation/Chromium avec la même version verrouillée.

## Matrice de réception M07

| Critère | Preuve automatisée à contrôler sur le SHA exact |
| --- | --- |
| Build Vue/TypeScript, dépendances figées | Job PHP core : `npm ci`, `npm run build`, `npm test` |
| Compatibilité PHP 8.4, MariaDB, migrations, restauration, isolation, concurrence et rejeu | Jobs `core` et `mariadb` ; tests d'intégration existants |
| Navigation clavier, lien d'évitement, focus et largeur 375 px | `tests/browser/locale.spec.cjs` |
| Français/anglais, textes dynamiques et préservation des champs/identifiants | `tests/browser/locale.spec.cjs`, `client/tests/catalogues.test.cjs` |
| Production, transferts, marché, reconnexion et autorisations | `tests/browser/economy.spec.cjs`, `tls.spec.cjs`, `registry.spec.cjs` |
| Parcours médiéval mobile/ordinateur jusqu'à la victoire | `tests/browser/medieval.spec.cjs` |
| Parcours industriel mobile/ordinateur jusqu'à la victoire | `tests/browser/industrial.spec.cjs` |
| Commandes, annulation, reprise et confirmation sans écriture optimiste | `tests/browser/construction.spec.cjs`, `economy.spec.cjs` et les deux campagnes |

**Aucune de ces lignes n'est réputée validée avant que les CI associées au SHA final de PR soient complètement vertes.** L'application ne doit pas être présentée comme une V1.0.

## Sécurité, compatibilité et restauration

La navigation est dans le domaine de confiance du navigateur ; l'API PHP reste seule responsable des contrôles d'identité, de monde, d'acteur et d'idempotence. Les chaînes dynamiques affichées sont affectées via `textContent` ou les fonctions de rendu de Vue, jamais via un `innerHTML` provenant de l'API. Les sessions restent en mémoire, sont effacées à la déconnexion et ne sont pas mises en cache. La reprise conserve l'identifiant de la commande initiale : elle n'invente pas une deuxième transaction.

M07 **ne contient aucune migration SQL**. Avant tout remplacement des fichiers sur un hôte approuvé, sauvegarder le répertoire public ; en cas de défaut, restaurer le HTML, les JS, CSS et le bundle de la version précédente ensemble. Les données MariaDB ne sont pas modifiées par le build. La validation effective d'un environnement O2Switch/VPS ainsi que les procédures d'exploitation et de restauration relèvent des jalons ultérieurs et ne sont pas revendiquées ici.
