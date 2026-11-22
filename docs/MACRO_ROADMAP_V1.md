# Aeternum Engine — V1.0 : 13 macro-itérations (M01–M13)

**Base vérifiée :** `main` au commit `c711a6efc4a9b8672b2d25c5661ec56e4011bf4a`, après l'itération historique 0039. **Périmètre normatif :** les neuf jalons G01–G09 de [ROADMAP.md](../ROADMAP.md) ; aucun n'est déclaré intégralement validé à cette date. Ce plan regroupe le travail restant sans supprimer les exigences initiales.

## Mode d'exécution demandé par le propriétaire

1. **Travail concentré sur une grande livraison fonctionnelle.** Une macro-itération n'est **pas** une micro-PR. Chaque exécution doit exploiter au maximum sa fenêtre d'outillage disponible ; **objectif d'effort : jusqu'à 40–45 minutes par exécution si la plateforme le permet**, sans prétendre pouvoir garantir cette durée. Pendant les CI : poursuivre l'examen du code, anticiper les corrections et préparer les tests suivants, sans sommeil artificiel obligatoire.
2. **Le compteur est fonctionnel, pas temporel.** M01 reste M01 tant que son périmètre complet n'est pas livré, même si plusieurs sessions horaires, commits, correctifs ou PR sont nécessaires. Regrouper systématiquement les changements API, persistance, UI, migrations, tests et documentation d'un même parcours en une macro. Ne pas compter les correctifs séparément.
3. **Progresser réellement à chaque exécution.** Examiner `main`, PR et CI, les fichiers courants et les écarts du présent plan ; reprendre la PR ouverte avant toute nouvelle branche ; réaliser le maximum de développement vérifiable. Dans une même session, accumuler plusieurs commits cohérents, lancer les vérifications disponibles et corriger les échecs.
4. **Règle de fusion.** CI PHP 8.4, MariaDB et Chromium pour le SHA exact, tests négatifs, d'autorisation, d'idempotence et de concurrence pertinents ; ne pas fusionner en présence de contrôle requis rouge, incomplet ou de conflit. Les CI de `main` après fusion restent à surveiller. Les tests locaux ne remplacent pas les CI.
5. **Définition de terminé.** Code intégré sur `main`, intégration complète fonctionnelle, migration et compatibilité vérifiées, tests E2E correspondants verts, documentation et risques résiduels explicités. Une PR ouverte ou un mock ne termine pas une macro.
6. **Contraintes constantes.** Conserver ACCL-1.0, le propriétaire et l'attribution, aucune modification de licence sans accord. Aucune dépense, paiement réel ni déploiement public sans accord explicite. Aucun secret en dépôt, aucun contournement de l'authentification/CI. Priorité aux invariants transactionnels, à l'isolation intermondes et inter-acteurs et aux anciens mondes épinglés.
7. **Traitement d'un blocage.** Si une sous-tâche est plus complexe, elle reste dans la macro active ; ne pas créer une nouvelle itération pour masquer un retard. Une macro nécessite potentiellement plusieurs fenêtres horaires. **Treize macros ne signifient pas treize exécutions ni une promesse de finir la V1.0 en 10 heures.**
8. **Suivi.** À chaque exécution : identifier la macro active, son avancement **par critères réellement acquis**, les SHA/PR/CI, la prochaine sous-tâche concrète. Ne passer à la suivante qu'après preuve de tous les critères de sortie.

## Les 13 macro-itérations restantes

### M01 — Inscription publique, identité et intégration des joueurs (G05, G08)
- Inscription sécurisée, politique mots de passe et erreurs non révélatrices, quota anti-abus, validation/activation adaptée à l'environnement ; modèle de compte et flux de récupération testables, sans envoyer de vrais messages hors environnement approuvé.
- Provisionnement transactionnel/idempotent du joueur et de son compte dans un monde configuré ; reprise après interruption, limitation des inscriptions, prévention du multi-compte abusif et retrait de l'obligation du CLI opérateur pour le parcours normal.
- Interface minimale d'inscription/connexion/choix du monde, API autorisée, tests de concurrence, doublons, refus, activation, relance et E2E nouveau joueur.
- **Sortie :** un utilisateur de test extérieur au CLI peut créer son compte, se connecter, entrer dans un monde, produire et revenir après reconnexion ; aucun accès à un autre compte.

### M02 — Registre de packs, SDK et règles versionnées (G02, G04)
- Retirer la liste en dur de deux jeux des routes et du provisionnement ; registre de packs et mondes approuvés, validation stricte, configuration sans modifier le contrôleur.
- SDK et schémas de composants/actions, hooks d'événements typés, version des règles et protocole de mise à niveau des mondes/compatibilité du rejeu.
- Exemples, documentation développeur, packaging, test d'un **troisième** pack non livré comme jeu.
- **Sortie :** ajout d'un troisième pack test et création de monde par configuration, sans éditer le front controller ; ancien monde épinglé reste valide après évolution du code.

### M03 — Persistance relationnelle extensible, marché sans plafond de vie (G01, G02, G03, G06)
- Remplacer le stockage mondial scalaire des comptes/ressources/offres là où il impose des limites de référence ou une contention excessive ; tables, index, verrous ciblés et pagination.
- Migration contrôlée des états existants, compatibilité des reçus idempotents, écriture atomique du grand livre, règles de replay et double comptage.
- Offres expirables et annulables avec libération de l'escrow même lorsque le stock a atteint sa capacité, invariants économiques explicités ; concurrence à plusieurs acteurs.
- **Sortie :** plus de plafond de 1 000 comptes ou de 1 000 offres *à vie* dans le parcours public ; preuve de migration de monde non vide, conservation, contention et retour arrière documenté.

### M04 — Monde stratégique : géographie, composants et actions génériques (G02, G03)
- Topologie, secteurs/lieux/territoires, relations d'appartenance, types d'entités, bâtiments et composants évolutifs sous contrôle des règles.
- Actions multi-ressources, constructions et améliorations planifiées, coûts, prérequis, annulation et échecs, événements et files de travail, intégration au moteur temporel.
- API, projections privées/publiques et interface de démonstration, autorisation, tests de rejeu, simulation accélérée et simultanéité.
- **Sortie :** deux joueurs construisent et développent des entités sur un monde structuré, sans accès croisé ni contradiction après rejeu.

### M05 — Jeu de référence médiéval réellement jouable (G03, G04)
- Progression médiévale cohérente : colonies, bâtiments, population ou main-d'œuvre, chaîne ressources, construction, territoire, interactions et objectifs.
- Configuration majoritairement déclarative sur le SDK, équilibrage et tutoriel, tests de gameplay et cycle de reconnexion.
- **Sortie :** parcours de jeu médiéval complet du démarrage à une condition d'objectif vérifiable ; aucune obligation de manipuler les API ou le CLI.

### M06 — Jeu de référence industriel réellement distinct (G03, G04)
- Réseau industriel avec mécaniques *différentes* du médiéval : production/énergie/logistique, chaînes, contraintes de capacité, évolution et objectifs.
- Réutilisation du moteur générique et du SDK, non copie de code dédiée ; scénarios de parties concurrentes.
- **Sortie :** parcours complet industriel, mécanismes différenciés, E2E comparatifs des deux jeux sur le même moteur et tests de séparation.

### M07 — Client web de production, accessibilité et i18n (G05)
- Application Vue/TypeScript avec compilation reproductible et intégration au backend, navigation joueur, écrans d'actions, états de chargement, reconnexion, erreurs, confirmations et aide.
- Français/anglais avec chaînes externalisées, clavier, labels, focus, contraste, responsive, tests de composants et Playwright.
- Adapter les workflows existants (production, marché, transferts, constructions, deux jeux) sans perdre les preuves navigateur.
- **Sortie :** parcours principal de chacun des jeux utilisable sur ordinateur et mobile, clavier et deux langues, CI de build et E2E verts.

### M08 — Commerce multijoueur complet (G03, G06)
- Passage du troc de référence à un marché viable : recherche, pagination, offres expirantes, exécution atomique, annulation sûre, historique et visibilité des transactions.
- Limites anti-abus, garanties de restitution, compteur de capacités, traitement des courses et migrations ; garde-fous de concurrence et charge.
- **Sortie :** deux joueurs distincts échangent sur la durée avec annulation, expiration, tentatives simultanées et reprise après incident sans duplication ni ressources perdues.

### M09 — Social, alliances, classement, notifications et modération (G06)
- Organisations/alliances et rôles, invitations et révocations, messagerie autorisée/modérée, blocage et signalement, audit opérateur.
- Classements reproductibles, notifications persistantes et mécanismes de lecture/accusé, tests d'isolation et d'abus.
- **Sortie :** parcours E2E de plusieurs acteurs avec rôles distincts, notifications et contrôle de modération ; statistiques et classements cohérents après reprise.

### M10 — Administration, premium et paiement simulé (G05, G07)
- Console opérateur/admin cloisonnée, gestion mondes/règles/identités et journal des interventions, contrôles des permissions et procédures documentées.
- Entitlements premium optionnels, idempotence et révocation, événements de paiement **en mode test uniquement**, absence d'avantage non acheté ; affichage et attribution ACCL-1.0.
- **Sortie :** droits premium simulés, rejets et rejouages de notifications testés, aucune transaction réelle ; les administrateurs ne peuvent franchir les frontières prévues.

### M11 — Exploitation, observabilité et restauration (G08)
- Installation et mises à jour MariaDB contrôlées, sauvegardes cohérentes/restauration répétée, tests de crash/roulement d'horloge, permissions minimales.
- Worker/CRON, rétention, logs structurés, métriques, alertes, commandes de diagnostic, secrets et configuration robuste.
- Guides et procédures distinctes mutualisé compatible O2Switch et VPS ; empaquetage déployable sans dépendance au navigateur de CI.
- **Sortie :** installation depuis zéro, mise à niveau sur un monde existant et restauration documentées **et exécutées en labo**, avec preuves conservées.

### M12 — Sécurité, résilience et capacité (G01–G09)
- Audit défensif auth/permissions/CSRF/CSP/sessions, API, composants, contenus de chat et marché ; tests de pénétration autorisés en labo.
- Inventaire des dépendances, alertes de CVE, couverture des invariants, fuzz/property/concurrency/replay, scénarios de panne, tests de charge reproductibles et seuils d'acceptation explicites.
- Correction des défauts détectés, mécanismes de dégradation sûre et documentation des limites.
- **Sortie :** aucune vulnérabilité critique/haute connue non traitée, résultats et limites de charge publiés, CI complète verte sur versions supportées.

### M13 — Qualification de release, documentation et V1.0 (G01–G09)
- Revue croisée des critères G01–G09, nettoyage documentaire, API/SDK et guide utilisateur, matrice de recette, journal de versions et compatibilité.
- Installation/upgrade/backup/restore de recette, tests de deux parties complètes avec plusieurs joueurs, smoke de déploiement en environnement **autorisé**.
- Tag/release V1.0 uniquement après preuve de satisfaction de tous les critères ; sinon bilan de blocage sans proclamation de disponibilité.
- **Sortie :** neuf jalons officiellement validés avec liens vers tests/preuves et release V1.0 ; aucune publication externe sans autorisation.

## Dépendances et ordre

```
0039 (base) -> M01 (onboarding) -> M02 (SDK)
                                   -> M03 (persistance) -> M04 (monde/stratégie)
                                                        -> M05 (médiéval)
                                                        -> M06 (industriel)
                                                        -> M07 (interface)
                                                        -> M08 (marché)
                                                        -> M09 (social)
                                                        -> M10 (admin/premium)
                                                        -> M11 (exploitation)
                                                        -> M12 (sécurité/charge)
                                                        -> M13 (release)
```

Ce graphe fixe un ordre de livraison et **non** un ordre exclusif de préparation : tests, documentation et corrections critiques sont continus. Le périmètre réel est important ; si la livraison complète M01–M13 dépasse 13 fenêtres de 40–45 minutes, il faut continuer la macro active, pas revendiquer une release prématurée.

## État de suivi initial

| Macro | Statut | Preuve de sortie |
|---|---|---|
| M01 | **Prochaine** | Non commencée |
| M02–M13 | Non commencées | Aucune |

La tâche récurrente doit utiliser ce fichier comme **plan de travail prioritaire**, tout en vérifiant la roadmap historique pour n'omettre aucun critère G01–G09.
