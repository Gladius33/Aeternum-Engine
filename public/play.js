'use strict';
let token = null, busy = false, pending = null, worlds = [];
const login = document.querySelector('#login'), game = document.querySelector('#game'), balance = document.querySelector('#balance'), action = document.querySelector('#action'), status = document.querySelector('#status'), stock = document.querySelector('#stock');
async function api(path, method='GET', data) {
    const headers = {'Accept':'application/json'};
    if (token) headers.Authorization = 'Bearer '+token;
    if (data !== undefined) headers['Content-Type'] = 'application/json';
    const response = await fetch(path,{method,headers,body:data===undefined?undefined:JSON.stringify(data),cache:'no-store',redirect:'error',credentials:'omit'});
    const result = await response.json();
    if (!response.ok) { const error=new Error(result.error || 'Request failed'); error.status=response.status; throw error; }
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
function selectWorld() {
    const w=worlds.find(w=>w.world===balance.elements.world.value);
    options(balance.elements.account,(w?.accounts??[]).map(a=>[a,a]));
    options(balance.elements.resource,(w?.resources??[]).map(a=>[a,a]));
    stock.replaceChildren();
}
async function discover() {
    const data=await api('/v1/me/worlds');
    worlds=data.worlds;
    options(balance.elements.world,worlds.map(w=>[w.world,w.name+' — '+w.world]));
    selectWorld();
}
balance.elements.world.addEventListener('change',selectWorld);
for (const key of ['account','resource']) balance.elements[key].addEventListener('change',()=>stock.replaceChildren());
document.querySelector('#discover').addEventListener('click',()=>run(discover));
async function refresh() {
    const f=locationFields();
    stock.replaceChildren();
    const data=await api(base(f)+'overview/'+encodeURIComponent(f.account)+'/'+encodeURIComponent(f.resource));
    if (JSON.stringify(f)!==JSON.stringify(locationFields())) return;
    stock.replaceChildren();
    function line(key,value) {
        const dt=document.createElement('dt'),dd=document.createElement('dd');
        dt.textContent=key; dd.textContent=String(value); stock.append(dt,dd);
    }
    for (const key of ['stock','capacity','amount','interval','at','level']) line(key,data[key]);
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
    try { await work(); status.textContent='Success'; }
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
    run(async()=>{const data=await api('/v1/auth/login','POST',credentials);token=data.token;login.hidden=true;game.hidden=false;await discover();});
});
balance.addEventListener('submit',e=>{e.preventDefault();run(refresh);});
const transferFields=document.querySelector('#transfer-fields');
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
        const signature=JSON.stringify([f.world,type,payload]);
        if (pending && pending.signature!==signature) throw new Error('Retry the previous action with identical fields first.');
        if (!pending) pending={signature,body:{id:crypto.randomUUID(),type,payload}};
        try { await api(base(f)+'commands','POST',pending.body); } catch(e) { if ([400,401,404,409,413].includes(e.status)) pending=null; throw e; }
        pending=null;
        await refresh();
    });
});
function resetSession() {
    token=null; pending=null; worlds=[]; options(balance.elements.world,[]); selectWorld();
    stock.replaceChildren(); action.reset(); syncTransferFields(); game.hidden=true; login.hidden=false;
}
document.querySelector('#logout').addEventListener('click',()=>run(async()=>{
    try { await api('/v1/auth/logout','POST'); }
    finally { resetSession(); }
}));
