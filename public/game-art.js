/* Aeternum original SVG graphics kit. Presentation only. */
'use strict';
(()=>{
    let instance=0;
    function create({skin,palette}){
        if(typeof skin!=='function'||typeof palette!=='function')throw TypeError('AeternumArt requires skin and palette providers');
        const prefix='aeternum-art-'+(++instance)+'-';
        const ns='http://www.w3.org/2000/svg';
        const el=(tag,attrs={},parent,text)=>{
            const n=document.createElementNS(ns,tag);
            for(const [k,v] of Object.entries(attrs))n.setAttribute(k,String(v));
            if(text!==undefined)n.textContent=text;
            if(parent)parent.append(n);return n;
        };
    const poly=(g,points,fill,stroke='none')=>el('polygon',{points,fill,stroke,'stroke-width':1.1},g);
    const rect=(g,x,y,w,h,fill,extra={})=>el('rect',{x,y,width:w,height:h,fill,...extra},g);
    const line=(g,x1,y1,x2,y2,stroke,width=2)=>el('line',{x1,y1,x2,y2,stroke,'stroke-width':width,'stroke-linecap':'round'},g);
    const ellipse=(g,cx,cy,rx,ry,fill,extra={})=>el('ellipse',{cx,cy,rx,ry,fill,...extra},g);
    const path=(g,d,fill,extra={})=>el('path',{d,fill,...extra},g);
    // Original vector materials. Small details are bounded by the sprite dimensions.
    let materialId=0;
    function shade(g,colors,vertical=true){
        const id=prefix+(++materialId),defs=el('defs',{},g);
        const grad=el('linearGradient',{id,x1:'0%',y1:'0%',x2:vertical?'0%':'100%',y2:vertical?'100%':'0%'},defs);
        colors.forEach((color,i)=>el('stop',{offset:(i/(colors.length-1)*100)+'%','stop-color':color},grad));
        return 'url(#'+id+')';
    }
    function brickwork(g,x,y,w,h,color='#b7a993'){
        for(let row=0;row<Math.floor(h/6);row++){
            const yy=y+row*6;line(g,x,yy,x+w,yy,color,.45);
            for(let xx=x+((row%2)*5+5);xx<x+w;xx+=10)line(g,xx,yy,xx,Math.min(y+h,yy+6),color,.45);
        }
    }
    function windowFrame(g,x,y,w,h,lit=true){
        rect(g,x-1,y-1,w+2,h+2,'#3a3229',{rx:.5});
        rect(g,x,y,w,h,lit?'#e7bc75':'#53666a');
        line(g,x+w/2,y,x+w/2,y+h,'#5b4837',.8);
        line(g,x,y+h/2,x+w,y+h/2,'#5b4837',.8);
        rect(g,x-2,y+h,w+4,1.5,'#b59d77');
    }
    function roofTiles(g,w,h){
        for(let i=1;i<5;i++){
            const yy=-h-19+i*3.8,half=(w/2+4)*i/5;
            line(g,-half,yy,half,yy,'#382f2b',.55);
            for(let x=-half+4;x<half-1;x+=6)line(g,x,yy,x+1,yy-2,'#ead1aa65',.5);
        }
        line(g,0,-h-19,12,-h-27,'#f6d3a1',1.1);
    }
    function house(g,x,y,wall,roof,w=32,h=22){
        const a=el('g',{transform:'translate('+x+' '+y+')'},g);
        ellipse(a,0,5,w*.85,10,'#101c2380');
        poly(a,[-w/2,0,w/2,0,w/2,-h,-w/2,-h].join(' '),shade(a,['#f4e1b8',wall,'#8c7154']));
        brickwork(a,-w/2,-h,w,h,'#856f5355');
        poly(a,[w/2,0,w/2+12,-8,w/2+12,-h-8,w/2,-h].join(' '),'#4b403b');
        poly(a,[-w/2-4,-h,0,-h-19,w/2+4,-h].join(' '),roof,'#362e2a');
        poly(a,[0,-h-19,12,-h-27,w/2+16,-h-8,w/2+4,-h].join(' '),roof);
        roofTiles(a,w,h);
        path(a,'M-4 0 V-9 Q0 -15 4 -9 V0 Z','#382b24',{stroke:'#776148','stroke-width':1});
        windowFrame(a,-w/2+5,-h+6,5,7);if(w>32)windowFrame(a,w/2-11,-h+6,6,7);
        line(a,-w/2,-h,w/2,-h,'#e9c795',1.3);line(a,-w/2,0,w/2,0,'#5f4c38',1.3);
        if(skin()==='medieval'){for(const xx of [-w/2+1,w/2-1])line(a,xx,-h,xx,0,'#614735',2);line(a,-w/2,-h+2,-3,-2,'#705037',1.5);line(a,w/2,-h+2,3,-2,'#705037',1.5);}
    }
    function tree(g,x,y){
        ellipse(g,x+3,y+3,15,6,'#142b2580');rect(g,x-2,y-24,4,26,'#60452e');
        for(let i=0;i<3;i++){
            const r=14-i*3,yy=y-6-i*10,top=yy-26+i*2;
            path(g,'M'+(x-r)+' '+yy+' Q'+(x-r*.8)+' '+(yy-12)+' '+x+' '+top+' Q'+(x+r*.8)+' '+(yy-9)+' '+(x+r)+' '+yy+' Q'+x+' '+(yy+8)+' '+(x-r)+' '+yy+'Z',['#244c36','#376744','#628451'][i]);
            path(g,'M'+x+' '+top+' Q'+(x-r*.6)+' '+(yy-8)+' '+(x-r)+' '+yy+' Q'+(x-r*.4)+' '+(yy-1)+' '+x+' '+(yy-4)+'Z',['#50764a','#688f52','#9bac68'][i]);
        }
    }
    function factory(g,x,y){
        const a=el('g',{transform:'translate('+x+' '+y+')'},g);
        ellipse(a,0,8,42,12,'#101a2588');
        poly(a,'-35,0 28,0 43,-12 -20,-12','#47555d');
        rect(a,-35,-29,63,29,'#829096');poly(a,'28,-29 43,-41 43,-12 28,0','#37494d');
        poly(a,'-35,-29 -22,-44 -22,-29 -8,-44 -8,-29 6,-44 6,-29 20,-44 28,-29','#bd9876','#303c40');
        brickwork(a,-35,-29,63,29,'#34444b66');
        for(let i=0;i<5;i++)windowFrame(a,-30+i*11,-20,7,8);
        for(const x of [-34,-13,8,27])line(a,x,-28,x,0,'#bdc4bc',1.1);
        line(a,-35,-5,28,-5,'#33464c',2);rect(a,-6,-10,12,10,'#35474d');
        rect(a,29,-65,9,41,'#78503d');rect(a,27,-66,13,5,'#c4a18c');
    }
    function tent(g,x,y){
        ellipse(g,x,y+3,30,9,'#382b2470');
        poly(g,(x-27)+','+y+' '+x+','+(y-32)+' '+(x+27)+','+y,'#e5bd83','#784d36');
        poly(g,x+','+(y-32)+' '+(x+27)+','+y+' '+(x+38)+','+(y-7)+' '+(x+11)+','+(y-39),'#a65c43');
        for(let i=-2;i<=2;i++){line(g,x+i*10,y-2,x+i*4,y-23,'#a14b3e',2);line(g,x+i*10+1,y-2,x+i*4+1,y-23,'#f5d9a4',.65);}
        for(const sign of [-1,1]){line(g,x+sign*20,y-9,x+sign*37,y+3,'#c8a66f',1);line(g,x+sign*37,y+3,x+sign*37,y+6,'#73513a',2);}
        line(g,x-27,y,x+27,y,'#77543a',2);
        poly(g,(x-7)+','+y+' '+x+','+(y-18)+' '+(x+7)+','+y,'#593d32');
    }
    // G15 architectural kit: handcrafted orthographic elevations, with
    // front, side and roof planes sharing one consistent perspective.
    function solid(g,x,y,w,h,depth,front,side,roof){
        const a=el('g',{transform:'translate('+x+' '+y+')'},g);
        const dy=depth*.57;
        poly(a,[0,0,w,0,w+depth,-dy,depth,-dy].join(' '),'#202b2770');
        rect(a,0,-h,w,h,shade(a,front),{stroke:'#303b38','stroke-width':.75});
        poly(a,[w,0,w+depth,-dy,w+depth,-h-dy,w,-h].join(' '),shade(a,side,false),'#303b38');
        poly(a,[0,-h,depth,-h-dy,w+depth,-h-dy,w,-h].join(' '),shade(a,roof,false),'#303b38');
        line(a,0,-h,w,-h,'#eadbb180',1.1);line(a,w,-h,w+depth,-h-dy,'#d2d2b280',.8);
        return a;
    }
    function masonry(g,x,y,w,h){
        const a=solid(g,x,y,w,h,12,['#e0d6b8','#aaa995','#7e8272'],['#939b88','#626e65'],['#c1c0a5','#899481']);
        brickwork(a,0,-h,w,h,'#555f5366');
        for(let i=0;i<h/7;i++)line(a,w,-i*7,w+12,-i*7-6.8,'#47594e88',.55);
        return a;
    }
    function parapet(g,x,y,w){
        rect(g,x,y,w,5,'#c4c4ac',{stroke:'#677466','stroke-width':.7});
        for(let i=0;i<Math.floor(w/8);i++)rect(g,x+i*8,y-5,5.5,5.5,'#d5d4bc',{stroke:'#70796b','stroke-width':.6});
    }
    function arch(g,x,y,w,h,fill='#303b39',border='#c9b99a'){
        path(g,'M'+x+' '+y+'V'+(y-h+w/2)+'A'+w/2+' '+w/2+' 0 0 1 '+(x+w)+' '+(y-h+w/2)+'V'+y+'Z',fill,{stroke:border,'stroke-width':1.3});
    }
    function timber(g,x,y,w,h){
        const a=solid(g,x,y,w,h,12,['#eed4a1','#c9aa79','#9d7b50'],['#ae936a','#776447'],['#c7ae7d','#997b50']);
        for(const xx of [1,w/2,w-1])line(a,xx,-h,xx,0,'#634a35',1.7);
        for(const yy of [-h+2,-h*.45,-1])line(a,0,yy,w,yy,'#73543b',1.6);
        line(a,2,-h+3,w/2,-h*.45,'#73543b',1.2);line(a,w-2,-h+3,w/2,-h*.45,'#73543b',1.2);
        const ht=15,peak=-h-ht;
        poly(a,[-3,-h,w/2,peak,w+3,-h].join(' '),'#9a6346','#593f33');
        poly(a,[w/2,peak,w/2+12,peak-7,w+15,-h-7,w+3,-h].join(' '),shade(a,['#bc8055','#83523d'],false),'#563e31');
        for(let j=1;j<5;j++){const yy=peak+j*ht/5,span=(w/2+3)*j/5;line(a,w/2-span,yy,w/2+span,yy,'#634732',.7);for(let xx=w/2-span+3;xx<w/2+span;xx+=6)line(a,xx,yy,xx-1,yy-2,'#f1c49580',.55);}
        line(a,w/2,peak,w/2+12,peak-7,'#f3d1a0',1.2);
        return a;
    }
    function plankStack(g,x,y){
        const a=el('g',{transform:'translate('+x+' '+y+')'},g);
        for(let i=0;i<4;i++){const yy=-i*3;poly(a,[0,yy,23,yy,29,yy-4,6,yy-4].join(' '),'#d3aa72','#6f5035');rect(a,0,yy,23,3,'#9d7346',{stroke:'#5d482f','stroke-width':.5});poly(a,[23,yy,29,yy-4,29,yy-1,23,yy+3].join(' '),'#785b3b');}
    }
    function metalHall(g,x,y,w,h,depth=15){
        const a=solid(g,x,y,w,h,depth,['#c2cbc5','#899b9c','#526972'],['#7b9195','#415964'],['#b1bcb8','#657d84']);
        for(let xx=3;xx<w;xx+=5)line(a,xx,-h+2,xx,-1,'#344b534f',.6);
        for(let xx=0;xx<=w;xx+=Math.max(10,w/4))line(a,xx,-h,xx,0,'#cdd3c1',1);
        rect(a,0,-4,w,4,'#42535a');line(a,0,-h,w,-h,'#d2bd91',1.2);
        return a;
    }
    function rollingDoor(g,x,y,w,h){
        rect(g,x-1,y-h-1,w+2,h+1,'#344750',{stroke:'#b1b7a4','stroke-width':.9});
        rect(g,x,y-h,w,h,shade(g,['#647b83','#344952']));
        for(let yy=y-h+3;yy<y;yy+=3)line(g,x,yy,x+w,yy,'#8da0a1',.65);
        rect(g,x,y-h,w,2,'#c2a46e');
    }
    function tank(g,x,y,r,h){
        const a=el('g',{transform:'translate('+x+' '+y+')'},g);
        ellipse(a,3,3,r*1.3,r*.55,'#24353a65');rect(a,-r,-h,r*2,h,shade(a,['#485d64','#d6dbd0','#778d91'],false),{stroke:'#40535a','stroke-width':.8});
        ellipse(a,0,-h,r,r*.30,'#acb9b4',{stroke:'#52636a','stroke-width':.8});
        ellipse(a,0,-h,r*.55,r*.17,'#6f858b');for(const yy of [-h*.7,-h*.3])path(a,'M'+(-r)+' '+yy+'Q0 '+(yy+r*.5)+' '+r+' '+yy,'none',{stroke:'#4a6269','stroke-width':.9});
        line(a,r*.65,-h+1,r*.65,0,'#d6c291',1.4);
        for(let yy=-h+5;yy<0;yy+=4)line(a,r*.65-3,yy,r*.65+1,yy,'#d6c291',.7);
        return a;
    }
    function gantry(g,x,y,w,h){
        const a=el('g',{transform:'translate('+x+' '+y+')'},g);
        for(const xx of [0,w]){line(a,xx,0,xx,-h,'#283b43',5);line(a,xx,0,xx,-h,'#d2aa5e',3);line(a,xx-4,0,xx+4,0,'#b3a38a',2);}
        line(a,0,-h,w,-h,'#293e46',6);line(a,0,-h,w,-h,'#e0bc72',4);
        for(let xx=0;xx<w;xx+=8)line(a,xx,-h+2,Math.min(xx+5,w),-h-2,'#3d4d50',1.1);
        rect(a,w*.58,-h+3,9,5,'#54676c',{stroke:'#d2b675'});line(a,w*.63,-h+8,w*.63,-12,'#bcbdb0',.9);
        path(a,'M'+(w*.63)+' -12q-1 5 3 5q3 0 3 -3','none',{stroke:'#e8c478','stroke-width':1.6});
    }
    function courtyard(g,fill,edge){
        ellipse(g,7,12,57,18,'#14252160');
        poly(g,'-53,1 -18,-19 53,-3 31,21 -20,24',fill,edge);
        for(let i=0;i<7;i++)line(g,-48+i*11,4+i*2,-24+i*11,-9+i*2,edge,.45);
    }
    function plaster(g,x,y,w,h,roof='#b9714a'){
        const a=solid(g,x,y,w,h,14,['#f7e2b8','#d9ad77','#b08756'],['#c59a68','#94724f'],['#e4c697','#bf955f']);
        rect(a,0,-h-3,w,4,roof,{stroke:'#80563e','stroke-width':.7});
        for(let i=0;i<Math.floor(w/7);i++)rect(a,1+i*7,-h-6,4,4,'#e2bf91',{stroke:'#a57b50','stroke-width':.5});
        line(a,0,-h+8,w,-h+8,'#b78a5a',1.2);
        return a;
    }
    function awning(g,x,y,w,color='#a65241'){
        const a=el('g',{transform:'translate('+x+' '+y+')'},g);
        for(let i=0;i<Math.ceil(w/7);i++){const xx=i*7,ww=Math.min(7,w-xx);poly(a,[xx,0,xx+ww,0,xx+ww+4,8,xx+4,8].join(' '),i%2?'#ecd1a2':color,'#986c49');path(a,'M'+(xx+4)+' 8q'+ww/2+' 4 '+ww+' 0',i%2?'#ecd1a2':color,{stroke:'#986c49','stroke-width':.6});}
        line(a,4,8,4,19,'#6c4e34',1.5);line(a,w+4,8,w+4,19,'#6c4e34',1.5);
    }
    const architecturalTypes=['lumberyard','farm','granary','keep','power_plant','steelworks','warehouse','freight_terminal','market_hall','caravan_hub'];
    function architecture(a,type,level){
        const advanced=level>=2,scope=el('g',{'data-architecture':type,'data-architecture-level':level},a);
        const g=scope;
        if(['lumberyard','farm','granary','keep'].includes(type))courtyard(g,'#8e925e','#c1b985');
        else if(['power_plant','steelworks','warehouse','freight_terminal'].includes(type))courtyard(g,'#718288','#acb3a5');
        else courtyard(g,'#bd9662','#e1c18b');
        switch(type){
            case 'lumberyard':{
                tree(g,-38,-8);const hut=timber(g,-26,-7,38,25);arch(hut,14,0,10,17);windowFrame(hut,4,-17,6,7);
                const shed=timber(g,16,2,25,17);arch(shed,6,0,15,13);plankStack(g,-41,15);plankStack(g,9,17);
                if(advanced){const ext=timber(g,23,-15,20,19);windowFrame(ext,5,-11,6,6);plankStack(g,27,7);}
                break;
            }
            case 'farm':{
                for(let j=0;j<5;j++){const x=-47+j*9,y=2+j*3;poly(g,[x,y,x+6,y+2,x+34,y-12,x+28,y-14].join(' '),'#67583c');for(let i=0;i<6;i++){const xx=x+i*5,yy=y-i*2;line(g,xx,yy,xx,yy-6,'#eed078',1);path(g,'M'+xx+' '+(yy-4)+'q-4 -5 -3 -6q3 0 3 4q3 -4 4 -3q0 4 -4 5Z','#d8b75d');}}
                const home=timber(g,10,-12,29,22);arch(home,12,0,8,13);windowFrame(home,4,-13,5,6);
                if(advanced){const barn=timber(g,-11,-26,24,20);arch(barn,7,0,10,13);}
                break;
            }
            case 'granary':{
                const barn=timber(g,-30,-1,51,39);
                for(let xx=5;xx<48;xx+=10){windowFrame(barn,xx,-29,6,7,false);line(barn,xx,-10,xx+5,-10,'#c4a46b',1);}
                arch(barn,19,0,13,22);rect(barn,-3,0,57,4,'#938873',{stroke:'#514c3a'});
                for(let i=0;i<3;i++){ellipse(g,-38+i*13,12,5,7,'#ac8d57',{stroke:'#785c3b'});line(g,-42+i*13,10,-34+i*13,10,'#73583d',.8);}
                break;
            }
            case 'keep':{
                const main=masonry(g,-24,-4,40,49);parapet(main,-2,-52,44);
                const tower=masonry(g,17,3,23,37);parapet(tower,-2,-40,27);
                arch(main,14,0,12,23);for(const x of [6,29])arch(main,x,-28,5,14);
                arch(tower,8,-15,6,13);line(g,-22,-60,-22,-74,'#67573c',1.4);poly(g,'-22,-74 -5,-70 -22,-65','#9d5545','#714438');
                if(advanced){const flank=masonry(g,-43,6,20,34);parapet(flank,-2,-37,24);arch(flank,7,-10,5,12);}
                break;
            }
            case 'power_plant':{
                const hall=metalHall(g,-40,2,55,27);for(let i=0;i<4;i++)windowFrame(hall,5+i*12,-19,8,8,false);rollingDoor(hall,22,0,12,13);
                tank(g,-22,-22,12,42);tank(g,14,-24,11,38);
                const pipe=path(g,'M-22 -13V-5H26V-18H39','none',{stroke:'#263c45','stroke-width':5});pipe.setAttribute('data-material','pipe');path(g,'M-22 -13V-5H26V-18H39','none',{stroke:'#cfb17a','stroke-width':2.5});
                if(advanced){tank(g,38,5,9,26);line(g,38,-20,26,-20,'#b8a178',2);}
                break;
            }
            case 'steelworks':{
                const hall=metalHall(g,-43,1,63,34);for(let i=0;i<3;i++)rollingDoor(hall,5+i*19,0,14,23);
                for(let i=0;i<4;i++){const x=-43+i*16;poly(g,[x,-33,x+8,-47,x+16,-33].join(' '),'#a5b2ac','#40565d');}
                tank(g,30,-13,7,51);tank(g,44,-15,5,40);
                gantry(g,-31,13,58,22);
                if(advanced){const ext=metalHall(g,19,-5,22,24,10);rollingDoor(ext,4,0,13,16);}
                break;
            }
            case 'warehouse':{
                const hall=metalHall(g,-42,2,68,32,17);
                for(let i=0;i<3;i++)rollingDoor(hall,5+i*22,0,16,23);
                for(let i=0;i<5;i++)line(g,-38+i*12,-30,-21+i*12,-40,'#d4c5a6',.8);
                for(let i=0;i<3;i++){rect(g,-32+i*25,6,19,5,'#baad90',{stroke:'#586268'});line(g,-32+i*25,11,-13+i*25,11,'#e1ba75',1);}
                if(advanced){const office=metalHall(g,29,9,20,17,9);windowFrame(office,5,-12,7,7,false);}
                break;
            }
            case 'freight_terminal':{
                for(const y of [4,13]){line(g,-49,y,49,y,'#333f46',3);line(g,-49,y,49,y,'#c0c3b4',1);}
                for(let i=0;i<12;i++)line(g,-46+i*8,1,-46+i*8,16,'#545e60',1.4);
                for(let i=0;i<3;i++){const x=-46+i*29;const a=solid(g,x,0,24,14,8,[['#b97c57','#965b42','#6c5043'],['#a3b4b0','#788e94','#4c666e'],['#d3ba80','#ae8f5e','#8c7048']][i],['#738078','#495a5b'],['#bdbaa1','#8c907e']);for(let j=3;j<24;j+=4)line(a,j,-13,j,-1,'#384a4766',.7);for(const xx of [5,19])ellipse(a,xx,3,3.5,3,'#29353c');}
                gantry(g,-39,18,78,51);
                if(advanced)gantry(g,-29,-3,54,39);
                break;
            }
            case 'market_hall':{
                const hall=plaster(g,-35,-3,59,36);
                for(let i=0;i<3;i++)arch(hall,5+i*19,0,13,22,'#69513d','#efc994');
                for(let i=0;i<3;i++)windowFrame(hall,8+i*19,-30,7,5,false);
                awning(g,-40,-17,66,'#a34e40');
                for(let i=0;i<3;i++){const x=-35+i*25;rect(g,x,8,15,6,'#9d7244',{stroke:'#e0bc83'});ellipse(g,x+4,7,3,2,'#b75e42');ellipse(g,x+10,7,3,2,'#869454');}
                if(advanced){const side=plaster(g,28,-4,19,27,'#68857b');arch(side,5,0,10,17);}
                break;
            }
            case 'caravan_hub':{
                const inn=plaster(g,-33,-7,48,31,'#758b78');for(let i=0;i<2;i++)windowFrame(inn,6+i*30,-23,7,7,false);arch(inn,17,0,14,24);
                const gate=plaster(g,22,3,21,26,'#b47751');arch(gate,4,0,13,21);
                tent(g,-25,12);plankStack(g,14,15);
                if(advanced){const store=plaster(g,-8,-23,22,23);arch(store,5,0,10,16);}
                break;
            }
        }
    }
    // Distinct original silhouettes; no completed building is drawn without a verified stage.
    function model(g,type,detail){
        const p=palette(),level=detail?.level,verified=Number.isSafeInteger(level),pending=detail?.pending!=null;
        const a=el('g',{'data-model':type,'data-stage':!verified?'unknown':pending?'pending':level===0?'foundation':'built'},g);
        ellipse(a,0,3,48,13,'#0b171b70');
        if(!verified||level===0){
            poly(a,'-42,0 0,-20 42,0 0,20','#80755d','#e5ca90');
            poly(a,'-35,0 0,-15 35,0 0,15','#313a32','#d9c18d');
            for(const x of [-29,29]){line(a,x,-2,x,-15,'#d3b57e',3);line(a,x-5,-11,x+5,-11,'#d3b57e',2);}
            if(!verified)el('text',{x:0,y:3,'text-anchor':'middle',fill:'#fff','font-size':12},a,'?');
        }else{
            if(architecturalTypes.includes(type))architecture(a,type,level);
            else switch(type){
                case 'lumberyard':
                    poly(a,'-48,3 -17,-12 45,-6 26,15 -21,20','#8c815b','#5b6145');
                    for(let i=0;i<7;i++)line(a,-43+i*8,5+i*.9,-20+i*8,-6+i*.9,'#d8c49a',.65);
                    tree(a,-35,-12);house(a,-10,-7,'#c6aa79','#755040',43,28);
                    for(const x of [10,38])line(a,x,-18,x,6,'#73543b',2.6);
                    poly(a,'5,-20 24,-31 47,-20 34,-13','#795443','#49382f');
                    poly(a,'24,-31 47,-20 34,-13 12,-23','#ad7955');
                    rect(a,11,-4,24,4,'#c0a175');line(a,16,0,16,7,'#6d5036',2);line(a,31,0,31,7,'#6d5036',2);
                    for(let j=0;j<7;j++)line(a,9+j*5,-20,24+j*3,-28,'#e2b686',.6);
                    for(let i=0;i<4;i++){rect(a,12+i*4,1+i*3,23,5,'#9b7045',{rx:2});ellipse(a,35+i*4,3+i*3,3,2,'#dfb77d');}
                    break;
                case 'farm':
                    poly(a,'-43,2 0,-17 43,2 0,21','#997a42','#e3c781');
                    for(let i=0;i<7;i++){line(a,-36+i*9,2+i*2,-8+i*7,-11+i*2,'#634c32',4);line(a,-36+i*9,1+i*2,-8+i*7,-12+i*2,'#d9bc5f',2);for(let j=0;j<4;j++){const x=-36+i*9+j*6,y=1+i*2-j*3;line(a,x,y,x,y-5,'#e9cf79',.8);line(a,x,y-2,x+2,y-4,'#f0db94',.8);}}
                    house(a,12,-14,'#e0c498','#8c5541',24,15);break;
                case 'granary':
                    house(a,0,-4,'#e1c9a2','#956744',51,38);
                    for(let i=0;i<3;i++)line(a,-19+i*17,-36,-19+i*17,-7,'#826645',2);break;
                case 'keep':
                    rect(a,-23,-48,46,48,shade(a,['#dedbc6','#b8b9ad','#797f70']),{stroke:'#595c57'});brickwork(a,-23,-48,46,48,'#657065');
                    poly(a,'23,0 39,-10 39,-58 23,-48','#777f77');
                    for(let i=0;i<4;i++)rect(a,-25+i*14,-55,10,10,'#d2d1bf');
                    for(const x of [-15,13]){path(a,'M'+x+' -23 V-34 Q'+(x+2)+' -39 '+(x+4)+' -34 V-23 Z','#34454a');line(a,x-1,-23,x+5,-23,'#e3d8b8',1);}
                    line(a,-23,-18,23,-18,'#6f796d',1);line(a,-23,-47,23,-47,'#dfd8ba',1.2);
                    path(a,'M-8 0 V-12 A8 8 0 0 1 8 -12 V0 Z','#424946');break;
                case 'power_plant':
                    poly(a,'-49,0 0,-23 49,-4 20,19 -29,18','#657478','#3b4c52');
                    poly(a,'-39,-13 -13,-27 45,-27 27,-13','#a1acaa','#3d4d52');
                    rect(a,-39,-13,66,20,shade(a,['#b1bab4','#738588','#4c6168']));
                    poly(a,'27,-13 45,-27 45,-7 27,7','#4b5d63','#33434b');
                    for(let i=0;i<5;i++)windowFrame(a,-34+i*11,-8,7,6,false);
                    for(const x of [33,40]){rect(a,x,-29,4,22,'#a18062');ellipse(a,x+2,-29,2.2,1.2,'#3c4b50');}
                    for(const x of [-23,14]){path(a,'M'+(x-13)+' -5 Q'+(x-6)+' -26 '+(x-9)+' -48 H'+(x+9)+' Q'+(x+6)+' -26 '+(x+13)+' -5 Z',shade(a,['#d6d9cb','#aebbb8','#62777b'],false),{stroke:'#475961'});ellipse(a,x,-48,9,3,'#41525a');}
                    for(const x of [-23,14]){ellipse(a,x,-46,10,3,'none',{stroke:'#cdd5ce','stroke-width':1});path(a,'M'+(x-10)+' -24 Q'+x+' -19 '+(x+10)+' -24','none',{stroke:'#63777d','stroke-width':1.3});}
                    line(a,-40,5,40,5,'#e9bc6d',3);line(a,-38,1,38,1,'#344b54',2);break;
                case 'steelworks':
                    factory(a,-3,0);rect(a,-18,-16,18,14,'#4f3930');rect(a,-15,-13,12,8,'#ffb259');
                    break;
                case 'warehouse':
                    rect(a,-42,-27,70,27,'#8b9da0');poly(a,'-42,-27 -27,-43 43,-43 28,-27','#c9a77d');
                    poly(a,'28,0 43,-10 43,-43 28,-27','#53666b');
                    for(let i=0;i<3;i++){const x=-36+i*20;rect(a,x,-19,15,19,'#394b53');for(let yy=-17;yy<0;yy+=3)line(a,x,yy,x+15,yy,'#87999c',.7);rect(a,x-1,-21,17,2,'#d2b38a');}
                    for(let i=0;i<5;i++)line(a,-40+i*13,-27,-25+i*13,-43,'#80694e',.9);
                    break;
                case 'freight_terminal':
                    for(const y of [0,8])line(a,-46,y,47,y,'#c0b4a2',2);
                    for(let i=0;i<3;i++){rect(a,-41+i*26,-12,24,12,['#a36242','#748d92','#c7a66a'][i]);ellipse(a,-36+i*26,2,4,3,'#29343b');ellipse(a,-22+i*26,2,4,3,'#29343b');}
                    line(a,-24,-2,-24,-49,'#d4ac59',4);line(a,-24,-49,35,-49,'#d4ac59',4);line(a,23,-49,23,-21,'#e4dbc2',1);break;
                case 'market_hall':
                    poly(a,'-48,5 -8,-16 47,-3 26,19 -25,20','#b68d59','#86673f');
                    for(let i=0;i<5;i++)line(a,-42+i*13,8+i*2,-15+i*13,-6+i*2,'#e2c38c',.7);
                    house(a,0,-9,'#e6c18a','#a55340',55,35);
                    rect(a,-29,-22,58,19,shade(a,['#f0d4a0','#b48c5f']));
                    for(let i=0;i<3;i++){const x=-23+i*19;path(a,'M'+x+' -3V-12Q'+(x+5)+' -22 '+(x+10)+' -12V-3Z','#694535',{stroke:'#d6b082','stroke-width':1.1});}
                    for(const x of [-29,-10,9,29])line(a,x,-20,x,-2,'#be9866',2.1);
                    for(let i=0;i<3;i++){const x=-24+i*19;rect(a,x,-30,9,4,'#735345');line(a,x,-31,x+9,-31,'#f1d7a6',1);}
                    line(a,-31,-23,31,-23,'#f2d4a0',1.5);
                    for(let i=0;i<5;i++){const x=-28+i*12;poly(a,[x,-18,x+12,-18,x+10,-8,x-2,-8].join(' '),i%2?'#f1d4a1':'#a95342');path(a,'M'+(x-2)+' -8 q5 5 12 0','none',{stroke:'#6e4331','stroke-width':.9});}
                    for(const x of [-27,27])line(a,x,-8,x,2,'#654332',1.5);
                    for(let i=0;i<3;i++)rect(a,-23+i*20,0,12,8,'#ad7645',{stroke:'#e1ba80'});break;
                case 'caravan_hub':
                    tent(a,-12,-3);tent(a,25,3);
                    rect(a,-29,8,27,9,'#bd8b50',{stroke:'#f2cc87'});
                    for(const x of [-24,-7]){ellipse(a,x,18,5,5,'#47382d',{stroke:'#d4b384'});line(a,x-4,18,x+4,18,'#b89b6a',.8);line(a,x,14,x,22,'#b89b6a',.8);}
                    for(let i=0;i<3;i++)rect(a,-27+i*8,4,7,5,['#9c6d43','#d7bd80','#a16f4f'][i],{stroke:'#77513b','stroke-width':.6});
                    line(a,-2,12,14,6,'#caab74',2);break;
                default: house(a,0,0,p[4],p[3],40,28);
            }
            if(level>1){rect(a,31,-45,18,18,'#1a3034',{rx:3,stroke:'#e9cb86'});el('text',{x:40,y:-33,'text-anchor':'middle',fill:'#ffe3a4','font-size':10},a,String(level));}
        }
        if(verified&&level>0&&!pending){
            // A level marker is a readout, not a simulation event.
            const seal=el('g',{class:'scene-level-seal','aria-hidden':'true'},a);
            ellipse(seal,-37,-36,9,9,'#273d38',{stroke:'#e6c68a','stroke-width':1.1});
            el('text',{x:-37,y:-33,'text-anchor':'middle',fill:'#fff0c7','font-size':8},seal,String(level));
        }
        if(pending){
            const scaffold=el('g',{class:'scene-scaffold'},a);
            for(const x of [-39,39]){line(scaffold,x,8,x,-48,'#d6b16f',3);line(scaffold,x-4,8,x+4,8,'#d6b16f',2);}
            for(const y of [-10,-31])line(scaffold,-39,y,39,y,'#d6b16f',2);
            line(scaffold,-39,-10,39,-31,'#d6b16f',1);line(scaffold,-39,-31,39,-10,'#d6b16f',1);
            path(scaffold,'M-39 -48 H40 V-44 H-39 Z','#e4c177');
        }
    }

        const extensions=new Map();
        function register(type,renderer){
            if(typeof type!=='string'||!type||typeof renderer!=='function')throw TypeError('Expected sprite type and renderer');
            if(extensions.has(type))throw Error('Sprite already registered');
            extensions.set(type,renderer);
        }
        function render(g,type,detail){
            // Unknown and unfinished foundations must retain the generic stage artwork.
            if(Number.isSafeInteger(detail?.level)&&detail.level>0&&!detail.pending&&extensions.has(type)){
                const layer=el('g',{'data-model':type,'data-stage':'built'},g);
                extensions.get(type)(layer,Object.freeze({...detail}),Object.freeze({el,poly,rect,line,ellipse,path,shade,brickwork,windowFrame,roofTiles,house,tree,factory,tent}));
            }else model(g,type,detail);
        }
        return Object.freeze({el,poly,rect,line,ellipse,path,shade,brickwork,windowFrame,roofTiles,house,tree,factory,tent,model:render,register});
    }
    // Independent viewBox controller. Coordinates refer to presentation space, never server map positions.
    function camera(svg,{min=1,max=3,onChange=()=>{}}={}){
        let extent={w:1,h:1},state={x:0,y:0,z:1},pointer=null;
        function draw(){
            const w=extent.w/state.z,h=extent.h/state.z;
            state.x=Math.max(0,Math.min(extent.w-w,state.x));
            state.y=Math.max(0,Math.min(extent.h-h,state.y));
            svg.setAttribute('viewBox',[state.x,state.y,w,h].join(' '));onChange({...state});
        }
        function size(w,h){if(!(w>0&&h>0))throw RangeError('Invalid scene extent');if(w!==extent.w||h!==extent.h){extent={w,h};state={x:0,y:0,z:1};}draw();}
        function zoom(f,atX=.5,atY=.5){
            if(!Number.isFinite(f)||f<=0)return;
            const z=Math.max(min,Math.min(max,state.z*f));
            state.x+=(extent.w/state.z-extent.w/z)*atX;state.y+=(extent.h/state.z-extent.h/z)*atY;state.z=z;draw();
        }
        function reset(){state={x:0,y:0,z:1};draw();}
        function down(e){
            if(e.button!==0||e.target.closest('[role=button]'))return;
            pointer={id:e.pointerId,x:e.clientX,y:e.clientY};svg.setPointerCapture(e.pointerId);
        }
        function move(e){
            if(pointer?.id!==e.pointerId)return;
            const matrix=svg.getScreenCTM();if(!matrix)return;
            const dx=(e.clientX-pointer.x)/Math.max(.0001,Math.abs(matrix.a)),dy=(e.clientY-pointer.y)/Math.max(.0001,Math.abs(matrix.d));
            pointer.x=e.clientX;pointer.y=e.clientY;state.x-=dx;state.y-=dy;draw();
        }
        function up(){pointer=null;}
        function key(e){
            if(e.target!==svg)return;
            if(e.key==='+'||e.key==='=')zoom(1.25);
            else if(e.key==='-')zoom(.8);
            else if(e.key==='Home')reset();
            else if(['ArrowUp','ArrowDown','ArrowLeft','ArrowRight'].includes(e.key)){
                state.x+=({'ArrowLeft':-1,'ArrowRight':1}[e.key]??0)*extent.w/state.z*.12;
                state.y+=({'ArrowUp':-1,'ArrowDown':1}[e.key]??0)*extent.h/state.z*.12;draw();
            }else return;
            e.preventDefault();
        }
        svg.addEventListener('pointerdown',down);svg.addEventListener('pointermove',move);svg.addEventListener('keydown',key);
        for(const event of ['pointerup','pointercancel','lostpointercapture'])svg.addEventListener(event,up);
        return Object.freeze({size,zoom,reset,state:()=>({...state}),destroy(){svg.removeEventListener('pointerdown',down);svg.removeEventListener('pointermove',move);svg.removeEventListener('keydown',key);for(const event of ['pointerup','pointercancel','lostpointercapture'])svg.removeEventListener(event,up);}});
    }
    window.AeternumArt=Object.freeze({create,camera,version:1});
})();
