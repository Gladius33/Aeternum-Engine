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
    ['Never expires','Sans expiration']
  ];
  const toFr = new Map(entries), toEn = new Map(entries.map(([a,b])=>[b,a]));
  const translatable = 'h1,h2,h3,h4,button,label,legend,p,summary,dt,li,option';
  const select = document.getElementById('locale-select');
  if (!select) return;
  let current = 'en';
  let applying = false;
  function convert(value, locale) {
    const trimmed = value.trim();
    if (!trimmed) return value;
    const english = toEn.get(trimmed) || trimmed;
    const translated = locale === 'fr' ? (toFr.get(english) || english) : english;
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
      for (const element of elements) {
        if (element.closest('#recovery-secret')) continue;
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
