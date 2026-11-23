// Public UI catalogue. Locale changes never modify form values, credentials or API payloads.
(() => {
  'use strict';
  // Each entry maps a user-facing English string to its French equivalent.
  const entries = [
    ['Language','Langue'], ['Sign in','Connexion'], ['Sign out','Déconnexion'],
    ['Create a player','Créer un joueur'], ['Recover an account','Récupérer un compte'],
    ['Settlement','Colonie'], ['World map','Carte du monde'], ['Market','Marché'],
    ['Industrial network — tutorial and victory goal','Réseau industriel — tutoriel et objectif'],
    ['Medieval colony — tutorial and goal','Colonie médiévale — tutoriel et objectif'],
    ['Reference economy client · create a player or sign in','Client de jeu · créez un joueur ou connectez-vous'],
    ['Login','Identifiant'], ['Password','Mot de passe'], ['New login','Nouvel identifiant'],
    ['New password (12 characters minimum)','Nouveau mot de passe (12 caractères minimum)'],
    ['Confirm new password','Confirmer le nouveau mot de passe'],
    ['World to join','Monde à rejoindre'], ['Invitation code','Code d’invitation'],
    ['Email address for verification','Adresse électronique de vérification'],
    ['Activate your new player','Activer votre compte de joueur'],
    ['Enter the one-time code sent to your email address. Unverified accounts cannot sign in.','Saisissez le code à usage unique reçu par courriel. Un compte non vérifié ne peut pas se connecter.'],
    ['Activation login','Identifiant à activer'], ['Activation password','Mot de passe du compte'],
    ['Email activation code','Code d’activation reçu par courriel'], ['Activate player','Activer le joueur'],
    ['Request a new activation code','Demander un nouveau code d’activation'],
    ['Wait at least one minute after the last code.','Attendez au moins une minute après le dernier code.'],
    ['Account login for resend','Identifiant du compte'], ['Account password for resend','Mot de passe du compte'],
    ['Email used at registration','Courriel utilisé à l’inscription'],
    ['Request another activation code','Demander un autre code d’activation'],
    ['Use the one-time code saved when you created your account.','Utilisez le code à usage unique enregistré lors de la création du compte.'],
    ['Recovery login','Identifiant à récupérer'], ['Recovery code','Code de récupération'],
    ['Replacement password','Mot de passe de remplacement'], ['Confirm replacement password','Confirmer le mot de passe de remplacement'],
    ['Reset password','Réinitialiser le mot de passe'],
    ['Log out all devices','Déconnecter tous les appareils'],
    ['Change password','Modifier le mot de passe'],
    ['Current password','Mot de passe actuel'], ['New account password (12 characters minimum)','Nouveau mot de passe du compte (12 caractères minimum)'],
    ['Confirm account password','Confirmer le mot de passe du compte'],
    ['Current password for recovery code','Mot de passe actuel pour le code de récupération'],
    ['Replace recovery code','Remplacer le code de récupération'],
    ['Retry pending action','Réessayer l’action en attente'], ['Reload available worlds','Actualiser les mondes disponibles'],
    ['World','Monde'], ['Account','Compte'], ['Resource','Ressource'], ['Refresh stock','Actualiser les stocks'],
    ['Read-only geography. Territory ownership and building foundations are configured by an operator.','Géographie en lecture seule. Les territoires et les fondations initiales sont configurés par un opérateur.'],
    ['Explore territories','Explorer les territoires'], ['Next territory page','Page suivante des territoires'],
    ['Map location','Lieu de la carte'], ['Inspect map routes','Consulter les routes'],
    ['Map destination','Destination'], ['Find shortest route','Trouver le trajet le plus court'],
    ['Your buildings','Vos bâtiments'], ['Create an owned foundation','Créer une fondation personnelle'],
    ['Find construction sites','Rechercher des sites de construction'],
    ['Owned site and entity','Site et entité dont vous êtes propriétaire'], ['Building type','Type de bâtiment'],
    ['New building identifier (optional)','Identifiant du nouveau bâtiment (facultatif)'],
    ['Place foundation','Placer la fondation'], ['Existing owned buildings','Bâtiments possédés'],
    ['The active player can pay resources to begin construction, cancel before the deadline, and claim refunds when storage space becomes available. Progress requires the world worker.','Le joueur peut financer la construction, l’annuler avant l’échéance et récupérer les ressources lorsque la capacité le permet. La progression dépend du traitement des événements du monde.'],
    ['Owned building ID','Identifiant du bâtiment possédé'], ['Inspect building plan','Consulter le plan'],
    ['Begin construction','Lancer la construction'], ['Cancel construction','Annuler la construction'],
    ['Claim construction refund','Récupérer les ressources de la construction'],
    ['Claim pending escrow refund','Récupérer les ressources bloquées'],
    ['Action','Action'], ['Settle production','Calculer la production'],
    ['Upgrade production','Améliorer la production'], ['Transfer resources','Transférer des ressources'],
    ['Transfer details','Détails du transfert'], ['Find recipients','Rechercher des destinataires'],
    ['Available recipients','Destinataires disponibles'], ['Choose a recipient','Choisir un destinataire'],
    ['Recipient account','Compte destinataire'], ['Units','Unités'], ['Submit action','Exécuter l’action'],
    ['Settlement actions','Actions de la colonie'],
    ['Feed and welcome one resident','Nourrir et accueillir un habitant'],
    ['Organize lumber work','Organiser le travail du bois'],
    ['Review your colony','Examiner votre colonie'], ['Victory milestones','Objectifs de victoire'],
    ['Industrial operations','Opérations industrielles'], ['Review industrial network','Examiner le réseau industriel'],
    ['Generate grid electricity','Produire de l’électricité'],
    ['Manufacture goods','Fabriquer des marchandises'], ['Dispatch freight','Expédier des marchandises'],
    ['Delivery and construction objectives','Objectifs de livraison et de construction'],
    ['Publish a barter offer','Publier une offre de troc'], ['Offered resource','Ressource proposée'],
    ['Offered units','Quantité proposée'], ['Requested resource','Ressource demandée'],
    ['Requested units','Quantité demandée'],
    ['Optional expiry (seconds from now)','Expiration facultative (secondes à compter de maintenant)'],
    ['Create offer','Créer l’offre'], ['Reload market','Actualiser le marché'],
    ['Filter market offers','Filtrer les offres du marché'],
    ['Search offered resource','Ressource proposée recherchée'],
    ['Search requested resource','Ressource demandée recherchée'],
    ['Search offers','Rechercher des offres'], ['All resources','Toutes les ressources'],
    ['My trade history','Mon historique des échanges'],
    ['Completed, cancelled and expired trades from your selected account.','Échanges conclus, annulés et expirés pour le compte sélectionné.'],
    ['Load my trade history','Charger mon historique'],
    ['Next history page','Page suivante de l’historique'],
    ['Private account history.','Historique privé du compte.'],
    ['No completed trades.','Aucun échange clôturé.'],
    ['Search changed. Reload market offers.','Recherche modifiée. Actualisez les offres du marché.'],
    ['Select both resource filters or neither.','Sélectionnez les deux filtres de ressources ou aucun.'],
    ['Next market page','Page suivante du marché'], ['Open offers','Offres ouvertes'],
    ['Choose an offer','Choisir une offre'], ['Accept selected offer','Accepter l’offre choisie'],
    ['Cancel selected offer','Annuler l’offre choisie'], ['No open offers.','Aucune offre ouverte.'],
    ['No outgoing routes','Aucune route sortante'],
    ['No directed route found.','Aucun trajet orienté trouvé.'],
    ['Territories in this world.','Territoires de ce monde.'],
    ['No territories on this page.','Aucun territoire sur cette page.'],
    ['Working…','Traitement en cours…'], ['Success','Succès'], ['Request failed','Échec de la requête'],
    ['Passwords do not match','Les mots de passe ne correspondent pas'],
    ['Choose a different password','Choisissez un autre mot de passe'],
    ['Invalid transfer','Transfert invalide'], ['Invalid market terms','Conditions de marché invalides'],
    ['Invalid expiry duration','Durée d’expiration invalide'],
    ['Registration server is busy. Please retry shortly.','Serveur d’inscription occupé. Réessayez dans quelques instants.'],
    ['Authentication server is busy. Please retry shortly.','Serveur d’authentification occupé. Réessayez dans quelques instants.'],
    ['Choose a world first','Choisissez d’abord un monde'], ['Choose a world','Choisissez un monde'],
    ['Choose an offer and account first','Choisissez d’abord une offre et un compte'],
    ['Cannot buy own offer','Vous ne pouvez pas accepter votre propre offre'],
    ['No pending action','Aucune action en attente'],
    ['Market changed. Reload offers to see the current market.','Marché modifié. Actualisez les offres pour consulter le dernier état.'],
    ['Saved action confirmed. Reload stock and offers.','Action enregistrée confirmée. Actualisez les stocks et les offres.'],
    ['Residents','Habitants'], ['Available workers','Travailleurs disponibles'],
    ['Grain per new resident','Céréales par nouvel habitant'],
    ['Grain per lumber shift','Céréales par période de travail du bois'],
    ['Wood per lumber shift','Bois par période de travail'],
    ['Grid power supply','Production électrique disponible'], ['Installed power demand','Demande électrique installée'],
    ['Spare grid power','Électricité disponible'], ['Warehouse goods capacity','Capacité de l’entrepôt'],
    ['Goods delivered','Marchandises livrées'], ['Building','Bâtiment'], ['Type','Type'],
    ['Territory','Territoire'], ['Level','Niveau'], ['Status','État'], ['Ready','Prêt'],
    ['Construction pending','Construction en cours'], ['Pending refunds','Remboursements en attente'],
    ['Next stage duration','Durée de la prochaine étape'], ['Next stage costs','Coûts de la prochaine étape'],
    ['Prerequisites','Prérequis'], ['Next stage','Prochaine étape'], ['Complete','Terminé'],
    ['Pending escrow refund','Remboursement bloqué'], ['Upgrade status','État de l’amélioration'],
    ['Upgrade cost','Coût de l’amélioration'], ['Duration (seconds)','Durée (secondes)'],
    ['Future production','Production future'], ['stock','stock'], ['capacity','capacité'],
    ['amount','quantité'], ['interval','intervalle'], ['at','à'], ['level','niveau'],
    ['VICTORY — Your medieval colony has fulfilled every objective!','VICTOIRE — Votre colonie médiévale a atteint tous les objectifs !'],
    ['Your colony is progressing. Complete all milestones to win.','Votre colonie progresse. Terminez tous les objectifs pour gagner.'],
    ['VICTORY — Your industrial network has fulfilled all construction and delivery goals!','VICTOIRE — Votre réseau industriel a atteint tous les objectifs de construction et de livraison !'],
    ['Build the complete network and dispatch enough goods to win.','Construisez tout le réseau et livrez suffisamment de marchandises pour gagner.'],
    ['Enter a valid map location','Saisissez un lieu valide sur la carte'],
    ['Enter valid source and destination locations','Saisissez un lieu de départ et une destination valides'],
    ['Only the first 100 owned sites or buildings are shown.','Seuls les 100 premiers sites ou bâtiments possédés sont affichés.'],
    ['No owned matching territory and entity.','Aucun territoire et aucune entité correspondants ne vous appartiennent.'],
    ['No operator-approved building types in this world.','Aucun type de bâtiment n’est autorisé par l’opérateur dans ce monde.'],
    ['Choose a site and building type; placement is free, construction stages consume resources.','Choisissez un site et un type de bâtiment. La fondation est gratuite, mais les étapes de construction consomment des ressources.'],
    ['Choose an owned site and approved building type','Choisissez un site possédé et un type de bâtiment autorisé'],
    ['Invalid building identifier','Identifiant de bâtiment invalide'],
    ['Choose an owned building ID','Choisissez l’identifiant d’un de vos bâtiments'],
    ['Select a building belonging to the refund account','Choisissez un bâtiment appartenant au compte destinataire du remboursement'],
    ['Choose a pending building and its funding account','Choisissez une construction en attente et son compte de financement'],
    ['Choose an eligible building and its funding account','Choisissez un bâtiment admissible et son compte de financement'],
    ['Choose a world and account','Choisissez un monde et un compte'],
    ['Check your email for the activation code, then enter it below.','Consultez votre messagerie et saisissez ci-dessous le code d’activation.'],
    ['If the details match a pending player, a replacement activation code has been sent.','Si les renseignements correspondent à un compte en attente, un nouveau code d’activation a été envoyé.'],
    ['Password reset. Sign in with the replacement password.','Mot de passe réinitialisé. Connectez-vous avec le nouveau mot de passe.'],
    ['Password changed. All sessions signed out. Save your new recovery code before signing in.','Mot de passe modifié. Toutes les sessions ont été fermées. Conservez le nouveau code de récupération avant de vous reconnecter.'],
    ['First 100 recipients shown. Enter another account ID manually.','Les 100 premiers destinataires sont affichés. Saisissez manuellement un autre identifiant de compte.'],
    ['Choose a recipient or enter an account ID manually.','Choisissez un destinataire ou saisissez manuellement un identifiant de compte.'],
    ['No recipients found.','Aucun destinataire trouvé.'],
    ['Retry the previous action with identical fields first.','Réessayez d’abord la commande précédente avec les mêmes paramètres.'],
    ['Choose an offer to accept, or cancel your own offer.','Choisissez une offre à accepter ou annulez votre propre offre.'],
    ['Select the seller account to cancel','Choisissez le compte vendeur pour annuler'],
    ['Choose a world, account and resource','Choisissez un monde, un compte et une ressource'],
    ['Start with a lumberyard, then a farm. Build a granary and keep, improve lumberyard and farm to level 2, and grow six residents to win.','Commencez par une exploitation forestière, puis une ferme. Construisez un grenier et un donjon, améliorez l’exploitation forestière et la ferme au niveau 2, puis accueillez six habitants pour gagner.'],
    ['Build a power plant and steelworks, add a warehouse, upgrade them to level 2 and construct a freight terminal. Generate grid energy from steel, manufacture goods using your electricity reserve, then deliver 45 goods to win. Operations obey network power and storage limits.','Construisez une centrale électrique et une aciérie, puis un entrepôt. Améliorez les installations au niveau 2 et construisez un terminal de fret. Produisez de l’électricité, fabriquez des marchandises grâce à votre réserve énergétique et livrez-en 45 pour gagner. Les opérations respectent les limites électriques et de stockage.'],
    ['Store this recovery code offline now; it will not be shown again:','Conservez immédiatement ce code de récupération hors ligne ; il ne sera plus affiché :'],
    ['Alliances','Alliances'],
    ['Alliances and invitations belong to the selected world. Actor IDs are not account passwords.','Les alliances et les invitations appartiennent au monde sélectionné. Les identifiants des joueurs ne sont pas des mots de passe.'],
    ['Found an alliance','Fonder une alliance'],
    ['New alliance ID','Identifiant de la nouvelle alliance'],
    ['Alliance name','Nom de l’alliance'],
    ['Create alliance','Créer l’alliance'],
    ['Browse alliances','Parcourir les alliances'],
    ['Alliance ID','Identifiant de l’alliance'],
    ['Inspect my alliance','Consulter mon alliance'],
    ['Invite actor ID','Identifiant du joueur à inviter'],
    ['Invite player','Inviter le joueur'],
    ['Member actor ID','Identifiant du membre'],
    ['Role','Rôle'],
    ['Update role','Modifier le rôle'],
    ['Transfer ownership','Transférer la direction'],
    ['Remove member','Exclure le membre'],
    ['Leave alliance','Quitter l’alliance'],
    ['My invitations','Mes invitations'],
    ['Refresh invitations','Actualiser les invitations'],
    ['Pending invitations','Invitations en attente'],
    ['No invitation selected','Aucune invitation sélectionnée'],
    ['Accept invitation','Accepter l’invitation'],
    ['Decline invitation','Refuser l’invitation'],
    ['Social action confirmed.','Action sociale confirmée.'],
    ['Alliance directory loaded.','Répertoire des alliances chargé.'],
    ['Only the first page of alliances is shown.','Seule la première page des alliances est affichée.'],
    ['Choose a world first','Choisissez d’abord un monde'],
    ['Enter a valid alliance ID','Saisissez un identifiant d’alliance valide'],
    ['Enter a valid actor ID','Saisissez un identifiant de joueur valide'],
    ['Select an invitation','Sélectionnez une invitation'],
    ["Messages and notifications","Messages et notifications"],
    ["Recipient actor ID","Identifiant du destinataire"],
    ["Private message","Message privé"],
    ["Send private message","Envoyer un message privé"],
    ["Refresh messages","Actualiser les messages"],
    ["Blocked actor ID","Identifiant du joueur bloqué"],
    ["Block player","Bloquer le joueur"],
    ["Unblock player","Débloquer le joueur"],
    ["Review blocked players","Consulter les joueurs bloqués"],
    ["Message ID to report","Identifiant du message à signaler"],
    ["Report reason","Motif du signalement"],
    ["Report message","Signaler le message"],
    ["Report this message","Signaler ce message"],
    ["Refresh notifications","Actualiser les notifications"],
    ["Unread notification","Notification non lue"],
    ["Select notification","Sélectionnez une notification"],
    ["Mark notification read","Marquer la notification comme lue"],
    ["Alliance leaderboard","Classement des alliances"],
    ["Show alliance leaderboard","Afficher le classement des alliances"],
    ["Message sent.","Message envoyé."],
    ["Messages loaded.","Messages chargés."],
    ["Notifications loaded.","Notifications chargées."],
    ["Leaderboard loaded.","Classement chargé."],
    ["Only the newest 50 messages are visible.","Seuls les 50 messages les plus récents sont affichés."],
    ["Only the newest 50 notifications are visible.","Seules les 50 notifications les plus récentes sont affichées."],
    ["Only the top 50 alliances are shown.","Seules les 50 premières alliances sont affichées."],
    ["Report submitted to moderation.","Signalement transmis à la modération."],
    ['Never expires','Sans expiration']
  ];
  const toFr = new Map(entries), toEn = new Map(entries.map(([a,b])=>[b,a]));
  const translatable = 'h1,h2,h3,h4,button,label,legend,p,summary,dt';
  const select = document.getElementById('locale-select');
  if (!select) return;
  let current = 'en';
  let applying = false;
  function convert(value, locale) {
    const trimmed = value.trim();
    if (!trimmed) return value;
    const english = toEn.get(trimmed) || trimmed;
    let translated = locale === 'fr' ? (toFr.get(english) || english) : english;
    // Variable suffixes such as the one-time recovery code remain byte-for-byte unchanged.
    if (translated === trimmed) {
      const recoveryPrefix = 'Store this recovery code offline now; it will not be shown again:';
      const frenchPrefix = toFr.get(recoveryPrefix);
      const prefix = locale === 'fr' ? recoveryPrefix : frenchPrefix;
      const replacement = locale === 'fr' ? frenchPrefix : recoveryPrefix;
      if (prefix && replacement && trimmed.startsWith(prefix + ' ')) {
        translated = replacement + trimmed.slice(prefix.length);
      }
    }
    if (translated === trimmed) return value;
    const at = value.indexOf(trimmed);
    return value.slice(0, at) + translated + value.slice(at + trimmed.length);
  }
  function translateRoot(root) {
    if (applying) return;
    applying = true;
    try {
      const elements = [];
      if (root.nodeType === Node.ELEMENT_NODE && root.matches(translatable)) elements.push(root);
      if (root.querySelectorAll) elements.push(...root.querySelectorAll(translatable));
      // Protect user-controlled account IDs, resource IDs, and map/market list items.
      // Only the known empty-value placeholders and fixed action choices may be translated.
      elements.push(...document.querySelectorAll('#social-inbox option[value=""], #social-notification-id option[value=""], #recipient-directory option[value=""], #market-offer option[value=""], #market-filter-give option[value=""], #market-filter-want option[value=""], #owned-building-list option[value=""], #action select[name="type"] option'));
      for (const element of elements) {
        for (const node of element.childNodes) {
          if (node.nodeType === Node.TEXT_NODE) {
            const value = convert(node.nodeValue, current);
            if (value !== node.nodeValue) node.nodeValue = value;
          }
        }
      }
      for (const item of document.querySelectorAll('input[placeholder]')) {
        if (item.placeholder) item.placeholder = convert(item.placeholder, current);
      }
    } finally { applying = false; }
  }
  function applyLocale(locale) {
    if (!['en','fr'].includes(locale)) return;
    current = locale;
    document.documentElement.lang = locale;
    translateRoot(document.body);
    window.dispatchEvent(new CustomEvent('aeternum:localechange', {detail:{locale}}));
  }
  const observer = new MutationObserver(records => {
    // Dynamic, server-derived text is only translated when it exactly matches a
    // known UI string. Player identifiers, world names and values are untouched.
    if (applying || current === 'en') return;
    for (const record of records) {
      const root = record.type === 'characterData' ? record.target.parentElement : record.target;
      if (root && root.nodeType === Node.ELEMENT_NODE) translateRoot(root);
    }
  });
  observer.observe(document.body, {subtree:true,childList:true,characterData:true});
  select.addEventListener('change', () => applyLocale(select.value));
  applyLocale(select.value);
})();
