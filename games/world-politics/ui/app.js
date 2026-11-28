'use strict';
const $=id=>document.getElementById(id);
let token=null, world=null, universe=null, page=1, busy=false, internationalMode=false, internationalNo=null, internationalPage=1;
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
function reset(){token=null;world=null;universe=null;page=1;internationalMode=false;internationalNo=null;internationalPage=1;$('international-lobby').hidden=true;$('international-dashboard').hidden=true;$('game').hidden=true;$('auth').hidden=false;$('dashboard').hidden=true;$('lobby').hidden=false;for(const id of ['career','offices','cabinet','economy','policies','propose','laws','submit-bill','ranking','worlds','countries'])clear($(id));}
async function run(fn){if(busy)return;busy=true;document.querySelectorAll('button').forEach(b=>b.disabled=true);try{await fn();}catch(e){say(e.message||'Erreur de communication.');}finally{busy=false;document.querySelectorAll('button').forEach(b=>b.disabled=false);}}
async function login(register=false){const username=$('username').value,password=$('password').value;if(register)await request('/accounts','POST',{username,password,invite:$('invite').value});const r=await request('/sessions','POST',{username,password});token=r.access_token;$('password').value='';$('invite').value='';$('auth').hidden=true;$('game').hidden=false;await lobby();}
$('auth-form').addEventListener('submit',e=>{e.preventDefault();run(()=>login());});
$('register').addEventListener('click',()=>run(()=>login(true)));
$('logout').addEventListener('click',()=>run(async()=>{try{await request('/session','DELETE');}finally{reset();say('Déconnecté.');}}));
$('lobby-btn').addEventListener('click',()=>run(lobby));
$('refresh').addEventListener('click',()=>run(()=>internationalMode?(internationalNo?internationalDashboard():internationalLobby()):(world?dashboard():lobby())));
$('previous-worlds').addEventListener('click',()=>run(async()=>{page=Math.max(1,page-1);await lobby();}));
$('next-worlds').addEventListener('click',()=>run(async()=>{page++;await lobby();}));
$('identity').addEventListener('change',()=>{$('fiction').hidden=Boolean($('identity').value);});
async function lobby(){internationalMode=false;internationalNo=null;$('international-lobby').hidden=true;$('international-dashboard').hidden=true;world=null;universe=null;$('dashboard').hidden=true;$('lobby').hidden=false;
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
 const activity=c.campaign_activity;
 if(activity){
  const names={programme_review:'Travail sur le programme',public_meeting:'Réunion publique simulée',debate:'Débat politique simulé',constituency_work:'Travail de terrain simulé'};
  line($('career'),'Influence électorale relative simulée',(activity.support_modifier_bps/100).toFixed(2)+' %');
  line($('career'),'Règle','Une activité tous les '+activity.cooldown_days+' jours ; effet dégressif sur '+activity.decay_days+' jours');
  for(const [at,a] of Object.entries(activity.recent||{}).reverse())line($('career'),at+' · '+(names[a.kind]||a.kind),(a.points_bps/100).toFixed(2)+' % avant décroissance');
  if(activity.can_act){
   const kind=select($('career'),'Activité politique',activity.activity_types.map(k=>[k,names[k]||k]));
   button($('career'),'Effectuer cette activité',async()=>{await request(path+'/campaign-activity','POST',{kind:kind.value});await dashboard();});
  }else line($('career'),'Prochaine activité disponible',activity.next_available_day);
 }
 const election=c.assembly_election;
 if(election){
  line($('career'),'Prochain scrutin législatif simulé',election.election_day);line($('career'),'Clôture des candidatures',election.candidacy_deadline);
  if(election.own_candidacy)line($('career'),'Votre circonscription fictive',election.own_candidacy.seat);
  if(election.can_declare){
   const input=el('input');input.type='number';input.min='1';input.max='577';input.step='1';input.value=election.own_candidacy?.seat||1;
   const l=el('label','Circonscription simulée (1 à 577)');l.append(input);$('career').append(l);
   button($('career'),'Déclarer ou modifier ma candidature',async()=>{const seat=Number(input.value);if(!Number.isInteger(seat)||seat<1||seat>577)throw Error('Circonscription invalide.');await request(path+'/assembly-candidacy','POST',{seat,election_day:election.election_day});await dashboard();});
  }
 }
 const senate=c.senate_election;
 if(senate){
  line($('career'),'Renouvellement sénatorial simulé',senate.election_day+' · série '+senate.series);
  line($('career'),'Clôture des candidatures sénatoriales',senate.candidacy_deadline);
  if(senate.own_candidacy)line($('career'),'Votre siège sénatorial fictif',senate.own_candidacy.seat);
  if(senate.can_declare){
   const input=el('input');input.type='number';input.min=String(senate.min);input.max=String(senate.max);input.step='1';input.value=senate.own_candidacy?.seat||senate.min;
   const l=el('label','Siège fictif de la série ('+senate.min+' à '+senate.max+')');l.append(input);$('career').append(l);
   button($('career'),'Déclarer ma candidature au Sénat',async()=>{const seat=Number(input.value);if(!Number.isInteger(seat)||seat<senate.min||seat>senate.max)throw Error('Siège hors série.');await request(path+'/senate-candidacy','POST',{seat,election_day:senate.election_day});await dashboard();});
  }
 }
 if(c.parliament_resignation_enabled)for(const m of c.mandates.filter(x=>x.powers.includes('assembly.vote')||x.powers.includes('senate.vote')))
  button($('career'),'Démissionner : '+officeName(m.office),async()=>{if(confirm('Abandonner définitivement ce mandat ?')){await request(path+'/parliament-resignation','POST',{mandate:m.id,generation:m.generation});await dashboard();}});
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
 for(const b of Object.values(i.state.legislation?.bills||{})){
  line($('laws'),b.policy_id,b.status+' · '+(b.effect_on||'Date non fixée'));
  const ch=b.next_chamber,m=ch==='assembly'?own('assembly.vote'):ch==='senate'?own('senate.vote'):null;
  if(i.state.legislation.career_ordinary==='career-ordinary-v2'&&m&&b.status==='navette'&&b.readings[ch]===0&&(ch!=='senate'||b.last_votes.assembly?.vote?.passed)){
   const prior=(c.own_ordinary_votes||[]).find(v=>v.bill===b.id&&v.mandate===m.id);
   if(prior)line($('laws'),'Votre vote',prior.choice);
   else{const choice=select($('laws'),'Vote '+(ch==='assembly'?'Assemblée':'Sénat'),[['yes','Pour'],['no','Contre'],['abstain','Abstention']]);button($('laws'),'Enregistrer mon vote',async()=>{if(!confirm('Confirmer ce vote définitif ?'))return;await request(path+'/ordinary-votes','POST',{bill:b.id,chamber:ch,text_sha:b.texts.standard,mandate:m.id,generation:m.generation,choice:choice.value});await dashboard();});}
  }
 }
 for(const b of Object.values(i.state.bills||{}))line($('laws'),b.title||b.id,b.status);
 const mp=pm||own('assembly.propose')||own('senate.propose');if(mp){const input=el('input');input.maxLength=120;input.placeholder='Titre du texte';$('submit-bill').append(input);button($('submit-bill'),'Soumettre un texte',async()=>{await request(path+'/bills','POST',{mandate:mp.id,generation:mp.generation,title:input.value},i.etag);await dashboard();});}
 await cohorts();say('État publié au '+c.day+'.');
}
async function cohorts(){const scope=$('rank-scope').value==='global'?'global':String(universe);const r=await request('/career-worlds/rankings/'+scope+'/cohorts');clear($('rank-cohort'));for(const c of r.cohorts)option($('rank-cohort'),c.cohort_sha,c.admission_mode+' · '+c.cohort_sha.slice(0,8));await ranking();}
async function ranking(){clear($('ranking'));const id=$('rank-cohort').value;if(!id){$('ranking').append(el('p','Aucune cohorte.'));return;}const scope=$('rank-scope').value==='global'?'global':String(universe);const r=await request('/career-worlds/rankings/'+scope+'/'+id);if(!r.rows.length)$('ranking').append(el('p','Aucun score qualifié (365 jours effectifs minimum).'));for(const row of r.rows)line($('ranking'),row.rank+'. '+(row.is_you?'Vous':row.player),row.score+' points · '+row.tenure_days+' jours');}
$('rank-scope').addEventListener('change',()=>run(cohorts));$('rank-cohort').addEventListener('change',()=>run(ranking));$('rank-refresh').addEventListener('click',()=>run(cohorts));

const countryNames=new Intl.DisplayNames(['fr'],{type:'region'});
const countryLabel=code=>countryNames.of(code)||code;
const fmt=new Intl.NumberFormat('fr-FR');
function observedLabel(v){return !v||v.value===null?'Non disponible':fmt.format(v.value)+' '+v.unit+' ('+v.year+', '+(v.kind||v.classification)+')';}
$('international-btn').addEventListener('click',()=>run(internationalLobby));
$('international-prev').addEventListener('click',()=>run(async()=>{internationalPage=Math.max(1,internationalPage-1);await internationalLobby();}));
$('international-next').addEventListener('click',()=>run(async()=>{internationalPage++;await internationalLobby();}));
let internationalDirectory=null;
function internationalChoices(){
 const no=$('international-choice').value;const row=internationalDirectory.universes.find(u=>String(u.universe_no)===no);
 clear($('international-country'));
 const slots=row?row.slots.filter(s=>s.controller==='ai'):internationalDirectory.playable_country_pilot.map(country=>({country}));
 for(const s of slots)option($('international-country'),s.country,countryLabel(s.country));
}
$('international-choice').addEventListener('change',internationalChoices);
async function internationalLobby(){
 internationalMode=true;internationalNo=null;world=null;universe=null;$('lobby').hidden=true;$('dashboard').hidden=true;$('international-dashboard').hidden=true;$('international-lobby').hidden=false;
 const data=await request('/universes/page/'+internationalPage);internationalDirectory=data;
 clear($('international-worlds'));clear($('international-choice'));option($('international-choice'),'','Sélection automatique');
 for(const u of data.universes){
  const item=el('div');item.className='entry';item.append(el('span','Univers '+u.universe_no+' · '+u.committed_day+' · '+u.vacant+'/'+u.slots.length+' États disponibles'));
  const own=u.slots.find(s=>s.is_you);
  if(own)button(item,'Reprendre '+countryLabel(own.country),async()=>{internationalNo=u.universe_no;await internationalDashboard();});
  else if(u.joinable)option($('international-choice'),String(u.universe_no),'Univers '+u.universe_no);
  $('international-worlds').append(item);
 }
 internationalChoices();say('Univers internationaux · page '+internationalPage);
}
$('international-join').addEventListener('submit',e=>{e.preventDefault();run(async()=>{
 const country=$('international-country').value,no=$('international-choice').value;
 if(!country)throw Error('Aucun État disponible.');
 const joined=await request('/universes/join','POST',{country,...(no?{universe_no:Number(no)}:{})});
 internationalNo=joined.universe_no;await internationalDashboard();
});});
async function internationalDashboard(){
 if(!internationalNo)throw Error('Univers non sélectionné.');
 const d=await request('/universes/'+internationalNo+'/international');
 const root='/worlds/'+encodeURIComponent(d.world_id);
 $('international-lobby').hidden=true;$('international-dashboard').hidden=false;
 $('international-title').textContent='Univers '+d.universe_no+' · '+countryLabel(d.country)+' · '+d.day;
 for(const id of ['international-own','international-actions','international-diplomacy','international-incoming','international-history','international-observations','international-trade','international-roster','international-ranking'])clear($(id));
 line($('international-own'),'État contrôlé',countryLabel(d.country));line($('international-own'),'Génération de contrôle',d.generation);
 const mode=select($('international-actions'),'Orientation diplomatique automatique',[['cooperative','Coopérative'],['balanced','Équilibrée'],['cautious','Prudente']]);mode.value=d.diplomacy_programme;
 if(!d.game_over)button($('international-actions'),'Enregistrer',async()=>{await request('/universes/'+d.universe_no+'/programme','POST',{diplomacy:mode.value});await internationalDashboard();});
 button($('international-actions'),'Quitter le contrôle de cet État',async()=>{if(confirm('Rendre cet État à la simulation automatique ?')){await request('/universes/'+d.universe_no+'/membership','DELETE');await internationalLobby();}});
 const actorLabels=new Map([[d.actor_id,countryLabel(d.country)],...d.actors.map(a=>[a.id,a.label])]);
 const actions={dialogue:'Dialogue',humanitarian_assistance:'Assistance humanitaire',trade_cooperation:'Coopération commerciale',treaty_proposal:'Proposition de traité',express_support:'Déclaration de soutien',express_opposition:'Déclaration d’opposition',sanctions_proposal:'Proposition de sanctions (sans effet automatique)'};
 if(!d.game_over){
  const target=select($('international-diplomacy'),'Interlocuteur',d.actors.map(a=>[a.id,a.label]));
  const action=select($('international-diplomacy'),'Action',Object.entries(actions));
  button($('international-diplomacy'),'Envoyer',async()=>{await request(root+'/diplomacy/proposals','POST',{from:d.actor_id,to:target.value,action:action.value},d.diplomacy_etag);await internationalDashboard();});
 }
 for(const p of d.incoming){
  const item=el('div');line(item,actorLabels.get(p.from)||p.from,actions[p.action]||p.action);line(item,'Échéance',p.response_deadline);
  if(!d.game_over)for(const [accept,label] of [[true,'Accepter en principe'],[false,'Refuser']])button(item,label,async()=>{await request(root+'/diplomacy/responses','POST',{responder:d.actor_id,proposal_id:p.id,accepted:accept},d.diplomacy_etag);await internationalDashboard();});
  $('international-incoming').append(item);
 }
 if(!d.incoming.length)line($('international-incoming'),'Propositions reçues','Aucune en attente');
 for(const p of d.recent_offers)line($('international-history'),(actorLabels.get(p.from)||p.from)+' → '+(actorLabels.get(p.to)||p.to),(actions[p.action]||p.action)+' · '+p.status);
 const own=d.countries.find(c=>c.is_you);
 if(own){
  for(const [k,label] of [['gdp','PIB de référence'],['population','Population de référence'],['budget','Budget de référence'],['gdp_per_capita','PIB par habitant historique']])line($('international-observations'),label,observedLabel(own.observations[k]));
  line($('international-observations'),'Institutions nationales',own.national_institution_clock?'Horloge institutionnelle active':'Module national non disponible');
  line($('international-observations'),'Économie domestique',own.domestic_policy?'Moteur français':(d.national_fiscal_ledger?'Budget normalisé expérimental':'Projection partielle, sans budget dynamique'));
  const profile=own.national_reference,sc=own.consumption_scenario;
  if(profile){
   line($('international-observations'),'Renouvellement ordinaire (mois de scénario)',profile.next_ordinary_chamber_renewal_month);
   line($('international-observations'),'Mode de gouvernement de référence',profile.government_mode);
   const keys={household_npish_consumption_per_capita_real:'Consommation réelle par habitant',gdp_per_capita_ppp_real:'PIB réel par habitant en PPA',central_government_debt:'Dette de l’administration centrale (% PIB)',revenue_excluding_grants_central_government:'Recettes centrales hors dons',expense_central_government:'Dépenses centrales',tax_revenue_central_government:'Recettes fiscales centrales',youth_unemployment_modelled:'Chômage des jeunes',gini_index:'Indice de Gini',total_natural_resource_rents:'Rentes des ressources naturelles',government_education_expenditure_share:'Dépenses publiques d’éducation',physicians_density:'Médecins pour 1 000 habitants',hospital_beds_density:'Lits hospitaliers pour 1 000 habitants'};
   for(const [id,label] of Object.entries(keys))line($('international-observations'),label,observedLabel(profile.observations[id]));
  }
  const ledger=d.national_fiscal_ledger;
  if(ledger){
   const pct=v=>(100*v/ledger.reference_gdp_units).toFixed(4)+' % du PIB de référence';
   line($('international-observations'),'Trésorerie simulée',pct(ledger.state.stocks.cash));
   line($('international-observations'),'Dette brute simulée',pct(ledger.state.stocks.gross_debt));
   line($('international-observations'),'Facilité de financement restante',pct(ledger.state.credit_remaining));
   line($('international-observations'),'Arriérés simulés',pct(Object.values(ledger.state.arrears).reduce((a,b)=>a+b,0)));
   const ratios=ledger.current_fiscal_ratios;
   if(ratios){const currentPct=v=>(v/100).toFixed(2)+' % du PIB nominal simulé';
    line($('international-observations'),'Ratio de dette brute courant',currentPct(ratios.gross_debt_bps));
    line($('international-observations'),'Ratio de trésorerie courant',currentPct(ratios.cash_bps));
    line($('international-observations'),'Arriérés primaires',currentPct(ratios.primary_arrears_bps));
    line($('international-observations'),'Arriérés d’intérêts',currentPct(ratios.interest_arrears_bps));
    line($('international-observations'),'Principal en retard (déjà inclus dans la dette)',currentPct(ratios.principal_arrears_included_in_debt_bps));
    line($('international-observations'),'Dénominateur courant','PIB réel × indice des prix à la consommation ; proxy nominal expérimental, hors classement.');
   }
   if(ledger.version==='wp130-normalized-fiscal-world-v3'){
    const panel=el('section');panel.append(el('h3','Étude budgétaire sur un an'));
    panel.append(el('p','Variation relative des enveloppes annuelles ; aucune mesure adoptée. Coefficients non calibrés.'));
    const choices=[[-1000,'−10 %'],[-500,'−5 %'],[0,'Sans changement'],[500,'+5 %'],[1000,'+10 %']];
    const receipts=select(panel,'Recettes publiques',choices);receipts.value='0';
    const spending=select(panel,'Dépenses primaires',choices);spending.value='0';
    const result=el('div');result.setAttribute('aria-live','polite');
    button(panel,'Comparer au budget inchangé',async()=>{
     const p=await request('/universes/'+internationalNo+'/budget-preview','POST',{receipts_change_bps:Number(receipts.value),primary_spending_change_bps:Number(spending.value)});
     clear(result);line(result,'Période',p.opening_day+' → '+p.end_day);
     for(const [k,label] of [['gross_debt','Dette brute'],['cash','Trésorerie'],['primary_arrears','Arriérés primaires']])line(result,label,pct(p.baseline.end[k])+' → '+pct(p.alternative.end[k]));
     for(const [k,label] of [['real_output_index','PIB réel'],['price_index','Prix'],['purchasing_power_index','Pouvoir d’achat']]){
      const ix=v=>v===null?'Non estimé':(v/10000000).toFixed(3);
      line(result,label+' (base 100)',ix(p.baseline.end[k])+' → '+ix(p.alternative.end[k]));
     }
     line(result,'Effet','Aucune modification du budget ou du classement. Recettes non ventilées ; pas d’effet fiscal direct estimé sur les ménages.');
    });
    panel.append(result);$('international-observations').append(panel);
   }
   const opening=ledger.opening_forecast;
   if(opening){
    line($('international-observations'),'Dette projetée au 14 mai 2027',pct(opening.opening_gross_debt));
    line($('international-observations'),'Prévisions macroéconomiques',opening.macro_source.source_name+' — '+opening.macro_source.published_on);
    line($('international-observations'),'Prévisions budgétaires',opening.fiscal_source.source_name+' — '+opening.fiscal_source.published_on);
   }
   if(ledger.macro){const m=ledger.macro,ix=v=>(v/10000000).toFixed(3);
    line($('international-observations'),'PIB réel simulé (base 100)',ix(m.real_output_index));
    line($('international-observations'),'Prix simulés (base 100)',ix(m.price_index));
    line($('international-observations'),'Salaire réel indicatif (base 100, non RDB)',ix(m.indicative_real_wage_index));
    line($('international-observations'),'Chômage simulé',m.unemployment_scaled===null?'Non estimé':(m.unemployment_scaled/1000000).toFixed(2)+' %');
   }
   if(ledger.household){const h=ledger.household;
    line($('international-observations'),'Pouvoir d’achat expérimental (base 100)',h.available?(h.purchasing_power_index/10000000).toFixed(3):'Non estimé');
    line($('international-observations'),'Revenus des ménages','Compte normalisé synthétique, parts de revenu et fiscalité supposées ; hors classement.');
   }
   if(ledger.macro?.trade){const t=ledger.macro.trade;
    line($('international-observations'),'Exposition commerciale simulée',t.known_exposure_bps===null?'Inconnue':(t.known_exposure_bps/100).toFixed(2)+' % du PIB historique'+(t.coverage_complete?'':' (couverture partielle)'));
   }
   line($('international-observations'),'Périmètre','Administrations publiques ; scénario normalisé, non monétaire, sans échéancier de principal connu. Coefficients non calibrés.');
  }
  const fiscal=own.fiscal_scenario;
  if(fiscal){
   const share=(v,year)=>v===null?'Données insuffisantes':(v/100).toFixed(2)+' % du PIB de référence ('+year+')';
   line($('international-observations'),'Écart recettes hors dons − charges centrales (hors solde complet)',share(fiscal.revenue_minus_expense_excluding_grants_bps_gdp,fiscal.gap_reference_year));
   line($('international-observations'),'Manque à gagner fiscal hypothétique',share(fiscal.trade_tax_shortfall_bps_reference_gdp,fiscal.tax_reference_year));
   if(fiscal.trade_tax_shortfall_usd_reference_year!==null)line($('international-observations'),'Équivalent fiscal hypothétique',fmt.format(fiscal.trade_tax_shortfall_usd_reference_year)+' USD de référence ('+fiscal.tax_reference_year+')');
   line($('international-observations'),'Hypothèse fiscale','Sensibilité unitaire fictive à la pression commerciale ; aucun budget ni score modifié.');
  }
  if(sc){
   line($('international-observations'),'Indice de consommation simulé (base 100)',sc.scenario_index_bps===null?'Non calculable':(sc.scenario_index_bps/100).toFixed(2));
   line($('international-observations'),'Tendance historique bornée',sc.historical_annual_trend_bps===null?'Non calculable':(sc.historical_annual_trend_bps/100).toFixed(2)+' %/an');
   line($('international-observations'),'Pression commerciale simulée',sc.trade_stress_bps===null?'Données insuffisantes':(sc.trade_stress_bps/100).toFixed(2)+' points d’indice');
   line($('international-observations'),'Périmètre','Projection expérimentale, ni revenu réel ni score.');
  }
 }
 for(const p of d.trade_effects)line($('international-trade'),(actorLabels.get(p.from)||p.from)+' → '+(actorLabels.get(p.to)||p.to),p.central_usd===null?'Projection non chiffrable (référence absente)':fmt.format(p.central_usd)+' USD · réduction annuelle hypothétique');
 if(!d.trade_effect_count)line($('international-trade'),'Effets commerciaux','Aucune projection effective concernant cet État');
 for(const c of d.countries)line($('international-roster'),countryLabel(c.country),(c.is_you?'Vous':c.controller)+' · '+(c.national_institution_clock?'Institutions suivies':'Diplomatie seulement'));
 try{const ranking=await request('/universes/'+d.universe_no+'/rankings');for(const p of ranking.rows)line($('international-ranking'),p.rank+' · '+(p.is_you?'Vous':p.player)+' · '+countryLabel(p.country),p.control_days+' jours');}catch(e){line($('international-ranking'),'Classement',e.message);}
 say('Publication internationale au '+d.day+'.');
}
