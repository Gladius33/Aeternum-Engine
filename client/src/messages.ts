export const messages = {
  en: {
    nav: 'Player navigation', home: 'Sign in', economy: 'Stock and production',
    map: 'World map', game: 'Game objectives', market: 'Trade market', social: 'Alliances',
    help: 'How to play', helpText: 'Choose a world and your account. Review stock and production, build on owned territory, then follow your game objectives. Market offers and resource transfers are shared between players in the same world.',
    offline: 'Network connection lost. Actions cannot be confirmed until it returns.',
    online: 'Connection restored. Check the latest server state before acting.',
    skip: 'Skip to player content',
    loading: 'Contacting the game server…', confirmed: 'Action confirmed',
    pending: 'An action may still be pending. Use the retry control to check the original command safely.',
    currentWorld: 'Selected world', currentAccount: 'Player account'
  },
  fr: {
    nav: 'Navigation du joueur', home: 'Connexion', economy: 'Stocks et production',
    map: 'Carte du monde', game: 'Objectifs de jeu', market: 'Marché des échanges', social: 'Alliances',
    help: 'Comment jouer', helpText: 'Choisissez un monde et votre compte. Consultez les stocks et la production, construisez sur vos territoires, puis suivez les objectifs du jeu. Les offres du marché et les transferts concernent les joueurs du même monde.',
    offline: 'Connexion réseau perdue. Aucune action ne peut être confirmée avant son rétablissement.',
    online: 'Connexion rétablie. Vérifiez l’état du serveur avant toute action.',
    skip: 'Aller au contenu du joueur',
    loading: 'Communication avec le serveur de jeu…', confirmed: 'Action confirmée',
    pending: 'Une action reste peut-être en attente. Utilisez le bouton de reprise pour vérifier la commande initiale en toute sécurité.',
    currentWorld: 'Monde sélectionné', currentAccount: 'Compte joueur'
  }
} as const;
export type Language = keyof typeof messages;
export type MessageKey = keyof typeof messages.en;
export function languageOf(value: string | null | undefined): Language {
  return value === 'fr' ? 'fr' : 'en';
}
export function translate(language: Language, key: MessageKey): string {
  return messages[language][key];
}
