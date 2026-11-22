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
function clearMap() {
    mapList.replaceChildren(); mapLinks.replaceChildren();
    mapAfter=null; mapNext.hidden=true; mapNotice.textContent='';
    document.querySelector('#map-location').value='';
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
document.querySelector('#map-links-load').addEventListener('click',()=>run(loadMapLinks));
function selectWorld() {
    const w=worlds.find(w=>w.world===balance.elements.world.value);
    options(balance.elements.account,(w?.accounts??[]).map(a=>[a,a]));
    options(balance.elements.resource,(w?.resources??[]).map(a=>[a,a]));
    stock.replaceChildren(); marketClaim.hidden=true;
    clearRecipients();
    clearMarket();
    clearMap();
}
async function discover() {
    const data=await api('/v1/me/worlds');
    worlds=data.worlds;
    options(balance.elements.world,worlds.map(w=>[w.world,w.name+' — '+w.world]));
    selectWorld();
}
balance.elements.world.addEventListener('change',selectWorld);
for (const key of ['account','resource']) balance.elements[key].addEventListener('change',()=>{stock.replaceChildren();marketClaim.hidden=true;});
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
    const controls=[...document.querySelectorAll('button, select')];
    controls.forEach(x=>x.disabled=true);
    try { const message=await work(); status.textContent=typeof message==='string'?message:'Success'; }
    catch(e) {
        if (e.status===401 && token) resetSession();
        status.textContent=e.message || 'Request failed';
    }
    finally { busy=false; controls.forEach(x=>x.disabled=false); }
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
function syncPendingRetry() { retryCommand.hidden=pending===null; }
async function sendPending() {
    if (!pending) throw new Error('No pending action');
    const saved=pending;
    try { await api(base({world:saved.world})+'commands','POST',saved.body); pending=null; }
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
let marketOffers=[], marketAfter=null;
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
    clearMarketPage();
}
async function loadMarket(after=null) {
    const f=locationFields();
    clearMarketPage();
    if (!f.world) throw new Error('Choose a world first');
    const data=await api(base(f)+'market'+(after===null?'':'/'+encodeURIComponent(after)));
    marketOffers=data.offers; marketAfter=data.nextAfter;
    options(marketChoice,[['','Choose an offer'],...marketOffers.map(o=>[o.id,o.seller+': '+o.giveUnits+' '+o.giveResource+' for '+o.wantUnits+' '+o.wantResource])]);
    marketNext.hidden=marketAfter===null;
    marketNotice.textContent=marketOffers.length?'Choose an offer to accept, or cancel your own offer.':'No open offers.';
}
function marketChanged() {
    stock.replaceChildren();
    marketClaim.hidden=true;
    clearMarketPage('Market changed. Reload offers to see the current market.');
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
marketNext.addEventListener('click',()=>{ if (marketAfter!==null) run(()=>loadMarket(marketAfter)); });
document.querySelector('#market-accept').addEventListener('click',()=>run(()=>selectedMarketCommand('market.accept')));
document.querySelector('#market-cancel').addEventListener('click',()=>run(()=>selectedMarketCommand('market.cancel')));
retryCommand.addEventListener('click',()=>run(async()=>{
    await sendPending();
    stock.replaceChildren();
    clearMarketPage('Saved action confirmed. Reload stock and offers.');
}));
function resetSession() {
    token=null; pending=null; syncPendingRetry(); worlds=[]; options(balance.elements.world,[]); selectWorld();
    stock.replaceChildren(); marketClaim.hidden=true; action.reset(); syncTransferFields(); game.hidden=true; login.hidden=false; recovery.hidden=false; registration.hidden=joinableWorlds.length===0; activation.hidden=!activationRequired; activationResend.hidden=!activationRequired; showRecoveryCode(null);
}
document.querySelector('#logout').addEventListener('click',()=>run(async()=>{
    try { await api('/v1/auth/logout','POST'); }
    finally { resetSession(); }
}));
document.querySelector('#logout-all').addEventListener('click',()=>run(async()=>{
    await api('/v1/auth/logout/all','POST');
    resetSession();
}));
