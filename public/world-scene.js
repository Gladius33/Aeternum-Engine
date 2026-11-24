/* Original vector artwork and interactive presentation; server remains authoritative. */
'use strict';
(()=>{
    const host=document.getElementById('world-scene');
    const ns='http://www.w3.org/2000/svg';
    const el=(tag,attrs={},parent,text)=>{
        const n=document.createElementNS(ns,tag);
        for(const [k,v] of Object.entries(attrs))n.setAttribute(k,String(v));
        if(text!==undefined)n.textContent=text;
        if(parent)parent.append(n);
        return n;
    };
    const html=(tag,parent,cls,text)=>{
        const n=document.createElement(tag); if(cls)n.className=cls;
        if(text!==undefined)n.textContent=text; parent.append(n);return n;
    };
    const locales={
        en:{medieval:'The living realm',industrial:'The industrial frontier',guild:'The merchant roads',neutral:'World atlas',sync:'Load atlas & holdings',empty:'Load the atlas to see server territories and your holdings.',caption:'Strategic schematic · artwork is illustrative; locations and holdings are server records.',holdings:'Your holdings',none:'No holdings loaded.',choose:'Select a territory or building',from:'Set origin',to:'Set destination',routes:'Inspect connections',route:'Trace route',zoomIn:'Zoom in',zoomOut:'Zoom out',reset:'Reset camera',inspect:'Inspect building',build:'Construction',trade:'Market',goals:'Objectives',production:'Production',level:'Level',pending:'Construction pending',unknown:'Inspect for current stage',territories:'Territories',registered:'Registered holdings'},
        fr:{medieval:'Le royaume vivant',industrial:'La frontière industrielle',guild:'Les routes marchandes',neutral:'Atlas du monde',sync:'Charger atlas et propriétés',empty:'Chargez l’atlas pour voir les territoires et vos propriétés du serveur.',caption:'Schéma stratégique · décor illustratif ; lieux et propriétés issus du serveur.',holdings:'Vos propriétés',none:'Aucune propriété chargée.',choose:'Sélectionnez un territoire ou bâtiment',from:'Définir le départ',to:'Définir la destination',routes:'Inspecter les liaisons',route:'Tracer la route',zoomIn:'Agrandir',zoomOut:'Réduire',reset:'Recentrer',inspect:'Inspecter le bâtiment',build:'Construction',trade:'Marché',goals:'Objectifs',production:'Production',level:'Niveau',pending:'Construction en cours',unknown:'Inspecter pour connaître le stade',territories:'Territoires',registered:'Propriétés enregistrées'}
    };
    Object.assign(locales.en,{foundation:'Foundation',planned:'Unverified stage',ready:'Completed stage',startStage:'Start selected stage',cancelStage:'Cancel selected stage',stockSync:'Read resource reserve',campaignSync:'Read campaign status',reserve:'Reserve / capacity',yield:'Production per cycle',cycle:'Cycle (s)',snapshot:'Latest server reading',stale:'Refresh required after action',residents:'Residents',workers:'Workers',power:'Grid supply / load',freight:'Goods delivered',score:'Global score',feed:'Welcome resident',work:'Assign lumber shift',generate:'Generate energy',refine:'Process goods',ship:'Dispatch goods',marketSync:'Read trade offers',marketCount:'Offers on loaded page',costs:'Next stage costs',noSnapshot:'No reading yet',owned:'Owned',more:'More holdings in list'});
    Object.assign(locales.fr,{foundation:'Fondation',planned:'Stade non vérifié',ready:'Stade achevé',startStage:'Lancer le stade sélectionné',cancelStage:'Annuler le stade sélectionné',stockSync:'Lire la réserve',campaignSync:'Lire la campagne',reserve:'Réserve / capacité',yield:'Production par cycle',cycle:'Cycle (s)',snapshot:'Dernier relevé du serveur',stale:'Actualiser après une action',residents:'Habitants',workers:'Travailleurs',power:'Réseau : offre / charge',freight:'Biens livrés',score:'Score global',feed:'Accueillir un habitant',work:'Organiser la coupe',generate:'Produire de l’énergie',refine:'Transformer les biens',ship:'Expédier les biens',marketSync:'Consulter les offres',marketCount:'Offres sur la page chargée',costs:'Coût du prochain stade',noSnapshot:'Aucun relevé',owned:'Possédé',more:'Autres bâtiments dans la liste'});
    Object.assign(locales.en,{allStocks:'Read all reserves',allStages:'Read all building stages',workshop:'Construction workshop',plot:'Owned construction plot',place:'Establish foundation',pick:'Select a building type',noSite:'No approved construction plot',noTypes:'No approved types loaded',prerequisite:'Missing prerequisites',eligible:'Prerequisites met; funding still required',complete:'All stages complete',objectives:'Campaign milestones',achieved:'Objective achieved',activity:'Confirmed actions in this session',emptyActivity:'No actions confirmed',tradeBoard:'Trade board',noOffers:'No offers loaded',acceptTrade:'Accept barter',cancelTrade:'Withdraw offer',moreOffers:'More offers available in market',inventory:'Resource reserves',inventoryNote:'Sequential server readings, not a single atomic snapshot',unknownReading:'Not loaded',remaining:'Estimated stage time',awaitWorker:'Awaiting worker / refresh',buildHere:'Build on this plot',moreResources:'First 16 resources shown',receipt:'Server confirmed'});
    Object.assign(locales.fr,{allStocks:'Lire toutes les réserves',allStages:'Lire tous les stades',workshop:'Atelier de construction',plot:'Parcelle de construction possédée',place:'Établir la fondation',pick:'Choisir un type de bâtiment',noSite:'Aucune parcelle de construction autorisée',noTypes:'Aucun type autorisé chargé',prerequisite:'Prérequis manquants',eligible:'Prérequis satisfaits ; financement encore nécessaire',complete:'Tous les stades achevés',objectives:'Objectifs de campagne',achieved:'Objectif atteint',activity:'Actions confirmées de cette session',emptyActivity:'Aucune action confirmée',tradeBoard:'Place de marché',noOffers:'Aucune offre chargée',acceptTrade:'Accepter le troc',cancelTrade:'Retirer l’offre',moreOffers:'Autres offres disponibles dans le marché',inventory:'Réserves de ressources',inventoryNote:'Relevés successifs du serveur, non atomiques',unknownReading:'Non chargé',remaining:'Temps estimé du stade',awaitWorker:'En attente du worker / actualisation',buildHere:'Construire sur cette parcelle',moreResources:'16 premières ressources affichées',receipt:'Confirmé par le serveur'});
    const lang=()=>document.getElementById('locale-select').value==='fr'?'fr':'en';
    const t=k=>locales[lang()][k];
    const palettes={
        medieval:['#244a38','#57784a','#b8a76b','#79513e','#d9c394','#304d57'],
        industrial:['#263940','#53656a','#a69b7c','#a56037','#c2cdd0','#24313a'],
        guild:['#71503a','#a47848','#e0b878','#a14b3e','#f3d6a4','#42565a'],
        neutral:['#304e53','#557076','#9aa88e','#786855','#d3d8c4','#253c48']
    };
    let theme='neutral',territories=new Map(),buildings=new Map(),details=new Map(),links=new Map(),route=[],selected=null,camera={x:0,y:0,z:1},extent={w:900,h:460},drag=null;
    const header=html('div',host,'scene-header'),title=html('h3',header,'scene-title'),summary=html('p',header,'scene-summary');
    const hud=html('div',host,'scene-hud');
    let reading=null,campaign=null,marketReading=null,stale=false;
    let inventory=new Map(),catalogue={sites:[],types:[]},chosenSite='',chosenType='',offers=[],offerMore=false,chosenOffer='',receipts=[];
    const actionLabels={
        'building.place':['Foundation established','Fondation établie'],
        'building.start':['Construction authorized','Construction autorisée'],
        'building.cancel':['Construction cancelled','Construction annulée'],
        'settlement.feed':['Resident welcomed','Habitant accueilli'],
        'settlement.work':['Lumber shift confirmed','Coupe de bois confirmée'],
        'industry.generate':['Energy generated','Énergie produite'],
        'industry.refine':['Goods processed','Biens transformés'],
        'industry.ship':['Freight dispatched','Fret expédié'],
        'market.create':['Offer published','Offre publiée'],
        'market.accept':['Barter settled','Troc exécuté'],
        'market.cancel':['Offer withdrawn','Offre retirée'],
        'economy.upgrade':['Upgrade authorized','Amélioration autorisée'],
        'economy.settle':['Production settled','Production acquise']
    };
    const toolbar=html('div',host,'scene-toolbar');
    const button=(parent,key,fn)=>{const b=html('button',parent,'scene-button',t(key));b.type='button';b.dataset.label=key;b.addEventListener('click',fn);return b;};
    button(toolbar,'sync',()=>run(async()=>{await loadMap();await loadConstructionChoices();}));
    button(toolbar,'zoomIn',()=>zoom(1.25));button(toolbar,'zoomOut',()=>zoom(.8));
    button(toolbar,'reset',()=>{camera={x:0,y:0,z:1};view();});
    button(toolbar,'stockSync',()=>document.getElementById('balance').requestSubmit());
    button(toolbar,'allStocks',()=>run(readInventory));
    button(toolbar,'allStages',()=>run(readStages));
    async function readInventory(){
        const f=locationFields(),resources=[...balance.elements.resource.options].map(o=>o.value).filter(Boolean).slice(0,16);
        if(!f.world||!f.account)throw Error('Choose a world and account');
        const results=new Map();
        for(const resource of resources){
            const data=await api(base(f)+'overview/'+encodeURIComponent(f.account)+'/'+encodeURIComponent(resource));
            if(locationFields().world!==f.world||locationFields().account!==f.account)return;
            results.set(resource,data);
        }
        inventory=results;renderWorkspace();
    }
    async function readStages(){
        const f=locationFields();
        if(!f.world||!f.account)throw Error('Choose a world and account');
        await loadConstructionChoices();
        const root='/v1/worlds/'+encodeURIComponent(f.world).replaceAll('%3A',':');
        const data=await api(root+'/construction/progression');
        if(locationFields().world!==f.world||locationFields().account!==f.account)return;
        const next=new Map();
        for(const stage of data.buildings){
            const b=buildings.get(stage.id);if(!b)continue;
            const old=details.get(stage.id);
            next.set(stage.id,{...b,...(old?.level===stage.level&&old?.pending===stage.pending?old:{}),...stage});
        }
        details=next;render();
    }
    const campaignButton=button(toolbar,'campaignSync',()=>{
        const id=theme==='medieval'?'medieval-check':theme==='industrial'?'industrial-check':'progression-load';
        document.getElementById(id).click();
    });
    const marketButton=button(toolbar,'marketSync',()=>document.getElementById('market-load').click());
    const svg=el('svg',{class:'scene-atlas',role:'group','aria-label':'Strategic atlas',viewBox:'0 0 900 460'});
    host.append(svg);
    const hint=html('p',host,'scene-caption'),panel=html('div',host,'scene-inspector');
    const selection=html('strong',panel,'scene-selection'),info=html('p',panel,'scene-info');
    const controls=html('div',panel,'scene-toolbar');
    const origin=button(controls,'from',()=>setLocation('map-location'));
    const destination=button(controls,'to',()=>setLocation('map-destination'));
    const connection=button(controls,'routes',()=>{setLocation('map-location');document.getElementById('map-links-load').click();});
    const trace=button(controls,'route',()=>document.getElementById('map-route-load').click());
    const inspect=button(controls,'inspect',()=>inspectBuilding(selected?.id));
    const stageStart=button(controls,'startStage',()=>stageCommand('building-start'));
    const stageCancel=button(controls,'cancelStage',()=>stageCommand('building-cancel'));
    const buildHere=button(controls,'buildHere',()=>{
        const site=catalogue.sites.find(s=>s.territory_id===selected?.id);
        if(site){chosenSite=site.territory_id+'|'+site.entity_id;renderWorkspace();workshop.root.scrollIntoView({block:'nearest'});}
    });
    function stageCommand(id){
        if(busy||selected?.type!=='building'||currentBuilding?.id!==selected.id||currentBuilding.account!==locationFields().account)return;
        const control=document.getElementById(id);if(!control.hidden)control.click();
    }
    const nav=html('div',host,'scene-toolbar');
    const jump=id=>{const node=document.getElementById(id);if(node&&!node.hidden){node.scrollIntoView({block:'start'});const focus=node.querySelector('button:not([hidden]),input,select');focus?.focus({preventScroll:true});}};
    button(nav,'build',()=>jump('foundation-panel'));
    button(nav,'production',()=>jump('action'));
    button(nav,'trade',()=>jump('market-panel'));
    const goals=button(nav,'goals',()=>jump(theme==='medieval'?'medieval-panel':theme==='industrial'?'industrial-panel':'progression-panel'));
    const operations=[
        ['medieval','feed','medieval-feed'],['medieval','work','medieval-work'],
        ['industrial','generate','industrial-generate'],['industrial','refine','industrial-refine'],['industrial','ship','industrial-ship']
    ].map(([pack,key,id])=>({pack,control:button(nav,key,()=>{if(!busy)document.getElementById(id).click();})}));
    const holdingTitle=html('h4',host,'scene-holdings-title'),holdingList=html('div',host,'scene-holdings');
    const workspace=html('div',host,'scene-workspace');
    const block=()=>{
        const root=html('div',workspace,'scene-workshop');
        return {root,title:html('h4',root,'scene-holdings-title'),body:html('div',root,'scene-workshop-body')};
    };
    const inventoryBlock=block(),workshop=block(),objectiveBlock=block(),tradeBlock=block(),receiptBlock=block();
    const plotLabel=html('label',workshop.body,'scene-caption');plotLabel.htmlFor='scene-plot';
    const plotSelect=html('select',workshop.body,'scene-plot');plotSelect.id='scene-plot';
    plotSelect.addEventListener('change',()=>{chosenSite=plotSelect.value;renderWorkspace();});
    const catalogueList=html('div',workshop.body,'scene-catalogue');
    const place=button(workshop.body,'place',()=>{
        if(busy||!chosenSite||!chosenType)return;
        const site=document.getElementById('foundation-site'),type=document.getElementById('foundation-type');
        if(![...site.options].some(o=>o.value===chosenSite)||![...type.options].some(o=>o.value===chosenType))return;
        site.value=chosenSite;type.value=chosenType;
        document.getElementById('foundation-id').value='';
        document.getElementById('foundation-place').click();
    });
    const tradeList=html('div',tradeBlock.body,'scene-trade-list');
    const tradeControls=html('div',tradeBlock.body,'scene-toolbar');
    const acceptTrade=button(tradeControls,'acceptTrade',()=>tradeCommand('market-accept'));
    const cancelTrade=button(tradeControls,'cancelTrade',()=>tradeCommand('market-cancel'));
    function tradeCommand(id){
        if(busy||!chosenOffer)return;
        const target=document.getElementById('market-offer');
        if(![...target.options].some(o=>o.value===chosenOffer))return;
        target.value=chosenOffer;document.getElementById(id).click();
    }
    function tradeSelection(){
        const offer=offers.find(o=>o.id===chosenOffer);
        acceptTrade.hidden=!offer||offer.seller===locationFields().account;
        cancelTrade.hidden=!offer||offer.seller!==locationFields().account;
        tradeList.querySelectorAll('[data-offer]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.offer===chosenOffer)));
    }
    function setLocation(id){if(selected?.type==='territory'){document.getElementById(id).value=territories.get(selected.id).location;updateSelection();}}
    function inspectBuilding(id){if(!buildings.has(id))return;document.getElementById('building-id').value=id;document.getElementById('building-load').click();jump('building-details');}
    function updateSelection(){
        const territory=selected?.type==='territory'?territories.get(selected.id):null;
        const building=selected?.type==='building'?buildings.get(selected.id):null;
        const d=building?details.get(building.id):null;
        selection.textContent=territory?territory.location:building?building.id:t('choose');
        stageStart.hidden=!(d&&currentBuilding?.id===d.id&&d.account===locationFields().account&&d.pending===null&&d.next);
        stageCancel.hidden=!(d&&currentBuilding?.id===d.id&&d.account===locationFields().account&&d.pending!==null);
        info.textContent=territory?[territory.id,territory.kind,territory.control].join(' · '):building?building.type+' · '+(d?t('level')+' '+d.level+(d.pending?' · '+t('pending'):''):t('unknown')):'';
        if(d?.next)info.textContent+=' · '+t('costs')+': '+Object.entries(d.next.costs).map(([k,v])=>v+' '+k).join(', ');
        origin.hidden=destination.hidden=connection.hidden=!territory;inspect.hidden=!building;
        buildHere.hidden=!territory||!catalogue.sites.some(s=>s.territory_id===territory.id);
        if(d?.missing&&Object.keys(d.missing).length)info.textContent+=' · '+t('prerequisite')+': '+Object.entries(d.missing).map(([k,v])=>k+' '+v.current+'/'+v.required).join(', ');
        trace.hidden=!document.getElementById('map-location').value||!document.getElementById('map-destination').value;
        goals.hidden=theme==='neutral'||(theme==='guild'&&document.getElementById('progression-panel').hidden);
        svg.querySelectorAll('[data-territory]').forEach(n=>n.setAttribute('aria-pressed',String(territory?.id===n.dataset.territory)));
        holdingList.querySelectorAll('[data-building]').forEach(n=>n.setAttribute('aria-pressed',String(building?.id===n.dataset.building)));
    }
    function select(type,id){
        if(busy)return;
        selected={type,id};updateSelection();
        if(type==='building'&&currentBuilding?.id!==id){
            document.getElementById('building-id').value=id;
            document.getElementById('building-load').click();
        }
    }
    function view(){const w=extent.w/camera.z,h=extent.h/camera.z;camera.x=Math.max(-w*.15,Math.min(extent.w-w*.85,camera.x));camera.y=Math.max(-h*.15,Math.min(extent.h-h*.85,camera.y));svg.setAttribute('viewBox',[camera.x,camera.y,w,h].join(' '));}
    function zoom(f){const z=Math.max(1,Math.min(3,camera.z*f));camera.x+=(extent.w/camera.z-extent.w/z)/2;camera.y+=(extent.h/camera.z-extent.h/z)/2;camera.z=z;view();}
    svg.addEventListener('pointerdown',e=>{if(e.target.closest('[data-territory]')||e.button!==0)return;drag={id:e.pointerId,x:e.clientX,y:e.clientY};svg.setPointerCapture(e.pointerId);});
    svg.addEventListener('pointermove',e=>{if(drag?.id!==e.pointerId)return;const scale=extent.w/camera.z/Math.max(1,svg.clientWidth);camera.x-=(e.clientX-drag.x)*scale;camera.y-=(e.clientY-drag.y)*scale;drag.x=e.clientX;drag.y=e.clientY;view();});
    for(const event of ['pointerup','pointercancel','lostpointercapture'])svg.addEventListener(event,()=>{drag=null;});
    const poly=(g,points,fill,stroke='none')=>el('polygon',{points,fill,stroke,'stroke-width':1.1},g);
    const rect=(g,x,y,w,h,fill,extra={})=>el('rect',{x,y,width:w,height:h,fill,...extra},g);
    const line=(g,x1,y1,x2,y2,stroke,width=2)=>el('line',{x1,y1,x2,y2,stroke,'stroke-width':width,'stroke-linecap':'round'},g);
    const ellipse=(g,cx,cy,rx,ry,fill,extra={})=>el('ellipse',{cx,cy,rx,ry,fill,...extra},g);
    const path=(g,d,fill,extra={})=>el('path',{d,fill,...extra},g);
    function house(g,x,y,wall,roof,w=32,h=22){
        const a=el('g',{transform:'translate('+x+' '+y+')'},g);
        ellipse(a,0,5,w*.85,10,'#101c2380');
        poly(a,[-w/2,0,w/2,0,w/2,-h,-w/2,-h].join(' '),wall);
        poly(a,[w/2,0,w/2+12,-8,w/2+12,-h-8,w/2,-h].join(' '),'#4b403b');
        poly(a,[-w/2-4,-h,0,-h-19,w/2+4,-h].join(' '),roof,'#362e2a');
        poly(a,[0,-h-19,12,-h-27,w/2+16,-h-8,w/2+4,-h].join(' '),roof);
        rect(a,-4,-11,8,11,'#302a27');rect(a,-w/2+4,-h+5,5,6,'#ffd88d');
    }
    function tree(g,x,y){ellipse(g,x,y+3,13,5,'#142b2580');rect(g,x-2,y-19,4,22,'#634c36');for(let i=0;i<3;i++)poly(g,(x-14+i*3)+','+(y-6-i*10)+' '+x+','+(y-32-i*8)+' '+(x+14-i*3)+','+(y-6-i*10),['#244c36','#376744','#628451'][i]);}
    function factory(g,x,y){
        const a=el('g',{transform:'translate('+x+' '+y+')'},g);
        ellipse(a,0,8,42,12,'#101a2588');
        poly(a,'-35,0 28,0 43,-12 -20,-12','#47555d');
        rect(a,-35,-29,63,29,'#829096');poly(a,'28,-29 43,-41 43,-12 28,0','#37494d');
        poly(a,'-35,-29 -22,-44 -22,-29 -8,-44 -8,-29 6,-44 6,-29 20,-44 28,-29','#bd9876','#303c40');
        for(let i=0;i<5;i++)rect(a,-30+i*11,-20,7,8,'#f1c782');
        rect(a,29,-65,9,41,'#78503d');rect(a,27,-66,13,5,'#c4a18c');
    }
    function tent(g,x,y){
        ellipse(g,x,y+3,30,9,'#382b2470');
        poly(g,(x-27)+','+y+' '+x+','+(y-32)+' '+(x+27)+','+y,'#e5bd83','#784d36');
        poly(g,x+','+(y-32)+' '+(x+27)+','+y+' '+(x+38)+','+(y-7)+' '+(x+11)+','+(y-39),'#a65c43');
        for(let i=-2;i<=2;i++)line(g,x+i*10,y-2,x+i*4,y-23,'#a14b3e',2);
        poly(g,(x-7)+','+y+' '+x+','+(y-18)+' '+(x+7)+','+y,'#593d32');
    }
    // Distinct original silhouettes; no completed building is drawn without a verified stage.
    function model(g,type,detail){
        const p=palettes[theme],level=detail?.level,verified=Number.isSafeInteger(level),pending=detail?.pending!=null;
        const a=el('g',{'data-model':type,'data-stage':!verified?'unknown':pending?'pending':level===0?'foundation':'built'},g);
        ellipse(a,0,3,48,13,'#0b171b70');
        if(!verified||level===0){
            poly(a,'-42,0 0,-20 42,0 0,20','#80755d','#e5ca90');
            poly(a,'-35,0 0,-15 35,0 0,15','#313a32','#d9c18d');
            for(const x of [-29,29]){line(a,x,-2,x,-15,'#d3b57e',3);line(a,x-5,-11,x+5,-11,'#d3b57e',2);}
            if(!verified)el('text',{x:0,y:3,'text-anchor':'middle',fill:'#fff','font-size':12},a,'?');
        }else{
            switch(type){
                case 'lumberyard':
                    house(a,-8,-5,'#c6aa79','#755040',37,22);tree(a,-35,-1);
                    for(let i=0;i<4;i++){rect(a,12+i*4,1+i*3,23,5,'#9b7045',{rx:2});ellipse(a,35+i*4,3+i*3,3,2,'#dfb77d');}
                    break;
                case 'farm':
                    poly(a,'-43,2 0,-17 43,2 0,21','#997a42','#e3c781');
                    for(let i=0;i<7;i++)line(a,-36+i*9,2+i*2,-8+i*7,-11+i*2,'#d9bc5f',3);
                    house(a,12,-14,'#e0c498','#8c5541',24,15);break;
                case 'granary':
                    house(a,0,-4,'#e1c9a2','#956744',51,38);
                    for(let i=0;i<3;i++)line(a,-19+i*17,-36,-19+i*17,-7,'#826645',2);break;
                case 'keep':
                    rect(a,-23,-48,46,48,'#b8b9ad',{stroke:'#595c57'});
                    poly(a,'23,0 39,-10 39,-58 23,-48','#777f77');
                    for(let i=0;i<4;i++)rect(a,-25+i*14,-55,10,10,'#d2d1bf');
                    for(const x of [-15,13])rect(a,x,-36,4,13,'#34454a');
                    path(a,'M-8 0 V-12 A8 8 0 0 1 8 -12 V0 Z','#424946');break;
                case 'power_plant':
                    rect(a,-38,-18,67,18,'#72828a');poly(a,'-38,-18 -23,-28 43,-28 29,-18','#c8b6a1');
                    for(const x of [-23,14]){path(a,'M'+(x-13)+' -5 Q'+(x-6)+' -26 '+(x-9)+' -48 H'+(x+9)+' Q'+(x+6)+' -26 '+(x+13)+' -5 Z','#9faeb0',{stroke:'#475961'});ellipse(a,x,-48,9,3,'#41525a');}
                    line(a,-40,5,40,5,'#e9bc6d',3);break;
                case 'steelworks':
                    factory(a,-3,0);rect(a,-18,-16,18,14,'#4f3930');rect(a,-15,-13,12,8,'#ffb259');
                    break;
                case 'warehouse':
                    rect(a,-42,-27,70,27,'#8b9da0');poly(a,'-42,-27 -27,-43 43,-43 28,-27','#c9a77d');
                    poly(a,'28,0 43,-10 43,-43 28,-27','#53666b');
                    for(let i=0;i<3;i++)rect(a,-36+i*20,-19,15,19,'#394b53');
                    break;
                case 'freight_terminal':
                    for(const y of [0,8])line(a,-46,y,47,y,'#c0b4a2',2);
                    for(let i=0;i<3;i++){rect(a,-41+i*26,-12,24,12,['#a36242','#748d92','#c7a66a'][i]);ellipse(a,-36+i*26,2,4,3,'#29343b');ellipse(a,-22+i*26,2,4,3,'#29343b');}
                    line(a,-24,-2,-24,-49,'#d4ac59',4);line(a,-24,-49,35,-49,'#d4ac59',4);line(a,23,-49,23,-21,'#e4dbc2',1);break;
                case 'market_hall':
                    house(a,0,-9,'#e6c18a','#a55340',48,30);
                    for(let i=0;i<5;i++)rect(a,-28+i*12,-16,11,10,i%2?'#f1d4a1':'#a95342');
                    for(let i=0;i<3;i++)rect(a,-23+i*20,0,12,8,'#ad7645',{stroke:'#e1ba80'});break;
                case 'caravan_hub':
                    tent(a,-12,-3);tent(a,25,3);
                    rect(a,-29,8,27,9,'#bd8b50',{stroke:'#f2cc87'});
                    for(const x of [-24,-7])ellipse(a,x,18,5,5,'#47382d',{stroke:'#d4b384'});
                    line(a,-2,12,14,6,'#caab74',2);break;
                default: house(a,0,0,p[4],p[3],40,28);
            }
            if(level>1){rect(a,31,-45,18,18,'#1a3034',{rx:3,stroke:'#e9cb86'});el('text',{x:40,y:-33,'text-anchor':'middle',fill:'#ffe3a4','font-size':10},a,String(level));}
        }
        if(pending){
            const scaffold=el('g',{class:'scene-scaffold'},a);
            for(const x of [-39,39]){line(scaffold,x,8,x,-48,'#d6b16f',3);line(scaffold,x-4,8,x+4,8,'#d6b16f',2);}
            for(const y of [-10,-31])line(scaffold,-39,y,39,y,'#d6b16f',2);
            line(scaffold,-39,-10,39,-31,'#d6b16f',1);line(scaffold,-39,-31,39,-10,'#d6b16f',1);
            path(scaffold,'M-39 -48 H40 V-44 H-39 Z','#e4c177');
        }
    }
    function renderHud(){
        hud.replaceChildren();
        const item=(key,value,tag)=>{
            const slot=html('div',hud,'scene-stat');if(tag)slot.dataset.stat=tag;
            html('small',slot,'',key);html('strong',slot,'',String(value));return slot;
        };
        if(reading){
            const d=reading.data;
            item(reading.resource+' · '+t('reserve'),d.stock+' / '+d.capacity,'stock');
            item(t('yield'),d.amount,'yield');item(t('cycle'),d.interval,'interval');
        }else item(t('reserve'),t('noSnapshot'),'stock');
        if(campaign?.kind==='medieval'){
            item(t('residents'),campaign.data.population+' / '+campaign.data.capacity,'population');
            item(t('workers'),campaign.data.workers,'workers');
        }else if(campaign?.kind==='industrial'){
            item(t('power'),campaign.data.grid.supply+' / '+campaign.data.grid.load,'grid');
            item(t('freight'),campaign.data.delivery.current+' / '+campaign.data.delivery.required,'delivery');
        }else if(campaign?.kind==='progression')item(t('score'),campaign.data.global,'score');
        if(marketReading!==null)item(t('marketCount'),marketReading,'market');
        const stamp=html('p',hud,'scene-reading',stale?t('stale'):t('snapshot'));
        stamp.dataset.stale=String(stale);
    }
    function renderWorkspace(){
        for(const [block,key] of [[inventoryBlock,'inventory'],[workshop,'workshop'],[objectiveBlock,'objectives'],[tradeBlock,'tradeBoard'],[receiptBlock,'activity']])block.title.textContent=t(key);
        inventoryBlock.body.replaceChildren();
        if(!inventory.size)html('p',inventoryBlock.body,'scene-caption',t('unknownReading'));
        for(const [resource,d] of inventory){
            const row=html('div',inventoryBlock.body,'scene-resource');row.dataset.reserve=resource;
            html('strong',row,'',resource);html('span',row,'',d.stock+' / '+d.capacity);
            const meter=html('progress',row,'scene-meter');meter.max=Math.max(1,d.capacity);meter.value=Math.max(0,Math.min(d.stock,meter.max));meter.setAttribute('aria-label',resource+' '+t('reserve'));
            html('small',row,'',t('yield')+' '+d.amount+' · '+d.interval+' s');
        }
        if(inventory.size)html('p',inventoryBlock.body,'scene-caption',t('inventoryNote'));
        if(balance.elements.resource.options.length>16)html('p',inventoryBlock.body,'scene-caption',t('moreResources'));
        plotLabel.textContent=t('plot');
        plotSelect.replaceChildren();
        const addPlot=(value,label)=>{const o=document.createElement('option');o.value=value;o.textContent=label;plotSelect.append(o);};
        addPlot('',t('noSite'));
        for(const site of catalogue.sites)addPlot(site.territory_id+'|'+site.entity_id,site.location_id+' · '+site.territory_id);
        if(!catalogue.sites.some(s=>s.territory_id+'|'+s.entity_id===chosenSite))chosenSite=catalogue.sites.length===1?catalogue.sites[0].territory_id+'|'+catalogue.sites[0].entity_id:'';
        plotSelect.value=chosenSite;
        catalogueList.replaceChildren();
        if(!catalogue.types.length)html('p',catalogueList,'scene-caption',t('noTypes'));
        for(const type of catalogue.types){
            const card=html('button',catalogueList,'scene-catalogue-card');card.type='button';card.dataset.catalogue=type.id;
            card.setAttribute('aria-pressed',String(chosenType===type.id));
            const icon=el('svg',{viewBox:'0 0 120 96','aria-hidden':'true'},card);
            model(el('g',{transform:'translate(60 73) scale(.8)'},icon),type.id,{level:1,pending:null});
            html('strong',card,'',type.id);
            html('small',card,'',Object.entries(type.first.costs).map(([k,v])=>v+' '+k).join(' · '));
            html('small',card,'',String(type.first.duration)+' s');
            card.addEventListener('click',()=>{if(!busy){chosenType=type.id;renderWorkspace();}});
        }
        place.textContent=t('place');place.hidden=!chosenSite||!chosenType||!catalogue.types.some(x=>x.id===chosenType);
        objectiveBlock.body.replaceChildren();
        objectiveBlock.root.hidden=theme!=='medieval'&&theme!=='industrial';
        if(!campaign||!['medieval','industrial'].includes(campaign.kind))html('p',objectiveBlock.body,'scene-caption',t('unknownReading'));
        else {
            const data=campaign.data;
            const rows=Object.entries(data.victory.buildings).map(([name,v])=>[name,v.current,v.required]);
            if(campaign.kind==='medieval')rows.push([t('residents'),data.population,data.victory.populationRequired]);
            else rows.push([t('freight'),data.delivery.current,data.delivery.required]);
            for(const [name,current,required] of rows){
                const row=html('div',objectiveBlock.body,'scene-resource');row.dataset.objective=name;
                row.dataset.achieved=String(current>=required);
                html('strong',row,'',name);html('span',row,'',current+' / '+required);
                const meter=html('progress',row,'scene-meter');meter.max=Math.max(1,required);meter.value=Math.max(0,Math.min(current,meter.max));meter.setAttribute('aria-label',name);
            }
            if(data.victory.achieved)html('p',objectiveBlock.body,'scene-victory',t('achieved'));
        }
        tradeBlock.root.hidden=theme!=='guild';
        tradeList.replaceChildren();
        if(!offers.length)html('p',tradeList,'scene-caption',t('noOffers'));
        for(const offer of offers){
            const row=html('button',tradeList,'scene-offer');row.type='button';row.dataset.offer=offer.id;
            html('strong',row,'',offer.giveUnits+' '+offer.giveResource+' ↔ '+offer.wantUnits+' '+offer.wantResource);
            html('small',row,'',offer.seller);row.addEventListener('click',()=>{if(!busy){chosenOffer=offer.id;tradeSelection();}});
        }
        if(offerMore)html('p',tradeList,'scene-caption',t('moreOffers'));
        tradeSelection();
        receiptBlock.body.replaceChildren();
        if(!receipts.length)html('p',receiptBlock.body,'scene-caption',t('emptyActivity'));
        for(const entry of receipts){
            const row=html('div',receiptBlock.body,'scene-receipt');row.dataset.command=entry.type;
            html('span',row,'scene-receipt-mark','✓');
            html('strong',row,'',actionLabels[entry.type]?.[lang()==='fr'?1:0]??entry.type);
            html('small',row,'',t('receipt'));
        }
        workspace.querySelectorAll('[data-label]').forEach(b=>b.textContent=t(b.dataset.label));
    }
    function tile(g,x,y,territory,index){
        const p=palettes[theme],a=el('g',{transform:'translate('+x+' '+y+')',tabindex:0,role:'button','aria-label':territory.location+' · '+territory.kind,'data-territory':territory.id,'aria-pressed':'false',class:'scene-territory'},g);
        el('title',{},a,territory.id+' · '+territory.location+' · '+territory.kind+' · '+territory.control);
        poly(a,'-115,0 0,49 115,0 115,14 0,63 -115,14',p[0],'#111e22');
        poly(a,'-115,0 0,-49 115,0 0,49',p[1],'#233b39');
        for(let i=0;i<5;i++)line(a,-78+i*9,0+i*3,-16+i*9,-27+i*3,p[2],2);
        // Terrain accents are decorative; holdings are drawn exclusively from the owned catalogue.
        if(theme==='medieval'){tree(a,-80,-5);tree(a,-62,3);tree(a,72,0);for(let i=0;i<6;i++)ellipse(a,-33+i*12,22-i*3,3,1.5,'#c4bd7a');}
        else if(theme==='industrial'){line(a,-85,10,-20,35,'#c3a477',4);line(a,-87,16,-22,41,'#25323b',2);for(let i=0;i<5;i++)line(a,-76+i*11,11+i*4,-82+i*11,17+i*4,'#a2a49b',1);}
        else if(theme==='guild'){path(a,'M-87 4 Q-38 24 18 7 T87 -4','none',{stroke:'#d7ad6c','stroke-width':5});for(let i=0;i<5;i++)ellipse(a,-70+i*34,16-i*4,5,2,'#82664b');}
        else {poly(a,'-30,5 0,-27 29,5',p[2]);ellipse(a,34,13,16,5,p[5]);}
        const owned=[...buildings.values()].filter(b=>b.territory===territory.id);
        owned.slice(0,3).forEach((b,i)=>{
            const layer=el('g',{transform:'translate('+((i-(Math.min(owned.length,3)-1)/2)*52)+' '+(i%2?17:0)+') scale(.65)','data-map-holding':b.id},a);
            model(layer,b.type,details.get(b.id));
        });
        if(owned.length>3)el('text',{x:75,y:35,fill:'#fff','font-size':11},a,'+'+(owned.length-3));
        rect(a,-100,51,200,35,'#15232bea',{rx:7,stroke:'#ffffff35'});
        el('text',{x:0,y:66,'text-anchor':'middle',class:'scene-node-label'},a,territory.location.length>26?territory.location.slice(0,24)+'…':territory.location);
        el('text',{x:0,y:79,'text-anchor':'middle',class:'scene-node-meta'},a,territory.kind+' · '+territory.control);
        a.addEventListener('click',()=>select('territory',territory.id));
        a.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();select('territory',territory.id);}});
    }
    function render(){
        host.dataset.theme=theme; title.textContent=t(theme);
        campaignButton.hidden=theme==='neutral'||(theme==='guild'&&document.getElementById('progression-panel').hidden);
        marketButton.hidden=theme!=='guild';
        for(const op of operations)op.control.hidden=op.pack!==theme;
        renderHud();
        summary.textContent=territories.size+' '+t('territories')+' · '+buildings.size+' '+t('registered');
        hint.textContent=t('caption');holdingTitle.textContent=t('holdings');
        host.querySelectorAll('[data-label]').forEach(b=>b.textContent=t(b.dataset.label));
        svg.setAttribute('aria-label',t(theme));
        svg.replaceChildren(); holdingList.replaceChildren();
        const nodes=[...territories.values()],cols=Math.min(3,Math.max(1,Math.ceil(Math.sqrt(nodes.length))));
        extent={w:900,h:Math.max(460,Math.ceil(nodes.length/cols)*170+120)};
        const p=palettes[theme],defs=el('defs',{},svg),gradient=el('linearGradient',{id:'scene-bg',x2:'0',y2:'1'},defs);
        el('stop',{offset:'0%','stop-color':p[5]},gradient);el('stop',{offset:'100%','stop-color':p[0]},gradient);
        rect(svg,0,0,extent.w,extent.h,'url(#scene-bg)');
        for(let i=0;i<extent.h/27;i++)path(svg,'M0 '+(i*27)+' Q225 '+(i*27-45)+' 450 '+(i*27)+' T900 '+(i*27),'none',{stroke:p[2],'stroke-opacity':.09,'stroke-width':1});
        const positions=new Map();
        nodes.forEach((n,i)=>{const x=450+((i%cols)-(cols-1)/2)*270,y=110+Math.floor(i/cols)*170;positions.set(n.location,{x,y});});
        for(const [from,neighbors] of links)for(const n of neighbors){const a=positions.get(from),b=positions.get(n.location);if(!a||!b)continue;const l=line(svg,a.x,a.y,b.x,b.y,p[2],5);l.setAttribute('stroke-dasharray','7 5');el('title',{},l,from+' → '+n.location+' · '+n.cost);}
        for(let i=1;i<route.length;i++){const a=positions.get(route[i-1]),b=positions.get(route[i]);if(a&&b){const road=line(svg,a.x,a.y,b.x,b.y,'#ffe095',8);road.setAttribute('class','scene-confirmed-route');}}
        nodes.forEach((n,i)=>{const q=positions.get(n.location);tile(svg,q.x,q.y,n,i);});
        if(!nodes.length)el('text',{x:450,y:220,'text-anchor':'middle',class:'scene-empty'},svg,t('empty'));
        if(!buildings.size)html('p',holdingList,'scene-caption',t('none'));
        for(const b of buildings.values()){
            const card=html('button',holdingList,'scene-holding');card.type='button';card.dataset.building=b.id;
            const d=details.get(b.id);
            card.dataset.level=d?String(d.level):'unknown';
            card.dataset.stage=!d?'unknown':d.pending?'pending':d.level===0?'foundation':'built';
            const art=el('svg',{viewBox:'0 0 130 100','aria-hidden':'true'},card);
            const layer=el('g',{transform:'translate(63 75)'},art);
            model(layer,b.type,d);
            html('strong',card,'',b.type);html('span',card,'',b.id);
            html('small',card,'scene-stage',d?t('level')+' '+d.level+' · '+t(d.pending?'pending':d.level===0?'foundation':'ready'):t('planned'));
            card.addEventListener('click',()=>select('building',b.id));
        }
        renderWorkspace();view();updateSelection();
    }
    const on=(name,fn)=>window.addEventListener('aeternum:'+name,e=>fn(e.detail));
    on('context',d=>{theme=d.theme??(d.world?theme:'neutral');reading=null;campaign=null;marketReading=null;stale=false;inventory.clear();catalogue={sites:[],types:[]};chosenSite='';chosenType='';offers=[];offerMore=false;chosenOffer='';receipts=[];territories.clear();buildings.clear();details.clear();links.clear();route=[];selected=null;camera={x:0,y:0,z:1};render();});
    on('territories',d=>{if(!d.append){territories.clear();links.clear();route=[];selected=null;}for(const n of d.territories)territories.set(n.id,n);render();});
    on('links',d=>{links.set(d.location,d.neighbors);render();});
    on('route',d=>{route=d?.locations??[];render();});
    on('buildings',d=>{catalogue={sites:d.sites,types:d.types};buildings=new Map(d.buildings.map(b=>[b.id,b]));for(const id of details.keys())if(!buildings.has(id))details.delete(id);render();});
    on('building',d=>{buildings.set(d.id,d);details.set(d.id,d);render();});
    on('reserve',d=>{reading=d;stale=false;renderHud();});
    on('campaign',d=>{campaign=d;renderHud();renderWorkspace();});
    on('market-snapshot',d=>{marketReading=d.count;offers=d.offers??[];offerMore=d.more===true;chosenOffer='';renderHud();renderWorkspace();});
    on('confirmed',d=>{reading=null;campaign=null;marketReading=null;inventory.clear();offers=[];offerMore=false;chosenOffer='';stale=true;receipts=[{type:d.type},...receipts].slice(0,8);renderHud();renderWorkspace();});
    on('resource-change',()=>{reading=null;renderHud();});
    on('activity',d=>{host.setAttribute('aria-busy',String(d.busy));if(!d.busy)updateSelection();});
    document.getElementById('locale-select').addEventListener('change',render);
    document.getElementById('map-location').addEventListener('input',updateSelection);
    document.getElementById('map-destination').addEventListener('input',updateSelection);
    render();
})();
