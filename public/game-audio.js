/* G20 original procedural music and sound effects. Repository license applies.
 * No audio samples, network requests, or third-party compositions. */
'use strict';
(()=>{
    const profiles=Object.freeze({
        medieval:Object.freeze({tempo:76,root:50,mode:[0,2,3,5,7,9,10],motif:[0,2,4,3,2,1,0,4,5,4,2,3,1,0,1,0],chords:[0,5,3,4],lead:'triangle',bass:'sine',ambient:165}),
        industrial:Object.freeze({tempo:92,root:45,mode:[0,2,3,5,7,8,10],motif:[0,0,4,2,0,3,2,0,5,5,4,2,3,2,1,0],chords:[0,3,5,4],lead:'triangle',bass:'triangle',ambient:95}),
        guild:Object.freeze({tempo:84,root:52,mode:[0,1,4,5,7,8,10],motif:[0,1,2,3,4,3,2,1,0,4,5,4,3,2,1,0],chords:[0,3,4,0],lead:'sine',bass:'triangle',ambient:205})
    });
    const cues=Object.freeze({
        'building.place':[[130,.12,'triangle'],[196,.19,'sine']],
        'building.start':[[105,.12,'triangle'],[155,.15,'triangle'],[220,.23,'sine']],
        'building.complete':[[261.63,.18,'triangle'],[329.63,.18,'triangle'],[392,.38,'sine']],
        'building.cancel':[[220,.13,'sine'],[146.83,.22,'triangle']],
        'settlement.work':[[105,.10,'triangle'],[142,.09,'triangle'],[196,.15,'sine']],
        'settlement.feed':[[330,.13,'sine'],[440,.18,'triangle'],[523.25,.26,'sine']],
        'industry.generate':[[82.41,.18,'triangle'],[164.81,.14,'sine'],[246.94,.24,'triangle']],
        'industry.refine':[[110,.10,'triangle'],[220,.12,'triangle'],[293.66,.22,'sine']],
        'industry.ship':[[130.81,.24,'triangle'],[196,.28,'triangle']],
        'market.create':[[392,.12,'sine'],[523.25,.22,'triangle']],
        'market.accept':[[523.25,.11,'triangle'],[659.25,.13,'sine'],[783.99,.29,'sine']],
        'market.cancel':[[329.63,.15,'triangle'],[246.94,.22,'sine']],
        'economy.upgrade':[[261.63,.12,'triangle'],[392,.26,'sine']],
        'economy.settle':[[293.66,.12,'sine'],[440,.2,'triangle']],
        'campaign.victory':[[261.63,.16,'triangle'],[329.63,.16,'triangle'],[392,.18,'triangle'],[523.25,.65,'sine']]
    });
    const clamp=v=>Number.isFinite(v)?Math.max(0,Math.min(1,v)):0;
    const hz=m=>440*Math.pow(2,(m-69)/12);
    function phrase(theme,step){
        const p=profiles[theme];if(!p||!Number.isSafeInteger(step)||step<0)return [];
        const degree=p.motif[step%p.motif.length],bar=Math.floor(step/4)%4;
        const notes=[{hz:hz(p.root+12+p.mode[degree]),duration:.42,wave:p.lead,level:.055}];
        if(step%4===0){
            const chord=p.chords[bar];
            notes.push({hz:hz(p.root+p.mode[chord]),duration:1.75,wave:p.bass,level:.07});
            notes.push({hz:hz(p.root+12+p.mode[(chord+2)%7]+(chord+2>=7?12:0)),duration:1.55,wave:'sine',level:.025});
        }
        return notes;
    }
    function create({AudioContext=window.AudioContext||window.webkitAudioContext,schedule=(fn,ms)=>setTimeout(fn,ms),cancel=id=>clearTimeout(id),onChange=()=>{}}={}){
        let ctx=null,buses=null,enabled=false,theme=null,visible=true,step=0,timer=null,generation=0,next=0,error=null;
        let volumes={music:.45,ambience:.25,effects:.65};
        const sources=new Set();
        const state=()=>({supported:typeof AudioContext==='function',enabled,theme,visible,volumes:{...volumes},error});
        const notify=()=>onChange(state());
        function silence(){
            if(timer!==null){cancel(timer);timer=null;}
            for(const node of sources){try{node.stop();}catch{}try{node.disconnect();}catch{}}
            sources.clear();
        }
        function gainValue(bus,value){
            const param=buses?.[bus]?.gain;
            if(param){param.cancelScheduledValues(ctx.currentTime);param.setTargetAtTime(value,ctx.currentTime,.035);}
        }
        function mix(){
            if(!buses)return;
            gainValue('master',enabled&&visible&&profiles[theme]? .55:0);
            for(const key of Object.keys(volumes))gainValue(key,volumes[key]);
        }
        function tone(bus,freq,at,duration,wave,level){
            if(!ctx||!buses||!enabled||!visible||!profiles[theme]||sources.size>=36)return;
            const osc=ctx.createOscillator(),env=ctx.createGain();
            osc.type=wave;osc.frequency.value=freq;
            const peak=Math.max(.0001,level);
            env.gain.setValueAtTime(.0001,at);
            env.gain.exponentialRampToValueAtTime(peak,at+Math.min(.035,duration*.25));
            env.gain.exponentialRampToValueAtTime(.0001,at+duration);
            osc.connect(env);env.connect(buses[bus]);sources.add(osc);
            osc.onended=()=>{sources.delete(osc);osc.disconnect();env.disconnect();};
            osc.start(at);osc.stop(at+duration+.02);
        }
        function tick(){
            timer=null;
            if(!enabled||!visible||!profiles[theme]||ctx?.state!=='running')return;
            const p=profiles[theme],interval=60/p.tempo/2;
            if(next<ctx.currentTime)next=ctx.currentTime+.03;
            while(next<ctx.currentTime+.18){
                if(volumes.music>0)for(const n of phrase(theme,step))tone('music',n.hz,next,n.duration*interval,n.wave,n.level);
                if(step%8===0&&volumes.ambience>0){
                    tone('ambience',p.ambient,next,interval*5,'sine',.018);
                    tone('ambience',p.ambient*1.003,next+.08,interval*5,'sine',.012);
                }
                step++;next+=interval;
            }
            timer=schedule(tick,100);
        }
        function restart(){
            silence();mix();step=0;next=(ctx?.currentTime??0)+.035;
            if(enabled&&visible&&profiles[theme]&&ctx?.state==='running')tick();
        }
        async function enable(){
            if(enabled)return true;
            if(typeof AudioContext!=='function'){error='unsupported';notify();return false;}
            const attempt=++generation;
            try{
                if(!ctx){
                    ctx=new AudioContext();
                    buses={master:ctx.createGain(),music:ctx.createGain(),ambience:ctx.createGain(),effects:ctx.createGain()};
                    buses.master.gain.value=0;
                    for(const key of Object.keys(volumes))buses[key].connect(buses.master);
                    buses.master.connect(ctx.destination);
                }
                await ctx.resume();
                if(attempt!==generation)return false;
                if(ctx.state!=='running')throw Error('Audio unavailable');
                enabled=true;error=null;restart();notify();return true;
            }catch{if(attempt===generation){enabled=false;error='unavailable';silence();mix();notify();}return false;}
        }
        function disable(){generation++;enabled=false;silence();mix();notify();}
        function setTheme(value){const target=Object.hasOwn(profiles,value)?value:null;if(target===theme)return;theme=target;restart();notify();}
        function setVisible(value){value=Boolean(value);if(visible===value)return;visible=value;restart();notify();}
        function setVolume(key,value){if(!Object.hasOwn(volumes,key))return;volumes[key]=clamp(value);mix();notify();}
        function cue(type){
            if(!enabled||!visible||!profiles[theme]||!ctx||ctx.state!=='running'||!volumes.effects||!Object.hasOwn(cues,type))return false;
            let at=ctx.currentTime+.025;
            for(const [freq,duration,wave] of cues[type]){tone('effects',freq,at,duration,wave,.11);at+=duration*.75;}
            return true;
        }
        function reset(){disable();theme=null;notify();}
        return Object.freeze({enable,disable,setTheme,setVisible,setVolume,cue,reset,state});
    }
    window.AeternumAudio=Object.freeze({create,phrase,profiles,cues,version:1});
})();
