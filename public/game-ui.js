/* Framework-neutral shell. Only presentation; consumers supply actions and DOM slots. */
'use strict';
(()=>{
    const node=(tag,cls,parent,text)=>{
        const n=document.createElement(tag);if(cls)n.className=cls;if(text!==undefined)n.textContent=text;
        if(parent)parent.append(n);return n;
    };
    const icons={domain:'M3 10 12 3 21 10V21H3Z M9 21V14H15V21',atlas:'M3 5 9 3 15 5 21 3V19L15 21 9 19 3 21Z M9 3V19M15 5V21',construction:'M3 20 13 10M10 4 20 14M13 3 21 11 18 14 10 6Z',holdings:'M3 21V8L12 3 21 8V21Z M8 21V12H16V21',reserves:'M3 7 12 3 21 7 12 11Z M3 7V17L12 21 21 17V7M12 11V21',objectives:'M12 3 15 9 22 10 17 15 18 22 12 19 6 22 7 15 2 10 9 9Z',market:'M3 8H21L18 3H6Z M4 8V21H20V8M8 21V14H16V21',activity:'M4 12 9 17 20 6',relations:'M3 5H21V15H13L8 20V15H3Z M7 9H17M7 12H14',settings:'M4 7H20M4 12H20M4 17H20'};
    function icon(key,parent){
        const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');
        svg.setAttribute('viewBox','0 0 24 24');svg.setAttribute('aria-hidden','true');svg.setAttribute('class','game-ui-icon');
        const p=document.createElementNS(svg.namespaceURI,'path');p.setAttribute('d',icons[key]??icons.settings);p.setAttribute('fill','none');p.setAttribute('stroke','currentColor');p.setAttribute('stroke-width','1.6');p.setAttribute('stroke-linejoin','round');p.setAttribute('stroke-linecap','round');svg.append(p);parent.append(svg);
    }
    const tr=(v,locale)=>typeof v==='string'?v:v?.[locale]??v?.en??'';
    // Presentation-only helpers shared across the shell and scene. Preserve
    // focus across authoritative redraws without interpolating selectors.
    function focusKey(root,attributes){
        const active=document.activeElement;
        if(!active||!root.contains(active))return null;
        for(const key of attributes){
            const value=active.getAttribute(key);
            if(value!==null)return {key,value};
        }
        return null;
    }
    function restoreFocus(root,saved){
        if(!saved)return false;
        const next=[...root.querySelectorAll('['+saved.key+']')].find(n=>n.getAttribute(saved.key)===saved.value);
        if(!next||next.disabled||!next.getClientRects().length)return false;
        next.focus({preventScroll:true});return true;
    }
    const failures={
        command_or_access_conflict:['Check resources, permissions and prerequisites.','Vérifiez vos ressources, vos droits et les prérequis.'],
        database_busy:['The server is busy. Retry the saved action if offered.','Le serveur est occupé. Réessayez l’action enregistrée si elle est proposée.'],
        rate_limited:['Too many requests. Try again shortly.','Trop de requêtes. Réessayez dans un instant.'],
        network_unavailable:['Connection interrupted. The result is not confirmed.','Connexion interrompue. Le résultat n’est pas confirmé.'],
        invalid_response:['The server response could not be verified.','La réponse du serveur n’a pas pu être vérifiée.'],
        unauthorized:['Your session has ended. Sign in again.','Votre session a expiré. Reconnectez-vous.'],
        due_events_pending:['Awaiting the world worker. Retry the same action.','Le monde doit terminer ses événements. Réessayez la même action.'],
        not_found:['This item is unavailable. Refresh your view.','Cet élément est indisponible. Actualisez votre vue.']
    };
    function feedback(locale,key){
        return failures[key]?.[locale==='fr'?1:0]??(locale==='fr'?'Action non terminée. Vérifiez votre connexion et vos droits.':'Action not completed. Check your connection and permissions.');
    }
    function create({host,slots,actions={},profile,locale='en'}){
        if(!(host instanceof HTMLElement)||!slots||!profile)throw TypeError('Game UI requires host, slots and profile');
        const root=node('div','game-ui',null);root.dataset.uiTemplate='1';
        const mast=node('header','game-ui-mast',root);
        const brand=node('div','game-ui-brand',mast);
        const name=node('h2','game-ui-title',brand),subtitle=node('p','game-ui-subtitle',brand);
        const context=node('div','game-ui-context',mast);
        const status=node('p','game-ui-status',mast);status.setAttribute('role','status');status.setAttribute('aria-live','polite');status.setAttribute('aria-atomic','true');
        const introduction=node('div','game-ui-introduction',root);
        const resources=node('div','game-ui-resources',root);
        const layout=node('div','game-ui-layout',root);
        const nav=node('nav','game-ui-nav',layout);
        const stage=node('div','game-ui-stage',layout);
        const side=node('aside','game-ui-side',layout);
        const lower=node('div','game-ui-lower',root);
        const groups={introduction,resources,stage,side,lower,context};
        for(const [key,items] of Object.entries(slots)){
            if(!Object.hasOwn(groups,key))throw TypeError('Unknown UI slot: '+key);
            for(const item of items){if(item instanceof Element)groups[key].append(item);}
        }
        host.prepend(root);
        let current=profile,language=locale,active='',locked=false;
        nav.addEventListener('keydown',event=>{
            if(!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Home','End'].includes(event.key)||locked)return;
            const buttons=[...nav.querySelectorAll('button:not([disabled])')];
            const at=buttons.indexOf(document.activeElement);if(at<0||!buttons.length)return;
            const next=event.key==='Home'?0:event.key==='End'?buttons.length-1:
                (at+(event.key==='ArrowLeft'||event.key==='ArrowUp'?-1:1)+buttons.length)%buttons.length;
            event.preventDefault();buttons[next].focus();
        });
        function renderNav(){
            const previous=document.activeElement?.dataset?.gameNav;
            nav.replaceChildren();nav.setAttribute('aria-label',language==='fr'?'Navigation du jeu':'Game navigation');
            for(const key of current.nav){
                if(typeof actions[key]!=='function')continue;
                const button=node('button','game-ui-nav-button',nav);button.type='button';button.dataset.gameNav=key;
                icon(key,button);node('span','',button,tr(current.labels[key],language));
                button.setAttribute('aria-pressed',String(active===key));button.disabled=locked;
                button.addEventListener('click',()=>{if(locked)return;active=key;for(const b of nav.querySelectorAll('button'))b.setAttribute('aria-pressed',String(b===button));actions[key]();});
                if(previous===key)button.focus({preventScroll:true});
            }
        }
        function update(next=current,lang=language){
            const nextLanguage=lang==='fr'?'fr':'en';
            const unchanged=current===next&&language===nextLanguage&&nav.childElementCount>0;
            current=next;language=nextLanguage;root.dataset.skin=current.id;
            name.textContent=tr(current.title,language);subtitle.textContent=tr(current.subtitle,language);if(!unchanged)renderNav();
        }
        update();
        return Object.freeze({root,update,setBusy(value){locked=Boolean(value);for(const b of nav.querySelectorAll('button'))b.disabled=locked;},setStatus(value){status.textContent=String(value??'');}});
    }
    window.AeternumGameUI=Object.freeze({create,focusKey,restoreFocus,feedback,version:1});
})();
