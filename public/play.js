'use strict';
let token = null, busy = false, pending = null, worlds = [];
const login = document.querySelector('#login'), game = document.querySelector('#game'), balance = document.querySelector('#balance'), action = document.querySelector('#action'), status = document.querySelector('#status'), stock = document.querySelector('#stock'), marketClaim=document.querySelector('#market-claim');
async function api(path, method='GET', data) {
    const headers = {'Accept':'application/json'};
    if (token) headers.Authorization = 'Bearer '+token;
    if (data !== undefined) headers['Content-Type'] = 'application/json';
    const response = await fetch(path,{method,headers,body:data===undefined?undefined:JSON.stringify(data),cache:'no-store',redirect:'error',credentials:'omit'});
    const result = await response.json();
    if (!response.ok) { const error=new Error(response.status===503 && result.error==='registration_busy' ? 'Registration server is busy. Please retry shortly.' : response.status===503 && result.error==='authentication_busy' ? 'Authentication server is busy. Please retry shortly.' : (result.error || 'Request failed')); error.status=response.status; throw error; }
    return result.data;
}
function locationFields() {
    return Object.fromEntries(['world','account','resource'].map(k=>[k,balance.elements[k].value]));
}
function base(f) { return '/v1/worlds/'+encodeURIComponent(f.world).replaceAll('%3A',':')+'/economy/'; }
function options(select, values) {
    select.replaceChildren();
    for (const [value,label] of values) {
        const option=document.createElement('option');
        option.value=value; option.textContent=label; select.append(option);
    }
}
const mapList=document.querySelector('#map-territories'), mapLinks=document.querySelector('#map-links'),
      mapNext=document.querySelector('#map-next'), mapNotice=document.querySelector('#map-notice');
let mapAfter=null;
let currentBuilding=null;
function clearMap() {
    mapList.replaceChildren(); mapLinks.replaceChildren();
    mapAfter=null; mapNext.hidden=true; mapNotice.textContent='';
    document.querySelector('#map-location').value='';
    document.querySelector('#map-destination').value='';
    document.querySelector('#map-route').textContent='';
    document.querySelector('#building-id').value='';
    document.querySelector('#building-details').replaceChildren();
    options(document.querySelector('#foundation-site'),[]);
    options(document.querySelector('#foundation-type'),[]);
    options(document.querySelector('#owned-building-list'),[]);
    document.querySelector('#foundation-id').value='';
    document.querySelector('#foundation-notice').textContent='';
    ownSiteOptions=[];
    currentBuilding=null;
    document.querySelector('#building-start').hidden=true;
    document.querySelector('#building-cancel').hidden=true;
    document.querySelector('#building-claim').hidden=true;
}
async function loadMap(after=null) {
    const world=balance.elements.world.value;
    if (!world) throw new Error('Choose a world first');
    const root='/v1/worlds/'+encodeURIComponent(world).replaceAll('%3A',':')+'/map';
    const data=await api(root+'/territories'+(after===null?'':'/'+encodeURIComponent(after)));
    if (world!==balance.elements.world.value) return;
    mapList.replaceChildren();
    for (const territory of data.territories) {
        const item=document.createElement('li');
        item.textContent=territory.id+' — '+territory.location+' — '+territory.kind+' — '+territory.control;
        mapList.append(item);
    }
    mapAfter=data.nextAfter;
    mapNext.hidden=mapAfter===null;
    mapNotice.textContent=data.territories.length?'Territories in this world.':'No territories on this page.';
}
async function loadMapLinks() {
    const world=balance.elements.world.value, location=document.querySelector('#map-location').value.trim();
    if (!world || !/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/.test(location)) throw new Error('Enter a valid map location');
    const root='/v1/worlds/'+encodeURIComponent(world).replaceAll('%3A',':')+'/map';
    const data=await api(root+'/locations/'+encodeURIComponent(location)+'/links');
    if (world!==balance.elements.world.value) return;
    mapLinks.replaceChildren();
    for(const link of data.neighbors) {
        const item=document.createElement('li');
        item.textContent=location+' → '+link.location+' (cost '+link.cost+')';
        mapLinks.append(item);
    }
    if (!data.neighbors.length) {
        const item=document.createElement('li');
        item.textContent='No outgoing routes'; mapLinks.append(item);
    }
}
document.querySelector('#map-load').addEventListener('click',()=>run(()=>loadMap()));
mapNext.addEventListener('click',()=>{if(mapAfter!==null)run(()=>loadMap(mapAfter));});
async function loadShortestRoute() {
    const world=balance.elements.world.value,
          start=document.querySelector('#map-location').value.trim(),
          destination=document.querySelector('#map-destination').value.trim();
    if (!world || ![start,destination].every(id=>/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/.test(id))) {
        throw new Error('Enter valid source and destination locations');
    }
    const root='/v1/worlds/'+encodeURIComponent(world).replaceAll('%3A',':')+'/map';
    const data=await api(root+'/routes/'+encodeURIComponent(start)+'/'+encodeURIComponent(destination));
    if (world!==balance.elements.world.value) return;
    document.querySelector('#map-route').textContent=data.route===null?
        'No directed route found.':data.route.locations.join(' → ')+' (cost '+data.route.cost+')';
}
document.querySelector('#map-route-load').addEventListener('click',()=>run(loadShortestRoute));
let ownSiteOptions=[];
async function loadConstructionChoices() {
    const world=balance.elements.world.value;
    if (!world) throw new Error('Choose a world');
    const root='/v1/worlds/'+encodeURIComponent(world).replaceAll('%3A',':');
    const choices=await api(root+'/construction/options');
    if (world!==balance.elements.world.value) return;
    ownSiteOptions=choices.sites;
    options(document.querySelector('#foundation-site'),
        choices.sites.map(site=>[site.territory_id+'|'+site.entity_id,
            site.territory_id+' — '+site.entity_id]));
    options(document.querySelector('#foundation-type'),
        choices.types.map(type=>[type.id,type.id+' — '+Object.entries(type.first.costs)
            .map(([resource,units])=>units+' '+resource).join(', ')]));
    options(document.querySelector('#owned-building-list'),
        [['','Choose a building'],...choices.buildings.map(item=>[item.id,item.type+' — '+item.id])]);
    const note=document.querySelector('#foundation-notice');
    note.textContent=choices.truncated?'Only the first 100 owned sites or buildings are shown.':
        choices.sites.length===0?'No owned matching territory and entity.':
        choices.types.length===0?'No operator-approved building types in this world.':
        'Choose a site and building type; placement is free, construction stages consume resources.';
}
document.querySelector('#foundation-discover').addEventListener('click',()=>run(loadConstructionChoices));
document.querySelector('#owned-building-list').addEventListener('change',()=>{
    const id=document.querySelector('#owned-building-list').value;
    if (id) {
        document.querySelector('#building-id').value=id;
        document.querySelector('#building-details').replaceChildren();
        currentBuilding=null;
        document.querySelector('#building-start').hidden=true;
        document.querySelector('#building-cancel').hidden=true;
        document.querySelector('#building-claim').hidden=true;
    }
});
document.querySelector('#foundation-place').addEventListener('click',()=>run(async()=>{
    const f=locationFields();
    const siteChoice=document.querySelector('#foundation-site').value,
          type=document.querySelector('#foundation-type').value;
    const site=ownSiteOptions.find(s=>s.territory_id+'|'+s.entity_id===siteChoice);
    if (!f.world || !f.account || !site || !type) throw new Error('Choose an owned site and approved building type');
    const idInput=document.querySelector('#foundation-id');
    if (idInput.value.trim()==='') idInput.value=crypto.randomUUID();
    const id=idInput.value.trim();
    if (!/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/.test(id)) throw new Error('Invalid building identifier');
    await submitCommand(f,'building.place',{
        building:id,account:f.account,territory:site.territory_id,entity:site.entity_id,type
    });
    document.querySelector('#building-id').value=id;
    await loadConstructionChoices();
    await loadBuilding();
}));
async function loadBuilding() {
    const world=balance.elements.world.value,id=document.querySelector('#building-id').value.trim();
    const detail=document.querySelector('#building-details');
    detail.replaceChildren();
    currentBuilding=null;
    document.querySelector('#building-start').hidden=true;
    document.querySelector('#building-cancel').hidden=true;
    document.querySelector('#building-claim').hidden=true;
    if (!world || !/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/.test(id)) throw new Error('Choose an owned building ID');
    const root='/v1/worlds/'+encodeURIComponent(world).replaceAll('%3A',':');
    const building=await api(root+'/buildings/'+encodeURIComponent(id));
    if (world!==balance.elements.world.value) return;
    currentBuilding=building;
    document.querySelector('#building-start').hidden=building.next===null || building.pending!==null;
    document.querySelector('#building-cancel').hidden=building.pending===null;
    document.querySelector('#building-claim').hidden=Object.keys(building.refundPending??{}).length===0;
    const append=(key,value)=>{
        const dt=document.createElement('dt'),dd=document.createElement('dd');
        dt.textContent=key;dd.textContent=String(value);detail.append(dt,dd);
    };
    append('Building',building.id);
    append('Type',building.type);
    append('Territory',building.territory);
    append('Level',building.level);
    append('Status',building.pending===null?'Ready':'Construction pending');
    const outstanding=Object.entries(building.refundPending??{});
    if (outstanding.length>0) {
        append('Pending refunds',outstanding.map(([resource,units])=>units+' '+resource).join(', '));
    }
    if (building.next) {
        append('Next stage duration',building.next.duration);
        append('Next stage costs',Object.entries(building.next.costs).map(([key,qty])=>qty+' '+key).join(', '));
        append('Prerequisites',Object.entries(building.next.requires).map(([key,lvl])=>key+' level '+lvl).join(', ')||'None');
    } else append('Next stage','Complete');
}
document.querySelector('#building-load').addEventListener('click',()=>run(loadBuilding));
document.querySelector('#building-claim').addEventListener('click',()=>run(async()=>{
    const f=locationFields(),building=currentBuilding;
    const claims=building?Object.entries(building.refundPending??{}):[];
    if (!building || !f.world || !f.account || f.account!==building.account || claims.length===0) {
        throw new Error('Select a building belonging to the refund account');
    }
    await submitCommand(f,'building.claim',{account:f.account,resource:claims[0][0]});
    await loadBuilding();
    await refresh();
}));
document.querySelector('#building-cancel').addEventListener('click',()=>run(async()=>{
    const f=locationFields(),building=currentBuilding;
    if (!building || !f.world || !f.account || building.account!==f.account
        || building.pending===null || document.querySelector('#building-id').value.trim()!==building.id) {
        throw new Error('Choose a pending building and its funding account');
    }
    await submitCommand(f,'building.cancel',{
        building:building.id,account:f.account,type:building.type
    });
    await loadBuilding();
    await refresh();
}));
document.querySelector('#building-start').addEventListener('click',()=>run(async()=>{
    const f=locationFields(),building=currentBuilding;
    if (!building || !f.world || !f.account || building.account!==f.account
        || building.pending!==null || building.next===null
        || document.querySelector('#building-id').value.trim()!==building.id) {
        throw new Error('Choose an eligible building and its funding account');
    }
    await submitCommand(f,'building.start',{
        building:building.id,account:f.account,type:building.type
    });
    await loadBuilding();
    await refresh();
}));


document.querySelector('#map-links-load').addEventListener('click',()=>run(loadMapLinks));
const medievalPanel=document.querySelector('#medieval-panel');
const medievalDetails=document.querySelector('#medieval-details');
const medievalGoals=document.querySelector('#medieval-goals');
const medievalVictory=document.querySelector('#medieval-victory');
function resetMedieval() {
    medievalDetails.replaceChildren();medievalGoals.replaceChildren();
    medievalVictory.textContent='';
}
async function reviewMedieval() {
    const fields=locationFields();
    if (!fields.world || !fields.account) throw new Error('Choose a world and account');
    const root='/v1/worlds/'+encodeURIComponent(fields.world).replaceAll('%3A',':');
    const data=await api(root+'/settlement/status/'+encodeURIComponent(fields.account));
    if (JSON.stringify(fields)!==JSON.stringify(locationFields())) return;
    resetMedieval();
    const line=(name,value)=>{
        const dt=document.createElement('dt'),dd=document.createElement('dd');
        dt.textContent=name;dd.textContent=String(value);medievalDetails.append(dt,dd);
    };
    line('Residents',data.population+' / '+data.capacity);
    line('Available workers',data.workers);
    line('Grain per new resident',data.foodCost);
    line('Grain per lumber shift',data.workFoodCost);
    line('Wood per lumber shift',data.workYield);
    if(data.nextWorkAt!==null)line('Next shift (UTC)',new Date(data.nextWorkAt*1000).toISOString());
    for(const [type,levels] of Object.entries(data.victory.buildings)) {
        const item=document.createElement('li');
        item.textContent=type+': '+levels.current+' / '+levels.required;
        medievalGoals.append(item);
    }
    const population=document.createElement('li');
    population.textContent='Residents: '+data.population+' / '+data.victory.populationRequired;
    medievalGoals.append(population);
    medievalVictory.textContent=data.victory.achieved
        ? 'VICTORY — Your medieval colony has fulfilled every objective!'
        : 'Your colony is progressing. Complete all milestones to win.';
}
async function medievalAction(type) {
    const fields=locationFields();
    if (!fields.world || !fields.account) throw new Error('Choose a world and account');
    await submitCommand(fields,type,{account:fields.account});
    await reviewMedieval();
    await refresh();
}
document.querySelector('#medieval-check').addEventListener('click',()=>run(reviewMedieval));
document.querySelector('#medieval-feed').addEventListener('click',()=>run(()=>medievalAction('settlement.feed')));
document.querySelector('#medieval-work').addEventListener('click',()=>run(()=>medievalAction('settlement.work')));
const industrialPanel=document.querySelector('#industrial-panel');
const industrialDetails=document.querySelector('#industrial-details');
const industrialGoals=document.querySelector('#industrial-goals');
const industrialVictory=document.querySelector('#industrial-victory');
function resetIndustrial() {
    industrialDetails.replaceChildren();industrialGoals.replaceChildren();
    industrialVictory.textContent='';
}
async function reviewIndustrial() {
    const fields=locationFields();
    if (!fields.world || !fields.account) throw new Error('Choose a world and account');
    const root='/v1/worlds/'+encodeURIComponent(fields.world).replaceAll('%3A',':');
    const data=await api(root+'/industrial/status/'+encodeURIComponent(fields.account));
    if (JSON.stringify(fields)!==JSON.stringify(locationFields())) return;
    resetIndustrial();
    const line=(name,value)=>{
        const dt=document.createElement('dt'),dd=document.createElement('dd');
        dt.textContent=name;dd.textContent=String(value);industrialDetails.append(dt,dd);
    };
    line('Grid power supply',data.grid.supply);
    line('Installed power demand',data.grid.load);
    line('Spare grid power',data.grid.surplus);
    line('Warehouse goods capacity',data.warehouseCapacity);
    line('Goods delivered',data.delivery.current+' / '+data.delivery.required);
    for(const [type,level] of Object.entries(data.victory.buildings)) {
        const item=document.createElement('li');
        item.textContent=type+': '+level.current+' / '+level.required;
        industrialGoals.append(item);
    }
    const delivered=document.createElement('li');
    delivered.textContent='Deliveries: '+data.delivery.current+' / '+data.delivery.required;
    industrialGoals.append(delivered);
    for(const [action,at] of Object.entries(data.nextActionAt)) {
        if(at!==null)line(action+' next available (UTC)',new Date(at*1000).toISOString());
    }
    industrialVictory.textContent=data.victory.achieved
        ? 'VICTORY — Your industrial network has fulfilled all construction and delivery goals!'
        : 'Build the complete network and dispatch enough goods to win.';
}
async function industrialAction(type) {
    const fields=locationFields();
    if (!fields.world || !fields.account) throw new Error('Choose a world and account');
    await submitCommand(fields,type,{account:fields.account});
    await reviewIndustrial();
    await refresh();
}
document.querySelector('#industrial-check').addEventListener('click',()=>run(reviewIndustrial));
document.querySelector('#industrial-generate').addEventListener('click',()=>run(()=>industrialAction('industry.generate')));
document.querySelector('#industrial-refine').addEventListener('click',()=>run(()=>industrialAction('industry.refine')));
document.querySelector('#industrial-ship').addEventListener('click',()=>run(()=>industrialAction('industry.ship')));
function selectWorld() {
    const w=worlds.find(w=>w.world===balance.elements.world.value);
    options(balance.elements.account,(w?.accounts??[]).map(a=>[a,a]));
    options(balance.elements.resource,(w?.resources??[]).map(a=>[a,a]));
    stock.replaceChildren(); marketClaim.hidden=true;
    clearRecipients();
    clearMarket();
    clearMap();
    clearSocial();
    resetMedieval();
    resetIndustrial();
    medievalPanel.hidden=w?.settlementEnabled!==true;
    industrialPanel.hidden=w?.industryEnabled!==true;
    window.dispatchEvent(new CustomEvent('aeternum:context',{detail:{world:w?.world??'',account:balance.elements.account.value}}));
}
async function discover() {
    const data=await api('/v1/me/worlds');
    worlds=data.worlds;
    options(balance.elements.world,worlds.map(w=>[w.world,w.name+' — '+w.world]));
    selectWorld();
}
balance.elements.world.addEventListener('change',selectWorld);
for (const key of ['account','resource']) balance.elements[key].addEventListener('change',()=>{stock.replaceChildren();marketClaim.hidden=true;if(key==='account'){clearMarketPage();clearMarketHistory();resetMedieval();resetIndustrial();window.dispatchEvent(new CustomEvent('aeternum:context',{detail:{world:balance.elements.world.value,account:balance.elements.account.value}}));}});
document.querySelector('#discover').addEventListener('click',()=>run(discover));
async function refresh() {
    const f=locationFields();
    stock.replaceChildren();
    marketClaim.hidden=true;
    const data=await api(base(f)+'overview/'+encodeURIComponent(f.account)+'/'+encodeURIComponent(f.resource));
    if (JSON.stringify(f)!==JSON.stringify(locationFields())) return;
    stock.replaceChildren();
    marketClaim.hidden=!Number.isSafeInteger(data.refundPending) || data.refundPending<=0;
    function line(key,value) {
        const dt=document.createElement('dt'),dd=document.createElement('dd');
        dt.textContent=key; dd.textContent=String(value); stock.append(dt,dd);
    }
    for (const key of ['stock','capacity','amount','interval','at','level']) line(key,data[key]);
    if (data.refundPending>0) line('Pending escrow refund',data.refundPending);
    if (data.pending) {
        line('Upgrade status','Pending level '+data.pending.level);
        line('Due (UTC)',new Date(data.pending.dueAt*1000).toISOString());
        line('Future production',data.pending.amount);
    } else if (data.next) {
        line('Upgrade status','Next level '+data.next.level);
        line('Upgrade cost',data.next.cost);
        line('Duration (seconds)',data.next.duration);
        line('Future production',data.next.amount);
    } else line('Upgrade status','Maximum level');
}
async function run(work) {
    if (busy) return;
    busy=true;
    status.textContent='Working…';
    status.setAttribute('aria-busy','true');
    window.dispatchEvent(new CustomEvent('aeternum:activity',{detail:{busy:true}}));
    const controls=[...document.querySelectorAll('button, select')];
    controls.forEach(x=>x.disabled=true);
    try { const message=await work(); status.textContent=typeof message==='string'?message:'Success'; }
    catch(e) {
        if (e.status===401 && token) resetSession();
        status.textContent=e.message || 'Request failed';
    }
    finally {
        busy=false; status.removeAttribute('aria-busy');
        controls.forEach(x=>x.disabled=false);
        window.dispatchEvent(new CustomEvent('aeternum:activity',{detail:{busy:false}}));
    }
}
login.addEventListener('submit',e=>{
    e.preventDefault();
    const f=new FormData(login);
    const credentials={login:f.get('login'),password:f.get('password')};
    login.elements.password.value='';
    // An offline recovery secret is only for its issuance screen. Never leave
    // it visible after the user attempts to enter an authenticated session.
    showRecoveryCode(null);
    run(async()=>{const data=await api('/v1/auth/login','POST',credentials);token=data.token;login.hidden=true;registration.hidden=true;activation.hidden=true;activationResend.hidden=true;recovery.hidden=true;game.hidden=false;await discover();});
});
const registration=document.querySelector('#register');
let joinableWorlds=[], inviteRequired=false, activationRequired=false;
const activation=document.querySelector('#activate'), activationResend=document.querySelector('#activation-resend');
async function discoverRegistrations() {
    try {
        const data=await api('/v1/public/worlds');
        joinableWorlds=data.worlds;
        inviteRequired=data.invitationRequired===true;
        activationRequired=data.activationRequired===true;
        document.querySelector('#email-wrap').hidden=!activationRequired;
        registration.elements.email.required=activationRequired;
        activation.hidden=!activationRequired || token!==null;
        activationResend.hidden=!activationRequired || token!==null;
        document.querySelector('#invite-wrap').hidden=!inviteRequired;
        registration.elements.inviteCode.required=inviteRequired;
        options(registration.elements.world,joinableWorlds.map(w=>[w.world,w.name+' — '+w.world]));
        registration.hidden=joinableWorlds.length===0 || token!==null;
    } catch {
        joinableWorlds=[]; inviteRequired=false; activationRequired=false;
        document.querySelector('#email-wrap').hidden=true;
        registration.elements.email.required=false;
        activation.hidden=true;
        activationResend.hidden=true;
        document.querySelector('#invite-wrap').hidden=true;
        registration.elements.inviteCode.required=false;
        registration.hidden=true;
    }
}
void discoverRegistrations();
registration.addEventListener('submit',e=>{
    e.preventDefault();
    const f=new FormData(registration);
    const loginValue=String(f.get('login')),password=String(f.get('password')),confirm=String(f.get('confirm')),world=String(f.get('world'));
    run(async()=>{
        if (password!==confirm) throw new Error('Passwords do not match');
        const payload={login:loginValue,password,world};
        if (inviteRequired) payload.inviteCode=String(f.get('inviteCode'));
        if (activationRequired) payload.email=String(f.get('email'));
        const data=await api('/v1/auth/register','POST',payload);
        registration.elements.password.value='';
        registration.elements.confirm.value='';
        if (activationRequired) {
            activation.elements.login.value=loginValue;
            activationResend.elements.login.value=loginValue;
            activationResend.elements.email.value=String(f.get('email'));
            activation.hidden=false;
            activationResend.hidden=false;
            registration.hidden=true;
            return 'Check your email for the activation code, then enter it below.';
        }
        token=data.token;
        showRecoveryCode(data.recoveryCode);
        login.hidden=true; registration.hidden=true; recovery.hidden=true; game.hidden=false;
        await discover();
    });
});
activation.addEventListener('submit',e=>{
    e.preventDefault();
    const f=new FormData(activation);
    const payload={login:String(f.get('login')),password:String(f.get('password')),code:String(f.get('code'))};
    activation.elements.password.value='';
    activation.elements.code.value='';
    run(async()=>{
        const data=await api('/v1/auth/activate','POST',payload);
        token=data.token;
        activation.hidden=true; activationResend.hidden=true; login.hidden=true; registration.hidden=true; recovery.hidden=true; game.hidden=false;
        showRecoveryCode(data.recoveryCode);
        await discover();
    });
});
activationResend.addEventListener('submit',e=>{
    e.preventDefault();
    const f=new FormData(activationResend);
    const payload={login:String(f.get('login')),password:String(f.get('password')),email:String(f.get('email'))};
    activationResend.elements.password.value='';
    run(async()=>{
        await api('/v1/auth/activation/resend','POST',payload);
        return 'If the details match a pending player, a replacement activation code has been sent.';
    });
});
const recovery=document.querySelector('#recover'), secret=document.querySelector('#recovery-secret'), rotate=document.querySelector('#rotate');
function showRecoveryCode(code) {
    secret.hidden=!code;
    secret.textContent=code ? 'Store this recovery code offline now; it will not be shown again: '+code : '';
}
recovery.addEventListener('submit',e=>{
    e.preventDefault();
    const f=new FormData(recovery), payload={login:String(f.get('login')),code:String(f.get('code')),newPassword:String(f.get('newPassword'))};
    run(async()=>{
        if (payload.newPassword!==String(f.get('confirm'))) throw new Error('Passwords do not match');
        const data=await api('/v1/auth/recover','POST',payload);
        recovery.reset();
        showRecoveryCode(data.recoveryCode);
        return 'Password reset. Sign in with the replacement password.';
    });
});
rotate.addEventListener('submit',e=>{
    e.preventDefault();
    const pass=String(new FormData(rotate).get('password'));
    rotate.reset();
    run(async()=>{
        const data=await api('/v1/auth/recovery/rotate','POST',{password:pass});
        showRecoveryCode(data.recoveryCode);
    });
});
const passwordChange=document.querySelector('#password-change');
passwordChange.addEventListener('submit',e=>{
    e.preventDefault();
    const f=new FormData(passwordChange);
    const payload={currentPassword:String(f.get('currentPassword')),newPassword:String(f.get('newPassword'))};
    const confirm=String(f.get('confirm'));
    passwordChange.reset();
    run(async()=>{
        if (payload.newPassword!==confirm) throw new Error('Passwords do not match');
        if (payload.currentPassword===payload.newPassword) throw new Error('Choose a different password');
        const data=await api('/v1/auth/password/change','POST',payload);
        // The server atomically revokes every bearer for this identity.
        // Clear local auth and reveal only the newly issued offline code.
        resetSession();
        showRecoveryCode(data.recoveryCode);
        return 'Password changed. All sessions signed out. Save your new recovery code before signing in.';
    });
});
balance.addEventListener('submit',e=>{e.preventDefault();run(refresh);});
const transferFields=document.querySelector('#transfer-fields');
const directory=document.querySelector('#recipient-directory'), recipientNotice=document.querySelector('#recipient-notice');
function clearRecipients() {
    options(directory,[['','Choose a recipient']]);
    recipientNotice.textContent='';
    action.elements.recipient.value='';
}
async function loadRecipients() {
    const f=locationFields();
    options(directory,[['','Choose a recipient']]);
    recipientNotice.textContent='';
    if (!f.world) throw new Error('Choose a world first');
    const data=await api('/v1/worlds/'+encodeURIComponent(f.world).replaceAll('%3A',':')+'/recipients');
    options(directory,[['','Choose a recipient'],...data.accounts.map(a=>[a,a])]);
    recipientNotice.textContent=data.truncated?'First 100 recipients shown. Enter another account ID manually.':data.accounts.length?'Choose a recipient or enter an account ID manually.':'No recipients found.';
}
document.querySelector('#load-recipients').addEventListener('click',()=>run(loadRecipients));
directory.addEventListener('change',()=>{action.elements.recipient.value=directory.value;});
function syncTransferFields() {
    const active=action.elements.type.value==='economy.transfer';
    transferFields.hidden=!active; transferFields.disabled=!active;
}
action.elements.type.addEventListener('change',syncTransferFields);
action.addEventListener('submit',e=>{
    e.preventDefault();
    run(async()=>{
        const f=locationFields(), type=action.elements.type.value;
        const payload=type==='economy.transfer' ? {from:f.account,to:action.elements.recipient.value,resource:f.resource,units:Number(action.elements.units.value)} : {account:f.account,resource:f.resource};
        if (type==='economy.transfer' && (!Number.isSafeInteger(payload.units) || payload.units<1 || payload.units>1000000000000 || !/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/.test(payload.to) || payload.to===payload.from)) throw new Error('Invalid transfer');
        await submitCommand(f,type,payload);
        await refresh();
    });
});
const retryCommand=document.querySelector('#retry-command');
function syncPendingRetry() {
    retryCommand.hidden=pending===null;
    window.dispatchEvent(new CustomEvent('aeternum:retry',{detail:{pending:pending!==null}}));
}
async function sendPending() {
    if (!pending) throw new Error('No pending action');
    const saved=pending;
    try {
        await api(base({world:saved.world})+'commands','POST',saved.body);
        pending=null;
        window.dispatchEvent(new CustomEvent('aeternum:confirmed', {detail:{type:saved.body.type,world:saved.world,id:saved.body.id}}));
    }
    catch(e) { if ([400,401,404,409,413].includes(e.status)) pending=null; throw e; }
    finally { syncPendingRetry(); }
}
async function submitCommand(f,type,payload) {
    const signature=JSON.stringify([f.world,type,payload]);
    if (pending && pending.signature!==signature) throw new Error('Retry the previous action with identical fields first.');
    if (!pending) {
        const id=crypto.randomUUID();
        pending={world:f.world,signature,body:{id,type,payload:type==='market.create'?{...payload,offer:id}:payload}};
    }
    syncPendingRetry();
    await sendPending();
}
const marketCreate=document.querySelector('#market-create'), marketChoice=document.querySelector('#market-offer'), marketNext=document.querySelector('#market-next'), marketNotice=document.querySelector('#market-notice');
const marketFilterGive=document.querySelector('#market-filter-give'),marketFilterWant=document.querySelector('#market-filter-want');
const marketHistoryList=document.querySelector('#market-history-list'),marketHistoryNext=document.querySelector('#market-history-next'),marketHistoryNotice=document.querySelector('#market-history-notice');
let marketOffers=[], marketAfter=null, historyAfter=null;
function clearMarketHistory(message='') {
    marketHistoryList.replaceChildren();
    historyAfter=null;marketHistoryNext.hidden=true;
    marketHistoryNotice.textContent=message;
}
async function loadMarketHistory(after=null) {
    const f=locationFields();
    clearMarketHistory();
    if (!f.world || !f.account) throw new Error('Choose a world and account');
    const path='market/history/'+encodeURIComponent(f.account)+(after===null?'':'/'+encodeURIComponent(after));
    const data=await api(base(f)+path);
    if (f.world!==balance.elements.world.value || f.account!==balance.elements.account.value) return;
    for(const trade of data.transactions) {
        const item=document.createElement('li');
        const counterparty=trade.seller===f.account?(trade.buyer??'—'):trade.seller;
        item.textContent=trade.id+' — '+trade.status+' — '+trade.giveUnits+' '+trade.giveResource+
            ' for '+trade.wantUnits+' '+trade.wantResource+' — '+counterparty;
        marketHistoryList.append(item);
    }
    historyAfter=data.nextAfter;
    marketHistoryNext.hidden=historyAfter===null;
    marketHistoryNotice.textContent=data.transactions.length?'Private account history.':'No completed trades.';
}

function clearMarketPage(message='') {
    marketOffers=[]; marketAfter=null;
    options(marketChoice,[['','Choose an offer']]);
    marketNext.hidden=true;
    marketNotice.textContent=message;
}
function clearMarket() {
    const w=worlds.find(w=>w.world===balance.elements.world.value);
    const resources=(w?.resources??[]).map(r=>[r,r]);
    options(marketCreate.elements.giveResource,resources);
    options(marketCreate.elements.wantResource,resources);
    if (resources.length>1) marketCreate.elements.wantResource.selectedIndex=1;
    marketCreate.elements.giveUnits.value='1';
    marketCreate.elements.wantUnits.value='1';
    marketCreate.elements.expirySeconds.value='';
    options(marketFilterGive,[['','All resources'],...resources]);
    options(marketFilterWant,[['','All resources'],...resources]);
    clearMarketPage();
    clearMarketHistory();
}
async function loadMarket(after=null) {
    const f=locationFields();
    clearMarketPage();
    if (!f.world) throw new Error('Choose a world first');
    const give=marketFilterGive.value,want=marketFilterWant.value;
    if ((give==='')!==(want==='')) throw new Error('Select both resource filters or neither.');
    const path=give?'market/search/'+encodeURIComponent(give)+'/'+encodeURIComponent(want):'market';
    const data=await api(base(f)+path+(after===null?'':'/'+encodeURIComponent(after)));
    if (f.world!==balance.elements.world.value || f.account!==balance.elements.account.value) return;
    marketOffers=data.offers; marketAfter=data.nextAfter;
    options(marketChoice,[['','Choose an offer'],...marketOffers.map(o=>[o.id,o.seller+': '+o.giveUnits+' '+o.giveResource+' for '+o.wantUnits+' '+o.wantResource])]);
    marketNext.hidden=marketAfter===null;
    marketNotice.textContent=marketOffers.length?'Choose an offer to accept, or cancel your own offer.':'No open offers.';
}
function marketChanged() {
    stock.replaceChildren();
    marketClaim.hidden=true;
    clearMarketPage('Market changed. Reload offers to see the current market.');
    clearMarketHistory();
}
function validMarketUnits(value) { return Number.isSafeInteger(value) && value>=1 && value<=1000000000000; }
marketCreate.addEventListener('submit',e=>{
    e.preventDefault();
    run(async()=>{
        const f=locationFields(), fields=marketCreate.elements;
        const payload={seller:f.account,giveResource:fields.giveResource.value,giveUnits:Number(fields.giveUnits.value),wantResource:fields.wantResource.value,wantUnits:Number(fields.wantUnits.value)};
        if (!f.world || !f.account || !payload.giveResource || !payload.wantResource || payload.giveResource===payload.wantResource || !validMarketUnits(payload.giveUnits) || !validMarketUnits(payload.wantUnits)) throw new Error('Invalid market terms');
        const expiryRaw=fields.expirySeconds.value.trim();
        if (expiryRaw!=='') {
            const seconds=Number(expiryRaw);
            if (!Number.isSafeInteger(seconds) || seconds<1 || seconds>2592000) throw new Error('Invalid expiry duration');
            payload.expiresAt=Math.floor(Date.now()/1000)+seconds;
        }
        await submitCommand(f,'market.create',payload);
        marketChanged();
    });
});
async function selectedMarketCommand(type) {
    const f=locationFields(), offer=marketOffers.find(o=>o.id===marketChoice.value);
    if (!f.world || !f.account || !offer) throw new Error('Choose an offer and account first');
    const payload={offer:offer.id,seller:offer.seller};
    if (type==='market.accept') {
        if (f.account===offer.seller) throw new Error('Cannot buy own offer');
        payload.buyer=f.account;
    } else if (f.account!==offer.seller) throw new Error('Select the seller account to cancel');
    await submitCommand(f,type,payload);
    marketChanged();
}
marketClaim.addEventListener('click',()=>run(async()=>{
    const f=locationFields();
    if (!f.world || !f.account || !f.resource) throw new Error('Choose a world, account and resource');
    await submitCommand(f,'market.claim',{account:f.account,resource:f.resource});
    marketClaim.hidden=true;stock.replaceChildren();
}));
document.querySelector('#market-load').addEventListener('click',()=>run(()=>loadMarket()));
document.querySelector('#market-search').addEventListener('click',()=>run(()=>loadMarket()));
for(const select of [marketFilterGive,marketFilterWant]) {
    select.addEventListener('change',()=>clearMarketPage('Search changed. Reload market offers.'));
}
document.querySelector('#market-history-load').addEventListener('click',()=>run(()=>loadMarketHistory()));
marketHistoryNext.addEventListener('click',()=>{if(historyAfter!==null)run(()=>loadMarketHistory(historyAfter));});
marketNext.addEventListener('click',()=>{ if (marketAfter!==null) run(()=>loadMarket(marketAfter)); });
document.querySelector('#market-accept').addEventListener('click',()=>run(()=>selectedMarketCommand('market.accept')));
document.querySelector('#market-cancel').addEventListener('click',()=>run(()=>selectedMarketCommand('market.cancel')));
retryCommand.addEventListener('click',()=>run(async()=>{
    await sendPending();
    stock.replaceChildren();
    clearMarketPage('Saved action confirmed. Reload stock and offers.');
}));

const socialDirectory=document.querySelector('#social-directory');
const socialMembers=document.querySelector('#social-member-list');
const socialInbox=document.querySelector('#social-inbox');
const socialNotice=document.querySelector('#social-notice');
let socialMessageAfter=null, socialNotificationAfter=null;
let socialMessageGeneration=0, socialNotificationGeneration=0;
function clearSocial() {
    // Invalidate an outstanding API result when the player switches worlds
    // or signs out; old private pages must never repaint a new session.
    socialMessageAfter=null;socialNotificationAfter=null;
    ++socialMessageGeneration;++socialNotificationGeneration;
    document.getElementById('social-messages-next').hidden=true;
    document.getElementById('social-notifications-next').hidden=true;
    socialDirectory.replaceChildren();
    socialMembers.replaceChildren();
    for (const listId of ['social-messages-list','social-block-list','social-notifications-list','social-leaderboard-list']) {
        document.getElementById(listId).replaceChildren();
    }
    options(document.getElementById('social-notification-id'),[['','Select notification']]);
    document.getElementById('social-mail-notice').textContent='';
    options(socialInbox,[['','No invitation selected']]);
    socialNotice.textContent='';
    for (const id of ['social-alliance-id','social-invite-actor','social-role-actor']) {
        document.getElementById(id).value='';
    }
}
function socialRoot() {
    const world=balance.elements.world.value;
    if (!world) throw new Error('Choose a world first');
    return '/v1/worlds/'+encodeURIComponent(world).replaceAll('%3A',':')+'/social';
}
function socialAllianceId() {
    const id=document.querySelector('#social-alliance-id').value.trim();
    if (!/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/.test(id)) throw new Error('Enter a valid alliance ID');
    return id;
}
function socialActor(id) {
    const actor=document.getElementById(id).value.trim();
    if (!/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/.test(actor)) throw new Error('Enter a valid actor ID');
    return actor;
}
async function socialLoadDirectory() {
    const root=socialRoot(), world=balance.elements.world.value;
    const data=await api(root+'/alliances');
    if (world!==balance.elements.world.value) return;
    socialDirectory.replaceChildren();
    for (const alliance of data.alliances) {
        const item=document.createElement('li'),button=document.createElement('button');
        button.type='button'; button.textContent='Inspect '+alliance.id;
        button.addEventListener('click',()=>run(async()=>{
            document.getElementById('social-alliance-id').value=alliance.id;
            await socialLoadDetail();
        }));
        // Untrusted alliance names are always text, never HTML.
        item.append(document.createTextNode(alliance.name+' ('+alliance.members+') '),button);
        socialDirectory.append(item);
    }
    socialNotice.textContent=data.nextAfter===null?'Alliance directory loaded.':'Only the first page of alliances is shown.';
}
async function socialLoadDetail() {
    const root=socialRoot(),world=balance.elements.world.value,id=socialAllianceId();
    const data=await api(root+'/alliances/'+encodeURIComponent(id));
    if (world!==balance.elements.world.value || id!==socialAllianceId()) return;
    socialMembers.replaceChildren();
    for (const member of data.members) {
        const item=document.createElement('li');
        item.textContent=member.actor+' — '+member.role;
        socialMembers.append(item);
    }
    socialNotice.textContent=data.name+' — '+data.role;
}
async function socialLoadInbox() {
    const root=socialRoot(),world=balance.elements.world.value;
    const data=await api(root+'/invitations');
    if (world!==balance.elements.world.value) return;
    options(socialInbox,[['','No invitation selected'],...data.invitations.map(invitation=>
        [invitation.alliance,invitation.name+' — '+invitation.invitedBy])]);
    socialNotice.textContent=data.invitations.length+' pending invitations.';
}
async function socialAction(path,payload) {
    await api(socialRoot()+path,'POST',payload);
    socialNotice.textContent='Social action confirmed.';
}
document.querySelector('#social-directory-load').addEventListener('click',()=>run(socialLoadDirectory));
document.querySelector('#social-inbox-load').addEventListener('click',()=>run(socialLoadInbox));
document.querySelector('#social-detail-form').addEventListener('submit',event=>{
    event.preventDefault();run(socialLoadDetail);
});
document.querySelector('#social-create').addEventListener('submit',event=>{
    event.preventDefault();run(async()=>{
        const id=document.getElementById('social-new-id').value.trim(),
              name=document.getElementById('social-new-name').value.trim();
        await socialAction('/alliances',{id,name});
        document.getElementById('social-alliance-id').value=id;
        await socialLoadDirectory();
        await socialLoadDetail();
    });
});
document.querySelector('#social-invite-form').addEventListener('submit',event=>{
    event.preventDefault();run(async()=>{
        await socialAction('/alliances/'+encodeURIComponent(socialAllianceId())+'/invite',
            {actor:socialActor('social-invite-actor')});
        await socialLoadDetail();
    });
});
document.querySelector('#social-role-form').addEventListener('submit',event=>{
    event.preventDefault();run(async()=>{
        const alliance=socialAllianceId();
        await socialAction('/alliances/'+encodeURIComponent(alliance)+'/role',{
            actor:socialActor('social-role-actor'),role:document.getElementById('social-role-choice').value
        });
        await socialLoadDetail();
    });
});
document.querySelector('#social-owner-transfer').addEventListener('click',()=>run(async()=>{
    const alliance=socialAllianceId();
    await socialAction('/alliances/'+encodeURIComponent(alliance)+'/owner',
        {actor:socialActor('social-role-actor')});
    await socialLoadDetail();
}));
document.querySelector('#social-member-remove').addEventListener('click',()=>run(async()=>{
    const alliance=socialAllianceId();
    await socialAction('/alliances/'+encodeURIComponent(alliance)+'/remove',
        {actor:socialActor('social-role-actor')});
    await socialLoadDetail();
}));
document.querySelector('#social-leave').addEventListener('click',()=>run(async()=>{
    const alliance=socialAllianceId();
    await socialAction('/alliances/'+encodeURIComponent(alliance)+'/leave',{});
    socialMembers.replaceChildren();
}));
for (const [selector,accepted] of [['#social-accept',true],['#social-decline',false]]) {
    document.querySelector(selector).addEventListener('click',()=>run(async()=>{
        const alliance=socialInbox.value;
        if (!alliance) throw new Error('Select an invitation');
        await socialAction('/alliances/'+encodeURIComponent(alliance)+'/respond',{accept:accepted});
        if (accepted) document.getElementById('social-alliance-id').value=alliance;
        await socialLoadInbox();
        if (accepted) await socialLoadDetail();
    }));
}


async function socialRefreshMessages(older=false) {
    const world=balance.elements.world.value,root=socialRoot(),
        previous=older?socialMessageAfter:null;
    if (older && previous===null) return;
    const generation=++socialMessageGeneration;
    const nextButton=document.getElementById('social-messages-next');
    nextButton.disabled=true;
    try {
        const path=previous===null?'/messages':
            '/messages/page/'+previous.createdAt+'/'+encodeURIComponent(previous.id);
        const data=await api(root+path);
        if (balance.elements.world.value!==world || generation!==socialMessageGeneration) return;
        const list=document.getElementById('social-messages-list');
        if (!older) list.replaceChildren();
        for (const message of data.messages) {
            const li=document.createElement('li'),report=document.createElement('button');
            li.append(document.createTextNode(message.from+' — '+message.text+' ('+message.id+') '));
            report.type='button';report.textContent='Report this message';
            report.addEventListener('click',()=>{
                document.getElementById('social-report-id').value=message.id;
                document.getElementById('social-report-reason').focus();
            });
            li.append(report);
            list.append(li);
        }
        socialMessageAfter=data.nextAfter;
        nextButton.hidden=socialMessageAfter===null;
        document.getElementById('social-mail-notice').textContent=socialMessageAfter===null?
            'All visible messages loaded.':'Older messages available.';
    } finally {
        if (generation===socialMessageGeneration) nextButton.disabled=false;
    }
}
document.getElementById('social-messages-next').addEventListener('click',
    ()=>run(()=>socialRefreshMessages(true)));
document.getElementById('social-send-form').addEventListener('submit',event=>{
    event.preventDefault();run(async()=>{
        const recipient=socialActor('social-recipient'),
            text=document.getElementById('social-message-text').value;
        const messageId=crypto.randomUUID();
        await socialAction('/messages',{id:messageId,recipient,text});
        document.getElementById('social-message-text').value='';
        document.getElementById('social-mail-notice').textContent='Message sent.';
    });
});
document.getElementById('social-messages-refresh').addEventListener('click',()=>run(socialRefreshMessages));
document.getElementById('social-block-form').addEventListener('submit',event=>{
    event.preventDefault();run(async()=>{
        await socialAction('/blocks',{actor:socialActor('social-block-target')});
        await socialRefreshBlocks();await socialRefreshMessages();
    });
});
document.getElementById('social-unblock').addEventListener('click',()=>run(async()=>{
    await socialAction('/blocks/remove',{actor:socialActor('social-block-target')});
    await socialRefreshBlocks();await socialRefreshMessages();
}));
async function socialRefreshBlocks() {
    const world=balance.elements.world.value,root=socialRoot();
    const data=await api(root+'/blocks');
    if (balance.elements.world.value!==world) return;
    const list=document.getElementById('social-block-list');
    list.replaceChildren();
    for (const actor of data.actors) {
        const item=document.createElement('li');item.textContent=actor;list.append(item);
    }
}
document.getElementById('social-blocks-refresh').addEventListener('click',()=>run(socialRefreshBlocks));
document.getElementById('social-report-form').addEventListener('submit',event=>{
    event.preventDefault();run(async()=>{
        await socialAction('/reports',{
            id:crypto.randomUUID(),message:socialActor('social-report-id'),
            reason:document.getElementById('social-report-reason').value
        });
        document.getElementById('social-report-reason').value='';
        document.getElementById('social-mail-notice').textContent='Report submitted to moderation.';
    });
});
async function socialRefreshNotifications(older=false) {
    const world=balance.elements.world.value,root=socialRoot(),
        previous=older?socialNotificationAfter:null;
    if (older && previous===null) return;
    const generation=++socialNotificationGeneration;
    const nextButton=document.getElementById('social-notifications-next');
    nextButton.disabled=true;
    try {
        const path=previous===null?'/notifications':
            '/notifications/page/'+previous.createdAt+'/'+encodeURIComponent(previous.id);
        const data=await api(root+path);
        if (balance.elements.world.value!==world || generation!==socialNotificationGeneration) return;
        const select=document.getElementById('social-notification-id'),
            list=document.getElementById('social-notifications-list');
        if (!older) {
            list.replaceChildren();
            options(select,[['','Select notification']]);
        }
        for (const notice of data.notifications) {
            const item=document.createElement('li');
            item.textContent=notice.kind+' — '+notice.subject+
                (notice.readAt===null?' (unread)':' (read)');
            list.append(item);
            if (notice.readAt===null) {
                const option=document.createElement('option');
                option.value=notice.id;option.textContent=notice.kind+' — '+notice.subject;
                select.append(option);
            }
        }
        socialNotificationAfter=data.nextAfter;
        nextButton.hidden=socialNotificationAfter===null;
        document.getElementById('social-mail-notice').textContent=socialNotificationAfter===null?
            'All notifications loaded.':'Older notifications available.';
    } finally {
        if (generation===socialNotificationGeneration) nextButton.disabled=false;
    }
}
document.getElementById('social-notifications-next').addEventListener('click',
    ()=>run(()=>socialRefreshNotifications(true)));
document.getElementById('social-notifications-refresh').addEventListener('click',
    ()=>run(socialRefreshNotifications));
document.getElementById('social-notification-read').addEventListener('click',()=>run(async()=>{
    const id=document.getElementById('social-notification-id').value;
    if (!id) throw new Error('Select notification');
    await socialAction('/notifications/'+encodeURIComponent(id)+'/read',{});
    await socialRefreshNotifications();
}));
async function socialRefreshLeaderboard() {
    const world=balance.elements.world.value,root=socialRoot();
    const data=await api(root+'/leaderboard');
    if (balance.elements.world.value!==world) return;
    const list=document.getElementById('social-leaderboard-list');
    list.replaceChildren();
    for (const entry of data.alliances) {
        const item=document.createElement('li');
        item.textContent=entry.name+' — '+entry.activeMembers+' active members';
        list.append(item);
    }
    document.getElementById('social-mail-notice').textContent=data.truncated?
        'Only the top 50 alliances are shown.':'Leaderboard loaded.';
}
document.getElementById('social-leaderboard-refresh').addEventListener('click',
    ()=>run(socialRefreshLeaderboard));

function resetSession() {
    token=null; pending=null; syncPendingRetry(); worlds=[]; options(balance.elements.world,[]); selectWorld();
    stock.replaceChildren(); marketClaim.hidden=true; action.reset(); syncTransferFields(); game.hidden=true; login.hidden=false; recovery.hidden=false; registration.hidden=joinableWorlds.length===0; activation.hidden=!activationRequired; activationResend.hidden=!activationRequired; showRecoveryCode(null);
    window.dispatchEvent(new Event('aeternum:reset'));
}
document.querySelector('#logout').addEventListener('click',()=>run(async()=>{
    try { await api('/v1/auth/logout','POST'); }
    finally { resetSession(); }
}));
document.querySelector('#logout-all').addEventListener('click',()=>run(async()=>{
    await api('/v1/auth/logout/all','POST');
    resetSession();
}));
