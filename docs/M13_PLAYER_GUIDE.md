# Aeternum Engine — guide de démarrage des joueurs (candidat M13)

**Statut : documentation de recette, non annonce de V1.0 publique.**
Les trois jeux sont des packs de référence du même moteur, et non trois
applications indépendantes. Les mécanismes réels sont testés sur PHP 8.4,
MariaDB 11.4 et Chromium dans des environnements jetables.

## Avant de jouer

L'exploitant doit fournir une adresse HTTPS autorisée, une invitation
valide ou une activation de compte suivant sa politique, et un monde
compatible. **Le mode d'inscription sans vérification d'identité ne doit
jamais être activé en production** : il est réservé au laboratoire local.
Aucun outil de programmation, terminal ou commande SQL n'est demandé au
joueur. Ouvrir l'interface web, choisir la langue français/anglais, créer
son compte puis se reconnecter avec son mot de passe.

Un monde désigne une instance persistante, un pack ses règles immuables
et un compte économique les ressources appartenant au joueur.
La liste des mondes peut différer selon l'installation. Les versions actuelles 0.4.0 (médiéval et industriel) et 0.5.0 (Guild) proposent des campagnes courtes ; leurs règles épinglées ne constituent pas un équilibrage définitif.

## Campagne médiévale (pack `reference-medieval@0.4.0`)

1. Sélectionner un monde médiéval approuvé, créer son compte et accéder
   au territoire initial. L'entité, le lieu et le compte de ressources
   appartiennent au joueur authentifié.
2. Ouvrir les emplacements de construction. Installer une scierie et
   une ferme sur les parcelles admissibles puis lancer les chantiers.
3. Attendre le traitement des événements par le moteur : les travaux
   programmés ne sont pas achevés simplement parce que la page reste
   ouverte. Rafraîchir les projections après le passage du worker.
4. Utiliser les opérations du peuplement pour accueillir les résidents,
   alimenter la colonie et organiser les travaux du bois. Surveiller les
   stocks et capacités, ainsi que les périodes de repos.
5. Développer la scierie et la ferme au niveau 2, le grenier au niveau 1
   et le donjon au niveau 2, avec au moins six résidents. Les prérequis
   sont vérifiés par le serveur, pas par la seule interface.
6. Consulter le statut de la colonie pour confirmer l'objectif serveur ;
   fermer la session, se reconnecter et vérifier la persistance.

La procédure et les chiffres proviennent du
[document M05](M05_MEDIEVAL_GAMEPLAY.md). Les niveaux et le rythme
pourraient changer dans une version future de pack, sans modification
silencieuse d'un monde existant.

## Campagne industrielle (pack `reference-industrial@0.4.0`)

1. Choisir un monde industriel compatible et installer les fondations
   d'une centrale, d'une aciérie, d'un entrepôt et d'un terminal de fret.
2. Achever les structures selon leurs coûts et délais, puis produire
   l'énergie à partir des matières premières (`industry.generate`).
3. Raffiner en marchandises (`industry.refine`) : la disponibilité de
   l'énergie, les capacités de stockage et les prérequis sont contrôlés
   de manière transactionnelle.
4. Expédier des marchandises par le terminal (`industry.ship`).
   La commande consomme effectivement des stocks et l'énergie prévue.
5. Atteindre les objectifs d'infrastructure et au moins 45 marchandises
   livrées ; confirmer l'état de victoire par la projection du serveur.
6. Se déconnecter puis revenir pour vérifier la persistance du résultat.

Les données de référence et limites figurent dans le
[document M06](M06_INDUSTRIAL_GAMEPLAY.md). Ce jeu n'est pas un
simple habillage du médiéval : il exploite un réseau d'énergie, une
raffinerie, le stockage et les expéditions.

## Campagne marchande (reference-guild@0.5.0)

Construire une halle marchande niveau 2, un dépôt caravanier niveau 2
et une tour de guet niveau 1. Achever deux recherches et deux expéditions
via le panneau Objectifs. Conclure trois échanges distincts avec
d'autres comptes : seules les offres effectivement remplies comptent.
Le serveur confirme la victoire, qui persiste après reconnexion.
Une expédition est une étude logistique, sans gain de territoire ni
transport physique. Les défis sont immédiats et non destructifs ;
la protection, le délai et les trêves mutuelles sont appliqués par le serveur.

## Recherche et expéditions dans les trois mondes

La recherche exige une installation achevée au niveau requis ; une
expédition exige une recherche confirmée et une installation appropriée.
Le reçu signifie que l'action est planifiée, non achevée. Actualiser
la progression pour constater la fin réelle du travail. Les anciennes
versions n'exposent pas nécessairement ces commandes.

## Multijoueur, marché et communication

Les marchés utilisent des offres à conditions fixes, réservations
transactionnelles et reçus idempotents. Les ressources disponibles
doivent être vérifiées après une confirmation du serveur ; une réponse
réseau perdue ne justifie **jamais** d'inventer une nouvelle commande.
Utiliser la fonction de reprise conservant l'identifiant et la charge
utile initiaux. Après déconnexion, cet état local disparaît : vérifier
l'historique et les stocks confirmés avant toute nouvelle commande. Une annulation ou une expiration peut modifier l'offre
visible, et la pagination n'est pas un instantané figé entre pages.
Voir le [fonctionnement M08](M08_MARKET.md).

Dans les mondes proposant les fonctionnalités sociales, les joueurs
peuvent créer une alliance, inviter, gérer des rôles, échanger des
messages privés, bloquer et signaler des abus. La consultation des
informations privées exige une authentification et une appartenance
active au monde. Les messages et notifications relèvent des règles
décrites dans le [module M09](M09_SOCIAL.md).

## Compte, sécurité et assistance

- Le mot de passe et les jetons de session ne doivent jamais être
  publiés ni insérés dans une URL ou un rapport d'incident.
- L'interface permet la fermeture de session ; les sessions peuvent
  être révoquées côté serveur. Une réponse 401 exige une nouvelle
  authentification. N'envoyer ni captures de jetons ni codes de
  récupération à un tiers.
- Une réponse 400 est une requête invalide ; 404 peut masquer un
  accès non autorisé ; 409 désigne notamment un conflit de commande ;
  429 limite les demandes excessives. Les erreurs temporaires doivent
  être traitées sans commandes dupliquées.
- Les droits premium actuels sont **uniquement cosmétiques et
  simulés**. Aucun paiement réel ni achat intégré n'est activé. Voir
  [les limites M10](M10_ADMIN_PREMIUM.md).
- Les tests automatisés ne remplacent pas une assistance ou des
  sauvegardes réelles de l'exploitant. L'opérateur doit annoncer le
  niveau de service, la politique de confidentialité et les voies
  d'assistance avant toute ouverture publique.

## Accessibilité et limites de cette documentation

Le client propose le français et l'anglais, une navigation au clavier
et des scénarios Chromium sur largeur mobile. Ces contrôles ne
constituent ni un audit WCAG externe ni une garantie de fonctionnement
sur tous terminaux. Le client reste hybride Vue/TypeScript et
adaptateur JavaScript, comme précisé dans [M07](M07_WEB_CLIENT.md).

**V1.0 demeure non signée** tant que les neuf jalons de
[la matrice M13](M13_RELEASE_ACCEPTANCE.md) ne sont pas reçus.
