'use strict';
const $=id=>document.getElementById(id);
let token=null, world=null, universe=null, page=1, busy=false;
const apiBase='/api/v1';
const labels={president:'Président','prime-minister':'Premier ministre',economy:'Économie',education:'Éducation',health:'Santé',justice:'Justice',interior:'Intérieur',defence:'Défense',foreign_affairs:'Affaires étrangères'};
const errText={401:'Session expirée ou identifiants incorrects.',403:'Accès refusé.',404:'Fonction ou univers indisponible.',409:'État modifié, action incompatible ou indisponible. Actualisez avant de réessayer.',422:'Paramètres invalides.',429:'Trop de requêtes.',503:'Service indisponible.'};
function el(tag,text){const n=document.createElement(tag);if(text!==undefined)n.textContent=String(text);return n;}
function say(s){$('status').textContent=s;}
function clear(n){n.replaceChildren();}
function line(n,k,v){const p=el('p');p.append(el('strong',k+' : '),el('span',v??'Non disponible'));n.append(p);}
function option(n,v,t){const o=el('option',t);o.value=v;n.append(o);}
function button(n,label,fn){const b=el('button',label);b.type='button';b.addEventListener('click',()=>run(fn));n.append(b);return b;}
function select(n,label,choices){const l=el('label',label);const s=el('select');for(const [v,t] of choices)option(s,v,t);l.append(s);n.append(l);return s;}
function key(){const b=new Uint8Array(16);crypto.getRandomValues(b);return Array.from(b,x=>x.toString(16).padStart(2,'0')).join('');}
async function request(path,method='GET',body,etag){
 const h={Accept:'application/json'};if(token)h.Authorization='Bearer '+token;
 if(body!==undefined){h['Content-Type']='application/json';h['Idempotency-Key']=key();}
 if(etag)h['If-Match']=etag;
 const response=await fetch(apiBase+path,{method,headers:h,body:body===undefined?undefined:JSON.stringify(body),cache:'no-store',credentials:'omit'});
 if(!response.ok){if(response.status===401&&token)reset();throw Error(errText[response.status]||'Erreur HTTP '+response.status);}
 return response.json();
}
function reset(){token=null;world=null;universe=null;page=1;$('game').hidden=true;$('auth').hidden=false;$('dashboard').hidden=true;$('lobby').hidden=false;for(const id of ['career','offices','cabinet','economy','policies','propose','laws','submit-bill','ranking','worlds','countries'])clear($(id));}
async function run(fn){if(busy)return;busy=true;document.querySelectorAll('button').forEach(b=>b.disabled=true);try{await fn();}catch(e){say(e.message||'Erreur de communication.');}finally{busy=false;document.querySelectorAll('button').forEach(b=>b.disabled=false);}}
async function login(register=false){const username=$('username').value,password=$('password').value;if(register)await request('/accounts','POST',{username,password,invite:$('invite').value});const r=await request('/sessions','POST',{username,password});token=r.access_token;$('password').value='';$('invite').value='';$('auth').hidden=true;$('game').hidden=false;await lobby();}
$('auth-form').addEventListener('submit',e=>{e.preventDefault();run(()=>login());});
$('register').addEventListener('click',()=>run(()=>login(true)));
$('logout').addEventListener('click',()=>run(async()=>{try{await request('/session','DELETE');}finally{reset();say('Déconnecté.');}}));
$('lobby-btn').addEventListener('click',()=>run(lobby));
$('refresh').addEventListener('click',()=>run(()=>world?dashboard():lobby()));
$('previous-worlds').addEventListener('click',()=>run(async()=>{page=Math.max(1,page-1);await lobby();}));
$('next-worlds').addEventListener('click',()=>run(async()=>{page++;await lobby();}));
$('identity').addEventListener('change',()=>{$('fiction').hidden=Boolean($('identity').value);});
async function lobby(){world=null;universe=null;$('dashboard').hidden=true;$('lobby').hidden=false;
 const [d,c,o]=await Promise.all([request('/career-worlds/public/'+page),request('/career-worlds/capabilities'),request('/candidate-options')]);
 clear($('worlds'));clear($('world-choice'));option($('world-choice'),'','Choix automatique');
 for(const u of d.universes){const p=el('div');p.className='entry';p.append(el('span','Univers '+u.universe_no+' · '+u.committed_day+' · '+u.member_count+'/'+u.capacity));if(u.is_member)button(p,'Ouvrir',async()=>{world=u.world_id;universe=u.universe_no;await dashboard();});else if(u.joinable)option($('world-choice'),u.world_id,'Univers '+u.universe_no);$('worlds').append(p);}
 if(!d.universes.length)$('worlds').append(el('p','Aucun univers sur cette page.'));
 clear($('identity'));option($('identity'),'','Personnage fictif');for(const p of o.provisional_programme_choices||[])if(p.playable)option($('identity'),p.reference_id||p.id,p.name+' — programme provisoire');
 $('fiction').hidden=false;clear($('objectives'));for(const p of c.fictional_objectives)option($('objectives'),p.id,p.id);
 clear($('countries'));for(const p of c.countries)line($('countries'),p.code,p.public_economic_career?'Carrière économique alpha':p.isolated_institutions?'Institutions isolées':'Référence documentaire');
 say('Univers publics — page '+page+'.');
}
$('join').addEventListener('submit',e=>{e.preventDefault();run(async()=>{
 const body={};if($('world-choice').value)body.world_id=$('world-choice').value;
 if($('identity').value)body.reference_id=$('identity').value;
 else{const ids=Array.from($('objectives').selectedOptions,o=>o.value);if(ids.length>15)throw Error('15 objectifs maximum.');const name=$('fiction-name').value.trim();if(!name)throw Error('Le nom est requis.');body.fictional={name,measure_ids:ids};}
 const r=await request('/career-worlds/join','POST',body);world=r.world_id;universe=r.universe_no;await dashboard();
});});
const prefix=()=>'/career-worlds/'+encodeURIComponent(world);
function officeName(x){return labels[x]||x;}
function cabinetAction(kind,mandate,target=null,portfolio=null,proposal=null){return request(prefix()+'/cabinet','POST',{kind,mandate:mandate.id,generation:mandate.generation,target,portfolio,proposal}).then(dashboard);}
async function dashboard(){const path=prefix();const [c,e,i,ch]=await Promise.all([request(path+'/career'),request(path+'/economy'),request(path+'/institutions'),request(path+'/characters')]);
 if(c.day!==e.day||i.state.day!==c.day||ch.day!==c.day)throw Error('Un nouveau jour a été publié. Actualisez.');
 $('dashboard').hidden=false;$('lobby').hidden=true;$('world-title').textContent='Univers '+universe+' · '+c.day;
 for(const id of ['career','offices','cabinet','economy','policies','propose','laws','submit-bill'])clear($(id));
 line($('career'),'Personnage',c.career?.identity?.name||c.career?.fictional_profile?.name||c.career?.character);
 line($('career'),'Mandats',c.mandates.map(m=>officeName(m.office)).join(', ')||'Aucun mandat');
 line($('career'),'Régime',c.institution.regime);line($('career'),'Gouvernement',c.institution.government);line($('career'),'Autonomie',c.cabinet_autonomy);
 for(const o of c.occupied_offices)line($('offices'),officeName(o.office),o.identity?.name||'Personnage fictif');
 const own=(ability)=>c.mandates.find(m=>m.powers.includes(ability));
 const pres=own('president.request_appointment'),pm=own('government.coordinate');
 const available=(c.appointment_candidates||[]).filter(x=>x.offices.length===0).map(x=>[x.career_id,x.name+' ('+x.controller+')']);
 if(pres&&!c.occupied_offices.some(o=>o.office==='prime-minister')&&available.length){const s=select($('cabinet'),'Nommer un Premier ministre',available);button($('cabinet'),'Nommer',()=>cabinetAction('appoint_pm',pres,s.value));}
 if(pm){if(available.length){const s=select($('cabinet'),'Ministre proposé',available);const p=select($('cabinet'),'Portefeuille',Object.keys(labels).filter(k=>!['president','prime-minister'].includes(k)).map(k=>[k,labels[k]]));button($('cabinet'),'Proposer la nomination',()=>cabinetAction('propose_minister',pm,s.value,p.value));}button($('cabinet'),'Présenter la démission',async()=>{if(confirm('Présenter la démission du gouvernement ?'))await cabinetAction('resign_pm',pm);});}
 if(pres)for(const p of c.appointment_proposals)button($('cabinet'),'Confirmer '+p.portfolio+' — '+((c.appointment_candidates||[]).find(a=>a.career_id===p.target)?.name||p.target),()=>cabinetAction('confirm_minister',pres,null,null,p.id));
 const d=e.domestic.state,last=d.days.at(-1);line($('economy'),'Trésorerie (centimes EUR)',d.balance);if(last?.macro){line($('economy'),'Chômage',last.macro.unemployment_bps===undefined?'Non disponible':(last.macro.unemployment_bps/100).toFixed(2)+' %');line($('economy'),'Production réelle (base 100)',last.macro.real_output_index_bps===undefined?'Non disponible':(last.macro.real_output_index_bps/100).toFixed(2));}
 for(const s of e.score_preview?.series||[]){line($('economy'),'Score provisoire',s.score);line($('economy'),'Jours au pouvoir',s.tenure_days);}
 for(const [id,p] of Object.entries(d.policies))line($('policies'),id,p.authorized_on?'Autorisé le '+p.authorized_on+' · effet '+p.effective_on:'Proposé · effet prévu '+p.effective_on+' · non autorisé');
 const opts=ch.fictional_objective_options.filter(p=>!Object.hasOwn(d.policies,p.id)&&(pm||(p.portfolio&&own('ministry.'+p.portfolio+'.propose'))));
 if(opts.length){const s=select($('propose'),'Mesure',opts.map(x=>[x.id,x.id]));const dt=el('input');dt.type='date';const date=new Date(c.day+'T00:00:00Z');date.setUTCDate(date.getUTCDate()+220);dt.value=date.toISOString().slice(0,10);const l=el('label','Date d’effet proposée');l.append(dt);$('propose').append(l);button($('propose'),'Déposer',async()=>{const p=opts.find(x=>x.id===s.value),m=pm||own('ministry.'+p.portfolio+'.propose');await request(path+'/policy-proposals','POST',{mandate:m.id,generation:m.generation,policy_id:p.id,effective_day:dt.value},e.domestic.etag);await dashboard();});}
 for(const b of Object.values(i.state.legislation?.bills||{}))line($('laws'),b.policy_id,b.status+' · '+(b.effect_on||'Date non fixée'));
 for(const b of Object.values(i.state.bills||{}))line($('laws'),b.title||b.id,b.status);
 const mp=pm||own('assembly.propose')||own('senate.propose');if(mp){const input=el('input');input.maxLength=120;input.placeholder='Titre du texte';$('submit-bill').append(input);button($('submit-bill'),'Soumettre un texte',async()=>{await request(path+'/bills','POST',{mandate:mp.id,generation:mp.generation,title:input.value},i.etag);await dashboard();});}
 await cohorts();say('État publié au '+c.day+'.');
}
async function cohorts(){const scope=$('rank-scope').value==='global'?'global':String(universe);const r=await request('/career-worlds/rankings/'+scope+'/cohorts');clear($('rank-cohort'));for(const c of r.cohorts)option($('rank-cohort'),c.cohort_sha,c.admission_mode+' · '+c.cohort_sha.slice(0,8));await ranking();}
async function ranking(){clear($('ranking'));const id=$('rank-cohort').value;if(!id){$('ranking').append(el('p','Aucune cohorte.'));return;}const scope=$('rank-scope').value==='global'?'global':String(universe);const r=await request('/career-worlds/rankings/'+scope+'/'+id);if(!r.rows.length)$('ranking').append(el('p','Aucun score qualifié (365 jours effectifs minimum).'));for(const row of r.rows)line($('ranking'),row.rank+'. '+(row.is_you?'Vous':row.player),row.score+' points · '+row.tenure_days+' jours');}
$('rank-scope').addEventListener('change',()=>run(cohorts));$('rank-cohort').addEventListener('change',()=>run(ranking));$('rank-refresh').addEventListener('click',()=>run(cohorts));
