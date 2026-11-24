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
            switch(type){
                case 'lumberyard':
                    house(a,-8,-5,'#c6aa79','#755040',37,22);tree(a,-35,-1);
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
                    rect(a,-38,-18,67,18,'#72828a');poly(a,'-38,-18 -23,-28 43,-28 29,-18','#c8b6a1');
                    for(const x of [-23,14]){path(a,'M'+(x-13)+' -5 Q'+(x-6)+' -26 '+(x-9)+' -48 H'+(x+9)+' Q'+(x+6)+' -26 '+(x+13)+' -5 Z','#9faeb0',{stroke:'#475961'});ellipse(a,x,-48,9,3,'#41525a');}
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
                    house(a,0,-9,'#e6c18a','#a55340',48,30);
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
