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
    Object.assign(locales.en,{plotId:'Optional foundation reference',yourHomestead:'Your homestead',occupiedHomestead:'Occupied homestead',unclaimedHomestead:'Unclaimed homestead',yourTerritory:'Your territory',occupiedTerritory:'Occupied territory',unclaimedTerritory:'Unclaimed territory',choosePlot:'Choose a plot',blueprint:'Architecture preview'});
    Object.assign(locales.fr,{plotId:'Référence facultative de la fondation',yourHomestead:'Votre domaine',occupiedHomestead:'Domaine occupé',unclaimedHomestead:'Domaine libre',yourTerritory:'Votre territoire',occupiedTerritory:'Territoire occupé',unclaimedTerritory:'Territoire libre',choosePlot:'Choisir une parcelle',blueprint:'Aperçu architectural'});
    Object.assign(locales.en,{district:'Domain view',atlasView:'World atlas',districtTitle:'Your domain',districtEmpty:'Select an owned territory or load your holdings',districtNote:'Illustrated district layout · buildings and stages reflect server records',previousPlot:'Previous district',nextPlot:'Next district',previousPage:'Previous buildings',nextPage:'Next buildings',merchant:'Merchant',myOffer:'Your offer',plotNumber:'Plot',buildingNumber:'Holding',unknownType:'Building',diagnostics:'Technical reference',districtRefresh:'Refresh district',layoutPage:'Buildings',terrain:'Landscape',siteOwned:'Owned plot',stageReady:'Stage complete'});
    Object.assign(locales.fr,{district:'Vue du domaine',atlasView:'Atlas du monde',districtTitle:'Votre domaine',districtEmpty:'Sélectionnez un territoire possédé ou chargez vos propriétés',districtNote:'Implantation illustrée · bâtiments et stades issus du serveur',previousPlot:'Domaine précédent',nextPlot:'Domaine suivant',previousPage:'Bâtiments précédents',nextPage:'Bâtiments suivants',merchant:'Marchand',myOffer:'Votre offre',plotNumber:'Parcelle',buildingNumber:'Propriété',unknownType:'Bâtiment',diagnostics:'Référence technique',districtRefresh:'Actualiser le domaine',layoutPage:'Bâtiments',terrain:'Paysage',siteOwned:'Parcelle possédée',stageReady:'Stade achevé'});
    const names={
        lumberyard:['Lumberyard','Scierie'],farm:['Farm','Ferme'],granary:['Granary','Grenier'],keep:['Keep','Donjon'],
        power_plant:['Power plant','Centrale électrique'],steelworks:['Steelworks','Aciérie'],warehouse:['Warehouse','Entrepôt'],freight_terminal:['Freight terminal','Terminal de fret'],
        market_hall:['Market hall','Halle marchande'],caravan_hub:['Caravan hub','Relais caravanier'],mill:['Mill','Moulin'],workshop:['Workshop','Atelier'],
        wood:['Wood','Bois'],grain:['Grain','Céréales'],stone:['Stone','Pierre'],iron:['Iron','Fer'],coal:['Coal','Charbon'],electricity:['Electricity','Électricité'],energy:['Energy','Énergie'],steel:['Steel','Acier'],goods:['Goods','Marchandises'],spice:['Spice','Épices'],silver:['Silver','Argent'],gold:['Gold','Or'],food:['Food','Nourriture'],water:['Water','Eau'],oil:['Oil','Pétrole']
    };
    function displayName(value){
        const key=String(value??'');
        if(Object.hasOwn(names,key))return names[key][lang()==='fr'?1:0];
        if(isTechnicalId(key))return t('unknownType');
        return key.replaceAll('_',' ').replace(/^\w/,c=>c.toUpperCase());
    }
    Object.assign(locales.en,{collect:'Collect production',upgradeResource:'Upgrade production',resourceLevel:'Production level',selectResource:'Select resource',unknownStock:'Read reserve',maxLevel:'Maximum level',refreshReserves:'Refresh reserves',pendingUpgrade:'Upgrade pending',lastKnown:'Last reading',confirmedAction:'Action confirmed',terrainDetail:'Landscape illustration'});
    Object.assign(locales.fr,{collect:'Récolter la production',upgradeResource:'Améliorer la production',resourceLevel:'Niveau de production',selectResource:'Sélectionner la ressource',unknownStock:'Lire la réserve',maxLevel:'Niveau maximal',refreshReserves:'Actualiser les réserves',pendingUpgrade:'Amélioration en cours',lastKnown:'Dernier relevé',confirmedAction:'Action confirmée',terrainDetail:'Paysage illustré'});
    Object.assign(locales.en,{districtZoomIn:'Zoom domain in',districtZoomOut:'Zoom domain out',districtReset:'Reset domain camera',cameraHelp:'Drag empty terrain to pan; use +, −, arrows or Home with the map focused.',chooseBuild:'Choose construction plot',recentAction:'Latest confirmed activity',showBuild:'Open construction catalogue'});
    Object.assign(locales.fr,{districtZoomIn:'Agrandir le domaine',districtZoomOut:'Réduire le domaine',districtReset:'Recentrer le domaine',cameraHelp:'Glissez sur le terrain pour déplacer la vue ; utilisez +, −, les flèches ou Début lorsque la carte est sélectionnée.',chooseBuild:'Choisir la parcelle',recentAction:'Dernière activité confirmée',showBuild:'Ouvrir le catalogue'});
    Object.assign(locales.en,{botOpponents:'Automated opponents',botReady:'Ready',botActive:'Active',botBlocked:'Blocked',botIdle:'Idle',botRefresh:'Refresh opponents',botBuilt:'Built structures',botBuilder:'Builder',botProducer:'Producer',botMerchant:'Merchant',botBalanced:'Balanced',botStale:'Last report is stale'});
    Object.assign(locales.fr,{botOpponents:'Adversaires automatisés',botReady:'Prêt',botActive:'Actif',botBlocked:'Bloqué',botIdle:'En attente',botRefresh:'Actualiser les adversaires',botBuilt:'Bâtiments achevés',botBuilder:'Bâtisseur',botProducer:'Producteur',botMerchant:'Marchand',botBalanced:'Équilibré',botStale:'Dernier relevé ancien'});
    const lang=()=>document.getElementById('locale-select').value==='fr'?'fr':'en';
    const t=k=>locales[lang()][k];
    const palettes={
        medieval:['#244a38','#57784a','#b8a76b','#79513e','#d9c394','#304d57'],
        industrial:['#263940','#53656a','#a69b7c','#a56037','#c2cdd0','#24313a'],
        guild:['#71503a','#a47848','#e0b878','#a14b3e','#f3d6a4','#42565a'],
        neutral:['#304e53','#557076','#9aa88e','#786855','#d3d8c4','#253c48']
    };
    let districtMode=false,districtId='',districtPage=0;
    let lighting='day',ambienceMotion=true;
    let theme='neutral',territories=new Map(),buildings=new Map(),details=new Map(),links=new Map(),route=[],selected=null,camera={x:0,y:0,z:1},extent={w:900,h:460};
    const atlasGate=window.AeternumArt.renderGate(),districtGate=window.AeternumArt.renderGate(),holdingGate=window.AeternumArt.renderGate();
    const stageSignature=b=>[b.id,b.type,b.territory,details.get(b.id)?.level??null,Boolean(details.get(b.id)?.pending)];
    const renderCounts=(node,gate)=>{const counts=gate.stats();node.dataset.renderDraws=String(counts.draws);node.dataset.renderHits=String(counts.hits);};
    const header=html('div',host,'scene-header'),title=html('h3',header,'scene-title'),summary=html('p',header,'scene-summary');
    const hud=html('div',host,'scene-hud');
    let reading=null,campaign=null,marketReading=null,stale=false,lastEconomicEvent=null;
    let stagesKnown=false,scoreReading=null,rankReading=null,campaignStale=false;
    let moduleReading=null,moduleTarget='',moduleTargets=[];
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
        'economy.settle':['Production settled','Production acquise'],
        'pack.campaign.research':['Research scheduled','Recherche planifiée'],
        'pack.campaign.expedition':['Expedition scheduled','Expédition planifiée'],
        'pack.guild.challenge':['Challenge confirmed','Défi confirmé'],
        'pack.diplomacy.offer':['Truce proposed','Trêve proposée'],
        'pack.diplomacy.accept':['Truce accepted','Trêve acceptée']
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
        inventory=results;stale=false;renderHud();renderWorkspace();updateSelection();
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
        // Observe a transition from a previously confirmed pending stage.
        // Initial reads and repeated polls never play a completion cue.
        const completed=[...next].some(([id,d])=>details.get(id)?.pending&&d.level>(details.get(id)?.level??0));
        details=next;stagesKnown=true;if(completed)audio.cue('building.complete');render();
        // A selected building's command form must follow real worker completion.
        const updated=currentBuilding?next.get(currentBuilding.id):null;
        if(updated&&currentBuilding.account===f.account&&(updated.level!==currentBuilding.level||Boolean(updated.pending)!==Boolean(currentBuilding.pending)))await loadBuilding();
    }
    const campaignButton=button(toolbar,'campaignSync',()=>{
        const id=theme==='medieval'?'medieval-check':theme==='industrial'?'industrial-check':'progression-load';
        document.getElementById(id).click();
    });
    const marketButton=button(toolbar,'marketSync',()=>document.getElementById('market-load').click());
    const svg=el('svg',{class:'scene-atlas',role:'group','aria-label':'Strategic atlas',viewBox:'0 0 900 460'});
    host.append(svg);
    svg.setAttribute('tabindex','0');
    const atlasPanel=html('section',host,'atlas-expedition');atlasPanel.hidden=true;
    atlasPanel.setAttribute('aria-labelledby','atlas-expedition-title');
    const atlasTitle=html('h3',atlasPanel,'');atlasTitle.id='atlas-expedition-title';
    const atlasSummary=html('p',atlasPanel,'atlas-expedition-summary');
    const atlasDirectory=html('label',atlasPanel,'atlas-directory');
    const atlasDirectoryLabel=html('span',atlasDirectory,'');
    const atlasDirectorySelect=html('select',atlasDirectory,'');atlasDirectorySelect.dataset.atlasDirectory='true';
    let atlasPositions=new Map();
    atlasDirectorySelect.addEventListener('change',()=>{
        const id=atlasDirectorySelect.value;if(!id||busy||!territories.has(id))return;
        select('territory',id);centerAtlas(id);
    });
    function centerAtlas(id){
        const n=territories.get(id),p=n?atlasPositions.get(n.location):null;
        if(!p)return;
        // Keep a region and its label comfortably within the camera.
        const z=narrow.matches?1.05:1.65;
        const size=atlasViewSize(z);
        camera={x:p.x-size.w/2,y:p.y+12-size.h/2,z};view();
    }
    const atlasControls=html('div',atlasPanel,'atlas-expedition-controls');
    const atlasButton=(key,fn)=>{const b=html('button',atlasControls,'atlas-command');b.type='button';b.dataset.atlasCommand=key;b.addEventListener('click',()=>{if(!busy)fn();});return b;};
    const atlasOpen=atlasButton('domain',()=>{if(selected?.type==='territory'&&districtIds().includes(selected.id)){districtId=selected.id;showDistrict(true);}});
    const atlasFrom=atlasButton('origin',()=>{setLocation('map-location');clearAtlasRoute();render();});
    const atlasTo=atlasButton('destination',()=>{setLocation('map-destination');clearAtlasRoute();render();});
    const atlasLinks=atlasButton('connections',()=>{setLocation('map-location');clearAtlasRoute();document.getElementById('map-links-load').click();});
    const atlasTrace=atlasButton('route',()=>document.getElementById('map-route-load').click());
    const atlasMore=atlasButton('more',()=>document.getElementById('map-next').click());
    const atlasEndpoints=html('div',atlasPanel,'atlas-endpoints');
    const atlasRoute=html('p',atlasPanel,'atlas-route-summary');atlasRoute.setAttribute('aria-live','polite');
    const atlasLegend=html('p',atlasPanel,'atlas-legend');
    let routeRead=false,routeCost=null;
    function clearAtlasRoute(){route=[];routeRead=false;routeCost=null;}
    function renderAtlasPanel(){
        atlasPanel.hidden=classic||!selectedGame||districtMode||theme==='neutral';
        if(atlasPanel.hidden)return;
        const fr=lang()==='fr',label=(en,frText)=>fr?frText:en;
        atlasTitle.textContent={medieval:label('Survey the realm','Explorer le royaume'),industrial:label('Regional network','Réseau régional'),guild:label('Merchant roads','Routes marchandes')}[theme];
        const current=selected?.type==='territory'?territories.get(selected.id):null;
        const own=current&&districtIds().includes(current.id);
        const state=current?label(current.control==='own'?'Your territory':current.control==='occupied'?'Occupied territory':'Unclaimed territory',current.control==='own'?'Votre territoire':current.control==='occupied'?'Territoire occupé':'Territoire libre'):'';
        atlasSummary.textContent=current?locationName(current)+' · '+state:label('Select a territory to explore its registered connections.','Sélectionnez un territoire pour explorer ses liaisons enregistrées.');
        atlasDirectoryLabel.textContent=label('Go to territory','Aller au territoire');
        atlasDirectorySelect.replaceChildren();
        const empty=html('option',atlasDirectorySelect,'',label('Choose a territory','Choisir un territoire'));empty.value='';
        const ordered=[...territories.values()].sort((a,b)=>Number(b.control==='own')-Number(a.control==='own')||locationName(a).localeCompare(locationName(b),fr?'fr':'en'));
        for(const n of ordered){const o=html('option',atlasDirectorySelect,'',locationName(n));o.value=n.id;}
        atlasDirectorySelect.value=current?.id??'';atlasDirectorySelect.disabled=busy||!ordered.length;
        atlasOpen.textContent=label('Enter domain','Entrer dans le domaine');atlasOpen.hidden=!own;
        atlasFrom.textContent=label('Set departure','Définir le départ');atlasTo.textContent=label('Set destination','Définir l’arrivée');
        atlasLinks.textContent=label('Survey connections','Consulter les liaisons');atlasTrace.textContent=label('Find route','Calculer l’itinéraire');atlasMore.textContent=label('More territories','Autres territoires');
        atlasFrom.disabled=atlasTo.disabled=atlasLinks.disabled=busy||!current;
        atlasOpen.disabled=atlasMore.disabled=busy;atlasMore.hidden=document.getElementById('map-next').hidden;
        const from=document.getElementById('map-location').value,to=document.getElementById('map-destination').value;
        atlasTrace.disabled=busy||!from||!to;
        const name=v=>{const n=[...territories.values()].find(x=>x.location===v);return n?locationName(n):v&&!isTechnicalId(v)?v:label('Location outside loaded map','Lieu hors carte chargée');};
        atlasEndpoints.textContent=label('Departure: ','Départ : ')+(from?name(from):'—')+'  ·  '+label('Destination: ','Arrivée : ')+(to?name(to):'—');
        atlasPanel.dataset.routeState=!routeRead?'unread':route.length?'found':'none';
        if(routeRead)atlasRoute.textContent=route.length?route.map(name).join(' → ')+' · '+label('Cost ','Coût ')+routeCost:label('No directed route found by the server.','Aucun itinéraire orienté trouvé par le serveur.');
        else if(current&&links.has(current.location))atlasRoute.textContent=label('Registered outgoing connections: ','Liaisons sortantes enregistrées : ')+links.get(current.location).length;
        else atlasRoute.textContent=label('Connections not yet surveyed.','Liaisons non encore consultées.');
        atlasLegend.textContent=label('Illustrative terrain · real territories and server-confirmed connections only.','Terrain illustré · territoires réels et liaisons confirmées par le serveur uniquement.');
    }
    const districtBar=html('div',host,'scene-district-bar');
    const districtToggle=button(districtBar,'district',()=>{if(busy)return;districtMode=!districtMode;districtPage=0;render();});
    const districtPrev=button(districtBar,'previousPlot',()=>moveDistrict(-1));
    const districtNext=button(districtBar,'nextPlot',()=>moveDistrict(1));
    const pagePrev=button(districtBar,'previousPage',()=>{districtPage=Math.max(0,districtPage-1);renderDistrict();});
    const pageNext=button(districtBar,'nextPage',()=>{districtPage++;renderDistrict();});
    button(districtBar,'districtRefresh',()=>run(async()=>{await loadMap();await readStages();if(selectedGame){await readInventory();if(theme==='medieval')await reviewMedieval();else if(theme==='industrial')await reviewIndustrial();}}));
    const districtSvg=el('svg',{class:'scene-district',role:'group',viewBox:'0 0 900 500'});
    host.append(districtSvg);
    districtSvg.setAttribute('tabindex','0');
    const domainCamera=window.AeternumArt.camera(districtSvg,{onChange:state=>districtSvg.dataset.zoom=String(state.z)});
    const districtZoomIn=button(districtBar,'districtZoomIn',()=>domainCamera.zoom(1.25));
    const districtZoomOut=button(districtBar,'districtZoomOut',()=>domainCamera.zoom(.8));
    const districtReset=button(districtBar,'districtReset',()=>domainCamera.reset());
    const districtCaption=html('p',host,'scene-caption');
    // Sound is opt-in and entirely client-side. It is never an economic signal
    // until the existing server receipt or authoritative stage confirms it.
    const soundPanel=html('details',host,'sound-panel');soundPanel.hidden=true;
    const soundSummary=html('summary',soundPanel,'sound-summary');
    const soundToggle=html('button',soundPanel,'scene-button');soundToggle.type='button';soundToggle.dataset.audioToggle='true';
    const soundStatus=html('p',soundPanel,'sound-status');soundStatus.setAttribute('role','status');
    const soundMix=html('div',soundPanel,'sound-mix');
    const soundInputs={};
    for(const key of ['music','ambience','effects']){
        const label=html('label',soundMix,'sound-channel');
        const title=html('span',label,'');
        const input=html('input',label,'');input.type='range';input.min='0';input.max='100';input.step='5';input.dataset.audioVolume=key;
        const value=html('span',label,'sound-value');
        soundInputs[key]={title,input,value};
        input.addEventListener('input',()=>audio.setVolume(key,Number(input.value)/100));
    }
    let audioStarting=false,soundVictory=false;
    const soundSeen=new Set();
    const audio=window.AeternumAudio.create({onChange:renderSound});
    soundToggle.addEventListener('click',async()=>{
        if(audioStarting)return;
        if(audio.state().enabled){audio.disable();return;}
        audioStarting=true;renderSound();
        try{await audio.enable();}finally{audioStarting=false;renderSound();}
    });
    function renderSound(){
        const fr=lang()==='fr',label=(en,f)=>fr?f:en,state=audio.state();
        soundPanel.hidden=classic||!selectedGame||theme==='neutral';
        soundSummary.textContent=label('Sound & music','Son et musique');
        soundToggle.textContent=state.enabled?label('Mute all sound','Couper le son'):label('Enable sound','Activer le son');
        soundToggle.disabled=audioStarting||!state.supported;
        soundToggle.setAttribute('aria-pressed',String(state.enabled));
        soundPanel.dataset.audioEnabled=String(state.enabled);
        soundStatus.textContent=!state.supported?label('Web Audio is unavailable in this browser.','Web Audio est indisponible dans ce navigateur.'):state.error?label('Sound could not start. Try again.','Le son n’a pas pu démarrer. Réessayez.'):state.enabled?label('Original soundscape enabled. Paused when this tab is hidden.','Ambiance originale activée. Suspendue lorsque cet onglet est masqué.'):label('Silent until you enable sound.','Silencieux jusqu’à votre activation du son.');
        const names={music:label('Music','Musique'),ambience:label('Ambience','Ambiance'),effects:label('Action sounds','Bruitages')};
        for(const [key,{title,input,value}] of Object.entries(soundInputs)){
            title.textContent=names[key];input.value=String(Math.round(state.volumes[key]*100));value.textContent=input.value+' %';
        }
    }
    function soundReceipt(d){
        if(typeof d.id!=='string'||soundSeen.has(d.id))return;
        if(soundSeen.size>=256)soundSeen.delete(soundSeen.values().next().value);
        soundSeen.add(d.id);audio.cue(d.type);
    }
    function soundVisibility(){audio.setVisible(!document.hidden);}
    document.addEventListener('visibilitychange',soundVisibility);
    window.addEventListener('pagehide',()=>audio.disable());
    const ambiencePanel=html('section',host,'ambience-panel');ambiencePanel.hidden=true;
    const ambienceTitle=html('h3',ambiencePanel,'ambience-title');ambienceTitle.id='ambience-title';
    ambiencePanel.setAttribute('aria-labelledby',ambienceTitle.id);
    const ambienceControls=html('div',ambiencePanel,'ambience-controls');
    const phaseControls=new Map();
    for(const phase of window.AeternumLivingArt.phases){
        const control=html('button',ambienceControls,'ambience-phase');
        control.type='button';control.dataset.lightingChoice=phase;
        control.addEventListener('click',()=>{
            if(lighting===phase||busy)return;
            lighting=phase;render();
        });
        phaseControls.set(phase,control);
    }
    const motionControl=html('button',ambienceControls,'ambience-motion-control');motionControl.type='button';motionControl.dataset.ambienceMotion='toggle';
    motionControl.addEventListener('click',()=>{ambienceMotion=!ambienceMotion;renderAmbience();});
    const ambienceNote=html('p',ambiencePanel,'ambience-note');
    const motionPreference=window.matchMedia('(prefers-reduced-motion: reduce)');
    motionPreference.addEventListener('change',renderAmbience);
    function renderAmbience(){
        const fr=lang()==='fr',label=(en,frText)=>fr?frText:en;
        host.dataset.lighting=lighting;
        host.dataset.ambienceMotion=ambienceMotion&&!motionPreference.matches?'on':'off';
        ambiencePanel.hidden=classic||!selectedGame||theme==='neutral';
        if(ambiencePanel.hidden)return;
        ambienceTitle.textContent=label('Atmosphere','Ambiance visuelle');
        const names={day:label('Daylight','Jour'),dusk:label('Dusk','Crépuscule'),night:label('Moonlight','Nuit')};
        for(const [phase,control] of phaseControls){
            control.textContent=names[phase];control.setAttribute('aria-pressed',String(lighting===phase));control.disabled=busy;
        }
        motionControl.textContent=motionPreference.matches?label('Reduced motion','Animations réduites'):label('Ambient motion','Animations ambiantes');
        motionControl.disabled=motionPreference.matches;
        motionControl.setAttribute('aria-pressed',String(ambienceMotion&&!motionPreference.matches));
        ambienceNote.textContent=label('Visual setting only · no change to world time, rules or resources.','Réglage visuel uniquement · sans effet sur le temps du monde, les règles ou les ressources.');
    }
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
        if(site){chosenSite=site.territory_id+'|'+site.entity_id;renderWorkspace();focusNode(workshop.root);}
    });
    function stageCommand(id){
        if(busy||selected?.type!=='building'||currentBuilding?.id!==selected.id||currentBuilding.account!==locationFields().account)return;
        const control=document.getElementById(id);if(!control.hidden)control.click();
    }
    // A building's visual inspection stays beside the map instead of sending
    // the player to a technical form. All mutations use the existing guarded
    // controls and canonical command pipeline.
    const districtCommand=html('section',host,'district-command');districtCommand.hidden=true;
    districtCommand.setAttribute('aria-labelledby','district-command-title');
    const districtPreview=el('svg',{viewBox:'0 0 128 110','aria-hidden':'true',class:'district-command-art'},districtCommand);
    const districtCommandBody=html('div',districtCommand,'district-command-body');
    const districtCommandTitle=html('h3',districtCommandBody,'');districtCommandTitle.id='district-command-title';
    const districtCommandState=html('p',districtCommandBody,'district-command-state');
    const districtCommandCosts=html('div',districtCommandBody,'district-command-costs');
    const districtCommandHint=html('p',districtCommandBody,'district-command-hint');
    const districtCommandButtons=html('div',districtCommand,'district-command-buttons');
    const districtPrimary=html('button',districtCommandButtons,'district-command-primary');districtPrimary.type='button';districtPrimary.dataset.districtCommand='primary';
    const districtDismiss=html('button',districtCommandButtons,'district-command-dismiss');districtDismiss.type='button';districtDismiss.dataset.districtCommand='dismiss';
    let districtPrimaryAction=null;
    districtPrimary.addEventListener('click',()=>{if(!busy)districtPrimaryAction?.();});
    districtDismiss.addEventListener('click',()=>{if(!busy){selected=null;updateSelection();districtSvg.focus({preventScroll:true});}});
    function renderDistrictCommand(b,d){
        districtCommand.hidden=classic||!selectedGame||!districtMode||!b||b.territory!==districtId;
        districtPrimaryAction=null;
        if(districtCommand.hidden)return;
        const fr=lang()==='fr',label=(en,frText)=>fr?frText:en;
        districtCommand.dataset.stage=!d?'unknown':d.pending?'pending':d.level===0?'foundation':'built';
        districtPreview.replaceChildren();
        model(el('g',{transform:'translate(64 80) scale(.85)'},districtPreview),b.type,d);
        districtCommandTitle.textContent=buildingName(b);
        districtCommandState.textContent=d?t('level')+' '+d.level+' · '+t(d.pending?'pending':d.level===0?'foundation':'ready'):t('planned');
        districtCommandCosts.replaceChildren();
        districtCommandHint.textContent='';
        districtPrimary.hidden=false;districtPrimary.disabled=busy;
        districtDismiss.textContent=label('Close','Fermer');districtDismiss.disabled=busy;
        if(!d||currentBuilding?.id!==b.id){
            districtPrimary.textContent=label('Inspect','Inspecter');
            districtPrimaryAction=()=>inspectBuilding(b.id);
        }else if(d.pending){
            districtPrimary.textContent=label('Check progress','Vérifier les travaux');
            districtPrimaryAction=()=>run(readStages);
            districtCommandHint.textContent=label('The worker completes this project; the map follows the server.','Le worker termine ce chantier ; la carte suit le serveur.');
        }else if(d.next&&!stageStart.hidden){
            districtPrimary.textContent=d.level===0?label('Start construction','Lancer les travaux'):label('Upgrade','Améliorer');
            districtPrimaryAction=()=>stageCommand('building-start');
            for(const [resource,amount] of Object.entries(d.next.costs)){
                const chip=html('span',districtCommandCosts,'district-command-cost');
                resourceIcon(chip,resource);html('span',chip,'',amount+' '+displayName(resource));
                const reserve=inventory.get(resource);
                if(reserve&&!stale&&reserve.stock<amount)chip.dataset.short='true';
            }
            const missing=Object.entries(d.missing??{});
            if(missing.length)districtCommandHint.textContent=t('prerequisite')+' : '+missing.map(([k,v])=>displayName(k)+' '+v.current+'/'+v.required).join(' · ');
            else if(!stale&&Object.entries(d.next.costs).some(([k,v])=>inventory.has(k)&&inventory.get(k).stock<v))districtCommandHint.textContent=label('Insufficient reserves in the last reading.','Réserves insuffisantes au dernier relevé.');
            else districtCommandHint.textContent=label('The server checks costs and prerequisites before starting.','Le serveur vérifie les coûts et prérequis avant le lancement.');
        }else{
            const key={lumberyard:'work',farm:'feed',power_plant:'generate',steelworks:'refine',freight_terminal:'ship',market_hall:'trade'}[b.type];
            const action=key?playGrid.querySelector('[data-domain-action='+key+']'):null;
            if(action&&!action.disabled){
                districtPrimary.textContent=label('Open production / trade','Production / échanges');
                districtPrimaryAction=()=>{playGrid.querySelector('[data-domain-action='+key+']')?.focus({preventScroll:true});playActions.scrollIntoView({block:'nearest'});};
            }else districtPrimary.hidden=true;
            districtCommandHint.textContent=label('Confirmed building state.','État du bâtiment confirmé.');
        }
    }
    // A larger inspection scene, tied to the same selected building and
    // verified state as the district command. No separate simulation.
    const architecturalInspector=html('section',host,'architectural-inspector');architecturalInspector.hidden=true;
    architecturalInspector.setAttribute('aria-labelledby','architecture-title');
    const architecturalHeader=html('div',architecturalInspector,'architectural-header');
    const architecturalTitle=html('h3',architecturalHeader,'');architecturalTitle.id='architecture-title';
    const architecturalToggle=html('button',architecturalHeader,'architectural-toggle');architecturalToggle.type='button';architecturalToggle.dataset.architectureToggle='true';
    const architecturalBody=html('div',architecturalInspector,'architectural-body');architecturalBody.hidden=true;
    architecturalBody.id='architecture-body';architecturalToggle.setAttribute('aria-controls',architecturalBody.id);
    const architecturalView=el('svg',{viewBox:'0 0 320 220',role:'img',class:'architectural-view'},architecturalBody);
    const architecturalFacts=html('div',architecturalBody,'architectural-facts');
    let architectureExpanded=false,architectureId=null;
    architecturalToggle.addEventListener('click',()=>{if(busy)return;architectureExpanded=!architectureExpanded;renderArchitecture();});
    function renderArchitecture(){
        const b=selected?.type==='building'?buildings.get(selected.id):null,d=b?details.get(b.id):null;
        architecturalInspector.hidden=classic||!selectedGame||!districtMode||!b||b.territory!==districtId;
        if(architecturalInspector.hidden){architectureExpanded=false;architectureId=null;architecturalBody.hidden=true;architecturalView.replaceChildren();return;}
        if(architectureId!==b.id){architectureId=b.id;architectureExpanded=false;}
        const fr=lang()==='fr',label=(en,frText)=>fr?frText:en;
        architecturalTitle.textContent=label('Architectural view','Vue architecturale')+' · '+buildingName(b);
        architecturalToggle.textContent=architectureExpanded?label('Close view','Fermer la vue'):label('Inspect architecture','Examiner le bâtiment');
        architecturalToggle.disabled=busy;architecturalToggle.setAttribute('aria-expanded',String(architectureExpanded));
        architecturalBody.hidden=!architectureExpanded;
        if(!architectureExpanded){architecturalView.replaceChildren();return;}
        architecturalView.replaceChildren();architecturalFacts.replaceChildren();
        architecturalView.setAttribute('aria-label',buildingName(b)+' · '+(d?t('level')+' '+d.level:t('unknown')));
        const bg=window.AeternumLivingArt.atmosphereProfile(theme,lighting)?.earth??['#729187','#9da773','#526d4d'];
        rect(architecturalView,0,0,320,220,shade(architecturalView,bg));
        const floor=el('g',{'aria-hidden':'true'},architecturalView);
        ellipse(floor,160,173,129,29,'#192b2a45');
        poly(floor,'32,175 160,114 288,175 160,213',shade(floor,[bg[1],bg[2]]),'#d2c3a080');
        for(let i=0;i<10;i++)line(floor,44+i*12,174+i*3,156+i*12,124+i*3,'#e6dbb830',.8);
        window.AeternumLivingArt.atmosphere(architecturalView,theme,lighting,320,220,'inspection');
        window.AeternumLivingArt.atmosphereDepth(architecturalView,theme,lighting,320,220,'inspection');
        model(el('g',{transform:'translate(157 165) scale(1.92)',class:'ambience-lit-model'},architecturalView),b.type,d);
        const stage=!d?t('unknown'):d.pending?t('pending'):d.level===0?t('foundation'):t('ready');
        const fact=(key,value)=>{const item=html('div',architecturalFacts,'architectural-fact');html('small',item,'',key);html('strong',item,'',String(value));};
        fact(label('Confirmed stage','État confirmé'),stage);
        fact(label('Confirmed level','Niveau confirmé'),d?.level??'—');
        if(d?.next)fact(label('Next stage duration','Durée du prochain niveau'),d.next.duration+' s');
        if(d?.next)fact(label('Next stage resources','Ressources du prochain niveau'),Object.entries(d.next.costs).map(([k,v])=>v+' '+displayName(k)).join(' · '));
        if(d?.pending)html('p',architecturalFacts,'',label('Construction remains pending until the worker confirms it.','Le chantier reste en attente jusqu’à sa validation par le worker.'));
    }
    const livingScene=html('section',host,'living-economy');livingScene.hidden=true;
    livingScene.setAttribute('aria-labelledby','living-economy-title');
    const livingHead=html('div',livingScene,'living-economy-heading');
    const livingTitle=html('h3',livingHead,'');livingTitle.id='living-economy-title';
    const livingScope=html('span',livingHead,'living-economy-scope');
    const livingContent=html('div',livingScene,'living-economy-content');
    const livingVignette=el('svg',{viewBox:'0 0 160 90','aria-hidden':'true',class:'living-economy-art'},livingContent);
    const livingText=html('div',livingContent,'living-economy-text');
    const livingEvent=html('p',livingText,'living-economy-event');livingEvent.setAttribute('aria-live','polite');
    const livingMetrics=html('div',livingText,'living-economy-metrics');
    let renderedEventId=null;
    const economicJournal=window.AeternumLivingArt.economicJournal(8);
    let selectedOperationId=null,renderedOperationId=null;
    const operationPanel=html('section',livingScene,'operation-panel');operationPanel.hidden=true;
    operationPanel.setAttribute('aria-labelledby','operation-title');
    const operationHead=html('div',operationPanel,'operation-head');
    const operationTitle=html('h4',operationHead,'');operationTitle.id='operation-title';
    const operationReplay=html('button',operationHead,'operation-replay');operationReplay.type='button';operationReplay.dataset.operationReplay='true';
    const operationView=el('svg',{viewBox:'0 0 320 170',role:'img',class:'operation-view'},operationPanel);
    const operationNote=html('p',operationPanel,'operation-note');
    const operationHistoryLabel=html('label',operationPanel,'operation-history');
    const operationHistoryTitle=html('span',operationHistoryLabel,'');
    const operationHistory=html('select',operationHistoryLabel,'');operationHistory.dataset.operationHistory='true';
    operationReplay.addEventListener('click',()=>{renderedOperationId=null;renderOperation();});
    operationHistory.addEventListener('change',()=>{
        const entry=economicJournal.entries()[Number(operationHistory.value)];
        if(entry){selectedOperationId=entry.id;renderOperation();}
    });
    function renderOperation(){
        const rows=economicJournal.entries(),fr=lang()==='fr',label=(en,frText)=>fr?frText:en;
        operationPanel.hidden=!rows.length;
        if(!rows.length){selectedOperationId=renderedOperationId=null;operationView.replaceChildren();operationHistory.replaceChildren();return;}
        const index=Math.max(0,rows.findIndex(e=>e.id===selectedOperationId)),entry=rows[index];
        selectedOperationId=entry.id;
        const title=actionLabels[entry.type]?.[fr?1:0]??t('receipt');
        operationTitle.textContent=title;
        operationReplay.textContent=label('Replay illustration','Rejouer la scène');
        operationReplay.setAttribute('aria-label',label('Replay illustration only','Rejouer uniquement la scène'));
        operationView.setAttribute('aria-label',title+' · '+label('Illustrated receipt','Confirmation illustrée'));
        operationPanel.dataset.operationType=entry.type;
        operationNote.textContent=label('Illustration of a confirmed account action, not a live shipment or production timer. Replay has no economic effect.','Illustration d’une action confirmée du compte, non d’un transport en direct ou d’un cycle de production. La relecture est sans effet économique.');
        operationHistoryTitle.textContent=label('Confirmed actions this session (latest eight)','Actions confirmées cette session (huit dernières)');
        operationHistory.replaceChildren();
        for(let i=0;i<rows.length;i++){const option=html('option',operationHistory,'',(i===0?label('Latest · ','Dernière · '):i+1+' · ')+(actionLabels[rows[i].type]?.[fr?1:0]??t('receipt')));option.value=String(i);}
        operationHistory.value=String(index);
        if(renderedOperationId!==entry.id){
            operationView.replaceChildren();
            window.AeternumLivingArt.operationScene(operationView,entry.type);
            renderedOperationId=entry.id;
        }
    }
    function renderLivingScene(){
        livingScene.hidden=classic||!selectedGame||!districtMode||theme==='neutral'||!districtId;
        if(livingScene.hidden)return;
        const fr=lang()==='fr',label=(en,frText)=>fr?frText:en;
        livingTitle.textContent={medieval:label('Village life','Vie du village'),industrial:label('Industrial operations','Activité industrielle'),guild:label('Merchant activity','Activité marchande')}[theme];
        livingScope.textContent=label('Account-wide · server readings','Compte entier · relevés serveur');
        const evt=lastEconomicEvent,meta=evt?window.AeternumLivingArt.eventTypes[evt.type]:null;
        const hasEvent=Boolean(meta&&meta.theme===theme);
        livingVignette.toggleAttribute('hidden',!hasEvent);
        if(hasEvent){
            livingScene.dataset.activity=evt.type;
            livingEvent.textContent=(actionLabels[evt.type]?.[fr?1:0]??t('receipt'))+' · '+label('confirmed','confirmée');
            if(renderedEventId!==evt.id){
                livingVignette.replaceChildren();
                window.AeternumLivingArt.event(el('g',{transform:'translate(80 51) scale(1.25)'},livingVignette),evt.type);
                renderedEventId=evt.id;
            }
        }else{
            delete livingScene.dataset.activity;livingEvent.textContent=label('No confirmed economic action in this session yet.','Aucune action économique confirmée dans cette session.');
            livingVignette.replaceChildren();renderedEventId=null;
        }
        livingMetrics.replaceChildren();
        const metric=(key,name,value)=>{const a=html('div',livingMetrics,'living-metric');a.dataset.livingMetric=key;html('small',a,'',name);html('strong',a,'',String(value));};
        if(campaign?.kind==='medieval'){
            const d=campaign.data;metric('population',label('Residents','Habitants'),d.population+' / '+d.capacity);metric('workers',label('Workers','Travailleurs'),d.workers);metric('yield',label('Wood per shift','Bois par coupe'),d.workYield);
        }else if(campaign?.kind==='industrial'){
            const d=campaign.data;metric('grid',label('Grid supply / load','Offre / charge réseau'),d.grid.supply+' / '+d.grid.load);metric('surplus',label('Spare power','Énergie disponible'),d.grid.surplus);metric('freight',label('Goods delivered','Biens livrés'),d.delivery.current+' / '+d.delivery.required);
        }else if(theme==='guild'&&marketReading!==null){
            metric('offers',label('Offers on loaded page','Offres sur la page chargée'),marketReading);
            metric('own-offers',label('Your offers on this page','Vos offres sur cette page'),offers.filter(o=>o.seller===locationFields().account).length);
        }
        if(!livingMetrics.childElementCount)html('span',livingMetrics,'living-economy-scope',label('Waiting for a server reading.','En attente d’un relevé serveur.'));
        renderOperation();
    }
    const nav=html('div',host,'scene-toolbar');
    const jump=id=>{const node=document.getElementById(id);if(node&&!node.hidden){const legacy=node.closest('.game-ui-legacy');if(legacy)legacy.open=true;node.scrollIntoView({block:'start'});const focus=node.querySelector('button:not([hidden]),input,select');focus?.focus({preventScroll:true});}};
    button(nav,'build',()=>classic?jump('foundation-panel'):focusNode(workshop.root));
    button(nav,'production',()=>classic?jump('action'):focusNode(inventoryBlock.root));
    button(nav,'trade',()=>classic?jump('market-panel'):focusNode(tradeBlock.root));
    const goals=button(nav,'goals',()=>classic?jump(theme==='medieval'?'medieval-panel':theme==='industrial'?'industrial-panel':'progression-panel'):focusNode(objectiveBlock.root));
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
    const inventoryBlock=block(),workshop=block(),objectiveBlock=block(),tradeBlock=block(),receiptBlock=block(),relationsBlock=block();
    const playActions=html('section',host,'domain-actions');playActions.hidden=true;
    playActions.setAttribute('aria-labelledby','domain-actions-title');
    const playTitle=html('h3',playActions,'');playTitle.id='domain-actions-title';
    const playGrid=html('div',playActions,'domain-action-grid');
    function renderPlayActions(){
        playActions.hidden=classic||!selectedGame||theme==='neutral';
        if(playActions.hidden)return;
        const fr=lang()==='fr',label=(en,frText)=>fr?frText:en;
        playTitle.textContent=label('Make your domain prosper','Faites prospérer votre domaine');
        playGrid.replaceChildren();
        const built=id=>[...buildings.values()].some(b=>b.type===id&&(details.get(b.id)?.level??0)>0);
        const add=(key,name,description,art,available,fn,reason)=>{
            const card=html('button',playGrid,'domain-action');card.type='button';card.dataset.domainAction=key;
            const graphic=el('svg',{viewBox:'0 0 110 92','aria-hidden':'true'},card);
            model(el('g',{transform:'translate(55 70) scale(.84)'},graphic),art,{level:1,pending:null});
            const copy=html('span',card,'domain-action-copy');
            html('strong',copy,'',name);html('small',copy,'',description);
            html('span',copy,'domain-action-state',available?label('Open action','Agir'):(reason??label('Build the required structure first','Construisez d’abord le bâtiment requis')));
            card.disabled=busy||!available;card.addEventListener('click',()=>{if(!busy&&available)fn();});
        };
        const click=id=>()=>document.getElementById(id).click();
        if(theme==='medieval'){
            add('work',label('Harvest timber','Récolter du bois'),label('Assign a lumber shift to your available workers.','Confiez une coupe à vos travailleurs disponibles.'),'lumberyard',built('lumberyard')&&campaign?.kind==='medieval'&&campaign.data.workers>0,click('medieval-work'),built('lumberyard')?label('Welcome a second resident first','Accueillez d’abord un deuxième habitant'):null);
            add('feed',label('Welcome a resident','Accueillir un habitant'),label('Spend food to grow your village within its housing capacity.','Utilisez des céréales pour agrandir votre population.'),'farm',built('farm')&&campaign?.kind==='medieval'&&campaign.data.population<campaign.data.capacity,click('medieval-feed'),built('farm')?label('No housing capacity available','Capacité d’accueil insuffisante'):null);
        }else if(theme==='industrial'){
            add('generate',label('Generate energy','Produire de l’énergie'),label('Convert raw materials into electricity.','Transformez vos matières premières en électricité.'),'power_plant',built('power_plant'),click('industrial-generate'));
            add('refine',label('Manufacture goods','Fabriquer des marchandises'),label('Use your factory, power and storage capacity.','Utilisez votre usine, votre énergie et vos capacités de stockage.'),'steelworks',built('steelworks')&&built('warehouse'),click('industrial-refine'));
            add('ship',label('Dispatch freight','Expédier du fret'),label('Deliver goods through your freight terminal.','Livrez des marchandises grâce au terminal de fret.'),'freight_terminal',built('freight_terminal'),click('industrial-ship'));
        }else{
            add('trade',label('Visit the market','Visiter le marché'),label('Browse real offers and publish your own barter.','Consultez les offres réelles et proposez votre troc.'),'market_hall',built('market_hall'),()=>{focusNode(tradeBlock.root);document.getElementById('market-load').click();});
        }
    }
    const plotLabel=html('label',workshop.body,'scene-caption');plotLabel.htmlFor='scene-plot';
    const plotSelect=html('select',workshop.body,'scene-plot');plotSelect.id='scene-plot';
    plotSelect.addEventListener('change',()=>{chosenSite=plotSelect.value;renderWorkspace();});
    const catalogueList=html('div',workshop.body,'scene-catalogue');
    const referenceLabel=html('label',workshop.body,'scene-caption');referenceLabel.htmlFor='scene-foundation-reference';
    const referenceInput=html('input',workshop.body,'scene-plot');referenceInput.id='scene-foundation-reference';referenceInput.maxLength=64;referenceInput.autocomplete='off';referenceInput.classList.add('scene-reference-optional');referenceLabel.classList.add('scene-reference-optional');
    const place=button(workshop.body,'place',()=>{
        if(busy||!chosenSite||!chosenType)return;
        const site=document.getElementById('foundation-site'),type=document.getElementById('foundation-type');
        if(![...site.options].some(o=>o.value===chosenSite)||![...type.options].some(o=>o.value===chosenType))return;
        site.value=chosenSite;type.value=chosenType;
        document.getElementById('foundation-id').value=referenceInput.value.trim();
        document.getElementById('foundation-place').click();
    });
    const commerceHall=el('svg',{viewBox:'0 0 480 130',class:'commerce-hall','aria-hidden':'true'},tradeBlock.body);
    let commerceTheme=null,tradeHistory=null,tradeHistoryMore=false;
    const logistics=html('section',tradeBlock.body,'commerce-logistics');
    const logisticsTitle=html('h4',logistics,'');
    const logisticsReadings=html('div',logistics,'commerce-readings');
    const logisticsRefresh=html('button',logistics,'scene-button');logisticsRefresh.type='button';logisticsRefresh.dataset.commerceRefresh='true';
    logisticsRefresh.addEventListener('click',()=>run(async()=>{await readInventory();if(theme==='medieval')await reviewMedieval();else if(theme==='industrial')await reviewIndustrial();await loadMarket();}));
    const commerceFilters=html('div',tradeBlock.body,'commerce-filters');
    const filters={};
    for(const key of ['give','want']){
        const label=html('label',commerceFilters,'');
        const title=html('span',label,'');
        const input=html('select',label,'scene-plot');input.dataset.commerceFilter=key;
        filters[key]={title,input};
    }
    const filterButton=html('button',commerceFilters,'scene-button');filterButton.type='button';filterButton.dataset.commerceSearch='true';
    filterButton.addEventListener('click',()=>{
        if(busy)return;
        for(const [key,{input}] of Object.entries(filters))document.getElementById('market-filter-'+key).value=input.value;
        document.getElementById('market-search').click();
    });
    for(const {input} of Object.values(filters))input.addEventListener('change',updateCommerceFilters);
    function updateCommerceFilters(){
        filterButton.disabled=busy||(Boolean(filters.give.input.value)!==Boolean(filters.want.input.value));
    }
    const tradeComposer=html('div',tradeBlock.body,'scene-trade-composer');
    const tradeFields={};
    for(const [key,kind] of [['giveResource','select'],['giveUnits','input'],['wantResource','select'],['wantUnits','input']]){
        const label=html('label',tradeComposer,'');
        const caption=html('span',label,'');
        const input=html(kind,label,'scene-plot');
        input.dataset.tradeField=key;
        if(kind==='input'){input.type='number';input.min='1';input.max='1000000000000';input.step='1';input.value='1';}
        tradeFields[key]={caption,input};
    }
    const exchangePreview=html('div',tradeComposer,'commerce-preview');
    const exchangeNote=html('p',tradeComposer,'commerce-note');
    const publish=html('button',tradeComposer,'scene-button');publish.type='button';
    publish.addEventListener('click',()=>{
        if(busy||!draftTerms())return;
        const form=document.getElementById('market-create');
        for(const [key,{input}] of Object.entries(tradeFields))form.elements[key].value=input.value;
        form.requestSubmit();
    });
    function draftTerms(){
        return window.AeternumLivingArt.exchangeTerms(tradeFields.giveResource.input.value,Number(tradeFields.giveUnits.input.value),tradeFields.wantResource.input.value,Number(tradeFields.wantUnits.input.value));
    }
    function cargoCard(parent,resource,units,caption){
        const card=html('span',parent,'commerce-cargo');
        const art=el('svg',{viewBox:'0 0 80 80','aria-hidden':'true'},card);
        window.AeternumLivingArt.cargo(art,resource);
        if(caption)html('small',card,'',caption);
        html('strong',card,'',units+' '+displayName(resource));
        return card;
    }
    function exchangeGraphic(parent,terms,own){
        const fr=lang()==='fr',row=html('span',parent,'commerce-exchange');
        cargoCard(row,terms.giveResource,terms.giveUnits,own?(fr?'Vous offrez':'You offer'):(fr?'Vous recevez':'You receive'));
        html('span',row,'commerce-arrow','⇄').setAttribute('aria-hidden','true');
        cargoCard(row,terms.wantResource,terms.wantUnits,own?(fr?'Vous demandez':'You request'):(fr?'Vous payez':'You pay'));
    }
    function refreshDraft(){
        const terms=draftTerms(),fr=lang()==='fr';
        exchangePreview.replaceChildren();
        if(terms)exchangeGraphic(exchangePreview,terms,true);
        const reserve=terms?inventory.get(terms.giveResource):null;
        exchangeNote.textContent=!terms?(fr?'Choisissez deux ressources distinctes et des quantités entières positives.':'Choose two distinct resources and positive whole quantities.'):reserve&&!stale&&reserve.stock<terms.giveUnits?(fr?'Réserve connue insuffisante ; le serveur vérifie les fonds.':'Last reserve reading is insufficient; the server checks funding.'):(fr?'Le serveur contrôle et réserve les ressources à la publication.':'The server checks and reserves resources when published.');
        publish.disabled=busy||!terms;
    }
    for(const {input} of Object.values(tradeFields))input.addEventListener('input',refreshDraft);
    function refreshTradeComposer(){
        const fr=lang()==='fr';
        const labels=fr?['Ressource offerte','Quantité offerte','Ressource demandée','Quantité demandée']:['Offer resource','Offer quantity','Request resource','Request quantity'];
        Object.entries(tradeFields).forEach(([key,{caption,input}],i)=>{
            caption.textContent=labels[i];
            if(input.tagName!=='SELECT')return;
            const old=input.value,original=document.getElementById('market-create').elements[key];
            input.replaceChildren();
            for(const o of original.options){const n=document.createElement('option');n.value=o.value;n.textContent=displayName(o.value);input.append(n);}
            if([...input.options].some(o=>o.value===old))input.value=old;
            else if(key==='wantResource'&&input.options.length>1)input.selectedIndex=1;
        });
        publish.textContent=fr?'Publier le troc':'Publish barter';refreshDraft();
    }
    const tradeList=html('div',tradeBlock.body,'scene-trade-list');
    const marketMore=html('button',tradeBlock.body,'scene-button');marketMore.type='button';marketMore.dataset.commerceMore='true';
    marketMore.addEventListener('click',()=>{if(!busy&&offerMore)document.getElementById('market-next').click();});
    const selectedTerms=html('div',tradeBlock.body,'commerce-selected');selectedTerms.hidden=true;
    const historyPanel=html('section',tradeBlock.body,'commerce-history');
    const historyTitle=html('h4',historyPanel,'');
    const historyLoad=html('button',historyPanel,'scene-button');historyLoad.type='button';historyLoad.dataset.commerceHistory='load';
    historyLoad.addEventListener('click',()=>{if(!busy)document.getElementById('market-history-load').click();});
    const historyList=html('div',historyPanel,'commerce-history-list');
    const historyMore=html('button',historyPanel,'scene-button');historyMore.type='button';historyMore.dataset.commerceHistory='more';
    historyMore.addEventListener('click',()=>{if(!busy&&tradeHistoryMore)document.getElementById('market-history-next').click();});
    function renderCommerce(){
        const fr=lang()==='fr',label=(en,f)=>fr?f:en;
        commerceHall.hidden=classic||theme==='neutral';
        if(commerceTheme!==theme){commerceHall.replaceChildren();window.AeternumLivingArt.marketHall(commerceHall,theme);commerceTheme=theme;}
        logistics.hidden=classic||theme==='neutral';
        logisticsTitle.textContent=theme==='industrial'?label('Logistics network','Réseau logistique'):theme==='medieval'?label('Village provisions','Approvisionnement du village'):label('Trading reserves','Réserves commerciales');
        logisticsRefresh.textContent=label('Refresh reserves and market','Actualiser réserves et marché');
        logisticsReadings.replaceChildren();
        const metric=(key,value)=>{const m=html('span',logisticsReadings,'commerce-reading');html('small',m,'',key);html('strong',m,'',String(value));};
        if(campaign?.kind==='industrial'){const d=campaign.data;metric(label('Warehouse capacity','Capacité entrepôt'),d.warehouseCapacity);metric(label('Delivered goods','Biens livrés'),d.delivery.current+' / '+d.delivery.required);metric(label('Spare power','Énergie disponible'),d.grid.surplus);}
        if(campaign?.kind==='medieval'){const d=campaign.data;metric(label('Residents','Habitants'),d.population+' / '+d.capacity);metric(label('Workers','Travailleurs'),d.workers);}
        for(const [resource,d] of [...inventory].slice(0,6))metric(displayName(resource),d.stock+' / '+d.capacity);
        if(!logisticsReadings.childElementCount)metric(label('No server reading','Aucun relevé serveur'),'—');
        if(stale)metric(label('Refresh required','Actualisation nécessaire'),'!');
        for(const [key,{title,input}] of Object.entries(filters)){
            title.textContent=key==='give'?label('Offered resource','Ressource offerte'):label('Requested resource','Ressource demandée');
            const previous=input.value,source=document.getElementById('market-filter-'+key);
            input.replaceChildren();
            for(const o of source.options){const opt=html('option',input,'',o.value?displayName(o.value):label('All resources','Toutes les ressources'));opt.value=o.value;}
            if([...input.options].some(o=>o.value===previous))input.value=previous;
        }
        filterButton.textContent=label('Search market','Rechercher');updateCommerceFilters();
        marketMore.hidden=!offerMore;marketMore.textContent=label('Next offer page','Page suivante');
        historyTitle.textContent=label('Private transaction history','Historique privé des transactions');
        historyLoad.textContent=label('Read my history','Consulter mon historique');
        historyMore.textContent=label('Next history page','Historique suivant');historyMore.hidden=!tradeHistoryMore;
        historyList.replaceChildren();
        if(tradeHistory===null)html('p',historyList,'commerce-note',label('Not loaded.','Non consulté.'));
        else if(!tradeHistory.length)html('p',historyList,'commerce-note',label('No transactions on this page.','Aucune transaction sur cette page.'));
        for(const tr of tradeHistory??[]){
            const row=html('div',historyList,'commerce-history-entry');row.dataset.tradeStatus=tr.status;
            const terms=window.AeternumLivingArt.exchangeTerms(tr.giveResource,tr.giveUnits,tr.wantResource,tr.wantUnits);
            html('strong',row,'',({filled:label('Exchange completed','Échange réalisé'),cancelled:label('Offer cancelled','Offre annulée'),expired:label('Offer expired','Offre expirée'),open:label('Open offer','Offre ouverte')})[tr.status]??label('Recorded transaction','Transaction enregistrée'));
            if(terms)exchangeGraphic(row,terms,tr.seller===locationFields().account);
        }
    }
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
        selectedTerms.hidden=!offer;selectedTerms.replaceChildren();
        if(offer){const terms=window.AeternumLivingArt.exchangeTerms(offer.giveResource,offer.giveUnits,offer.wantResource,offer.wantUnits);if(terms)exchangeGraphic(selectedTerms,terms,offer.seller===locationFields().account);}
    }
    function setLocation(id){if(selected?.type==='territory'){document.getElementById(id).value=territories.get(selected.id).location;updateSelection();}}
    function inspectBuilding(id){if(!buildings.has(id))return;document.getElementById('building-id').value=id;document.getElementById('building-load').click();}
    const narrow=window.matchMedia('(max-width:640px)');
    const isTechnicalId=value=>/^(?:[a-f0-9]{16,128}|[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12})$/i.test(String(value));
    const numberFor=(values,id)=>Math.max(1,[...values].findIndex(v=>v===id)+1);
    function buildingName(b){
        const peers=[...buildings.values()].filter(x=>x.type===b.type);
        return displayName(b.type)+(peers.length>1?' '+numberFor(peers.map(x=>x.id),b.id):'');
    }
    function siteName(s){
        const n=territories.get(s.territory_id);
        return n?locationName(n):t('plotNumber')+' '+numberFor(catalogue.sites.map(x=>x.territory_id),s.territory_id);
    }
    function merchantName(id){
        if(id===locationFields().account)return t('myOffer');
        const sellers=[...new Set(offers.map(o=>o.seller))];
        return t('merchant')+' '+numberFor(sellers,id);
    }
    function locationName(n){
        if(!isTechnicalId(n.location))return n.location;
        const own=n.control==='own',occupied=n.control==='occupied';
        return t(n.kind==='homestead'?(own?'yourHomestead':occupied?'occupiedHomestead':'unclaimedHomestead'):(own?'yourTerritory':occupied?'occupiedTerritory':'unclaimedTerritory'))+' '+numberFor(territories.keys(),n.id);
    }
    function updateSelection(){
        const territory=selected?.type==='territory'?territories.get(selected.id):null;
        const building=selected?.type==='building'?buildings.get(selected.id):null;
        const d=building?details.get(building.id):null;
        selection.textContent=territory?locationName(territory):building?buildingName(building):t('choose');
        stageStart.hidden=!(d&&currentBuilding?.id===d.id&&d.account===locationFields().account&&d.pending===null&&d.next);
        if(!stageStart.hidden)stageStart.textContent=lang()==='fr'?(d.level===0?'Lancer les travaux':'Améliorer le bâtiment'):(d.level===0?'Start construction':'Upgrade building');
        stageCancel.hidden=!(d&&currentBuilding?.id===d.id&&d.account===locationFields().account&&d.pending!==null);
        info.textContent=territory?locationName(territory):building?buildingName(building)+' · '+(d?t('level')+' '+d.level+(d.pending?' · '+t('pending'):''):t('unknown')):'';
        if(d?.next)info.textContent+=' · '+t('costs')+': '+Object.entries(d.next.costs).map(([k,v])=>v+' '+displayName(k)).join(', ');
        origin.hidden=destination.hidden=connection.hidden=!territory;inspect.hidden=!building;
        buildHere.hidden=!territory||!catalogue.sites.some(s=>s.territory_id===territory.id);
        if(d?.missing&&Object.keys(d.missing).length)info.textContent+=' · '+t('prerequisite')+': '+Object.entries(d.missing).map(([k,v])=>displayName(k)+' '+v.current+'/'+v.required).join(', ');
        trace.hidden=!document.getElementById('map-location').value||!document.getElementById('map-destination').value;
        goals.hidden=theme==='neutral'||(theme==='guild'&&document.getElementById('progression-panel').hidden);
        svg.querySelectorAll('[data-territory]').forEach(n=>n.setAttribute('aria-pressed',String(territory?.id===n.dataset.territory)));
        holdingList.querySelectorAll('[data-building]').forEach(n=>n.setAttribute('aria-pressed',String(building?.id===n.dataset.building)));
        districtSvg.querySelectorAll('[data-district-building]').forEach(n=>n.setAttribute('aria-pressed',String(building?.id===n.dataset.districtBuilding)));
        renderDistrictCommand(building,d);renderArchitecture();renderAtlasPanel();
    }
    function select(type,id){
        if(busy)return;
        selected={type,id};if(type==='territory'){districtId=id;districtPage=0;renderDistrict();}updateSelection();
        if(type==='building'&&currentBuilding?.id!==id){
            document.getElementById('building-id').value=id;
            document.getElementById('building-load').click();
        }
    }
    function atlasViewSize(z=camera.z){
        const modern=!classic&&theme!=='neutral';
        const height=modern?Math.min(extent.h,Math.max(300,extent.w*(narrow.matches?1.06:.63))):extent.h;
        return {w:extent.w/z,h:height/z};
    }
    function view(){const {w,h}=atlasViewSize();camera.x=Math.max(-w*.15,Math.min(extent.w-w*.85,camera.x));camera.y=Math.max(-h*.15,Math.min(extent.h-h*.85,camera.y));svg.setAttribute('viewBox',[camera.x,camera.y,w,h].join(' '));}
    function zoom(f,ax=.5,ay=.5){if(!Number.isFinite(f)||f<=0)return;const z=Math.max(1,Math.min(3,camera.z*f)),old=atlasViewSize(),next=atlasViewSize(z);camera.x+=(old.w-next.w)*ax;camera.y+=(old.h-next.h)*ay;camera.z=z;view();}
    window.AeternumArt.gestures(svg,{pan(dx,dy){camera.x+=dx;camera.y+=dy;view();},zoom});
    svg.addEventListener('keydown',e=>{
        if(e.target!==svg)return;
        if(e.key==='+'||e.key==='=')zoom(1.25);
        else if(e.key==='-')zoom(.8);
        else if(e.key==='Home'){camera={x:0,y:0,z:1};view();}
        else if(['ArrowUp','ArrowDown','ArrowLeft','ArrowRight'].includes(e.key)){
            const size=atlasViewSize();
            camera.x+=({'ArrowLeft':-1,'ArrowRight':1}[e.key]??0)*size.w*.12;
            camera.y+=({'ArrowUp':-1,'ArrowDown':1}[e.key]??0)*size.h*.12;view();
        }else return;
        e.preventDefault();
    });
    const artKit=window.AeternumArt.create({skin:()=>theme,palette:()=>palettes[theme]});
    const {poly,rect,line,ellipse,path,shade,tree,model}=artKit;
    const resourceGlyphs={
        wood:['#c29c6d','M5 15L15 5 20 10 10 20Z M5 15L10 20M8 14L13 9M12 15L17 10'],
        grain:['#ead18a','M12 21V4M12 8Q3 3 6 12Q10 15 12 12M12 13Q21 8 18 17Q14 20 12 17M12 5Q18 1 16 8'],
        stone:['#b6c2bc','M3 17L5 10 11 5 18 8 22 17 17 20 7 21Z M5 10L13 13 18 8M13 13L17 20'],
        silver:['#d3e1e5','M4 8H20L22 17H2Z M7 8L9 5H17L20 8M6 13H18'],
        spice:['#dc9970','M12 4Q5 9 5 15A7 7 0 0 0 19 15Q19 9 12 4ZM9 13Q11 10 14 12M12 17L16 14'],
        energy:['#f4d277','M13 2L5 14H11L9 22 20 9H13Z'],
        coal:['#97a8b0','M3 16L7 6 15 3 21 10 19 20 9 21Z M7 6L12 13 21 10M12 13L9 21'],
        goods:['#c6a573','M3 7L12 3 21 7V18L12 22 3 18Z M3 7L12 11 21 7M12 11V22']
    };
    function resourceIcon(parent,key){
        const [color,d]=resourceGlyphs[key]??resourceGlyphs[key==='electricity'?'energy':key==='food'?'grain':key==='iron'||key==='steel'||key==='gold'?'silver':'goods'];
        const icon=el('svg',{viewBox:'0 0 24 24',class:'scene-resource-icon','aria-hidden':'true'},parent);
        path(icon,d,color,{stroke:'#1e292b','stroke-width':1.1,'stroke-linejoin':'round'});
    }
    function selectResource(key){
        if(busy)return;
        if(![...balance.elements.resource.options].some(o=>o.value===key))return;
        balance.elements.resource.value=key;balance.elements.resource.dispatchEvent(new Event('change'));
        run(refresh);
    }
    function resourceAction(type){
        if(busy||!locationFields().resource)return;
        const form=document.getElementById('action');
        form.elements.type.value=type;form.elements.type.dispatchEvent(new Event('change'));form.requestSubmit();
    }
    let botReport=null,botExpanded=false;
    async function readBotRoster(){
        const world=locationFields().world;
        if(!['lab-medieval-f04','lab-industrial-f04','lab-guild-f04'].includes(world))return;
        try{
            const response=await fetch('/lab-bots/roster.json',{cache:'no-store'});
            if(!response.ok)return;
            const data=await response.json(),rows=data.worlds?.[world];
            if(locationFields().world!==world||data.version!==1||!Array.isArray(rows)||rows.length!==10)return;
            if(!rows.every(b=>/^Bot \d{2}$/.test(b.name)&&['builder','producer','merchant','balanced'].includes(b.profile)&&['ready','active','blocked','idle','paused'].includes(b.status)&&Number.isSafeInteger(b.built)&&b.built>=0))return;
            botReport={world,updatedAt:data.updatedAt,rows};renderHud();
        }catch{/* Missing lab roster is not a gameplay error. */}
    }
    function renderHud(){
        hud.replaceChildren();
        if(botReport?.world===locationFields().world){
            const roster=html('details',hud,'scene-bot-roster');roster.open=botExpanded;
            roster.addEventListener('toggle',()=>{botExpanded=roster.open;});
            const title=html('summary',roster,'',botReport.rows.length+' · '+t('botOpponents'));title.dataset.botCount=String(botReport.rows.length);
            if(Date.now()/1000-botReport.updatedAt>90)html('small',roster,'',t('botStale'));
            button(roster,'botRefresh',()=>run(readBotRoster));
            for(const b of botReport.rows){
                const row=html('div',roster,'scene-bot-row');row.dataset.botName=b.name;row.dataset.botStatus=b.status;row.dataset.botBuilt=String(b.built);
                html('strong',row,'',b.name+' · '+t('bot'+b.profile[0].toUpperCase()+b.profile.slice(1)));
                html('span',row,'',(b.status==='paused'?(lang()==='fr'?'En pause':'Paused'):t('bot'+b.status[0].toUpperCase()+b.status.slice(1)))+' · '+t('botBuilt')+': '+b.built);
                if(b.lastAction&&actionLabels[b.lastAction])html('small',row,'',actionLabels[b.lastAction][lang()==='fr'?1:0]);
            }
        }
        const resourceStrip=html('div',hud,'scene-resource-strip');
        const current=locationFields().resource;
        for(const option of [...balance.elements.resource.options].slice(0,16)){
            const key=option.value,d=(reading?.resource===key?reading.data:null)??inventory.get(key);
            const tile=html('button',resourceStrip,'scene-resource-tile');tile.type='button';tile.dataset.resourceTile=key;
            tile.setAttribute('aria-pressed',String(key===current));tile.setAttribute('aria-label',displayName(key)+' · '+t('selectResource'));
            resourceIcon(tile,key);
            const labels=html('span',tile,'scene-resource-values');
            html('small',labels,'',displayName(key));html('strong',labels,'',d?String(d.stock):'—');
            html('small',labels,'',d?'/ '+d.capacity:t('unknownStock'));
            tile.addEventListener('click',()=>selectResource(key));
        }
        const economy=html('div',hud,'scene-economy-actions');
        const active=(reading?.resource===current?reading.data:null)??inventory.get(current);
        if(active){
            const level=html('span',economy,'scene-caption',t('resourceLevel')+' '+active.level+(active.pending?' · '+t('pendingUpgrade'):''));
            level.dataset.resourceLevel=String(active.level);
        }
        const collect=button(economy,'collect',()=>resourceAction('economy.settle'));
        const upgrade=button(economy,'upgradeResource',()=>resourceAction('economy.upgrade'));
        upgrade.hidden=Boolean(active&&(!active.next||active.pending));
        collect.hidden=!current;upgrade.hidden=upgrade.hidden||!current;
        button(economy,'refreshReserves',()=>run(readInventory));
        const item=(key,value,tag)=>{
            const slot=html('div',hud,'scene-stat');if(tag)slot.dataset.stat=tag;
            html('small',slot,'',key);html('strong',slot,'',String(value));return slot;
        };
        if(reading){
            const d=reading.data;
            item(displayName(reading.resource)+' · '+t('reserve'),d.stock+' / '+d.capacity,'stock');
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
    const chapterCopy={
        'medieval-foundation':['I · The first settlement','I · Les premières fondations','Establish the fields and timber supply that sustain your domain.','Établissez les cultures et l’approvisionnement en bois du domaine.','lumberyard'],
        'medieval-community':['II · A growing community','II · Une communauté grandissante','Provide storage and welcome the population required by the realm.','Développez les réserves et accueillez la population requise.','granary'],
        'medieval-stronghold':['III · The stronghold','III · La place forte','Raise your keep to the level required by the campaign.','Élevez votre donjon au niveau exigé par la campagne.','keep'],
        'industrial-foundation':['I · The power network','I · Le réseau énergétique','Build the energy and storage infrastructure.','Développez les infrastructures d’énergie et de stockage.','power_plant'],
        'industrial-manufacture':['II · The manufacturing frontier','II · La frontière productive','Expand the factory network to its required capacity.','Développez le réseau industriel selon les objectifs fixés.','steelworks'],
        'industrial-freight':['III · The freight corridor','III · Le corridor logistique','Complete the terminal and meet the delivery target.','Achevez le terminal et atteignez l’objectif de livraison.','freight_terminal'],
        'guild-foundation':['I · The trading house','I · La maison de commerce','Open a market hall to begin your trading enterprise.','Ouvrez une halle pour lancer votre entreprise marchande.','market_hall'],
        'guild-mastery':['III · Merchant mastery','III · Maîtrise marchande','Complete verified exchanges, research and expeditions.','Achevez des échanges, recherches et expéditions confirmés.',null],
        'guild-caravan':['II · The caravan routes','II · Les routes caravanières','Establish a caravan hub and expand your trading estate.','Établissez un relais caravanier et développez votre domaine commercial.','caravan_hub'],
        additional:['Further objectives','Objectifs complémentaires','Complete the remaining requirements recorded by the server.','Remplissez les autres exigences enregistrées par le serveur.',null]
    };
    function renderChronicle(){
        const root=objectiveBlock.body,fr=lang()==='fr',label=(en,f)=>fr?f:en;
        const journal=html('section',root,'campaign-chronicle');
        journal.setAttribute('aria-label',label('Campaign chronicle','Chronique de campagne'));
        const guild=theme==='guild';
        const holdings={};
        if(guild&&stagesKnown)for(const key of ['market_hall','caravan_hub']){
            const own=[...buildings.values()].filter(b=>b.type===key);
            holdings[key]=own.every(b=>Number.isSafeInteger(details.get(b.id)?.level))?Math.max(0,...own.map(b=>details.get(b.id).level)):null;
        }
        const projection=window.AeternumLivingArt.campaignChapters(theme,guild?moduleReading:campaign?.kind===theme?campaign.data:null,guild&&stagesKnown?holdings:null);
        const header=html('div',journal,'campaign-heading');
        html('h4',header,'',guild?label('Merchant chronicle','Chronique marchande'):label('Campaign chronicle','Chronique de campagne'));
        const refresh=html('button',header,'scene-button');refresh.type='button';refresh.dataset.campaignRefresh='true';
        refresh.textContent=label('Refresh campaign','Actualiser la campagne');refresh.disabled=busy;
        refresh.addEventListener('click',()=>run(async()=>{
            await readStages();
            if(theme==='medieval')await reviewMedieval();
            else if(theme==='industrial')await reviewIndustrial();
            else if(!document.getElementById('progression-panel').hidden){
                try{await loadProgression();}catch(e){if(e.status!==404)throw e;scoreReading=null;}
            }
            await loadCampaignModule();
        }));
        html('p',journal,'campaign-note',guild&&projection.guide?label('Suggested milestones, not an official victory campaign. No additional rewards are granted.','Jalons suggérés, sans condition de victoire officielle ni récompense supplémentaire.'):label('Chapters group the existing server victory objectives. Artwork illustrates goals; no new rules or rewards.','Les chapitres regroupent les objectifs de victoire existants du serveur. Le décor illustre les objectifs ; aucune règle ni récompense ajoutée.'));
        if(campaignStale)html('p',journal,'campaign-note',label('Last reading; refresh after your latest action.','Dernier relevé ; actualisez après votre dernière action.'));
        const all=projection.chapters.flatMap(ch=>ch.goals),done=all.filter(g=>g.state==='complete').length;
        const summary=html('div',journal,'campaign-summary');
        summary.dataset.campaignVictory=projection.victory===null?'unknown':String(projection.victory);
        html('strong',summary,'',projection.victory===true?label('Campaign victory confirmed','Victoire de campagne confirmée'):all.length?done+' / '+all.length+' '+label('objectives complete','objectifs achevés'):label('Awaiting server reading','En attente du relevé serveur'));
        if(all.length){
            const meter=html('progress',summary,'scene-meter');meter.max=all.length;meter.value=done;
            meter.setAttribute('aria-label',label('Completed objectives','Objectifs achevés'));
        }
        const active=projection.chapters.find(ch=>ch.state!=='complete')?.key;
        for(const ch of projection.chapters){
            const copy=chapterCopy[ch.key]??chapterCopy.additional;
            const card=html('article',journal,'campaign-chapter');card.dataset.chapter=ch.key;card.dataset.chapterState=ch.state;
            if(ch.key===active)card.dataset.chapterNext='true';
            const image=el('svg',{viewBox:'0 0 320 120','aria-hidden':'true',class:'campaign-art'},card);
            window.AeternumLivingArt.chapterLandscape(image,theme,ch.key);
            // A blueprint, explicitly not a claim that the target building exists.
            if(copy[4])model(el('g',{transform:'translate(160 100) scale(1.12)'},image),copy[4],{level:ch.state==='complete'?1:0,pending:null});
            const body=html('div',card,'campaign-chapter-body');
            html('small',body,'campaign-chapter-state',ch.state==='complete'?label('Completed','Achevé'):ch.state==='unknown'?label('Not verified','Non vérifié'):label('In progress','En cours'));
            html('h5',body,'',copy[fr?1:0]);html('p',body,'',copy[fr?3:2]);
            for(const g of ch.goals){
                const row=html('div',body,'campaign-goal');row.dataset.chapterGoal=g.key;row.dataset.goalState=g.state;
                const title=g.kind==='population'?t('residents'):g.kind==='delivery'?t('freight'):g.kind==='trade'?label('Completed exchanges','Échanges achevés'):g.kind==='research'?label('Research','Recherche'):g.kind==='expedition'?label('Expeditions','Expéditions'):displayName(g.key);
                html('span',row,'',title);html('strong',row,'',String(g.current??'—')+' / '+g.required);
                const meter=html('progress',row,'scene-meter');meter.max=g.required;
                if(g.current!==null)meter.value=Math.min(g.current,g.required);
                meter.setAttribute('aria-label',title);
                if(g.state==='incomplete'){
                    const go=html('button',row,'scene-button');go.type='button';go.dataset.chapterAction=g.key;
                    go.textContent=g.kind==='building'?label('Inspect or build','Examiner ou construire'):label('Open domain actions','Ouvrir les actions du domaine');
                    go.disabled=busy;
                    go.addEventListener('click',()=>{
                        if(busy)return;
                        if(g.kind==='trade'){revealPanel('market');return;}
                        if(g.kind==='research'||g.kind==='expedition'){focusNode(journal.querySelector('[data-campaign-module]'));return;}
                        if(g.kind!=='building'){showDistrict(true);focusNode(playActions);return;}
                        const b=[...buildings.values()].filter(b=>b.type===g.key).sort((a,b)=>(details.get(b.id)?.level??-1)-(details.get(a.id)?.level??-1))[0];
                        if(b){districtId=b.territory;showDistrict(true);select('building',b.id);}
                        else if(catalogue.types.some(x=>x.id===g.key)){chosenType=g.key;renderWorkspace();focusNode(workshop.root);}
                    });
                    if(g.kind==='building'&&![...buildings.values()].some(b=>b.type===g.key)&&!catalogue.types.some(x=>x.id===g.key))go.disabled=true;
                }
            }
        }
        if(currentPackCapabilities().packViews?.includes('campaign')){
            const panel=html('section',journal,'campaign-score');panel.dataset.campaignModule='true';
            html('h5',panel,'',label('Research and expeditions','Recherche et expéditions'));
            html('p',panel,'campaign-note',label('Study completed facilities, then prepare an expedition. Confirmed research unlocks expeditions; no cargo or land is granted.','Étudiez les installations achevées, puis préparez une expédition. La recherche confirmée débloque les expéditions ; aucun bien ni territoire n’est attribué.'));
            const snapshot=moduleReading;
            panel.dataset.moduleResearch=String(snapshot?.research??'unknown');panel.dataset.moduleExpeditions=String(snapshot?.expeditions??'unknown');panel.dataset.modulePending=snapshot?.pending??'none';
            html('p',panel,'',label('Research: ','Recherche : ')+(snapshot?.research??'—')+' / 2 · '+label('Expeditions: ','Expéditions : ')+(snapshot?.expeditions??'—')+' / 3');
            if(snapshot?.pending){
                const job=snapshot.pending==='research'?label('research','recherche'):label('expedition','expédition');
                html('p',panel,'campaign-job-pending',label('Awaiting server completion: ','En attente de validation du serveur : ')+job);
                if(Number.isSafeInteger(snapshot.dueAt)&&snapshot.dueAt>=0){
                    const due=new Date(snapshot.dueAt*1000);
                    if(Number.isFinite(due.getTime()))html('small',panel,'',label('Scheduled time: ','Échéance prévue : ')+new Intl.DateTimeFormat(fr?'fr-FR':'en-GB',{dateStyle:'short',timeStyle:'medium',timeZone:'UTC'}).format(due)+' UTC');
                }
            }
            for(const [key,caption,frText,allowed] of [['research','Start research','Lancer la recherche',snapshot?.canResearch],['expedition','Start expedition','Lancer une expédition',snapshot?.canExpedition]]){
                const control=html('button',panel,'scene-button');control.type='button';control.dataset.moduleAction=key;
                control.textContent=label(caption,frText);control.disabled=busy||allowed!==true;control.dataset.moduleAllowed=String(allowed===true);
                control.addEventListener('click',()=>run(()=>campaignCommand('pack.campaign.'+key)));
            }
            const reload=html('button',panel,'scene-button');reload.type='button';reload.dataset.moduleRefresh='true';reload.textContent=label('Read confirmed progress','Lire la progression confirmée');reload.disabled=busy;
            reload.addEventListener('click',()=>run(loadCampaignModule));
            if(guild&&currentPackCapabilities().packActions?.includes('pack.guild.challenge')){
                html('h5',panel,'',label('Diplomacy and challenges','Diplomatie et défis'));
                html('p',panel,'campaign-note',label('Challenges are immediate and non-destructive. Protection and cooldown are enforced by the server. A truce requires consent.','Les défis sont immédiats et non destructifs. Protection et délai sont imposés par le serveur. Une trêve exige un consentement.'));
                const targets=html('select',panel,'');targets.setAttribute('aria-label',label('Other account','Autre compte'));targets.dataset.moduleTarget='true';
                for(const value of ['',...moduleTargets]){const option=html('option',targets,'',value||label('Choose account','Choisir un compte'));option.value=value;}
                targets.value=moduleTarget;targets.addEventListener('change',()=>{moduleTarget=targets.value;renderWorkspace();});
                const fetchTargets=html('button',panel,'scene-button');fetchTargets.type='button';fetchTargets.textContent=label('Find accounts','Rechercher les comptes');fetchTargets.disabled=busy;fetchTargets.dataset.moduleTargets='true';
                fetchTargets.addEventListener('click',()=>run(async()=>{const f=locationFields();const response=await api('/v1/worlds/'+encodeURIComponent(f.world).replaceAll('%3A',':')+'/recipients');if(f.world===locationFields().world&&f.account===locationFields().account){moduleTargets=response.accounts;renderWorkspace();}}));
                for(const [key,type,en,frText] of [['challenge','pack.guild.challenge','Issue challenge','Lancer un défi'],['offer','pack.diplomacy.offer','Offer truce (120s)','Proposer une trêve (120 s)'],['accept','pack.diplomacy.accept','Accept truce','Accepter la trêve']]){
                    if(!currentPackCapabilities().packActions.includes(type))continue;
                    const control=html('button',panel,'scene-button');control.type='button';control.dataset.moduleAction=key;control.textContent=label(en,frText);control.disabled=busy||!moduleTarget;control.dataset.moduleAllowed=String(Boolean(moduleTarget));
                    control.addEventListener('click',()=>run(()=>campaignCommand(type,{target:moduleTarget,...(key==='offer'?{duration:120}:{})})));
                }
            }
        }
        if(guild){
            const score=html('section',journal,'campaign-score');
            html('h5',score,'',label('Guild standing','Rang de la guilde'));
            const enabled=!document.getElementById('progression-panel').hidden;
            if(!enabled){html('p',score,'campaign-note',label('Scores are not enabled in this world.','Les scores ne sont pas activés dans ce monde.'));return;}
            const values=html('div',score,'campaign-score-values');
            for(const [key,en,frText] of [['development','Development','Développement'],['military','Military','Militaire'],['global','Global','Total']]){
                const row=html('span',values,'commerce-reading');row.dataset.campaignScore=key;
                html('small',row,'',label(en,frText));html('strong',row,'',String(scoreReading?.[key]??'—'));
            }
            const rank=html('p',score,'campaign-note',label('Rank: ','Rang : ')+(rankReading?rankReading.position+' / '+rankReading.participants:'—'));
            rank.dataset.campaignRank='true';
            const load=html('button',score,'scene-button');load.type='button';load.dataset.campaignRankLoad='true';load.textContent=label('Read rank','Consulter le rang');load.disabled=busy;
            load.addEventListener('click',()=>document.getElementById('progression-rank-load').click());
        }
    }
    function renderWorkspace(){
        for(const [block,key] of [[inventoryBlock,'inventory'],[workshop,'workshop'],[objectiveBlock,'objectives'],[tradeBlock,'tradeBoard'],[receiptBlock,'activity']])block.title.textContent=t(key);
        inventoryBlock.body.replaceChildren();
        if(!inventory.size)html('p',inventoryBlock.body,'scene-caption',t('unknownReading'));
        for(const [resource,d] of inventory){
            const row=html('div',inventoryBlock.body,'scene-resource');row.dataset.reserve=resource;
            html('strong',row,'',displayName(resource));html('span',row,'',d.stock+' / '+d.capacity);
            const meter=html('progress',row,'scene-meter');meter.max=Math.max(1,d.capacity);meter.value=Math.max(0,Math.min(d.stock,meter.max));meter.setAttribute('aria-label',resource+' '+t('reserve'));
            html('small',row,'',t('yield')+' '+d.amount+' · '+d.interval+' s');
        }
        if(inventory.size)html('p',inventoryBlock.body,'scene-caption',t('inventoryNote'));
        if(balance.elements.resource.options.length>16)html('p',inventoryBlock.body,'scene-caption',t('moreResources'));
        plotLabel.textContent=t('plot');referenceLabel.textContent=t('plotId');
        plotSelect.replaceChildren();
        const addPlot=(value,label)=>{const o=document.createElement('option');o.value=value;o.textContent=label;plotSelect.append(o);};
        addPlot('',t(catalogue.sites.length?'choosePlot':'noSite'));
        for(const site of catalogue.sites)addPlot(site.territory_id+'|'+site.entity_id,siteName(site));
        if(!catalogue.sites.some(s=>s.territory_id+'|'+s.entity_id===chosenSite))chosenSite=catalogue.sites.length===1?catalogue.sites[0].territory_id+'|'+catalogue.sites[0].entity_id:'';
        plotSelect.value=chosenSite;
        catalogueList.replaceChildren();
        if(!catalogue.types.length)html('p',catalogueList,'scene-caption',t('noTypes'));
        for(const type of catalogue.types){
            const card=html('button',catalogueList,'scene-catalogue-card');card.type='button';card.dataset.catalogue=type.id;
            card.setAttribute('aria-pressed',String(chosenType===type.id));
            const icon=el('svg',{viewBox:'0 0 120 96','aria-hidden':'true'},card);
            model(el('g',{transform:'translate(60 73) scale(.8)'},icon),type.id,{level:1,pending:null});
            html('strong',card,'',displayName(type.id));
            html('small',card,'',t('blueprint'));
            const prices=html('small',card,'scene-build-price','');
            const missing=Object.entries(type.first.costs).filter(([k,v])=>inventory.has(k)&&inventory.get(k).stock<v);
            prices.textContent=Object.entries(type.first.costs).map(([k,v])=>v+' '+displayName(k)).join(' · ');
            if(missing.length&&!stale){prices.classList.add('scene-unaffordable');html('small',card,'scene-unaffordable',lang()==='fr'?'Réserves insuffisantes pour les travaux':'Not enough reserves to start work');}
            html('small',card,'',String(type.first.duration)+' s');
            card.addEventListener('click',()=>{if(!busy){chosenType=type.id;renderWorkspace();}});
        }
        place.textContent=t('place');place.hidden=!chosenSite||!chosenType||!catalogue.types.some(x=>x.id===chosenType);
        const campaignFocus=window.AeternumGameUI.focusKey(objectiveBlock.body,['data-campaign-refresh','data-module-refresh','data-module-action','data-module-target','data-module-targets','data-chapter-action','data-campaign-rank-load']);
        objectiveBlock.body.replaceChildren();
        objectiveBlock.root.hidden=theme==='neutral'||(classic&&theme==='guild');
        if(!classic&&theme!=='neutral')renderChronicle();
        if(!campaign||!['medieval','industrial'].includes(campaign.kind)){if(classic)html('p',objectiveBlock.body,'scene-caption',t('unknownReading'));}
        else if(classic) {
            const data=campaign.data;
            const rows=Object.entries(data.victory.buildings).map(([name,v])=>[name,v.current,v.required]);
            if(campaign.kind==='medieval')rows.push([t('residents'),data.population,data.victory.populationRequired]);
            else rows.push([t('freight'),data.delivery.current,data.delivery.required]);
            for(const [name,current,required] of rows){
                const row=html('div',objectiveBlock.body,'scene-resource');row.dataset.objective=name;
                row.dataset.achieved=String(current>=required);
                html('strong',row,'',displayName(name));html('span',row,'',current+' / '+required);
                const meter=html('progress',row,'scene-meter');meter.max=Math.max(1,required);meter.value=Math.max(0,Math.min(current,meter.max));meter.setAttribute('aria-label',name);
            }
            if(data.victory.achieved)html('p',objectiveBlock.body,'scene-victory',t('achieved'));
        }
        window.AeternumGameUI.restoreFocus(objectiveBlock.body,campaignFocus);
        tradeBlock.root.hidden=theme==='neutral';refreshTradeComposer();renderCommerce();
        tradeList.replaceChildren();
        if(!offers.length)html('p',tradeList,'scene-caption',t('noOffers'));
        for(const offer of offers){
            const row=html('button',tradeList,'scene-offer');row.type='button';row.dataset.offer=offer.id;
            const terms=window.AeternumLivingArt.exchangeTerms(offer.giveResource,offer.giveUnits,offer.wantResource,offer.wantUnits);
            if(terms)exchangeGraphic(row,terms,offer.seller===locationFields().account);
            else html('strong',row,'',offer.giveUnits+' '+displayName(offer.giveResource)+' ↔ '+offer.wantUnits+' '+displayName(offer.wantResource));
            html('small',row,'',merchantName(offer.seller));row.addEventListener('click',()=>{if(!busy){chosenOffer=offer.id;tradeSelection();}});
        }
        if(offerMore)html('p',tradeList,'scene-caption',t('moreOffers'));
        tradeSelection();
        receiptBlock.body.replaceChildren();
        if(!receipts.length)html('p',receiptBlock.body,'scene-caption',t('emptyActivity'));
        for(const entry of receipts){
            const row=html('div',receiptBlock.body,'scene-receipt');row.dataset.command=entry.type;
            html('span',row,'scene-receipt-mark','✓');
            html('strong',row,'',actionLabels[entry.type]?.[lang()==='fr'?1:0]??t('receipt'));
            html('small',row,'',t('receipt'));
        }
        workspace.querySelectorAll('[data-label]').forEach(b=>b.textContent=t(b.dataset.label));
        renderPlayActions();
        renderMission();renderRelations();
    }
    // Large illustrated district. No fake structures or invented simulation entities.
    // Display positions are presentation slots, not server world coordinates.
    function districtIds(){return [...new Set([...catalogue.sites.map(s=>s.territory_id),...buildings.values()].map(x=>typeof x==='string'?x:x.territory))];}
    function moveDistrict(delta){
        const ids=districtIds();if(!ids.length)return;
        districtId=ids[(Math.max(0,ids.indexOf(districtId))+delta+ids.length)%ids.length];districtPage=0;domainCamera.reset();renderDistrict();
    }
    function ground(g,w,h){
        const p=palettes[theme],defs=el('defs',{},g),grad=el('linearGradient',{id:'district-sky',x2:'0%',y2:'100%'},defs);
        const ambient=!classic?window.AeternumLivingArt.atmosphereProfile(theme,lighting):null;
        const colors=ambient?.sky??(theme==='medieval'?['#789a9b','#c4cda5','#637d45']:theme==='industrial'?['#6b8190','#bbc3bf','#63717a']:theme==='guild'?['#ce9870','#f0c993','#ad7846']:['#799194','#b8c7be','#687f73']);
        for(let i=0;i<3;i++)el('stop',{offset:[0,42,100][i]+'%','stop-color':colors[i]},grad);
        rect(g,0,0,w,h,'url(#district-sky)');
        if(ambient)window.AeternumLivingArt.atmosphere(g,theme,lighting,w,h);
        else{
        const sun=el('g',{'aria-hidden':'true'},g);
        ellipse(sun,w*.77,h*.17,h*.16,h*.16,'#fff0bb18');ellipse(sun,w*.77,h*.17,h*.11,h*.11,'#fff0bb25');ellipse(sun,w*.77,h*.17,h*.045,h*.045,'#fff1cc');
        for(let i=0;i<5;i++){const x=w*(i+.4)/5,y=h*(.09+(i%2)*.04);ellipse(g,x,y,w*.07,h*.018,'#fff9e71a');ellipse(g,x+w*.035,y-h*.007,w*.04,h*.015,'#fff9e71a');}
        }
        const horizon=h*.26;
        for(let i=0;i<3;i++){
            let d='M0 '+(horizon+i*22);
            const hill=x=>horizon+i*22-25-Math.sin(x*.011+i*2)*35-Math.cos(x*.023+i)*12;
            if(classic){for(let x=0;x<=w+90;x+=90)d+=' L'+x+' '+hill(x);}
            else{d='M0 '+hill(0);for(let x=0;x<w;x+=45){const end=Math.min(w,x+45);d+=' Q'+(x+end)/2+' '+hill((x+end)/2)+' '+end+' '+hill(end);}}
            d+=' L'+w+' '+h+' H0 Z';
            path(g,d,[p[5],p[0],p[1]][i],{'fill-opacity':.82});
        }
        const floor=el('g',{'aria-hidden':'true','pointer-events':'none'},g);
        path(floor,'M0 '+(h*.36)+' Q'+w*.45+' '+(h*.27)+' '+w+' '+(h*.40)+' V'+h+' H0 Z',shade(floor,ambient?.earth??(theme==='medieval'?['#9ca66a','#69854c','#466b42']:theme==='industrial'?['#a7b1ab','#6d7b7a','#465963']:['#e7c28a','#bf9257','#9c7146'])));
        // Broad, layered landscape textures are non-interactive terrain, not production or map entities.
        for(let i=0;i<7;i++){
            const y=h*(.39+i*.091),bend=(i%2?1:-1)*h*.045;
            path(floor,'M0 '+y+' Q'+w*.26+' '+(y+bend)+' '+w*.48+' '+(y-h*.035)+' T'+w+' '+(y+bend),'none',{stroke:theme==='medieval'?'#d3cc92':theme==='industrial'?'#c7d2cc':'#f3d8a4','stroke-width':i%3===0?12:5,'stroke-opacity':.09});
        }
        for(let i=0;i<24;i++){
            const x=(i*197+31)%w,y=h*(.42+((i*31)%56)/100);
            ellipse(floor,x,y,10+i%6*4,2+i%3,theme==='medieval'?'#2a583c':theme==='industrial'?'#2e4650':'#926943',{'fill-opacity':.12});
        }
        if(!classic)window.AeternumLivingArt.terrainFinish(floor,theme,lighting,w,h);
        // G10 visual review: painted landscape details, not invented buildings or production.
        // A seeded sampler keeps the scenery stable when real game state changes.
        if(theme!=='neutral'){
            const seed=i=>{const v=Math.sin(i*127.1+311.7)*43758.5453;return v-Math.floor(v);};
            const scenery=el('g',{'aria-hidden':'true','pointer-events':'none','data-landscape':theme},floor);
            const clump=(x,y,k)=>{
                const a=el('g',{transform:'translate('+x+' '+y+') scale('+k+')'},scenery);
                if(theme==='medieval'){
                    ellipse(a,4,3,19,7,'#203b2980');
                    rect(a,-2,-20,4,23,'#74513c');
                    for(let n=0;n<5;n++){const xx=(seed(n+x)-.5)*22,yy=-19-seed(n+y)*18;ellipse(a,xx,yy,11+seed(n+4)*7,10+seed(n+5)*6,['#314e32','#4e7040','#65854b','#819a57','#a0ab62'][n]);}
                    path(a,'M-10 -35Q-3 -42 6 -37','none',{stroke:'#c3cb83','stroke-opacity':.45,'stroke-width':2});
                }else if(theme==='industrial'){
                    // Rock and ballast texture, not operating industrial assets.
                    poly(a,'-14,2 -10,-5 1,-7 15,0 9,5 -5,7','#5a6867','#455355');
                    poly(a,'-10,-5 1,-7 15,0 1,1','#a4aaa1');
                    line(a,-17,8,18,6,'#39494d',1);
                }else{
                    ellipse(a,3,4,19,6,'#76523960');
                    path(a,'M-2 1Q-5 -12 0 -20Q4 -10 3 2','#766b3d');
                    for(let n=0;n<6;n++){const xx=(n-2.5)*5;path(a,'M0 0Q'+xx+' -19 '+(xx*1.3)+' -8','none',{stroke:n%2?'#7f874c':'#576b42','stroke-width':2.5});}
                }
            };
            for(let i=0;i<48;i++){
                // Dense side groves frame the action area; leave the interactive centre clear.
                const side=i%2,x=(side?w*.79:0)+seed(i+10)*w*.21;
                const y=h*(.39+seed(i+90)*.56);
                clump(x,y,.42+(y/h-.35)*1.55);
            }
            for(let i=0;i<13;i++){
                const y=h*(.37+seed(i+200)*.13),x=seed(i+300)*w;
                clump(x,y,.25+seed(i+400)*.35);
            }
            if(theme==='medieval'){
                // Local terrain contours and flowering meadows, not farms generating stock.
                for(let i=0;i<65;i++){const x=seed(i+500)*w,y=h*(.42+seed(i+600)*.56);if(x>w*.27&&x<w*.73)continue;ellipse(scenery,x,y,1.4,1.1,i%3?'#eadba0':'#f2eee1');}
            }else if(theme==='industrial'){
                for(let i=0;i<7;i++){const y=h*(.75+i*.04);line(scenery,0,y,w*.18,y-w*.013,'#c1b49b',1.5);line(scenery,w*.83,y,w,y-w*.012,'#c1b49b',1.5);}
            }else{
                for(let i=0;i<15;i++){const x=seed(i+700)*w,y=h*(.62+seed(i+800)*.35);if(x>w*.25&&x<w*.75)continue;path(scenery,'M'+x+' '+y+' q15 -7 31 -2','none',{stroke:'#f4dca7','stroke-width':1.2,'stroke-opacity':.65});}
            }
        }
        // deterministic small ground marks and horizon silhouettes, decorative only.
        for(let i=0;i<180;i++){
            const x=(i*137+29)%w,y=h*.38+(i*73%(Math.floor(h*.62)));
            if(theme==='medieval'){line(floor,x,y,x+3,y-5,'#bcc28a',1);line(floor,x,y,x-2,y-3,'#354f32',1);}
            else if(theme==='industrial')line(floor,x,y,x+9,y-3,'#9da6a0',.7);
            else {ellipse(floor,x,y,2+i%3,1,'#8a633e',{'fill-opacity':.5});if(i%7===0)path(floor,'M'+(x-6)+' '+y+' q6 -4 12 0','none',{stroke:'#f2d2a0','stroke-width':.75});}
        }
        if(theme==='medieval'){
            path(floor,'M0 '+(h*.70)+' Q'+w*.27+' '+(h*.42)+' '+w*.45+' '+(h*.58)+' T'+w+' '+(h*.43),'none',{stroke:'#a1bfba','stroke-width':24});
            path(floor,'M0 '+(h*.70)+' Q'+w*.27+' '+(h*.42)+' '+w*.45+' '+(h*.58)+' T'+w+' '+(h*.43),'none',{stroke:'#527e83','stroke-width':16});
            for(let i=0;i<12;i++){const x=(i*83+15)%w,y=h*.32+(i%3)*15;tree(floor,x,y);}
            for(let i=0;i<10;i++){const x=(i*97+21)%w,y=h*.65+(i*31%(h*.32));if(i%3===0)tree(floor,x,y);else {ellipse(floor,x,y,9,3,'#38583d');ellipse(floor,x,y-3,6,5,'#789753');}}
        }else if(theme==='industrial'){
            for(let i=0;i<3;i++){const y=h*.39+i*12;line(floor,0,y,w,y,'#b5ac93',3);}
            for(let i=0;i<16;i++)line(floor,i*w/15,h*.39-4,i*w/15+8,h*.39+28,'#33444d',3);
            for(let i=0;i<5;i++){const x=i*w/4,y=h*.33;line(floor,x,y+24,x,y-24,'#43545a',2);line(floor,x-10,y-14,x+10,y-14,'#43545a',2);if(i<4)path(floor,'M'+x+' '+(y-14)+' Q'+(x+w/8)+' '+(y+3)+' '+(x+w/4)+' '+(y-14),'none',{stroke:'#34464d','stroke-width':1});}
        }else if(theme==='guild'){
            path(floor,'M0 '+h*.7+' C'+w*.32+' '+h*.35+' '+w*.65+' '+h*.85+' '+w+' '+h*.48,'none',{stroke:'#e5bf85','stroke-width':28});
            for(let i=0;i<8;i++){const x=15+i*w/8,y=h*.37;ellipse(floor,x+5,y,12,4,'#73533550');path(floor,'M'+x+' '+y+' Q'+(x+3)+' '+(y-12)+' '+(x+2)+' '+(y-28),'none',{stroke:'#795537','stroke-width':4});for(let j=-2;j<=2;j++){const xx=x+2+j*9;path(floor,'M'+(x+2)+' '+(y-28)+' Q'+xx+' '+(y-39)+' '+xx+' '+(y-20-Math.abs(j)*2),'none',{stroke:j%2?'#657c4a':'#435e3c','stroke-width':3});}}
        }
    }
    function renderDistrict(){
        const ids=districtIds();
        districtToggle.textContent=t(districtMode?'atlasView':'district');
        districtToggle.setAttribute('aria-pressed',String(districtMode));
        districtSvg.toggleAttribute('hidden',!districtMode);svg.toggleAttribute('hidden',districtMode);districtCaption.hidden=!districtMode;
        districtPrev.hidden=districtNext.hidden=!districtMode||ids.length<2;
        pagePrev.hidden=pageNext.hidden=true;
        districtZoomIn.hidden=districtZoomOut.hidden=districtReset.hidden=!districtMode;
        districtCaption.textContent=classic?t('districtNote')+' · '+t('cameraHelp'):(lang()==='fr'?'Touchez un bâtiment pour agir. Glissez ou pincez pour explorer.':'Tap a building to act. Drag or pinch to explore.');
        if(!districtMode){districtCommand.hidden=true;livingScene.hidden=true;renderArchitecture();renderAtlasPanel();return;}
        atlasPanel.hidden=true;
        if(!ids.includes(districtId)){districtId=ids[0]??'';districtPage=0;}
        renderLivingScene();
        const mobile=narrow.matches,w=mobile?360:900,h=mobile?(selectedGame?490:650):510,limit=mobile?4:6;
        const owned=[...buildings.values()].filter(b=>b.territory===districtId);
        districtPage=Math.min(districtPage,Math.max(0,Math.ceil(owned.length/limit)-1));
        pagePrev.hidden=districtPage===0;pageNext.hidden=(districtPage+1)*limit>=owned.length;
        domainCamera.size(w,h);
        districtSvg.setAttribute('aria-label',t('districtTitle'));
        const signature=[locationFields().world,locationFields().account,theme,lang(),lighting,w,h,districtId,districtPage,Boolean(selectedGame),classic,[...territories.values()],[...buildings.values()].map(stageSignature),catalogue.sites,campaign,marketReading,lastEconomicEvent?.type,receipts[0]?.type];
        districtGate.run(signature,()=>{
        const previousFocus=window.AeternumGameUI.focusKey(districtSvg,['data-district-building','data-district-site']);
        districtSvg.replaceChildren();ground(districtSvg,w,h);
        if(!classic)window.AeternumLivingArt.atmosphereDepth(districtSvg,theme,lighting,w,h);
        const n=territories.get(districtId),p=palettes[theme];
        rect(districtSvg,12,12,mobile?w-24:Math.min(w-24,310),51,'#14232be6',{rx:7,stroke:p[2]});
        el('text',{x:26,y:33,class:'district-heading'},districtSvg,n?locationName(n):t('districtTitle'));
        el('text',{x:26,y:49,class:'district-subheading'},districtSvg,t('layoutPage')+' : '+owned.length);
        if(!districtId){
            districtSvg.dataset.builtCount='0';
            el('text',{x:w/2,y:h*.55,'text-anchor':'middle',class:'district-label'},districtSvg,t('districtEmpty').slice(0,mobile?36:100));return;
        }
        const visible=owned.slice(districtPage*limit,(districtPage+1)*limit);
        districtSvg.dataset.builtCount=String(owned.filter(b=>(details.get(b.id)?.level??0)>0).length);
        const cols=mobile?2:3,spacing=w/(cols+0.15);
        const rows=Math.max(1,Math.ceil(visible.length/cols));
        // A shared approach through the visual district replaces isolated floating platforms.
        // It is an illustration of the placement layout, not a server route or an economic link.
        if(visible.length){
            const approach=el('g',{'aria-hidden':'true','pointer-events':'none'},districtSvg);
            const road=theme==='medieval'?'#a88d60':theme==='industrial'?'#424e53':'#b59060';
            const edge=theme==='medieval'?'#d4bc82':theme==='industrial'?'#a5aca4':'#e0c294';
            for(let row=0;row<rows;row++){
                const yy=h*(rows===1?.57:.43)+row*(h*.30/Math.max(1,rows-1))+spacing*.19;
                const d='M'+(w*.05)+' '+(yy+9)+' Q'+w*.48+' '+(yy-7)+' '+w*.95+' '+(yy+9);
                path(approach,d,'none',{stroke:edge,'stroke-width':mobile?24:39,'stroke-linecap':'round','stroke-opacity':.8});
                path(approach,d,'none',{stroke:road,'stroke-width':mobile?19:32,'stroke-linecap':'round'});
                if(theme==='industrial')path(approach,d,'none',{stroke:'#e9c77c','stroke-width':1.5,'stroke-dasharray':'8 8'});
                if(!classic)window.AeternumLivingArt.roadVerge(approach,theme,w,yy,mobile);
            }
        }
        visible.forEach((b,i)=>{
            const row=Math.floor(i/cols),col=i%cols;
            const x=w/2+(col-(Math.min(cols,visible.length-row*cols)-1)/2)*spacing,y=h*(rows===1?.57:.43)+row*(h*.30/Math.max(1,rows-1));
            const d=details.get(b.id),scale=mobile?(visible.length===1?1.85:1.28):(visible.length===1?2.55:1.85);
            const plot=el('g',{transform:'translate('+x+' '+y+')',class:'district-building',tabindex:0,role:'button','aria-label':buildingName(b)+' · '+(d?t('level')+' '+d.level+' · '+t(d.pending?'pending':d.level===0?'foundation':'ready'):t('planned')),'aria-pressed':'false','data-district-building':b.id,'data-district-level':d?.level??'unknown'},districtSvg);
            const hit=rect(plot,-spacing*.42,-spacing*.43,spacing*.84,spacing*.69,'transparent',{rx:12,class:'architectural-plot-hit'});
            hit.setAttribute('aria-hidden','true');
            ellipse(plot,spacing*.055,spacing*.065,spacing*.44,spacing*.17,'#14252255');
            const land=theme==='medieval'?['#a3a36c','#758555']:theme==='industrial'?['#abb2ad','#68767b']:['#dfc18e','#b18a59'];
            poly(plot,[-spacing*.43,4,0,-spacing*.20,spacing*.43,4,0,spacing*.21].join(' '),shade(plot,land),p[0]);
            for(let i=0;i<4;i++)line(plot,-spacing*.32+i*spacing*.12,3+i*3,-spacing*.04+i*spacing*.12,-spacing*.13+i*3,'#f2e5b840',1);
            const art=el('g',{transform:'scale('+scale+')',class:'ambience-lit-model'},plot);model(art,b.type,d);
            // Signal only authoritative building stages, with distinct silhouettes
            // and an animated pending ring that does not imply completion.
            const marker=el('g',{transform:'translate('+(-spacing*.32)+' '+(spacing*.23+15)+') scale(.69)','aria-hidden':'true','pointer-events':'none',class:'district-stage-marker','data-stage':!d?'unknown':d.pending?'pending':d.level>0?'built':'foundation'},plot);
            ellipse(marker,0,2,14,13,'#101b24e8',{stroke:'#d5c89f','stroke-width':1.2});
            if(d?.pending){
                const ring=ellipse(marker,0,2,10,9,'none',{stroke:'#ffd081','stroke-width':2,'stroke-dasharray':'9 5'});ring.setAttribute('class','district-pending-ring');
                path(marker,'M-3 -3H3V4H-3Z M0 -5V-3','none',{stroke:'#ffd081','stroke-width':1.5});
            }else if(d?.level>0){
                path(marker,'M-5 2L-1 6 6 -3','none',{stroke:'#b6e0a1','stroke-width':2.5,'stroke-linecap':'round','stroke-linejoin':'round'});
            }else{
                path(marker,'M-6 3L0 -3 6 3 0 8Z','none',{stroke:'#d4c49f','stroke-width':1.5});
            }
            if(d?.level>0&&!d.pending){
                const accent=el('g',{'aria-hidden':'true','pointer-events':'none',class:'district-asset-detail','data-district-decor':b.type},art);
                // These decorative props belong to an actual completed building;
                // they do not imply active production, inhabitants or shipments.
                const crate=(x,y,fill)=>{poly(accent,[x,y,x+8,y-4,x+17,y,x+9,y+4].join(' '),fill,'#443e32');poly(accent,[x,y,x+9,y+4,x+9,y+12,x,y+8].join(' '),'#806140');poly(accent,[x+9,y+4,x+17,y,x+17,y+8,x+9,y+12].join(' '),'#604d37');line(accent,x+2,y+2,x+7,y+5,'#c4aa7a',.65);};
                if(theme==='medieval'){
                    if(b.type==='lumberyard'){for(let i=0;i<4;i++){const y=8+i*3;line(accent,-36,y,-17,y-3,'#76543b',4);ellipse(accent,-17,y-3,2,2,'#d3ab70');}}
                    else if(b.type==='farm'){for(let i=0;i<6;i++){const x=-38+i*7;line(accent,x,10,x+11,5,'#ead183',1.3);}}
                    else{crate(-36,4,'#b59b65');crate(-25,7,'#c1a271');}
                    for(const x of [-41,38]){line(accent,x,8,x,-3,'#9c7b4b',1.6);line(accent,x-4,3,x+4,3,'#b69662',1);}
                }else if(theme==='industrial'){
                    for(const x of [-37,31]){rect(accent,x,5,9,6,'#7e969b',{stroke:'#364852','stroke-width':.8});line(accent,x+1,7,x+7,7,'#d0c4a1',.8);}
                    for(let i=0;i<5;i++)line(accent,-30+i*5,13,-27+i*5,9,'#e5bc64',1.3);
                }else if(theme==='guild'){
                    crate(-40,6,'#d2af75');crate(26,7,'#bd935c');
                    for(const x of [-26,36]){ellipse(accent,x,10,4,3,'#765037');rect(accent,x-4,4,8,6,'#b47c45',{rx:2});line(accent,x-4,7,x+4,7,'#775031',1);}
                }
            }
            if(d?.level>0){
                window.AeternumLivingArt.site(art,b.type,d.level);
                const evt=lastEconomicEvent,meta=evt?window.AeternumLivingArt.eventTypes[evt.type]:null;
                // These commands operate on an account, not a specific plot. Only
                // anchor the vignette if there is exactly one eligible structure;
                // otherwise the account-wide panel alone depicts the action.
                if(meta?.theme===theme&&meta.building===b.type&&[...buildings.values()].filter(x=>x.type===b.type&&(details.get(x.id)?.level??0)>0).length===1){
                    const vignette=el('g',{transform:'translate(31 -8) scale(.42)',class:'living-map-event','aria-hidden':'true','pointer-events':'none'},art);
                    window.AeternumLivingArt.event(vignette,evt.type);
                }
            }
            if(d?.level>1&&!art.querySelector('[data-architecture]')){ // Fallback for future packs without authored extensions.
                const annex=el('g',{transform:'translate('+(-spacing*.29)+' 12) scale(.48)'},plot);
                model(annex,b.type,{level:1,pending:null});
            }
            rect(plot,-spacing*.41,spacing*.23,spacing*.82,36,'#14232bea',{rx:8,stroke:p[2]});
            el('text',{x:spacing*.055,y:spacing*.23+14,'text-anchor':'middle',class:'district-label'},plot,buildingName(b));
            el('text',{x:spacing*.055,y:spacing*.23+27,'text-anchor':'middle',class:'district-subheading'},plot,d?t('level')+' '+d.level+' · '+t(d.pending?'pending':d.level===0?'foundation':'ready'):t('planned'));
            // Place one stage marker in the caption, above its background.
            // Full-size architecture retains its own level badges elsewhere.
            plot.append(marker);
            el('title',{},plot,buildingName(b));
            const choose=()=>select('building',b.id);
            plot.addEventListener('click',choose);plot.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();choose();}});
        });
        if(!visible.length||selectedGame&&visible.length<limit){
            const x=visible.length?w*.84:w/2,y=visible.length?h*.79:h*.61;
            const site=catalogue.sites.find(s=>s.territory_id===districtId);
            const attrs={transform:'translate('+x+' '+y+')'};
            if(site)Object.assign(attrs,{role:'button',tabindex:0,'aria-label':t('chooseBuild'),'data-district-site':'approved',class:'district-building'});
            if(visible.length)attrs.transform+=' scale('+(mobile?.53:.72)+')';
            const plot=el('g',attrs,districtSvg);
            ellipse(plot,0,7,91,42,'#192d2e50');
            poly(plot,'-85,0 0,-40 85,0 0,40',theme==='medieval'?'#9a9d67':theme==='industrial'?'#929e9e':'#cda976',p[2]);
            poly(plot,'-72,0 0,-34 72,0 0,34','none','#f0dca9');
            for(const x of [-64,64]){line(plot,x,5,x,-12,'#e6c98b',3);line(plot,x-5,-10,x+5,-10,'#f1ddb6',2);}
            if(site){
                path(plot,'M-22 0H22M0 -22V22','none',{stroke:'#fff1be','stroke-width':4});
                const choose=()=>{if(busy)return;chosenSite=site.territory_id+'|'+site.entity_id;renderWorkspace();focusNode(workshop.root);};
                plot.addEventListener('click',choose);plot.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();choose();}});
            }
            el('text',{x:0,y:62,'text-anchor':'middle',class:'district-label'},plot,site&&selectedGame?(lang()==='fr'?'Construire ici':'Build here'):t(site?'showBuild':'noSite'));
        }
        // The illustrated district grows with confirmed structures. A compact
        // skyline shows progress, and the actual server metrics drive the map legend.
        if(selectedGame){
            const progress=el('g',{'data-domain-progress':'true','aria-hidden':'true','pointer-events':'none'},districtSvg);
            const completed=owned.filter(b=>(details.get(b.id)?.level??0)>0).length;
            const pendingCount=owned.filter(b=>details.get(b.id)?.pending!=null).length;
            const px=mobile?12:w-244,py=mobile?69:12,pw=mobile?w-24:232;
            rect(progress,px,py,pw,43,'#15272be8',{rx:7,stroke:p[2]});
            const fr=lang()==='fr';
            el('text',{x:px+12,y:py+16,class:'district-label'},progress,completed+' '+(fr?'bâtiment(s) achevé(s)':'completed building(s)'));
            let metric=pendingCount+' '+(fr?'chantier(s)':'construction(s)');
            if(campaign?.kind==='medieval')metric=campaign.data.population+' / '+campaign.data.capacity+' '+(fr?'habitants':'residents');
            else if(campaign?.kind==='industrial')metric=campaign.data.grid.supply+' / '+campaign.data.grid.load+' '+(fr?'énergie réseau':'grid power');
            else if(theme==='guild'&&marketReading!==null)metric=marketReading+' '+(fr?'offres consultées':'offers loaded');
            el('text',{x:px+12,y:py+31,class:'district-subheading'},progress,metric);
        }
        // Event feedback reflects the last acknowledged server command only.
        if(receipts.length){
            const entry=receipts[0],label=actionLabels[entry.type]?.[lang()==='fr'?1:0]??t('receipt');
            const notification=el('g',{'data-district-receipt':entry.type,'aria-hidden':'true',class:'district-receipt'},districtSvg);
            rect(notification,12,h-44,w-24,32,'#172b24ed',{rx:6,stroke:'#9eb887'});
            el('text',{x:24,y:h-24,class:'district-label'},notification,'✓ '+label);
        }
        window.AeternumGameUI.restoreFocus(districtSvg,previousFocus);
        });renderCounts(districtSvg,districtGate);updateSelection();
    }
    function tile(g,x,y,territory,index){
        const p=palettes[theme],a=el('g',{transform:'translate('+x+' '+y+')',tabindex:0,role:'button','aria-label':locationName(territory)+' · '+territory.kind,'data-territory':territory.id,'aria-pressed':'false',class:'scene-territory'},g);
        el('title',{},a,locationName(territory));
        const illustrated=!classic&&theme!=='neutral';
        if(illustrated){
            // A real territory keeps an explicit accessible hit area; the
            // illustration itself never intercepts pointer selection.
            rect(a,-116,-55,232,141,'transparent',{class:'atlas-region-hit',rx:14});
            window.AeternumLivingArt.atlasRegion(a,theme,territory.control);
        }else{
        poly(a,'-115,0 0,49 115,0 115,14 0,63 -115,14',p[0],'#111e22');
        poly(a,'-115,0 0,-49 115,0 0,49',p[1],'#233b39');
        for(let i=0;i<5;i++)line(a,-78+i*9,0+i*3,-16+i*9,-27+i*3,p[2],2);
        // Terrain accents are decorative; holdings are drawn exclusively from the owned catalogue.
        if(theme==='medieval'){tree(a,-80,-5);tree(a,-62,3);tree(a,72,0);for(let i=0;i<6;i++)ellipse(a,-33+i*12,22-i*3,3,1.5,'#c4bd7a');}
        else if(theme==='industrial'){line(a,-85,10,-20,35,'#c3a477',4);line(a,-87,16,-22,41,'#25323b',2);for(let i=0;i<5;i++)line(a,-76+i*11,11+i*4,-82+i*11,17+i*4,'#a2a49b',1);}
        else if(theme==='guild'){path(a,'M-87 4 Q-38 24 18 7 T87 -4','none',{stroke:'#d7ad6c','stroke-width':5});for(let i=0;i<5;i++)ellipse(a,-70+i*34,16-i*4,5,2,'#82664b');}
        else {poly(a,'-30,5 0,-27 29,5',p[2]);ellipse(a,34,13,16,5,p[5]);}
        }
        const owned=[...buildings.values()].filter(b=>b.territory===territory.id);
        owned.slice(0,3).forEach((b,i)=>{
            const layer=el('g',{transform:'translate('+((i-(Math.min(owned.length,3)-1)/2)*52)+' '+(i%2?17:0)+') scale(.65)','data-map-holding':b.id},a);
            model(layer,b.type,details.get(b.id));
        });
        if(owned.length>3)el('text',{x:75,y:35,fill:'#fff','font-size':11},a,'+'+(owned.length-3));
        rect(a,-100,51,200,35,'#15232bea',{rx:7,stroke:'#ffffff35'});
        el('text',{x:0,y:66,'text-anchor':'middle',class:'scene-node-label'},a,locationName(territory).length>28?locationName(territory).slice(0,26)+'…':locationName(territory));
        const fr=lang()==='fr';
        const status=territory.control==='own'?(fr?'Votre domaine':'Your domain'):territory.control==='occupied'?(fr?'Occupé':'Occupied'):(fr?'Libre':'Unclaimed');
        el('text',{x:0,y:79,'text-anchor':'middle',class:'scene-node-meta'},a,illustrated?status+' · '+owned.length+' '+(fr?'bât.':'holdings'):locationName(territory));
        a.addEventListener('click',()=>select('territory',territory.id));
        a.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();select('territory',territory.id);}});
    }
    function render(){
        host.dataset.theme=theme; title.textContent=t(theme);renderAmbience();renderSound();
        campaignButton.hidden=theme==='neutral'||(theme==='guild'&&document.getElementById('progression-panel').hidden);
        marketButton.hidden=theme==='neutral';
        for(const op of operations)op.control.hidden=op.pack!==theme;
        renderHud();
        summary.textContent=territories.size+' '+t('territories')+' · '+buildings.size+' '+t('registered');
        hint.textContent=t('caption');holdingTitle.textContent=t('holdings');
        host.querySelectorAll('[data-label]').forEach(b=>b.textContent=t(b.dataset.label));
        svg.setAttribute('aria-label',t(theme));
        const sceneContext=[locationFields().world,locationFields().account,theme,lang(),Boolean(selectedGame),classic];
        const structureState=[...buildings.values()].map(stageSignature);
        atlasGate.run([...sceneContext,narrow.matches,lighting,[...territories.values()],structureState,[...links],route],()=>{
        svg.replaceChildren();
        const nodes=[...territories.values()],cols=narrow.matches?1:Math.min(3,Math.max(1,nodes.length));
        const rows=Math.max(1,Math.ceil(nodes.length/cols));
        extent={w:narrow.matches?340:Math.max(440,cols*270+60),h:Math.max(narrow.matches?310:300,rows*155+90)};
        // Initial viewport fits the first two rows; larger worlds remain pannable.
        if(classic&&rows>2&&camera.z===1)camera.z=Math.min(3,extent.h/(2*155+90));
        const p=palettes[theme],defs=el('defs',{},svg),gradient=el('linearGradient',{id:'scene-bg',x1:'0%',y1:'0%',x2:'100%',y2:'100%'},defs);
        el('stop',{offset:'0%','stop-color':p[5]},gradient);el('stop',{offset:'100%','stop-color':p[0]},gradient);
        const illustrated=!classic&&theme!=='neutral';
        if(illustrated){
            window.AeternumLivingArt.atlasTerrain(svg,theme,extent.w,extent.h);
            window.AeternumLivingArt.atmosphere(svg,theme,lighting,extent.w,extent.h,'atlas');
            window.AeternumLivingArt.atmosphereDepth(svg,theme,lighting,extent.w,extent.h,'atlas');
        }
        else rect(svg,0,0,extent.w,extent.h,'url(#scene-bg)');
        for(let i=0;!illustrated&&i<extent.h/22+3;i++){
            const y=i*22-30;
            path(svg,'M0 '+y+' Q'+extent.w*.25+' '+(y-35)+' '+extent.w*.5+' '+y+' T'+extent.w+' '+y,'none',{stroke:p[2],'stroke-opacity':.15,'stroke-width':1});
        }
        const border=el('g',{'aria-hidden':'true','pointer-events':'none'},svg);
        for(const [x,y,dx,dy] of [[12,12,1,1],[extent.w-12,12,-1,1],[12,extent.h-12,1,-1],[extent.w-12,extent.h-12,-1,-1]]){
            line(border,x,y,x+dx*24,y,p[2],2);line(border,x,y,x,y+dy*24,p[2],2);
        }
        const positions=new Map();atlasPositions=positions;
        nodes.forEach((n,i)=>{const x=extent.w/2+((i%cols)-(cols-1)/2)*270,y=(extent.h-rows*155)/2+65+Math.floor(i/cols)*155;positions.set(n.location,{x,y});});
        for(const [from,neighbors] of links)for(const n of neighbors){
            const a=positions.get(from),b=positions.get(n.location);if(!a||!b)continue;
            if(illustrated){const layer=el('g',{'data-atlas-link':'true'},svg);window.AeternumLivingArt.atlasEdge(layer,theme,a,b);el('title',{},layer,(lang()==='fr'?'Coût : ':'Cost: ')+n.cost);}
            else{const l=line(svg,a.x,a.y,b.x,b.y,p[2],5);l.setAttribute('stroke-dasharray','7 5');el('title',{},l,(isTechnicalId(from)?t('plotNumber'):from)+' → '+(isTechnicalId(n.location)?t('plotNumber'):n.location)+' · '+n.cost);}
        }
        for(let i=1;i<route.length;i++){
            const a=positions.get(route[i-1]),b=positions.get(route[i]);if(!a||!b)continue;
            if(illustrated){const layer=el('g',{'data-atlas-route-segment':'true'},svg);window.AeternumLivingArt.atlasEdge(layer,theme,a,b,{confirmed:true});}
            else{const road=line(svg,a.x,a.y,b.x,b.y,'#ffe095',8);road.setAttribute('class','scene-confirmed-route');}
        }
        nodes.forEach((n,i)=>{const q=positions.get(n.location);tile(svg,q.x,q.y,n,i);});
        if(!nodes.length){const label=el('text',{x:extent.w/2,y:extent.h/2-8,'text-anchor':'middle',class:'scene-empty'},svg);const words=t('empty').split(' ');const split=Math.ceil(words.length/2);el('tspan',{x:extent.w/2,dy:0},label,words.slice(0,split).join(' '));el('tspan',{x:extent.w/2,dy:20},label,words.slice(split).join(' '));}
        });renderCounts(svg,atlasGate);
        holdingGate.run([...sceneContext,structureState],()=>{
        holdingList.replaceChildren();
        if(!buildings.size)html('p',holdingList,'scene-caption',t('none'));
        for(const b of buildings.values()){
            const card=html('button',holdingList,'scene-holding');card.type='button';card.dataset.building=b.id;
            const d=details.get(b.id);
            card.dataset.level=d?String(d.level):'unknown';
            card.dataset.stage=!d?'unknown':d.pending?'pending':d.level===0?'foundation':'built';
            const art=el('svg',{viewBox:'0 0 130 100','aria-hidden':'true'},card);
            const layer=el('g',{transform:'translate(63 75)'},art);
            model(layer,b.type,d);
            html('strong',card,'',buildingName(b));
            html('small',card,'scene-stage',d?t('level')+' '+d.level+' · '+t(d.pending?'pending':d.level===0?'foundation':'ready'):t('planned'));
            card.addEventListener('click',()=>select('building',b.id));
        }
        });renderCounts(holdingList,holdingGate);
        renderWorkspace();view();renderDistrict();updateSelection();
    }
    const on=(name,fn)=>window.addEventListener('aeternum:'+name,e=>fn(e.detail));
    on('context',d=>{relationsTab='alliances';resetTutorial();atlasGate.invalidate();districtGate.invalidate();holdingGate.invalidate();botReport=null;botExpanded=false;theme=d.theme??(d.world?theme:'neutral');audio.reset();audio.setTheme(theme);soundVisibility();soundSeen.clear();soundVictory=false;reading=null;campaign=null;moduleReading=null;moduleTarget='';moduleTargets=[];stagesKnown=false;scoreReading=null;rankReading=null;campaignStale=false;marketReading=null;stale=false;inventory.clear();catalogue={sites:[],types:[]};chosenSite='';chosenType='';referenceInput.value='';districtMode=false;districtId='';districtPage=0;domainCamera.reset();offers=[];offerMore=false;chosenOffer='';tradeHistory=null;tradeHistoryMore=false;for(const {input} of Object.values(filters))input.value='';receipts=[];lastEconomicEvent=null;economicJournal.reset();selectedOperationId=renderedOperationId=null;operationView.replaceChildren();operationHistory.replaceChildren();operationPanel.hidden=true;territories.clear();buildings.clear();details.clear();links.clear();clearAtlasRoute();selected=null;camera={x:0,y:0,z:1};render();});
    on('territories',d=>{if(!d.append){territories.clear();links.clear();clearAtlasRoute();selected=null;}for(const n of d.territories)territories.set(n.id,n);render();queueMicrotask(renderAtlasPanel);});
    on('links',d=>{links.set(d.location,d.neighbors);render();});
    on('route',d=>{route=d?.locations??[];routeCost=d?.cost??null;routeRead=true;render();});
    on('buildings',d=>{catalogue={sites:d.sites,types:d.types};buildings=new Map(d.buildings.map(b=>[b.id,b]));for(const id of details.keys())if(!buildings.has(id))details.delete(id);render();});
    on('building',d=>{buildings.set(d.id,d);details.set(d.id,d);render();});
    on('reserve',d=>{reading=d;inventory.set(d.resource,d.data);stale=false;renderHud();renderWorkspace();updateSelection();});
    on('campaign-module',d=>{const f=locationFields();if(d.world!==f.world||d.account!==f.account)return;moduleReading=d.data;if(theme==='guild'){const won=d.data?.victory?.achieved===true;if(won&&!soundVictory&&campaignStale)audio.cue('campaign.victory');soundVictory=won;campaignStale=false;}renderWorkspace();});
    on('campaign',d=>{if(d.kind==='progression'){scoreReading=d.data;if(theme==='guild')campaign=d;}else {campaign=d;const won=d.data?.victory?.achieved===true;if(won&&!soundVictory&&campaignStale)audio.cue('campaign.victory');soundVictory=won;}campaignStale=false;renderHud();renderWorkspace();renderDistrict();});
    on('social-contact',d=>{if(classic||busy||d.world!==locationFields().world||!['message','block'].includes(d.kind)||typeof d.actor!=='string')return;relationsTab=d.kind==='message'?'messages':'safety';renderRelations();focusNode(relationsBlock.root);const input=document.getElementById(d.kind==='message'?'social-recipient':'social-block-target');input.value=d.actor;input.focus({preventScroll:true});});
    on('rank',d=>{const f=locationFields();if(d.world!==f.world||d.account!==f.account)return;rankReading=d.data;renderWorkspace();});
    on('trade-history',d=>{const f=locationFields();if(d.transactions!==null&&(d.world!==f.world||d.account!==f.account))return;tradeHistory=d.transactions;tradeHistoryMore=d.more===true;renderCommerce();});
    on('market-snapshot',d=>{marketReading=d.count;offers=d.offers??[];offerMore=d.more===true;chosenOffer='';renderHud();renderWorkspace();renderDistrict();updateSelection();});
    on('confirmed',d=>{
        const context={...locationFields(),theme};
        if(d.world!==context.world||d.account!==context.account)return;
        soundReceipt(d);
        if(window.AeternumTutorial.tracks[theme]?.some(l=>l.kind==='receipt'&&l.target===d.type))tutorialReceipts.add(d.type);
        if(economicJournal.record(d,context)){lastEconomicEvent={id:d.id,type:d.type};selectedOperationId=d.id;}
        if(d.type==='building.place')referenceInput.value='';campaignStale=true;rankReading=null;reading=null;marketReading=null;offers=[];offerMore=false;chosenOffer='';stale=true;receipts=[{type:d.type},...receipts].slice(0,8);renderHud();renderWorkspace();renderDistrict();});
    on('resource-change',()=>{reading=null;renderHud();});
    on('activity',d=>{host.setAttribute('aria-busy',String(d.busy));renderAmbience();renderSound();renderTutorial();renderRelations();districtPrimary.disabled=districtDismiss.disabled=d.busy;if(!d.busy){renderPlayActions();updateSelection();renderMission();refreshDraft();updateCommerceFilters();for(const b of objectiveBlock.body.querySelectorAll('[data-campaign-refresh],[data-campaign-rank-load],[data-module-refresh],[data-module-targets]'))b.disabled=false;for(const b of objectiveBlock.body.querySelectorAll('[data-module-action]'))b.disabled=b.dataset.moduleAllowed!=='true';}});
    document.getElementById('locale-select').addEventListener('change',render);
    narrow.addEventListener('change',()=>{camera={x:0,y:0,z:1};render();if(!classic&&!districtMode&&selected?.type==='territory')centerAtlas(selected.id);});
    document.getElementById('map-location').addEventListener('input',updateSelection);
    document.getElementById('map-destination').addEventListener('input',updateSelection);
    // Reusable shell mounts the existing scene slots, not a duplicate game implementation.
    const classic=new URLSearchParams(location.search).get('view')==='classic';
    const rootGame=document.getElementById('game');
    const legacy=html('details',rootGame,'game-ui-legacy');
    const legacySummary=html('summary',legacy,'',lang()==='fr'?'Commandes détaillées':'Detailed controls');
    const originalChildren=[...rootGame.children].filter(n=>n!==legacy);
    rootGame.prepend(host);
    for(const child of originalChildren)if(child!==host)legacy.append(child);
    legacy.open=classic;
    document.documentElement.dataset.gamePresentation=classic?'classic':'play';
    const contextControls=html('div',host,'game-ui-selectors');contextControls.hidden=classic;
    const mirrors={};
    for(const key of ['world','account','resource']){
        const label=html('label',contextControls,'');
        const title=html('span',label,'');
        const select=html('select',label,'');select.dataset.gameContext=key;
        mirrors[key]={title,select};
        select.addEventListener('change',()=>{
            if(busy)return;
            const original=balance.elements[key];
            if([...original.options].some(o=>o.value===select.value)){original.value=select.value;original.dispatchEvent(new Event('change'));}
            syncMirrors();
            if(key!=='resource'&&initialLoad&&!busy){initialLoad=false;run(loadInitialScene);}
        });
    }
    function syncMirrors(){
        for(const [key,{title,select}] of Object.entries(mirrors)){
            title.textContent=({world:['Active world','Monde actif'],account:['Player domain','Domaine du joueur'],resource:['Active resource','Ressource active']})[key][lang()==='fr'?1:0];
            const original=balance.elements[key];select.replaceChildren();
            [...original.options].forEach((o,i)=>{
                const n=document.createElement('option');n.value=o.value;
                n.textContent=key==='account'?(lang()==='fr'?'Domaine ':'Domain ')+(i+1):key==='resource'?displayName(o.value):(lang()==='fr'?'Monde ':'World ')+(i+1)+' · '+(isTechnicalId(worlds.find(w=>w.world===o.value)?.name)?t(theme):(worlds.find(w=>w.world===o.value)?.name??t(theme)));
                select.append(n);
            });
            select.value=original.value;select.disabled=busy;
            select.parentElement.hidden=Boolean(selectedGame&&key!=='resource'&&original.options.length<=1);
        }
    }
    // G23: relocate, never duplicate, the original authenticated social
    // forms. All authorization, validation, rate limits and mutations remain
    // in their original API handlers. Classic mode retains its exact DOM.
    let relationsTab='alliances';
    const relationTabs=new Map(),relationViews=new Map();
    let relationsTitle=null,relationsDescription=null,relationsArt=null,relationsArtTheme=null;
    function setupRelations(){
        relationsBlock.root.hidden=classic;
        if(classic)return;
        const root=relationsBlock.body;
        const header=html('div',root,'relations-header');
        relationsArt=el('svg',{viewBox:'0 0 480 130','aria-hidden':'true',class:'relations-art'},header);
        relationsTitle=html('h3',header,'');
        relationsDescription=html('p',header,'relations-note');
        const nav=html('div',root,'relations-tabs');nav.setAttribute('role','group');
        const alliances=document.getElementById('social-panel'),mail=document.getElementById('social-mail-panel');
        const add=(key,section)=>{
            const button=html('button',nav,'scene-button');button.type='button';button.dataset.relationsTab=key;
            const view=html('div',root,'relations-view');view.dataset.relationsView=key;view.append(section);
            relationTabs.set(key,button);relationViews.set(key,view);
            button.addEventListener('click',()=>{if(!busy){relationsTab=key;renderRelations();}});
            return view;
        };
        add('alliances',alliances);
        // Preserve the mail section and its existing status. Group its
        // direct children by purpose without changing form or element IDs.
        const inbox=document.createElement('section'),safety=document.createElement('section'),standing=document.createElement('section');
        inbox.className=safety.className=standing.className='relations-section';
        const move=(target,ids)=>{for(const id of ids)target.append(document.getElementById(id));};
        move(inbox,['social-send-form','social-messages-refresh','social-messages-list','social-messages-next']);
        move(safety,['social-block-form','social-blocks-refresh','social-block-list','social-blocks-next','social-report-form']);
        move(standing,['social-leaderboard-refresh','social-leaderboard-list','social-leaderboard-next']);
        // The notification label is a direct sibling, not part of a form.
        const notificationLabel=mail.querySelector('label[for=social-notification-id]');
        move(inbox,['social-notifications-refresh']);inbox.append(notificationLabel);
        move(inbox,['social-notification-id','social-notification-read','social-notifications-list','social-notifications-next']);
        const status=document.getElementById('social-mail-notice');root.append(status);
        mail.hidden=true;
        add('messages',inbox);add('safety',safety);add('standing',standing);
        for(const [section,en,frText] of [[inbox,'Messages and notifications','Messages et notifications'],[safety,'Player safety','Protection des joueurs'],[standing,'Alliance leaderboard','Classement des alliances']]){const heading=document.createElement('h2');heading.dataset.relationsEn=en;heading.dataset.relationsFr=frText;section.prepend(heading);}
        // Keep the original report flow: a message can open moderation with
        // the exact selected message ID, not a guessed or retyped identifier.
        inbox.addEventListener('click',e=>{
            if(e.target.closest('[data-social-report]')){
                relationsTab='safety';renderRelations();
                document.getElementById('social-report-reason').focus();
            }
        });
        // Explicitly clear privately typed drafts when the context changes.
        document.getElementById('social-role-choice').dataset.relationsRole='true';
    }
    function renderRelations(){
        if(classic||!relationsTitle)return;
        const fr=lang()==='fr',label=(en,f)=>fr?f:en;
        relationsBlock.root.hidden=!locationFields().world;
        relationsBlock.title.textContent=label('Relations','Relations');
        relationsTitle.textContent={medieval:label('Council of the realm','Conseil du royaume'),industrial:label('Industrial consortium','Consortium industriel'),guild:label('Merchant council','Conseil des marchands')}[theme]??label('Player relations','Relations entre joueurs');
        relationsDescription.textContent=label('Alliances, private correspondence and world-scoped diplomacy. Use a known player identifier for invitations or messages. Member and message records provide contact shortcuts. The server checks every permission.','Alliances, correspondance privée et diplomatie à l’échelle du monde. Utilisez un identifiant de joueur connu pour les invitations ou les messages. Les membres et messages fournissent des raccourcis de contact. Le serveur contrôle chaque autorisation.');
        if(relationsArtTheme!==theme){
            relationsArt.replaceChildren();window.AeternumLivingArt.marketHall(relationsArt,theme);relationsArtTheme=theme;
        }
        const names={alliances:label('Alliances','Alliances'),messages:label('Correspondence','Correspondance'),safety:label('Safety & reports','Blocages et signalements'),standing:label('Alliance ranking','Classement des alliances')};
        for(const [key,button] of relationTabs){
            button.textContent=names[key];button.setAttribute('aria-pressed',String(relationsTab===key));
            button.disabled=busy;relationViews.get(key).hidden=relationsTab!==key;
        }
        for(const heading of relationsBlock.body.querySelectorAll('[data-relations-en]'))heading.textContent=fr?heading.dataset.relationsFr:heading.dataset.relationsEn;
        for(const option of document.getElementById('social-role-choice').options){
            option.textContent=option.value==='officer'?label('Officer','Officier'):label('Member','Membre');
        }
    }
    const panels=new Map([['holdings',holdingList],['construction',workshop.root],['reserves',inventoryBlock.root],['objectives',objectiveBlock.root],['market',tradeBlock.root],['activity',receiptBlock.root],['relations',relationsBlock.root]]);
    setupRelations();
    for(const [key,node] of panels)node.dataset.workspacePanel=key;
    holdingTitle.dataset.workspacePanel='holdings';
    function revealPanel(key){host.dataset.workspace=key;for(const button of host.querySelectorAll('[data-game-nav]'))button.setAttribute('aria-pressed',String(button.dataset.gameNav===key));}
    const focusNode=n=>{const key=n.dataset.workspacePanel;if(key&&!classic)revealPanel(key);n.scrollIntoView({block:'nearest'});n.querySelector('button:not([hidden]),select,input')?.focus({preventScroll:true});};
    const showDistrict=mode=>{if(!classic)revealPanel(mode?'domain':'atlas');districtMode=mode;districtPage=0;domainCamera.reset();render();focusNode(districtBar);};
    // The next step is derived from server-confirmed construction state, not a local tutorial counter.
    const mission=html('div',host,'scene-first-mission');mission.hidden=classic;
    mission.setAttribute('aria-label','First objective');
    const missionKicker=html('strong',mission,''),missionTitle=html('h3',mission,''),missionCopy=html('p',mission,'');
    const missionGo=html('button',mission,'scene-button');missionGo.type='button';missionGo.dataset.firstMission='true';
    let missionAction=null;
    missionGo.addEventListener('click',()=>{if(!busy)missionAction?.();});
    // Optional, non-blocking apprenticeship. Explicit navigation only;
    // neither opening a lesson nor following its shortcut issues a command.
    const tutorial=html('details',host,'tutorial-panel');tutorial.hidden=true;
    const tutorialSummary=html('summary',tutorial,'tutorial-summary');
    const tutorialTitle=html('strong',tutorialSummary,'');
    const tutorialCount=html('span',tutorialSummary,'tutorial-count');
    const tutorialBody=html('section',tutorial,'tutorial-body');
    const tutorialIntro=html('p',tutorialBody,'tutorial-note');
    const tutorialMeter=html('progress',tutorialBody,'scene-meter tutorial-progress');
    const tutorialList=html('ol',tutorialBody,'tutorial-list');
    const tutorialDetail=html('article',tutorialBody,'tutorial-detail');
    const tutorialArt=el('svg',{viewBox:'0 0 320 120','aria-hidden':'true',class:'tutorial-art'},tutorialDetail);
    const tutorialContent=html('div',tutorialDetail,'tutorial-content');
    const tutorialState=html('small',tutorialContent,'tutorial-state');
    const tutorialLessonTitle=html('h4',tutorialContent,'');
    const tutorialText=html('p',tutorialContent,'');
    const tutorialTip=html('p',tutorialContent,'tutorial-tip');
    const tutorialHint=html('p',tutorialContent,'tutorial-note');
    const tutorialGo=html('button',tutorialContent,'scene-button');tutorialGo.type='button';tutorialGo.dataset.tutorialGo='true';
    const tutorialNav=html('div',tutorialBody,'tutorial-navigation');
    const tutorialPrev=html('button',tutorialNav,'scene-button');tutorialPrev.type='button';tutorialPrev.dataset.tutorialPrevious='true';
    const tutorialNext=html('button',tutorialNav,'scene-button');tutorialNext.type='button';tutorialNext.dataset.tutorialNext='true';
    const tutorialFollow=html('button',tutorialNav,'scene-button');tutorialFollow.type='button';tutorialFollow.dataset.tutorialFollow='true';
    const tutorialScope=html('p',tutorialBody,'tutorial-note');
    const tutorialReceipts=new Set();
    let tutorialIndex=null,tutorialProjection=null,tutorialArtKey=null;
    const tutorialPosition=()=>tutorialIndex??(tutorialProjection?.next===-1?Math.max(0,tutorialProjection.total-1):Math.max(0,tutorialProjection?.next??0));
    const tutorialLesson=()=>tutorialProjection?.rows[tutorialPosition()]??null;
    function resetTutorial(){
        tutorialReceipts.clear();tutorialIndex=null;tutorialProjection=null;tutorialArtKey=null;
        tutorial.open=false;tutorialArt.replaceChildren();tutorialList.replaceChildren();tutorial.hidden=true;
    }
    function tutorialBuilding(type){
        return [...buildings.values()].filter(b=>b.type===type).sort((a,b)=>(details.get(b.id)?.level??-1)-(details.get(a.id)?.level??-1))[0];
    }
    function tutorialNavigate(lesson){
        if(busy||!lesson)return;
        if(lesson.kind==='building'){
            if(lesson.state==='unknown'&&!stagesKnown){run(readStages);return;}
            const b=tutorialBuilding(lesson.target);
            if(b){districtId=b.territory;showDistrict(true);select('building',b.id);return;}
            if(catalogue.types.some(t=>t.id===lesson.target)){
                chosenType=lesson.target;
                if(!chosenSite&&catalogue.sites.length===1){const site=catalogue.sites[0];chosenSite=site.territory_id+'|'+site.entity_id;}
                renderWorkspace();focusNode(workshop.root);
            }
            return;
        }
        if(lesson.target==='market.create'){focusNode(tradeBlock.root);document.getElementById('market-load').click();return;}
        const key={'settlement.feed':'feed','settlement.work':'work','industry.generate':'generate','industry.refine':'refine','industry.ship':'ship'}[lesson.target];
        if(!key)return;
        showDistrict(true);
        playActions.scrollIntoView({block:'nearest'});
        const target=playGrid.querySelector('[data-domain-action='+key+']');
        if(target&&!target.disabled)target.focus({preventScroll:true});
    }
    tutorialGo.addEventListener('click',()=>tutorialNavigate(tutorialLesson()));
    tutorialPrev.addEventListener('click',()=>{if(!tutorialProjection)return;tutorialIndex=Math.max(0,tutorialPosition()-1);renderTutorial();});
    tutorialNext.addEventListener('click',()=>{if(!tutorialProjection)return;tutorialIndex=Math.min(tutorialProjection.total-1,tutorialPosition()+1);renderTutorial();});
    tutorialFollow.addEventListener('click',()=>{tutorialIndex=null;renderTutorial();});
    tutorial.addEventListener('toggle',()=>{if(tutorial.open)renderTutorial();});
    function renderTutorial(){
        tutorial.hidden=classic||!selectedGame||theme==='neutral';
        if(tutorial.hidden)return;
        const language=lang()==='fr'?'fr':'en',strings=window.AeternumTutorial.messages[language];
        const projection=window.AeternumTutorial.project(theme,{known:stagesKnown,holdings:[...buildings.values()].map(b=>({type:b.type,level:details.get(b.id)?.level,pending:Boolean(details.get(b.id)?.pending)})),confirmed:[...tutorialReceipts]});
        tutorialProjection=projection;
        tutorialTitle.textContent=strings.heading;
        tutorialCount.textContent=projection.done+' / '+projection.total;
        tutorial.dataset.tutorialDone=String(projection.done);
        tutorial.dataset.tutorialTotal=String(projection.total);
        tutorial.dataset.tutorialFinished=String(projection.finished);
        if(!tutorial.open)return;
        tutorialIntro.textContent=projection.finished?strings.finished:strings.intro;
        tutorialMeter.max=Math.max(1,projection.total);tutorialMeter.value=projection.done;
        tutorialMeter.setAttribute('aria-label',strings.summary);
        tutorialScope.textContent=strings.scope;
        const index=tutorialIndex??(projection.next===-1?Math.max(0,projection.total-1):projection.next);
        const lesson=projection.rows[index];
        if(!lesson)return;
        tutorial.dataset.tutorialLesson=lesson.id;
        tutorialDetail.dataset.lessonState=lesson.state;
        const activeId=document.activeElement?.dataset?.tutorialSelect;
        tutorialList.replaceChildren();
        for(let i=0;i<projection.rows.length;i++){
            const row=projection.rows[i],item=html('li',tutorialList,'tutorial-list-item');
            const control=html('button',item,'tutorial-lesson-button');
            control.type='button';control.dataset.tutorialSelect=row.id;control.dataset.lessonState=row.state;
            control.setAttribute('aria-pressed',String(index===i));
            html('span',control,'tutorial-lesson-number',String(i+1));
            html('span',control,'tutorial-lesson-label',row.copy[language][0]);
            html('small',control,'tutorial-lesson-status',strings[row.state]);
            control.addEventListener('click',()=>{tutorialIndex=i;renderTutorial();});
            if(activeId===row.id)control.focus({preventScroll:true});
        }
        const artKey=JSON.stringify([theme,lesson.id,lesson.state]);
        if(artKey!==tutorialArtKey){
            tutorialArt.replaceChildren();window.AeternumLivingArt.chapterLandscape(tutorialArt,theme,lesson.id);
            // An illustration of the lesson's target, not another owned holding.
            if(lesson.kind==='receipt')window.AeternumLivingArt.event(el('g',{transform:'translate(160 76) scale(1.1)'},tutorialArt),lesson.target);
            else model(el('g',{transform:'translate(160 100) scale(1.12)'},tutorialArt),lesson.art,{level:lesson.state==='complete'?1:0,pending:lesson.state==='pending'?{}:null});
            tutorialArtKey=artKey;
        }
        tutorialState.textContent=strings[lesson.state];tutorialLessonTitle.textContent=lesson.copy[language][0];
        tutorialText.textContent=lesson.copy[language][1];tutorialTip.textContent=lesson.copy[language][2];
        const unavailable=lesson.kind==='building'&&stagesKnown&&!tutorialBuilding(lesson.target)&&!catalogue.types.some(t=>t.id===lesson.target);
        tutorialHint.textContent=unavailable?strings.unsupported:lesson.kind==='receipt'?strings.actionHint:lesson.state==='pending'?strings.waitHint:strings.buildHint;
        tutorialGo.textContent=lesson.kind==='building'&&lesson.state==='unknown'&&!stagesKnown?strings.refresh:lesson.state==='complete'?strings.review:strings.go;
        tutorialGo.disabled=busy||unavailable;
        tutorialPrev.textContent=strings.previous;tutorialPrev.disabled=index===0;
        tutorialNext.textContent=strings.next;tutorialNext.disabled=index>=projection.total-1;
        tutorialFollow.textContent=strings.follow;tutorialFollow.disabled=tutorialIndex===null;
    }
    function renderMission(){
        const french=lang()==='fr',pick=(en,fr)=>french?fr:en;
        renderTutorial();
        mission.hidden=classic||theme==='neutral';
        if(mission.hidden)return;
        missionKicker.textContent=pick('Your next step','Votre prochain objectif');
        const order={medieval:['lumberyard','farm','granary','keep'],industrial:['power_plant','warehouse','steelworks','freight_terminal'],guild:['market_hall','caravan_hub']}[theme];
        const completed=id=>[...buildings.values()].some(x=>x.type===id&&(details.get(x.id)?.level??0)>0);
        const eligible=order.filter(id=>catalogue.types.some(t=>t.id===id));
        const first=eligible.find(id=>!completed(id))??eligible.find(id=>[...buildings.values()].some(b=>b.type===id&&details.get(b.id)?.next))??eligible[0]??order[0];
        const advanced=completed(order[0]);
        const type=catalogue.types.find(x=>x.id===first);
        const building=[...buildings.values()].find(x=>x.type===first);
        const detail=building?details.get(building.id):null;
        let title,copy,cta,step;
        if(!building){
            step=advanced?'next':'place';title=advanced?pick('Expand your domain','Développez votre domaine'):pick('Found your first building','Fondez votre premier bâtiment');
            copy=(advanced?pick('Next building: ','Prochain bâtiment : '):pick('Start with a ','Commencez par : '))+displayName(first)+pick('. Choose a plot and confirm the plan.','. Choisissez une parcelle et confirmez le projet.');
            cta=pick('Choose a building','Choisir le bâtiment');
            missionAction=()=>{if(type)chosenType=type.id;if(!chosenSite&&catalogue.sites.length===1){const site=catalogue.sites[0];chosenSite=site.territory_id+'|'+site.entity_id;}renderWorkspace();focusNode(workshop.root);};
        }else if(!detail||detail.next&&!detail.pending){
            step='start';title=detail?.level>0?pick('Upgrade your building','Améliorez votre bâtiment'):pick('Start the construction','Lancez le chantier');
            copy=pick('Your plot is ready. Select the building and start the work to make it productive.','Votre parcelle est prête. Sélectionnez le bâtiment puis lancez les travaux pour le rendre productif.');
            cta=pick('Inspect the building','Voir le chantier');
            missionAction=()=>{districtId=building.territory;showDistrict(true);select('building',building.id);};
        }else if(detail.pending){
            step='pending';title=pick('Construction in progress','Travaux en cours');
            copy=pick('The worker completes construction on the server. Refresh to see the latest confirmed progress.','Les travaux se terminent sur le serveur. Actualisez pour consulter leur avancement réel.');
            cta=pick('Check construction','Vérifier les travaux');missionAction=()=>run(async()=>{await readStages();await readInventory();});
        }else{
            step='next';title=pick('Your first building is ready','Votre premier bâtiment est prêt');
            const next={medieval:pick('Build a farm and grow your village.','Construisez une ferme et développez votre village.'),industrial:pick('Expand your power and production network.','Développez votre réseau énergétique et productif.'),guild:pick('Visit the market and publish your first exchange.','Visitez le marché et publiez votre premier échange.')}[theme];
            copy=next;cta=theme==='guild'?pick('Open the market','Ouvrir le marché'):pick('View campaign','Voir la campagne');
            missionAction=()=>{if(theme==='guild'){focusNode(tradeBlock.root);document.getElementById('market-load').click();}else focusNode(objectiveBlock.root);};
        }
        mission.dataset.missionStep=step;missionTitle.textContent=title;missionCopy.textContent=copy;missionGo.textContent=cta;
        missionGo.disabled=busy||(!building&&!type);
    }
    const ui=window.AeternumGameUI.create({host,profile:window.AeternumUIProfiles[theme],locale:lang(),
        slots:{introduction:[mission,tutorial],resources:[hud],context:[contextControls,soundPanel],stage:[districtBar,svg,atlasPanel,districtSvg,districtCaption,ambiencePanel,districtCommand,architecturalInspector,livingScene,hint,toolbar,playActions],side:[panel,nav],lower:[holdingTitle,holdingList,workspace]},
        actions:{domain:()=>showDistrict(true),atlas:()=>showDistrict(false),holdings:()=>focusNode(holdingList),construction:()=>focusNode(workshop.root),reserves:()=>focusNode(inventoryBlock.root),objectives:()=>{focusNode(objectiveBlock.root);if(theme==='guild'&&!busy&&!document.getElementById('progression-panel').hidden)document.getElementById('progression-load').click();},market:()=>{focusNode(tradeBlock.root);if(!busy)document.getElementById('market-load').click();},activity:()=>focusNode(receiptBlock.root),relations:()=>{renderRelations();focusNode(relationsBlock.root);}}
    });
    header.hidden=true;
    if(!classic)revealPanel('domain');
    const legacyLink=html('button',ui.root,'scene-button game-ui-diagnostic',lang()==='fr'?'Commandes détaillées':'Detailed controls');legacyLink.type='button';
    legacyLink.addEventListener('click',()=>{legacy.open=!legacy.open;if(legacy.open)legacy.scrollIntoView({block:'start'});});
    const retryNotice=html('div',ui.root,'game-ui-retry');retryNotice.hidden=true;
    retryNotice.setAttribute('role','status');
    const retryText=html('p',retryNotice,'');
    const retryAction=html('button',retryNotice,'scene-button');retryAction.type='button';retryAction.dataset.gameRetry='true';
    retryAction.addEventListener('click',()=>{if(!busy)document.getElementById('retry-command').click();});
    let retryPending=false;
    function renderRetry(){
        retryNotice.hidden=classic||!retryPending||!locationFields().world;
        retryText.textContent=lang()==='fr'?'Action sans confirmation. Réessayez avec le même identifiant ; ne créez pas une nouvelle action.':'Action not confirmed. Retry with the same identifier; do not create a new action.';
        retryAction.textContent=lang()==='fr'?'Réessayer l’action enregistrée':'Retry saved action';
        retryAction.disabled=busy;
    }
    on('retry',d=>{retryPending=d.pending===true;renderRetry();});
    on('activity',renderRetry);
    on('reset',()=>{retryPending=false;renderRetry();});
    const exit=html('button',contextControls,'scene-button',lang()==='fr'?'Déconnexion':'Sign out');exit.type='button';exit.dataset.gameExit='true';exit.addEventListener('click',()=>document.getElementById('logout').click());
    // The original sign-out label is retained in classic mode for existing accessible controls.
    exit.hidden=classic;
    function refreshShell(){
        ui.update(window.AeternumUIProfiles[theme],lang());syncMirrors();renderRetry();
        for(const button of host.querySelectorAll('[data-game-nav]'))button.setAttribute('aria-pressed',String(button.dataset.gameNav===host.dataset.workspace));
        const detailLabel=lang()==='fr'?'Commandes détaillées':'Detailed controls';
        legacySummary.textContent=detailLabel;legacyLink.textContent=detailLabel;
        exit.textContent=lang()==='fr'?'Changer de jeu':'Change game';
    }
    // A failed initial read must not leave a permanent, empty domain after
    // reconnect. Explicit read-only recovery, preserving server quotas.
    let sceneReadFailed=false;
    const sceneReload=html('button',contextControls,'scene-button');sceneReload.type='button';sceneReload.dataset.sceneReload='true';sceneReload.hidden=true;
    function renderSceneReload(){
        sceneReload.hidden=classic||!sceneReadFailed||!locationFields().world;
        sceneReload.disabled=busy;
        sceneReload.textContent=lang()==='fr'?'Recharger le monde':'Reload world';
    }
    sceneReload.addEventListener('click',()=>{if(!busy)run(loadInitialScene);});
    async function loadInitialScene(){
        try{
            await loadMap();await readStages();districtMode=true;render();await readInventory();
            if(theme==='medieval')await reviewMedieval();else if(theme==='industrial')await reviewIndustrial();
            await loadCampaignModule();await readBotRoster();sceneReadFailed=false;
        }catch(e){sceneReadFailed=true;throw e;}
        finally{renderSceneReload();}
    }
    let initialLoad=false;
    on('context',()=>{sceneReadFailed=false;renderSceneReload();refreshShell();if(!classic)revealPanel('domain');initialLoad=!classic&&Boolean(locationFields().world);});
    on('activity',d=>{
        ui.setBusy(d.busy);syncMirrors();renderSceneReload();
        if(d.busy){ui.setStatus(lang()==='fr'?'Synchronisation…':'Synchronizing…');return;}
        const ok=document.getElementById('status').textContent==='Success';
        const message=document.getElementById('status').textContent;
        const friendly=window.AeternumGameUI.feedback(lang(),message);
        ui.setStatus(ok?'':friendly);
        if(initialLoad){initialLoad=false;queueMicrotask(()=>{if(!busy&&!rootGame.hidden)run(loadInitialScene);});}
    });
    on('reset',()=>{resetTutorial();audio.reset();soundSeen.clear();soundVictory=false;initialLoad=false;sceneReadFailed=false;renderSceneReload();lastEconomicEvent=null;economicJournal.reset();selectedOperationId=renderedOperationId=null;operationView.replaceChildren();operationHistory.replaceChildren();operationPanel.hidden=true;livingScene.hidden=true;ui.setStatus('');});
    document.getElementById('locale-select').addEventListener('change',()=>{refreshShell();renderSceneReload();});
    for(const key of Object.keys(mirrors))balance.elements[key].addEventListener('change',syncMirrors);
    // Existing Vue navigation opens the relevant advanced controls when requested.
    document.addEventListener('click',e=>{const link=e.target.closest?.('.player-nav a');if(link&&link.hash){const target=document.getElementById(link.hash.slice(1));if(!classic&&['social-panel','social-mail-panel'].includes(target?.id)){e.preventDefault();relationsTab=target.id==='social-panel'?'alliances':'messages';renderRelations();focusNode(relationsBlock.root);}else if(legacy.contains(target))legacy.open=true;}});
    // Refresh only while a visible, authenticated player has a confirmed pending
    // construction. No local completion, no mutation, and no background requests.
    let constructionPoll=null;
    function scheduleConstructionPoll(){
        if(constructionPoll!==null){clearTimeout(constructionPoll);constructionPoll=null;}
        if(classic||!selectedGame||rootGame.hidden||document.hidden||![...details.values()].some(d=>d.pending!=null))return;
        constructionPoll=setTimeout(async()=>{
            constructionPoll=null;
            if(rootGame.hidden||document.hidden)return;
            try{
                if(!busy)await run(async()=>{
                    await readStages();
                    // Avoid repeatedly fetching every reserve and campaign
                    // while nothing changed. Refresh them once completion is
                    // observed from the authoritative progression endpoint.
                    if(![...details.values()].some(d=>d.pending!=null)){
                        await readInventory();
                        if(theme==='medieval')await reviewMedieval();
                        else if(theme==='industrial')await reviewIndustrial();
                    }
                });
            }finally{scheduleConstructionPoll();}
        },5000);
    }
    on('building',scheduleConstructionPoll);on('reset',scheduleConstructionPoll);
    on('context',scheduleConstructionPoll);
    on('activity',d=>{if(!d.busy)scheduleConstructionPoll();});
    document.addEventListener('visibilitychange',scheduleConstructionPoll);
    refreshShell();render();
})();
