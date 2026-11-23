// M07 transitional locale layer for the existing reference client.
// No network calls, HTML injection or persistent account data.
(() => {
  'use strict';
  const messages = Object.freeze({
    en: Object.freeze({
      language: 'Language', signIn: 'Sign in', create: 'Create a player',
      recovery: 'Recover an account', settlement: 'Settlement',
      worldMap: 'World map', market: 'Market', signOut: 'Sign out',
      industrial: 'Industrial network — tutorial and victory goal',
      medieval: 'Medieval colony — tutorial and goal'
    }),
    fr: Object.freeze({
      language: 'Langue', signIn: 'Connexion', create: 'Créer un joueur',
      recovery: 'Récupérer un compte', settlement: 'Colonie',
      worldMap: 'Carte du monde', market: 'Marché', signOut: 'Déconnexion',
      industrial: 'Réseau industriel — tutoriel et objectif',
      medieval: 'Colonie médiévale — tutoriel et objectif'
    })
  });
  const targets = Object.freeze({
    signIn: '#login h2', create: '#register h2',
    recovery: '#recover h2', settlement: '#game > h2',
    worldMap: '#map-title', market: '#market-panel > h2',
    signOut: '#logout', industrial: '#industrial-title',
    medieval: '#medieval-title'
  });
  function applyLocale(locale) {
    if (!Object.prototype.hasOwnProperty.call(messages, locale)) return false;
    document.documentElement.lang = locale;
    for (const [key, selector] of Object.entries(targets)) {
      const element = document.querySelector(selector);
      if (element) element.textContent = messages[locale][key];
    }
    const label = document.getElementById('locale-label');
    if (label) label.textContent = messages[locale].language;
    window.dispatchEvent(new CustomEvent('aeternum:localechange', {detail:{locale}}));
    return true;
  }
  const select = document.getElementById('locale-select');
  if (!select) return;
  select.addEventListener('change', () => applyLocale(select.value));
  applyLocale(select.value);
})();
