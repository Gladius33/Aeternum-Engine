import {createApp, h, onBeforeUnmount, onMounted, ref} from 'vue';
import {languageOf, translate, type Language, type MessageKey} from './messages';
import {actionLabel} from './command-labels';
import {playerContext,playerReceipt,playerFlag,type PlayerContext,type PlayerReceipt} from './player-events';


const Shell = {
  setup() {
    const locale = ref<Language>(languageOf(document.documentElement.lang));
    const authenticated = ref(false);
    const medieval = ref(false);
    const industrial = ref(false);
    const connected = ref(navigator.onLine);
    const networkChanged = ref(false);
    const active = ref(window.location.hash);
    const context = ref<PlayerContext|null>(null);
    const receipt = ref<PlayerReceipt|null>(null);
    const busy = ref(false);
    const hasPending = ref(false);
    const rootGame = document.getElementById('game');
    const medievalPanel = document.getElementById('medieval-panel');
    const industrialPanel = document.getElementById('industrial-panel');
    let observer: MutationObserver | null = null;
    const text = (key: MessageKey): string => translate(locale.value, key);
    function sync() {
      authenticated.value = rootGame?.hidden === false;
      medieval.value = medievalPanel?.hidden === false;
      industrial.value = industrialPanel?.hidden === false;
    }
    function onLocale(event: Event) {
      const choice = (event as CustomEvent<{locale?:string}>).detail?.locale;
      locale.value = languageOf(choice ?? document.documentElement.lang);
    }
    function onNetwork() {
      connected.value = navigator.onLine;
      networkChanged.value = true;
    }
    function onHash() { active.value = window.location.hash; }
    function onContext(event: Event) {
      context.value=playerContext((event as CustomEvent<unknown>).detail);
      receipt.value = null;
    }
    function onConfirmed(event: Event) {
      const confirmed=playerReceipt((event as CustomEvent<unknown>).detail,context.value);
      if(confirmed)receipt.value=confirmed;
    }
    function onActivity(event: Event) {
      busy.value = playerFlag((event as CustomEvent<unknown>).detail,'busy');
    }
    function onRetry(event: Event) {
      hasPending.value = playerFlag((event as CustomEvent<unknown>).detail,'pending');
    }
    function onReset() {
      receipt.value = null; context.value = null; hasPending.value = false; busy.value = false;
    }
    onMounted(() => {
      sync();
      observer = new MutationObserver(sync);
      for (const element of [rootGame, medievalPanel, industrialPanel]) {
        if (element) observer.observe(element, {attributes:true, attributeFilter:['hidden']});
      }
      window.addEventListener('aeternum:localechange', onLocale);
      window.addEventListener('aeternum:context', onContext);
      window.addEventListener('aeternum:confirmed', onConfirmed);
      window.addEventListener('aeternum:activity', onActivity);
      window.addEventListener('aeternum:retry', onRetry);
      window.addEventListener('aeternum:reset', onReset);
      window.addEventListener('online', onNetwork);
      window.addEventListener('offline', onNetwork);
      window.addEventListener('hashchange', onHash);
    });
    onBeforeUnmount(() => {
      observer?.disconnect();
      window.removeEventListener('aeternum:localechange', onLocale);
      window.removeEventListener('aeternum:context', onContext);
      window.removeEventListener('aeternum:confirmed', onConfirmed);
      window.removeEventListener('aeternum:activity', onActivity);
      window.removeEventListener('aeternum:retry', onRetry);
      window.removeEventListener('aeternum:reset', onReset);
      window.removeEventListener('online', onNetwork);
      window.removeEventListener('offline', onNetwork);
      window.removeEventListener('hashchange', onHash);
    });
    return () => {
      const links: {href:string;label:MessageKey}[] = authenticated.value
        ? [{href:'#balance',label:'economy'}, {href:'#world-map',label:'map'},
           ...(medieval.value || industrial.value ? [{href:medieval.value?'#medieval-panel':'#industrial-panel',label:'game' as const}] : []),
           {href:'#market-panel',label:'market'}, {href:'#social-panel',label:'social'}]
        : [{href:'#login',label:'home'}];
      return h('div', {class:'vue-shell-inner', 'data-client':'vue-typescript'}, [
        h('a', {class:'skip-link', href:'#main-content'}, text('skip')),
        h('nav', {class:'player-nav', 'aria-label':text('nav')},
          links.map(link=>h('a', {
            key:link.href, href:link.href,
            'aria-current':active.value===link.href?'location':undefined
          }, text(link.label)))),
        authenticated.value && context.value?.world ? h('p', {class:'player-context'}, [
          text('currentWorld')+': '+context.value.world,
          context.value.account ? ' · '+text('currentAccount')+': '+context.value.account : ''
        ]) : null,
        h('details', {class:'player-guide'}, [
          h('summary', text('help')),
          h('p', text('helpText'))
        ]),
        busy.value ? h('p',{class:'loading-indicator',role:'status','aria-live':'polite'},text('loading')) : null,
        hasPending.value ? h('p',{class:'pending-warning',role:'status'},text('pending')) : null,
        receipt.value ? h('p',{class:'command-confirmed',role:'status'},text('confirmed')+': '+actionLabel(locale.value, receipt.value.type ?? '')) : null,
        !connected.value ? h('p',{class:'connection-alert',role:'alert'},text('offline'))
          : networkChanged.value ? h('p',{class:'connection-restored',role:'status'},text('online')) : null
      ]);
    };
  }
};
const mount = document.getElementById('vue-shell');
if (mount) createApp(Shell).mount(mount);
