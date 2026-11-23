import {createApp, h, onBeforeUnmount, onMounted, ref} from 'vue';
import {languageOf, translate, type Language, type MessageKey} from './messages';

const Shell = {
  setup() {
    const locale = ref<Language>(languageOf(document.documentElement.lang));
    const authenticated = ref(false);
    const medieval = ref(false);
    const industrial = ref(false);
    const connected = ref(navigator.onLine);
    const networkChanged = ref(false);
    const active = ref(window.location.hash);
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
    onMounted(() => {
      sync();
      observer = new MutationObserver(sync);
      for (const element of [rootGame, medievalPanel, industrialPanel]) {
        if (element) observer.observe(element, {attributes:true, attributeFilter:['hidden']});
      }
      window.addEventListener('aeternum:localechange', onLocale);
      window.addEventListener('online', onNetwork);
      window.addEventListener('offline', onNetwork);
      window.addEventListener('hashchange', onHash);
    });
    onBeforeUnmount(() => {
      observer?.disconnect();
      window.removeEventListener('aeternum:localechange', onLocale);
      window.removeEventListener('online', onNetwork);
      window.removeEventListener('offline', onNetwork);
      window.removeEventListener('hashchange', onHash);
    });
    return () => {
      const links: {href:string;label:MessageKey}[] = authenticated.value
        ? [{href:'#balance',label:'economy'}, {href:'#world-map',label:'map'},
           ...(medieval.value || industrial.value ? [{href:medieval.value?'#medieval-panel':'#industrial-panel',label:'game' as const}] : []),
           {href:'#market-panel',label:'market'}]
        : [{href:'#login',label:'home'}];
      return h('div', {class:'vue-shell-inner', 'data-client':'vue-typescript'}, [
        h('a', {class:'skip-link', href:'#main-content'}, text('skip')),
        h('nav', {class:'player-nav', 'aria-label':text('nav')},
          links.map(link=>h('a', {
            key:link.href, href:link.href,
            'aria-current':active.value===link.href?'location':undefined
          }, text(link.label)))),
        h('details', {class:'player-guide'}, [
          h('summary', text('help')),
          h('p', text('helpText'))
        ]),
        !connected.value ? h('p',{class:'connection-alert',role:'alert'},text('offline'))
          : networkChanged.value ? h('p',{class:'connection-restored',role:'status'},text('online')) : null
      ]);
    };
  }
};
const mount = document.getElementById('vue-shell');
if (mount) createApp(Shell).mount(mount);
