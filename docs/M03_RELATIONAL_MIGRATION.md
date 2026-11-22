# M03 — Migration de l'économie relationnelle

## Portée de cette contribution

La migration 020 introduit trois projections relationnelles InnoDB :
engine_economy_balances (ressources, niveau, échéance),
engine_market_offers (offres, expiration, acheteur, index de pagination) et
engine_market_refunds (remboursements à réclamer).

Ces tables sont une projection transactionnelle de l'état historique :
elles ne remplacent PAS encore complètement le JSON canonique de rejeu.
Commandes, états JSON, projection SQL, reçus idempotents, grand livre et
outbox sont validés dans la même transaction. Un échec de projection
annule l'ensemble des mutations. La première lecture d'un monde existant
matérialise les données sous verrou mondial avec un marqueur atomique.
Les lectures ordinaires du marché utilisent ensuite les index SQL en MVCC.

Le moteur n'impose plus les plafonds de 1 000 comptes et de 1 000 offres à
vie ; les quotas par minute de l'API, les permissions et les capacités des
ressources restent obligatoires. Les tests vérifient 1 001 ouvertures
de comptes en mémoire et 1 001 offres réelles sous MariaDB.

## Procédure d'upgrade contrôlée

MariaDB valide implicitement les DDL : un simple ROLLBACK SQL ne suffit
pas à annuler la migration 020. L'opérateur doit :

1. Bloquer les inscriptions, les commandes et tous les workers du monde.
2. Conserver la révision du code, les manifestes octet par octet, les
   règles épinglées, la liste SHA-256 des migrations et la configuration.
3. Effectuer une sauvegarde transactionnellement cohérente hors instance.
   Vérifier sa restauration sur un serveur isolé et comparer données,
   journaux, identités, soldes, capacités et événements en attente.
4. Installer les migrations avec php bin/migrate.php après configuration
   de l'environnement MariaDB. Le runner refuse les fichiers modifiés.
5. Pour chaque monde approuvé, exécuter :
   php bin/m03-materialize.php ID_MONDE --backup-verified
   Le drapeau exprime l'attestation de l'opérateur : il ne remplace pas
   la preuve de restauration mentionnée plus haut.
6. Vérifier dans le rapport JSON les lignes de soldes, les offres, les
   montants différés, le journal, l'empreinte et la version 1.
7. Recetter sur la copie : pagination, création/acceptation/annulation/
   échéance d'offre, restitution, reconnexion et refus d'accès croisé.
8. Ne reprendre les écritures qu'après approbation humaine.

Il n'existe volontairement aucun script de DOWN destructif.
En cas de problème, restaurer dans une instance isolée la sauvegarde
vérifiée et la version exacte des règles, puis faire une bascule contrôlée.
Ne supprimer aucune table à chaud ; ne réinitialiser jamais le marqueur
de projection pour masquer une divergence.

## Expiration et remboursement

market.create accepte un champ expiresAt facultatif (horodatage Unix
entier, strictement futur et au plus 30 jours après l'instant logique).
L'absence de ce champ préserve les anciennes offres.
Un événement market.expire est écrit durablement avec la création.

La vente ou l'annulation d'une offre ne peut rembourser deux fois.
Si la production a rempli le stock du vendeur, les unités non
réintégrables sont conservées dans un compte remboursable durable,
séparé du stock et impossible à dépenser avant leur transfert.
L'action authentifiée market.claim réintègre autant d'unités que la
capacité autorise après libération de place ; le solde est maintenu
sans perte. Le grand livre différencie market_release et market_claim.
L'aperçu renvoie refundPending et l'interface donne une commande de
récupération. Les offres périmées ne sont pas visibles sur la liste SQL,
même si le worker doit encore terminer l'événement.

## Limites et validation

La projection SQL évite les scans de l'ensemble des offres lors des
lectures, mais le JSON canonique et le verrou mondial subsistent pour
l'ordonnancement des écritures, les mutations et le rejeu. Leur
suppression complète, les verrous ciblés par compte et une mesure de
capacité représentative exigent une nouvelle phase de travail.
Les épreuves CI sur MariaDB/Chromium sont nécessaires. Une CI verte
n'est pas une preuve de migration ou de sauvegarde sur O2Switch/VPS.

## Format compact des reçus M03

Les nouveaux reçus de commandes **joueur** sous migration 020 ne
stockent plus une copie complète du snapshot mondial : ils conservent
une valeur JSON vide dans la colonne historique result_state_json.
Les informations contractuelles du reçu joueur (identifiant, horodatage
logique et événements programmés), l'identité du demandeur, la charge
canonique, le journal et les mouvements de grand livre demeurent
persistés atomiquement. Les réessais d'un même identifiant renvoient
le même ActorReceipt, sans nouvelle exécution. Les commandes système
et toutes les lignes historiques restent inchangées ; aucune migration
destructrice de reçus n'est réalisée.
