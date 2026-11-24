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
    window.AeternumLivingArt=Object.freeze({site,event,eventTypes,version:1});
})();
