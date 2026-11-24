'use strict';
const assert=require('node:assert/strict');
const {readFileSync}=require('node:fs');
const {runInNewContext}=require('node:vm');
const path=require('node:path');
const window={};
runInNewContext(readFileSync(path.join(__dirname,'../../public/game-audio.js'),'utf8'),{window});
const art=window.AeternumAudio;
const param=()=>({value:0,cancelScheduledValues(){},setTargetAtTime(v){this.value=v;},setValueAtTime(){},exponentialRampToValueAtTime(){}});
let created=0,started=0,stopped=0;
class FakeContext{
    constructor(){created++;this.currentTime=0;this.state='suspended';this.destination={};}
    async resume(){this.state='running';}
    createGain(){return {gain:param(),connect(){},disconnect(){}};}
    createOscillator(){return {frequency:{value:0},type:'sine',connect(){},disconnect(){},start(){started++;},stop(){stopped++;}};}
}
(async()=>{
    let phrases=0;
    for(const theme of ['medieval','industrial','guild'])for(let i=0;i<32;i++){
        const notes=art.phrase(theme,i);assert.ok(notes.length>=1&&notes.length<=3);
        for(const n of notes){assert.ok(Number.isFinite(n.hz)&&n.hz>0&&n.duration>0&&n.level<=.1);}
        phrases++;
    }
    assert.notEqual(art.phrase('medieval',0)[0].hz,art.phrase('guild',0)[0].hz);
    const timers=new Map();let serial=0;
    const audio=art.create({AudioContext:FakeContext,schedule:(f)=>{timers.set(++serial,f);return serial;},cancel:id=>timers.delete(id)});
    audio.setTheme('medieval');assert.equal(created,0);assert.equal(audio.cue('market.accept'),false);
    assert.ok(await audio.enable());assert.equal(created,1);assert.equal(audio.state().enabled,true);
    assert.ok(started>0);assert.equal(timers.size,1);
    let cues=0;
    for(const key of Object.keys(art.cues)){audio.setTheme('industrial');audio.setTheme('guild');assert.ok(audio.cue(key));cues++;}
    assert.equal(audio.cue('fake-action'),false);
    audio.setVolume('effects',0);assert.equal(audio.cue('market.accept'),false);
    audio.setVolume('music',2);assert.equal(audio.state().volumes.music,1);
    audio.setVolume('ambience',-1);assert.equal(audio.state().volumes.ambience,0);
    audio.setVisible(false);assert.equal(timers.size,0);assert.equal(audio.cue('market.accept'),false);
    audio.setVisible(true);assert.equal(timers.size,1);
    audio.disable();assert.equal(timers.size,0);assert.equal(audio.state().enabled,false);assert.ok(stopped>0);
    audio.reset();assert.equal(audio.state().theme,null);
    const unsupported=art.create({AudioContext:null});assert.equal(await unsupported.enable(),false);
    assert.equal(unsupported.state().error,'unsupported');
    console.log('G20: '+phrases+' phrase steps, '+cues+' cues, opt-in, mixer, visibility, mute, reset and unsupported-browser contracts passed.');
})().catch(e=>{console.error(e);process.exitCode=1;});
