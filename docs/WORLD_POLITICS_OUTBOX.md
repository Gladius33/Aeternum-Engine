# World Politics — Transmission fiable diplomatie → économie (WP16)

**Module propre au jeu, exclusivement côté back-end.** Ne jamais le fusionner dans main d'Aeternum Engine.

## Pourquoi une outbox transactionnelle ?

Une proposition de sanctions enregistrée dans la diplomatie doit pouvoir ouvrir un dossier économique sans être perdue après un redémarrage et sans créer deux fois la même mesure.
Or la diplomatie et l'économie possèdent chacune leur propre agrégat de monde et leur journal InnoDB. Il serait trompeur de promettre une transaction distribuée unique sur les deux systèmes.

La stratégie WP16 repose donc sur :

1. Une insertion dans wp_diplomatic_outbox effectuée dans la **même transaction MariaDB** que le journal, le reçu et l'état diplomatiques. Seules les véritables propositions de sanctions sont inscrites.
2. Un service de confiance SanctionsOutboxDispatcher lisant l'enveloppe de la proposition et vérifiant son empreinte SHA, sa révision et son événement source dans le journal.
3. Un registre d'hypothèses économiques fourni explicitement au moment du traitement : amplitude, intervalle et date d'effet. Aucune estimation économique n'est déduite de l'identité d'un gouvernement réel.
4. Une proposition économique créée par PdoEconomicStore avec une **clé d'idempotence déterministe dérivée de l'événement source**.
5. Un acquittement de l'outbox après succès dans l'économie. Si le serveur tombe entre les étapes 4 et 5, le traitement suivant récupère le même reçu et ne recrée pas la politique.
6. Un contrôle permanent de la délégation du pays d'origine, y compris lorsqu'une commande a déjà été livrée.

Cette approche fournit une livraison **au moins une fois**, avec des mutations économiques dédupliquées et vérifiées. Elle n'est pas équivalente à une transaction distribuée globalement atomique.

## Fichiers

- games/world-politics/database/006_diplomatic_outbox.sql : table des événements à transmettre, index et clés étrangères vers état et journal diplomatiques.
- games/world-politics/src/PdoDiplomaticStore.php : publication de l'événement dans la transaction source.
- games/world-politics/src/SanctionsOutboxDispatcher.php : validation et livraison à l'économie, acquittement et récupération.
- games/world-politics/src/PdoEconomicStore.php : contrôle renforcé des délégations et reçu d'idempotence.
- tests/wp16-sanctions-outbox-db.php : tests d'intégration isolés sur MariaDB réelle.

## Conséquences économiques et limites juridiques

L'outbox ne signe aucun traité, ne crée aucune sanction applicable, ne débite aucun budget et n'altère aucune statistique historique. Elle crée seulement une proposition.
Le mécanisme économique national doit ensuite enregistrer une autorisation valide, propre au pays, puis attendre la date de prise d'effet. Les résultats sont des projections de scénario, jamais des prévisions vérifiées.

Le traitement WP16 reste appelé explicitement par un service de confiance ; **aucun ordonnanceur de production ni serveur HTTP n'a été déployé**.
Le processus d'extraction devra lui-même être conçu comme un agent multijoueur résilient, authentifié et soumis à des quotas, avec surveillance, relances et suivi des anomalies.

## Contraintes de déploiement

- Exécuter 003_diplomacy.sql, 005_economic_policies.sql puis 006_diplomatic_outbox.sql sur une base propre au jeu, après sauvegarde.
- Depuis WP16, le moteur diplomatique refuse de valider une nouvelle proposition de sanctions si la table outbox requise est absente (échec de la transaction source).
- Les connexions du dispatcher à la source diplomatique et au magasin économique doivent être gérées de manière indépendante par l'infrastructure.
- Les comptes de services doivent disposer des habilitations dédiées economy.bridge_dispatch, economy.read et economy.propose pour le pays concerné. Les États ai_only restent soumis à economy.ai_controller.
- Une connexion HTTPS ni un simple champ JSON ne constituent une authentification : l'API HTTP et la validation des identités restent à implémenter.
- Le modèle actuel verrouille encore une ligne par monde ; une conception plus distribuée sera nécessaire pour un grand nombre de joueurs.

## Tests de récupération et de sécurité

Le test WP16 vérifie : événement source et outbox atomiques ; absence d'outbox pour les dialogues ; refus intermondes ; refus de fausses hypothèses officielles ; transmission normale ; reprise après écriture économique non acquittée ; absence de doublon ; délégation révoquée ; autorisation nationale distincte ; effets différés ; rollback diplomatique complet si l'outbox n'est pas disponible.

**Aucune fusion dans le moteur générique Aeternum. Aucun élément graphique.**
