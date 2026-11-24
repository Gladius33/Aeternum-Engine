/* Aeternum G13 — original SVG economy scenery, covered by the repository license.
 * No external assets. Illustrations are not simulated entities, shipments or economic state.
 */
'use strict';
(()=>{
    const NS='http://www.w3.org/2000/svg';
    const node=(g,tag,attrs)=>{const n=document.createElementNS(NS,tag);for(const [k,v] of Object.entries(attrs))n.setAttribute(k,String(v));g.append(n);return n;};
    const group=(g,x=0,y=0,s=1,attrs={})=>node(g,'g',{transform:'translate('+x+' '+y+') scale('+s+')',...attrs});
    const p=(g,d,fill,stroke='none',width=.8)=>node(g,'path',{d,fill,stroke,'stroke-width':width,'stroke-linecap':'round','stroke-linejoin':'round'});
    const r=(g,x,y,w,h,fill,stroke='none',rx=0)=>node(g,'rect',{x,y,width:w,height:h,fill,stroke,'stroke-width':.7,rx});
    const e=(g,x,y,rx,ry,fill,attrs={})=>node(g,'ellipse',{cx:x,cy:y,rx,ry,fill,...attrs});
    const l=(g,x1,y1,x2,y2,stroke,width=1)=>node(g,'line',{x1,y1,x2,y2,stroke,'stroke-width':width,'stroke-linecap':'round'});
    const shadow=(g,x,y,a,b)=>e(g,x,y,a,b,'#15232166');
    function crate(g,x,y,c='#b99060'){
        const a=group(g,x,y);p(a,'M0 0L10 -5 21 0 11 6Z',c,'#604b37');p(a,'M0 0L11 6V17L0 11Z','#8e6945','#604b37');p(a,'M11 6L21 0V11L11 17Z','#6b523c','#483e30');l(a,2,5,9,9,'#d5ba86');l(a,2,8,9,12,'#c4a374');l(a,12,9,19,5,'#ac8a60');
    }
    function log(g,x,y){
        const a=group(g,x,y);p(a,'M0 0L20 -9 24 -3 4 7Z','#8f613d','#4d3929');e(a,2,3,4,4,'#d5aa70');e(a,2,3,2.4,2.4,'none',{stroke:'#947149','stroke-width':.6});l(a,6,1,21,-6,'#bf8b53',.9);
    }
    function person(g,x,y,c,gesture='idle'){
        const a=group(g,x,y);shadow(a,0,0,7,2.6);l(a,-2,-7,-3,0,'#353c38',2.6);l(a,2,-7,4,0,'#353c38',2.6);
        p(a,'M-4 -20Q0 -23 4 -20L6 -9 0 -6 -6 -9Z',c,'#4b4035');l(a,-4,-18,-8,-10,'#b78b63',2.6);
        l(a,4,-18,gesture==='lift'?10:7,gesture==='lift'?-24:-10,'#c6a17a',2.6);
        e(a,0,-25,4,4,'#d5ac80');p(a,'M-5 -25Q-5 -31 1 -30 5 -29 5 -25Z','#62523d');l(a,-4,-25,5,-25,'#3e352c');
        l(a,-4,-10,4,-10,'#544638',1.5);
    }
    function crop(g,x,y){
        const a=group(g,x,y);p(a,'M-20 3L0 -8 24 3 4 15Z','#806641','#5b573a');
        for(let j=0;j<4;j++){
            const bx=-16+j*8,by=4+j*2;
            l(a,bx,by,bx+17,by-9,'#ba9352',1.2);
            for(let i=0;i<5;i++){const xx=bx+i*3.5,yy=by-i*1.8;l(a,xx,yy,xx,yy-5,'#dcc07a',.9);p(a,'M'+xx+' '+(yy-3)+'q-4 -4 -3 -6q3 0 3 4q2 -4 3 -4q1 4 -3 6Z','#e2be66');}
        }
    }
    function transformer(g,x,y){
        const a=group(g,x,y);shadow(a,10,8,18,5);p(a,'M0 0L14 -7 28 0 14 8Z','#9ca8a5','#46565b');p(a,'M0 0L14 8V15L0 8Z','#586d74');p(a,'M14 8L28 0V7L14 15Z','#3f5059');
        for(let i=0;i<3;i++){const xx=5+i*8;l(a,xx,1,xx,-10,'#273e48',2);for(let j=0;j<3;j++)e(a,xx,-4-j*2.6,3,.9,'#cfb88a');}
        p(a,'M3 -11Q12 -18 20 -11T34 -12','none','#364953',1.3);l(a,34,-12,34,8,'#46535b',2);
    }
    function wagon(g,x,y){
        const a=group(g,x,y);shadow(a,0,6,24,6);
        for(const xx of [-12,12]){e(a,xx,4,7,7,'#47382b',{stroke:'#c3a272','stroke-width':1});e(a,xx,4,4.8,4.8,'#b49461');for(let j=0;j<4;j++){const z=j*Math.PI/2;l(a,xx,4,xx+Math.cos(z)*5,4+Math.sin(z)*5,'#604631',.9);}e(a,xx,4,1.7,1.7,'#433d35');}
        p(a,'M-22 -10L14 -10 18 -3 -19 0Z','#8b6240','#514031');p(a,'M-19 -10Q-18 -28 -3 -29 15 -28 15 -10Z','#d8b987','#765c43');p(a,'M-12 -10Q-13 -26 -3 -29Q5 -25 4 -10Z','#ecd5a6');l(a,-1,-27,-1,-10,'#a68155');l(a,18,-3,30,-7,'#74583c',2);
    }
    function stall(g,x,y){
        const a=group(g,x,y);shadow(a,0,4,24,6);for(const xx of [-17,17])l(a,xx,3,xx,-22,'#654b33',2);
        p(a,'M-22 -21L-13 -31 19 -31 25 -21Z','#edd3a2','#79603f');
        for(let i=0;i<4;i++)p(a,'M'+(-21+i*12)+' -21l9 -10h6l-7 10Z','#b35f48');
        r(a,-19,-10,38,5,'#9c784a','#62482e');for(let i=0;i<3;i++){e(a,-12+i*11,-12,4,3,['#c58f54','#819456','#b96243'][i]);}
        r(a,-17,-5,34,8,'#a57a49','#61472f');l(a,-14,-1,14,-1,'#d0aa72');
    }
    const siteKinds=Object.freeze({
        lumberyard:'timber',farm:'crops',granary:'storage',keep:'storage',
        power_plant:'transformer',steelworks:'transformer',warehouse:'freight',freight_terminal:'freight',
        market_hall:'stall',caravan_hub:'wagon'
    });
    function site(parent,type,level){
        if(!Number.isInteger(level)||level<1||!Object.hasOwn(siteKinds,type))return false;
        const kind=siteKinds[type],g=group(parent,0,0,1,{'data-living-site':type,'aria-hidden':'true','pointer-events':'none'});
        if(kind==='timber'){const a=group(g,-32,10,.62);for(let i=0;i<3;i++)log(a,0,i*5);const b=group(g,23,13,.65);l(b,-7,-2,-12,9,'#765338',2.4);l(b,7,-2,12,9,'#765338',2.4);log(b,-10,-5);}
        else if(kind==='crops'){crop(group(g,-28,10,.65),0,0);crop(group(g,28,11,.65),0,0);}
        else if(kind==='transformer'){transformer(group(g,26,7,.63),0,0);const a=group(g,-35,9,.6);for(let i=0;i<3;i++){r(a,i*9,-4,7,10,'#71848a','#435660');l(a,i*9+1,-1,i*9+6,-1,'#d2ad73');}}
        else if(kind==='freight'||kind==='storage'){crate(group(g,-36,8,.58),0,0);crate(group(g,24,10,.63),0,0);}
        else if(kind==='stall'){stall(group(g,30,10,.54),0,0);crate(group(g,-35,7,.55),0,0);}
        else if(kind==='wagon'){wagon(group(g,28,12,.62),0,0);crate(group(g,-35,8,.55),0,0);}
        return true;
    }
    const eventTypes=Object.freeze({
        'settlement.work':{theme:'medieval',building:'lumberyard',kind:'timber'},
        'settlement.feed':{theme:'medieval',building:'farm',kind:'resident'},
        'industry.generate':{theme:'industrial',building:'power_plant',kind:'energy'},
        'industry.refine':{theme:'industrial',building:'steelworks',kind:'manufacture'},
        'industry.ship':{theme:'industrial',building:'freight_terminal',kind:'freight'},
        'market.create':{theme:'guild',building:'market_hall',kind:'offer'},
        'market.accept':{theme:'guild',building:'market_hall',kind:'exchange'},
        'market.cancel':{theme:'guild',building:'market_hall',kind:'withdraw'}
    });
    function event(parent,type){
        if(!Object.hasOwn(eventTypes,type))return false;
        const kind=eventTypes[type].kind;
        const g=group(parent,0,0,1,{'data-living-event':type,'aria-hidden':'true','pointer-events':'none'});
        shadow(g,0,16,49,13);
        if(kind==='timber'){
            for(let i=0;i<3;i++)log(g,-32,i*6);
            person(g,13,13,'#6e8754','lift');person(g,32,10,'#a27a53');
            const a=group(g,23,-12,1,{class:'living-art-motion living-art-swing'});l(a,0,0,8,-13,'#9d7950',2);p(a,'M5 -14L14 -19 16 -11 10 -9Z','#aab7b2','#52636a');
        }else if(kind==='resident'){
            crop(g,-25,5);person(g,8,13,'#a36b50');person(g,26,11,'#6f8758','lift');
            p(g,'M35 -18V-31M29 -25H41','none','#bce0a8',2.2);
        }else if(kind==='energy'||kind==='manufacture'||kind==='freight'){
            if(kind==='energy'){transformer(g,-22,1);p(g,'M-46 -3L-26 -18 8 -18 29 -4','none','#7d918e',3);
                p(g,'M-46 -3L-26 -18 8 -18 29 -4','none','#ffe09a',2);const a=group(g,23,0,1,{class:'living-art-motion living-art-pulse'});p(a,'M3 -33L-8 -16H0L-5 -1 13 -21H5Z','#ffda79','#a67834',.8);
            }else{crate(g,-30,-4,'#b7b9af');crate(g,-7,0,'#c0a57a');crate(g,15,-7,'#aab7b6');const a=group(g,32,-3,1,{class:'living-art-motion living-art-pulse'});p(a,'M-6 -12H5L11 -6 5 0H-6L0 -6Z','#e3bc71');}
        }else{
            stall(g,-23,6);crate(g,18,0);person(g,7,14,'#b77353');const a=group(g,29,-20,1,{class:'living-art-motion living-art-pulse'});
            r(a,-11,-12,22,16,'#eed4a3','#78583d',1.4);
            if(kind==='withdraw'){l(a,-5,-7,5,0,'#ad644c',2);l(a,5,-7,-5,0,'#ad644c',2);}
            else if(kind==='exchange'){p(a,'M-7 -8H7L3 -12M7 -1H-7L-3 3','none','#5f8260',1.6);}
            else {l(a,-6,-8,6,-8,'#98683e');l(a,-6,-4,6,-4,'#98683e');l(a,-6,0,2,0,'#98683e');}
        }
        return true;
    }
    // G14: continuous illustrative terrain. No paths here represent registered
    // links, and no decorative object represents a server-owned entity.
    const atlasPalettes={
        medieval:{base:'#526b46',land:'#80925c',light:'#b5b77a',dark:'#344e39',water:'#658e91',edge:'#b4c7af',ink:'#244332'},
        industrial:{base:'#546b70',land:'#82918b',light:'#b7b8a0',dark:'#3c5059',water:'#526e7a',edge:'#a3b0ac',ink:'#3b5052'},
        guild:{base:'#b28a56',land:'#d0a56c',light:'#ebcb90',dark:'#926742',water:'#638984',edge:'#c6d1a3',ink:'#75583b'}
    };
    function atlasTerrain(parent,theme,w,h){
        const c=atlasPalettes[theme];if(!c)return false;
        const g=group(parent,0,0,1,{'data-atlas-landscape':theme,'aria-hidden':'true','pointer-events':'none'});
        const seed=i=>{const n=Math.sin(i*127.1+311.7)*43758.5453;return n-Math.floor(n);};
        r(g,0,0,w,h,c.base);
        for(let i=0;i<8;i++){
            const y=h*i/8,dy=h*.18;
            p(g,'M0 '+y+' Q'+w*.26+' '+(y-dy)+' '+w*.5+' '+(y+dy*.2)+' T'+w+' '+(y-dy*.2)+' V'+(y+dy)+' Q'+w*.65+' '+(y+dy*.4)+' '+w*.45+' '+(y+dy)+' T0 '+(y+dy*.8)+'Z',i%3===0?c.dark:i%3===1?c.land:c.base);
        }
        // Irregular shaded hill contours, with bounded element count.
        for(let i=0;i<18;i++){
            const x=seed(i+40)*w,y=seed(i+80)*h,rx=25+seed(i+150)*Math.min(w*.12,80),ry=rx*.39;
            for(let j=0;j<3;j++){
                const a=rx*(1-j*.24),b=ry*(1-j*.24);
                p(g,'M'+(x-a)+' '+y+' Q'+(x-a*.65)+' '+(y-b*1.6)+' '+x+' '+(y-b)+' T'+(x+a)+' '+y+' Q'+(x+a*.4)+' '+(y+b*1.4)+' '+x+' '+(y+b)+' T'+(x-a)+' '+y+'Z',j===0?c.dark:j===1?c.base:c.land,c.ink,.45);
            }
        }
        if(theme!=='industrial'){
            const d='M'+w*.16+' 0 C'+w*.09+' '+h*.23+' '+w*.42+' '+h*.34+' '+w*.31+' '+h*.57+' S'+w*.12+' '+h*.86+' '+w*.42+' '+h;
            p(g,d,'none',c.edge,theme==='guild'?16:27);p(g,d,'none',c.water,theme==='guild'?10:19);p(g,d,'none','#b6d0c275',1.1);
        }else{
            const d='M0 '+h*.18+' C'+w*.22+' '+h*.34+' '+w*.5+' '+h*.05+' '+w+' '+h*.24;
            p(g,d,'none',c.edge,29);p(g,d,'none',c.water,22);p(g,d,'none','#bbcaca88',1.4);
        }
        const texture=group(g,0,0,1,{opacity:.75});
        for(let i=0;i<140;i++){
            const x=seed(i+300)*w,y=seed(i+600)*h;
            if(theme==='medieval'){
                const a=group(texture,x,y,.65+seed(i+700)*.55);
                if(i%3===0){e(a,2,2,7,3,'#1b362a55');l(a,0,0,0,-8,'#796044',1.2);p(a,'M-7 -3L0 -17 7 -3 0 1Z','#34543b');p(a,'M0 -17L-7 -3 0 -5Z','#78915b');}
                else {l(a,-3,1,0,-2,'#a9b67a',.8);l(a,2,2,5,-1,'#405a40',.8);}
            }else if(theme==='industrial'){
                p(texture,'M'+x+' '+y+'l6 -3 5 1 -4 4Z',i%2?c.dark:c.light);if(i%4===0)l(texture,x,y+4,x+13,y+1,'#9da7a0',.7);
            }else{
                e(texture,x,y,2+seed(i+700)*5,1.3,c.dark);
                if(i%7===0){p(texture,'M'+(x-8)+' '+y+'q8 -4 16 0','none',c.light,1);}
            }
        }
        return true;
    }
    function atlasRegion(parent,theme,control){
        const c=atlasPalettes[theme];if(!c)return false;
        const g=group(parent,0,0,1,{'data-atlas-region':control,'aria-hidden':'true','pointer-events':'none'});
        const d='M-110 -9L-86 -31 -51 -41 -11 -48 26 -39 62 -38 104 -16 109 6 80 29 37 39 -3 47 -45 34 -84 29 -113 11Z';
        p(g,d,c.dark);p(g,'M-110 -13L-86 -35 -51 -45 -11 -52 26 -43 62 -42 104 -20 109 2 80 25 37 35 -3 43 -45 30 -84 25 -113 7Z',c.land,control==='own'?'#e5cf8d':control==='occupied'?'#c4a1a0':'#b4c0a0',1.4);
        for(let i=0;i<7;i++){
            const x=-90+i*28,y=13+(i%3)*6;
            p(g,'M'+x+' '+y+'q13 -8 27 -5','none',c.light,.65);
        }
        if(theme==='medieval'){
            for(const x of [-81,77]){const a=group(g,x,-12,.8);e(a,0,4,13,5,'#243e3255');p(a,'M-12 0L0 -25 12 0Z','#32563c');p(a,'M0 -25L-12 0 0 -5Z','#789457');}
            for(let i=0;i<5;i++){const x=-90+i*9,y=16+i%2*3;l(g,x,y,x+3,y-5,c.light,.9);}
        }else if(theme==='industrial'){
            for(const x of [-77,67])for(let i=0;i<4;i++)p(g,'M'+(x+i*6)+' '+(8+i%2*4)+'l5 -3 6 2 -4 4Z',i%2?c.dark:c.light,c.ink,.4);
        }else{
            for(const x of [-79,79]){const a=group(g,x,9,.5);p(a,'M-8 0L0 -9 9 0 0 4Z','#c6a16b','#8d6b43');}
            p(g,'M-95 17Q-78 7 -61 15','none','#e9cb94',1.4);
        }
        return true;
    }
    function atlasEdge(parent,theme,a,b,{confirmed=false}={}){
        const c=atlasPalettes[theme];if(!c)return false;
        const dx=b.x-a.x,dy=b.y-a.y,len=Math.hypot(dx,dy);if(len<1)return false;
        const bend=Math.min(28,len*.12),cx=(a.x+b.x)/2-dy/len*bend,cy=(a.y+b.y)/2+dx/len*bend;
        const d='M'+a.x+' '+a.y+' Q'+cx+' '+cy+' '+b.x+' '+b.y;
        const g=group(parent,0,0,1,{'aria-hidden':'true','pointer-events':'none'});
        p(g,d,'none','#1f2d2a',confirmed?10:8);
        p(g,d,'none',confirmed?'#ffe09a':theme==='industrial'?'#c5c3af':'#d3b17e',confirmed?6:4);
        if(theme==='industrial'&&!confirmed){
            const rail=p(g,d,'none','#59656b',2);rail.setAttribute('stroke-dasharray','2 5');
        }else if(confirmed){
            const glow=p(g,d,'none','#fff5cf',2);glow.setAttribute('stroke-dasharray','5 10');glow.setAttribute('class','atlas-route-motion');
        }
        return true;
    }
    // G16: the lighting preset is a client-only presentation choice. It
    // never represents simulated time, weather, power usage or production.
    const atmospheres=Object.freeze({
        medieval:Object.freeze({
            day:{sky:['#648b9d','#b8d0c0','#e9dcb2'],earth:['#a4b17b','#738c54','#426349'],mist:'#d9e7c4',light:'#fff0b7',shadow:'#304f50'},
            dusk:{sky:['#4e647f','#bd8078','#f4c28a'],earth:['#b3a375','#887b4e','#4b5d45'],mist:'#f1cba1',light:'#ffd19b',shadow:'#423c57'},
            night:{sky:['#142c4a','#355875','#7b919d'],earth:['#688079','#435d56','#293f45'],mist:'#a3bdcc',light:'#e5eccc',shadow:'#102639'}
        }),
        industrial:Object.freeze({
            day:{sky:['#647e92','#b1c2cb','#d5d6c3'],earth:['#a8b4ad','#788b8d','#465962'],mist:'#ced9dc',light:'#ecf0d8',shadow:'#344b58'},
            dusk:{sky:['#4c627d','#a58283','#e8bb90'],earth:['#aaa493','#7d7f7f','#4d5c68'],mist:'#e1c5a5',light:'#ffd5a0',shadow:'#3b465d'},
            night:{sky:['#172b47','#36536b','#8495aa'],earth:['#687d8c','#465e6b','#2c3e50'],mist:'#a0bbcb',light:'#d8e4ff',shadow:'#142738'}
        }),
        guild:Object.freeze({
            day:{sky:['#7da4b2','#d9bf99','#f6dfa8'],earth:['#e7c58b','#c39a61','#926c48'],mist:'#ffe1a9',light:'#fff3c5',shadow:'#715a4b'},
            dusk:{sky:['#62678e','#c97e6d','#ffc481'],earth:['#d9ae76','#b18555','#805b4b'],mist:'#f6c08b',light:'#ffcf95',shadow:'#654854'},
            night:{sky:['#182e50','#405675','#8a92a4'],earth:['#ac987b','#786e62','#4a4b50'],mist:'#c3c3ca',light:'#e9e7cf',shadow:'#343b50'}
        })
    });
    const phases=Object.freeze(['day','dusk','night']);
    function atmosphereProfile(theme,phase){
        return atmospheres[theme]?.[phase]??null;
    }
    let atmosphereSerial=0;
    function atmosphereGradient(g,stops,radial=false){
        const id='aeternum-ambience-'+(++atmosphereSerial),defs=node(g,'defs',{});
        const grad=node(defs,radial?'radialGradient':'linearGradient',radial?{id,cx:'50%',cy:'50%',r:'50%'}:{id,x1:'0%',y1:'0%',x2:'0%',y2:'100%'});
        for(const [offset,color,opacity] of stops)node(grad,'stop',{offset:offset+'%','stop-color':color,'stop-opacity':opacity});
        return 'url(#'+id+')';
    }
    function atmosphere(parent,theme,phase,w,h,scene='district'){
        const c=atmosphereProfile(theme,phase);
        if(!c||!['district','atlas','inspection'].includes(scene)||!Number.isFinite(w)||!Number.isFinite(h)||w<=0||h<=0)return false;
        const g=group(parent,0,0,1,{'data-atmosphere-layer':scene,'data-atmosphere-theme':theme,'data-lighting':phase,'aria-hidden':'true','pointer-events':'none'});
        const seed=i=>{const n=Math.sin(i*91.71+17.39)*43758.5453;return n-Math.floor(n);};
        if(scene==='district'){
            const sky=atmosphereGradient(g,[[0,c.sky[0],1],[55,c.sky[1],1],[100,c.sky[2],1]]);
            r(g,0,0,w,h*.48,sky);
            const x=w*(phase==='dusk'?.22:.76),y=h*(phase==='dusk'?.22:.16),radius=Math.min(w,h)*.045;
            const glow=atmosphereGradient(g,[[0,c.light,.65],[35,c.light,.28],[100,c.light,0]],true);
            e(g,x,y,radius*3.1,radius*3.1,glow);e(g,x,y,radius,radius,c.light);
            if(phase==='night'){
                e(g,x+radius*.35,y-radius*.18,radius*.86,radius*.86,c.sky[0]);
                for(let i=0;i<25;i++){
                    const sx=seed(i+10)*w,sy=(.025+seed(i+80)*.17)*h;
                    e(g,sx,sy,.7+seed(i+140)*.7,.7+seed(i+140)*.7,'#e4e8dc',{opacity:.3+seed(i+200)*.5});
                }
            }
            const cloudLayer=group(g,0,0,1,{class:'ambience-cloud-motion ambience-motion'});
            for(let i=0;i<5;i++){
                const cx=w*(.1+i*.2),cy=h*(.09+seed(i+270)*.12),rx=w*(.055+seed(i+310)*.045);
                const cloud=atmosphereGradient(g,[[0,c.mist,phase==='night'?.11:.27],[100,c.mist,0]],true);
                e(cloudLayer,cx,cy,rx,h*.024,cloud);e(cloudLayer,cx+rx*.3,cy-h*.013,rx*.6,h*.024,cloud);
            }
        }else{
            const wash=atmosphereGradient(g,[[0,c.shadow,phase==='night'?.38:phase==='dusk'?.13:.04],[55,c.light,phase==='night'?.04:.07],[100,c.shadow,.12]]);
            r(g,0,0,w,h,wash);
        }
        return true;
    }
    // Foreground details sit below interactive holdings and labels.
    // No decorative actor, smoke plume or animated shipment is fabricated.
    function atmosphereDepth(parent,theme,phase,w,h,scene='district'){
        const c=atmosphereProfile(theme,phase);
        if(!c||!Number.isFinite(w)||!Number.isFinite(h)||w<=0||h<=0||!['district','atlas','inspection'].includes(scene))return false;
        const g=group(parent,0,0,1,{'data-atmosphere-depth':scene,'aria-hidden':'true','pointer-events':'none'});
        const seed=i=>{const n=Math.sin(i*123.7+71.9)*43758.5453;return n-Math.floor(n);};
        if(scene==='district'){
            const haze=atmosphereGradient(g,[[0,c.mist,0],[35,c.mist,.25],[70,c.mist,.09],[100,c.mist,0]]);
            r(g,0,h*.19,w,h*.27,haze);
            const glints=group(g,0,0,1,{class:'ambience-motion ambience-glint-motion','data-ambience-effect':theme==='medieval'?'water-light':theme==='industrial'?'haze-light':'sand-light'});
            for(let i=0;i<15;i++){
                const x=w*(.04+seed(i+80)*.92),y=h*(.40+seed(i+180)*.46),len=3+seed(i+320)*12;
                // Side accents only; the central action area is kept clear.
                if(x>w*.24&&x<w*.76)continue;
                if(theme==='medieval')p(glints,'M'+x+' '+y+'q'+len/2+' -1 '+len+' 0','none',c.light,.8);
                else if(theme==='industrial')l(glints,x,y,x+len,y-1,c.mist,.7);
                else p(glints,'M'+x+' '+y+'q'+len/2+' -3 '+len+' -1','none',c.light,.7);
            }
        }
        const vignette=atmosphereGradient(g,[[0,c.shadow,0],[62,c.shadow,.015],[100,c.shadow,phase==='night'?.26:.12]],true);
        r(g,0,0,w,h,vignette);
        return true;
    }
    // G17: bounded, context-scoped session receipts. It is not a durable
    // transaction ledger, and a replay cannot submit or settle a command.
    function economicJournal(limit=8){
        if(!Number.isSafeInteger(limit)||limit<1||limit>32)throw RangeError('Invalid journal capacity');
        let rows=[],seen=new Set();
        return Object.freeze({
            record(receipt,context){
                if(!receipt||typeof receipt.id!=='string'||!receipt.id||!context?.world||!context?.account||
                    receipt.world!==context.world||receipt.account!==context.account||
                    !Object.hasOwn(eventTypes,receipt.type)||eventTypes[receipt.type].theme!==context.theme||
                    seen.has(receipt.id))return false;
                // Memory is bounded; old receipts beyond the deduplication
                // window are not a substitute for server idempotency.
                if(seen.size>=256)seen.delete(seen.values().next().value);
                seen.add(receipt.id);
                rows=[Object.freeze({id:receipt.id,type:receipt.type}),...rows].slice(0,limit);
                return true;
            },
            entries:()=>rows.slice(),
            reset(){rows=[];seen.clear();}
        });
    }
    function operationScene(parent,type){
        if(!Object.hasOwn(eventTypes,type))return false;
        const {theme,kind}=eventTypes[type];
        const g=group(parent,0,0,1,{'data-operation-scene':type,'data-operation-theme':theme,'aria-hidden':'true','pointer-events':'none'});
        const palette=theme==='medieval'?['#526c55','#819669','#b7b27b','#435c43']:theme==='industrial'?['#405865','#748b91','#a4b0aa','#435860']:['#8c694e','#c39a67','#e3c38e','#96714f'];
        const defs=node(g,'defs',{}),id='aeternum-operation-'+(++atmosphereSerial);
        const grad=node(defs,'linearGradient',{id,x1:'0%',y1:'0%',x2:'0%',y2:'100%'});
        palette.slice(0,3).forEach((c,i)=>node(grad,'stop',{offset:i*50+'%','stop-color':c}));
        r(g,0,0,320,170,'url(#'+id+')');
        for(let i=0;i<3;i++)p(g,'M0 '+(63+i*12)+' Q50 '+(22+i*19)+' 114 '+(55+i*13)+' T230 '+(40+i*17)+' T320 '+(66+i*12)+' V170H0Z',palette[(i+1)%4]);
        p(g,'M0 111Q72 85 140 101T320 105V170H0Z',palette[2]);
        for(let i=0;i<24;i++){const x=(i*79+13)%320,y=111+(i*17)%54;l(g,x,y,x+5+i%4,y-2,palette[3],.65);}
        const platform=group(g,160,126);
        shadow(platform,0,12,120,23);p(platform,'M-119 0L-36 -39 120 -9 48 35Z',palette[1],palette[3],1.4);
        for(let i=0;i<8;i++)l(platform,-105+i*20,3+i*3,-31+i*20,-30+i*3,palette[2],.6);
        const actor=(parent,x,y,scale,role,draw,motion)=>{
            const position=group(parent,x,y,scale,{'data-operation-actor':role});
            const inner=group(position,0,0,1,motion?{class:'operation-motion operation-'+motion}:{});
            draw(inner);
            return inner;
        };
        function cart(a,color='#af8c5a'){
            shadow(a,0,8,27,6);
            p(a,'M-23 -12H19L25 -3 18 2H-21Z',color,'#574936');
            for(const x of [-15,15]){e(a,x,4,7,7,'#403b31',{stroke:'#cfb17b','stroke-width':1});l(a,x-5,4,x+5,4,'#ac9366',.8);l(a,x,-1,x,9,'#ac9366',.8);}
            l(a,25,-3,38,-6,'#6a5038',2);
        }
        function belt(a){
            p(a,'M-38 -8L37 -8 47 -1 -29 -1Z','#a6aeaa','#41545e');r(a,-29,-1,76,8,'#4f6168','#2e424b');
            for(let i=0;i<8;i++){e(a,-25+i*10,4,3,3,'#c6c7b5');l(a,-31+i*10,-8,-21+i*10,-1,'#455962',.9);}
            for(const x of [-20,32])l(a,x,8,x,18,'#46545b',3);
        }
        function rail(a){
            for(const y of [0,12])l(a,-91,y,91,y,'#4b4d4c',3);
            for(let i=0;i<19;i++)l(a,-86+i*10,-4,-86+i*10,16,'#7e6d55',2);
            for(const y of [0,12])l(a,-91,y,91,y,'#c1c4b7',.8);
        }
        function freightCar(a){
            p(a,'M-27 -18L18 -18 31 -25 -14 -25Z','#9daeb0','#495c65');r(a,-27,-18,45,20,'#738a91','#354c57');p(a,'M18 -18L31 -25V-5L18 2Z','#405c66','#354c57');
            for(let x=-23;x<18;x+=6)l(a,x,-17,x,1,'#3d5863',.8);
            for(const x of [-18,11])e(a,x,5,5,5,'#29383e',{stroke:'#a7b1ac','stroke-width':.8});
        }
        function scroll(a,mark){
            r(a,-14,-20,28,25,'#e7c993','#795e40',2);
            for(let i=0;i<3;i++)l(a,-9,-14+i*5,7,-14+i*5,'#9a7952',.8);
            if(mark==='withdraw'){l(a,-10,-17,10,1,'#a9574b',2);l(a,10,-17,-10,1,'#a9574b',2);}
            else if(mark==='exchange')p(a,'M-10 -9H10L5 -14M10 -3H-10L-5 2','none','#537553',2);
            else e(a,9,3,4,4,'#a95443');
        }
        if(kind==='timber'){
            for(let i=0;i<4;i++)log(group(g,67,112+i*5,1.1),0,0);
            actor(g,125,121,1.28,'woodworker',a=>{person(a,0,0,'#6e8754','lift');const tool=group(a,9,-23,1);l(tool,0,0,10,-13,'#9d7950',2.5);p(tool,'M6 -14L16 -20 19 -11 11 -8Z','#b6c3bb','#52636a');},'work');
            actor(g,188,127,1.15,'timber-cart',a=>{cart(a);for(let i=0;i<3;i++)log(a,-19,-15-i*5);},'carry');
            actor(g,246,111,1.05,'worker',a=>person(a,0,0,'#a27a53'),'step');
        }else if(kind==='resident'){
            crop(group(g,66,108,1.25),0,0);crop(group(g,103,125,1.2),0,0);
            actor(g,162,126,1.25,'resident',a=>person(a,0,0,'#a36b50'),'step');
            actor(g,211,119,1.15,'welcome',a=>person(a,0,0,'#6f8758','lift'),'work');
            actor(g,246,94,1,'welcome-sign',a=>p(a,'M0 -18V2M-10 -8H10','none','#d2efaa',3),'signal');
        }else if(kind==='energy'){
            transformer(group(g,70,108,1.9),0,0);
            transformer(group(g,184,112,1.55),0,0);
            const cable=group(g,0,0,1);p(cable,'M105 82Q158 39 202 82','none','#45565b',4);
            p(cable,'M105 82Q158 39 202 82','none','#efc775',2).setAttribute('class','operation-motion operation-current');
            actor(g,160,73,1.25,'energy-signal',a=>p(a,'M3 -27L-9 -9H-1L-6 8 15 -14H6Z','#ffe38c','#9a7136',1.1),'signal');
        }else if(kind==='manufacture'){
            belt(group(g,160,118,1.8));
            actor(g,105,105,1,'input',a=>crate(a,-8,-8,'#a5aeac'),'carry');
            actor(g,179,105,1,'output',a=>crate(a,-8,-8,'#c9ad75'),'carry');
            actor(g,239,126,1.08,'operator',a=>person(a,0,0,'#b49b68','lift'),'work');
            const spark=group(g,153,76,1,{class:'operation-motion operation-signal'});for(let i=0;i<7;i++)l(spark,0,0,Math.cos(i*.9)*11,Math.sin(i*.9)*11,'#ffda88',1.3);
        }else if(kind==='freight'){
            rail(group(g,160,126,1.3));
            actor(g,98,119,1.15,'freight-car',freightCar,'carry');
            actor(g,192,119,1.15,'freight-load',a=>{freightCar(a);crate(a,-16,-40,'#bc9a68');},'carry');
            actor(g,251,113,1,'dispatcher',a=>person(a,0,0,'#c1a66c','lift'),'work');
        }else{
            stall(group(g,75,111,1.45),0,0);
            actor(g,130,128,1.12,'merchant',a=>person(a,0,0,'#b77353','lift'),'work');
            actor(g,188,127,1.1,'trader',a=>person(a,0,0,'#66847b'),kind==='exchange'?'step':null);
            if(kind==='exchange')actor(g,238,116,1.05,'trade-crates',a=>{crate(a,-12,-9);crate(a,4,-1,'#c9ad75');},'carry');
            else crate(group(g,240,119,1.05),-8,-8);
            actor(g,164,77,1.1,'document',a=>scroll(a,kind),'signal');
        }
        return true;
    }
    window.AeternumLivingArt=Object.freeze({site,event,eventTypes,atlasTerrain,atlasRegion,atlasEdge,atmosphereProfile,atmosphere,atmosphereDepth,phases,economicJournal,operationScene,version:4});
})();
