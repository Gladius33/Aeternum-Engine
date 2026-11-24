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
    const toolbar=html('div',host,'scene-toolbar');
    const button=(parent,key,fn)=>{const b=html('button',parent,'scene-button',t(key));b.type='button';b.dataset.label=key;b.addEventListener('click',fn);return b;};
    button(toolbar,'sync',()=>run(async()=>{await loadMap();await loadConstructionChoices();}));
    button(toolbar,'zoomIn',()=>zoom(1.25));button(toolbar,'zoomOut',()=>zoom(.8));
    button(toolbar,'reset',()=>{camera={x:0,y:0,z:1};view();});
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
    const nav=html('div',host,'scene-toolbar');
    const jump=id=>{const node=document.getElementById(id);if(node&&!node.hidden){node.scrollIntoView({block:'start'});const focus=node.querySelector('button:not([hidden]),input,select');focus?.focus({preventScroll:true});}};
    button(nav,'build',()=>jump('foundation-panel'));
    button(nav,'production',()=>jump('action'));
    button(nav,'trade',()=>jump('market-panel'));
    const goals=button(nav,'goals',()=>jump(theme==='medieval'?'medieval-panel':theme==='industrial'?'industrial-panel':'progression-panel'));
    const holdingTitle=html('h4',host,'scene-holdings-title'),holdingList=html('div',host,'scene-holdings');
    function setLocation(id){if(selected?.type==='territory'){document.getElementById(id).value=territories.get(selected.id).location;updateSelection();}}
    function inspectBuilding(id){if(!buildings.has(id))return;document.getElementById('building-id').value=id;document.getElementById('building-load').click();jump('building-details');}
    function updateSelection(){
        const territory=selected?.type==='territory'?territories.get(selected.id):null;
        const building=selected?.type==='building'?buildings.get(selected.id):null;
        const d=building?details.get(building.id):null;
        selection.textContent=territory?territory.location:building?building.id:t('choose');
        info.textContent=territory?[territory.id,territory.kind,territory.control].join(' · '):building?building.type+' · '+(d?t('level')+' '+d.level+(d.pending?' · '+t('pending'):''):t('unknown')):'';
        origin.hidden=destination.hidden=connection.hidden=!territory;inspect.hidden=!building;
        trace.hidden=!document.getElementById('map-location').value||!document.getElementById('map-destination').value;
        goals.hidden=theme==='neutral'||(theme==='guild'&&document.getElementById('progression-panel').hidden);
        svg.querySelectorAll('[data-territory]').forEach(n=>n.setAttribute('aria-pressed',String(territory?.id===n.dataset.territory)));
        holdingList.querySelectorAll('[data-building]').forEach(n=>n.setAttribute('aria-pressed',String(building?.id===n.dataset.building)));
    }
    function select(type,id){selected={type,id};updateSelection();}
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
    function tile(g,x,y,territory,index){
        const p=palettes[theme],a=el('g',{transform:'translate('+x+' '+y+')',tabindex:0,role:'button','aria-label':territory.location+' · '+territory.kind,'data-territory':territory.id,'aria-pressed':'false',class:'scene-territory'},g);
        el('title',{},a,territory.id+' · '+territory.location+' · '+territory.kind+' · '+territory.control);
        poly(a,'-115,0 0,49 115,0 115,14 0,63 -115,14',p[0],'#111e22');
        poly(a,'-115,0 0,-49 115,0 0,49',p[1],'#233b39');
        for(let i=0;i<5;i++)line(a,-78+i*9,0+i*3,-16+i*9,-27+i*3,p[2],2);
        if(theme==='medieval'){tree(a,-68,-6);tree(a,-47,9);house(a,15,-5,p[4],p[3]);house(a,52,12,p[4],p[3],23,17);}
        else if(theme==='industrial'){factory(a,15,5);line(a,-85,10,-20,35,'#c3a477',4);line(a,-87,16,-22,41,'#25323b',2);}
        else if(theme==='guild'){tent(a,-32,6);tent(a,25,-7);for(let i=0;i<3;i++)rect(a,40+i*10,13+i*3,8,8,p[3],{stroke:p[4]});}
        else {poly(a,'-30,5 0,-27 29,5',p[2]);ellipse(a,34,13,16,5,p[5]);}
        rect(a,-100,51,200,35,'#15232bea',{rx:7,stroke:'#ffffff35'});
        el('text',{x:0,y:66,'text-anchor':'middle',class:'scene-node-label'},a,territory.location.length>26?territory.location.slice(0,24)+'…':territory.location);
        el('text',{x:0,y:79,'text-anchor':'middle',class:'scene-node-meta'},a,territory.kind+' · '+territory.control);
        a.addEventListener('click',()=>select('territory',territory.id));
        a.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();select('territory',territory.id);}});
    }
    function render(){
        host.dataset.theme=theme; title.textContent=t(theme);
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
        for(let i=1;i<route.length;i++){const a=positions.get(route[i-1]),b=positions.get(route[i]);if(a&&b)line(svg,a.x,a.y,b.x,b.y,'#ffe095',8);}
        nodes.forEach((n,i)=>{const q=positions.get(n.location);tile(svg,q.x,q.y,n,i);});
        if(!nodes.length)el('text',{x:450,y:220,'text-anchor':'middle',class:'scene-empty'},svg,t('empty'));
        if(!buildings.size)html('p',holdingList,'scene-caption',t('none'));
        for(const b of buildings.values()){
            const card=html('button',holdingList,'scene-holding');card.type='button';card.dataset.building=b.id;
            const art=el('svg',{viewBox:'0 0 130 86','aria-hidden':'true'},card);
            const layer=el('g',{transform:'translate(58 68)'},art);
            if(theme==='industrial')factory(layer,0,0);else if(theme==='guild')tent(layer,0,0);else if(theme==='medieval')house(layer,0,0,p[4],p[3],42,30);else poly(layer,'-20,0 0,-42 20,0',p[2]);
            const d=details.get(b.id);
            html('strong',card,'',b.type);html('span',card,'',b.id);
            html('small',card,'',d?t('level')+' '+d.level+(d.pending?' · '+t('pending'):''):t('unknown'));
            card.addEventListener('click',()=>select('building',b.id));
        }
        view();updateSelection();
    }
    const on=(name,fn)=>window.addEventListener('aeternum:'+name,e=>fn(e.detail));
    on('context',d=>{theme=d.theme??(d.world?theme:'neutral');territories.clear();buildings.clear();details.clear();links.clear();route=[];selected=null;camera={x:0,y:0,z:1};render();});
    on('territories',d=>{if(!d.append){territories.clear();links.clear();route=[];selected=null;}for(const n of d.territories)territories.set(n.id,n);render();});
    on('links',d=>{links.set(d.location,d.neighbors);render();});
    on('route',d=>{route=d?.locations??[];render();});
    on('buildings',d=>{buildings=new Map(d.buildings.map(b=>[b.id,b]));for(const id of details.keys())if(!buildings.has(id))details.delete(id);render();});
    on('building',d=>{buildings.set(d.id,d);details.set(d.id,d);render();});
    document.getElementById('locale-select').addEventListener('change',render);
    document.getElementById('map-location').addEventListener('input',updateSelection);
    document.getElementById('map-destination').addEventListener('input',updateSelection);
    render();
})();
