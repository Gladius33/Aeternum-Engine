# Aeternum Game UI — reusable presentation template (v1)

This is an original implementation. Presentation is not a ruleset, permission boundary, or API implementation.

## Files and integration
- public/game-ui.js: framework-neutral create({host,slots,actions,profile,locale}) function. Slots are existing DOM nodes, so listeners and authenticated handlers remain intact. Returned methods update(profile,locale), setBusy(boolean), setStatus(text). Only the declared navigation actions are rendered.
- public/game-ui-profiles.js: presentation profiles (title, subtitle, localized labels, ordered nav keys).
- public/game-ui.css: common responsive layout, icon navigation and skin variables.
- public/world-scene.js: Aeternum-specific adapter for current worlds, account/resource selectors, real state and existing commands.
- public/play.html: loads local scripts with CSP unchanged.

## Create a new skin
1. Define a profile object with a stable id, title and subtitle as {en,fr}, labels for each navigation key and an ordered nav array.
2. Add a CSS rule for .game-ui[data-skin=your-id] overriding --ui-bg, --ui-surface, --ui-border, --ui-accent, --ui-ink and --ui-muted. Keep contrast and touch targets.
3. Pass DOM nodes into the resources, context, stage, side and lower slots. The shell owns placement but not the nodes' behavior.
4. Provide action callbacks matching the profile nav keys. Bind them to existing authorized commands and read APIs, not simulated success.
5. Bind locale changes, busy state and safe status messages. Do not put credentials, raw API errors, UUIDs or opaque IDs in normal player-facing labels.
6. Aeternum integration currently maps existing server capabilities to three presentation profiles. New game packs need an explicit adapter mapping; do not modify signed historical rules merely to skin a game.

Minimal example (existing DOM elements and game callbacks):
    const profile = {id:'new-game',title:{en:'New World',fr:'Nouveau Monde'},
      subtitle:{en:'Explore',fr:'Explorer'},labels:{atlas:{en:'Map',fr:'Carte'}},nav:['atlas']};
    const shell = AeternumGameUI.create({host,profile,locale:'fr',
      slots:{stage:[mapElement],resources:[resourceBar]},
      actions:{atlas:()=>mapElement.scrollIntoView()}});
    shell.setBusy(false);

## Compatibility / testing
The normal /play.html opens the game-focused layout with legacy detailed controls collapsed. /play.html?view=classic expands detailed controls for existing acceptance scenarios and manual diagnostics. This is presentation only, not authorization. Normal mode is independently exercised with a real Guild login and server-owned structures. All scenes remain bound to server records. Additional gameplay controls remain available through the detailed-controls drawer. No remote assets, scripts or dependencies are introduced. No release claim until full CI and screenshot review.

## Independent research (no copied code/assets)
- Freeciv-web: https://github.com/freeciv/freeciv-web/blob/develop/freeciv-web/src/main/webapp/javascript/2dcanvas/mapview.js — map rendering, separate sprite definitions, resize and rendering lifecycle.
- OpenFront: https://github.com/openfrontio/OpenFrontIO/blob/main/src/client/hud/GameRenderer.ts and https://github.com/openfrontio/OpenFrontIO/blob/main/src/client/hud/layers/ControlPanel.ts — layered HUD, event-driven components, touch adaptations. AGPL source observed, not incorporated.
- OpenCiv: https://github.com/RyanGrieb/OpenCiv/blob/master/client/src/ui/windows/city/CityScreen.ts — shared window dimensions and reusable production presentation. No code incorporated.
- OGame: https://ogame.gameforge.com/ and https://ogame.fandom.com/wiki/Overview_Screen — resource summary, selected domain and navigation hierarchy. Public landing redirects; authenticated client source not inspected.
- Travian: https://support.travian.com/en/articles/42-central-village-overview and https://support.travian.com/en/articles/155-discovering-map-features — domain switching, map and resource summaries.
- Forge of Empires: https://support.innogames.com/kb/ForgeOfEmpires/en_DK/1358 — contextual construction menus.
