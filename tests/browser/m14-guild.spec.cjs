const {test,expect}=require('@playwright/test');
const {execFileSync}=require('node:child_process');

const origin='http://127.0.0.1:8765';
const world='e2e-guild';
const url=origin+'/v1/worlds/'+world+'/economy/commands';
const password='browser-only test password';
const worker=()=>{
    const raw=execFileSync('php',['bin/worker.php','reference-guild',world],{
        encoding:'utf8',timeout:30000,
        env:{...process.env,AETERNUM_MEDIEVAL_WORLD:'',AETERNUM_INDUSTRIAL_WORLD:'',
            AETERNUM_WORLD_REGISTRY:process.env.AETERNUM_E2E_REGISTRY}
    });
    return JSON.parse(raw).processed;
};

async function signIn(page,login){
    await page.goto(origin+'/play.html');
    await page.getByLabel('Login',{exact:true}).fill(login);
    for(let n=0;n<3;n++){
        await page.getByLabel('Password',{exact:true}).fill(password);
        const future=page.waitForResponse(r=>r.url().endsWith('/v1/auth/login')
            && r.request().method()==='POST',{timeout:15000});
        await page.getByRole('button',{name:'Sign in',exact:true}).click();
        const result=await future;
        if(result.status()===200)break;
        expect(result.status()).toBe(429);
        if(n===2)throw Error('M14 player login denied by genuine global rate limiter');
        await page.waitForTimeout(65000);
    }
    await expect(page.locator('#balance select[name=world]')).toHaveValue(world);
    const account=await page.locator('#balance select[name=account]').inputValue();
    expect(account).toMatch(/^[a-z0-9_-]{1,64}$/);
    await page.getByLabel('Resource',{exact:true}).selectOption('spice');
    const req=page.waitForRequest(r=>r.url().includes('/economy/overview/'));
    await page.getByRole('button',{name:'Refresh stock'}).click();
    const token=(await (await req).allHeaders()).authorization;
    expect(token).toMatch(/^Bearer [a-f0-9]{64}$/);
    return {account,token};
}

async function build(page,id,type,seconds){
    const site=page.waitForResponse(r=>r.url().endsWith('/construction/options'));
    await page.getByRole('button',{name:'Find construction sites'}).click();
    expect((await site).status()).toBe(200);
    await page.getByLabel('Building type',{exact:true}).selectOption(type);
    await page.getByLabel('New building identifier (optional)').fill(id);
    const placed=page.waitForResponse(r=>r.url()===url
        && r.request().postDataJSON().type==='building.place');
    await page.getByRole('button',{name:'Place foundation'}).click();
    expect((await placed).status()).toBe(200);
    await page.getByLabel('Owned building ID').fill(id);
    await page.getByRole('button',{name:'Inspect building plan'}).click();
    const started=page.waitForResponse(r=>r.url()===url
        && r.request().postDataJSON().type==='building.start');
    await page.getByRole('button',{name:'Begin construction'}).click();
    const accepted=await started;
    expect(accepted.status(),id+': '+await accepted.text()).toBe(200);
    await page.waitForTimeout((seconds+2)*1000);
    expect(worker()).toBeGreaterThanOrEqual(1);
    await page.getByRole('button',{name:'Inspect building plan'}).click();
    await expect(page.locator('#building-details dd').nth(3)).toHaveText('1');
}

test('M14 third data-only guild game: two browser players build, trade and upgrade',async ({browser})=>{
    test.setTimeout(250000);
    const a=await browser.newPage(),b=await browser.newPage();
    try {
        const alice=await signIn(a,'m14-guild-alice');
        const bob=await signIn(b,'m14-guild-bob');
        expect(alice.account).not.toBe(bob.account);
        await expect(a.locator('select[name=resource] option[value=spice]')).toHaveCount(1);
        await build(a,'m14-guild-hall','market_hall',2);
        const steal=await b.request.post(url,{headers:{Authorization:bob.token},data:{
            id:'m14-guild-steal',type:'building.start',
            payload:{account:bob.account,building:'m14-guild-hall',type:'market_hall'}
        }});
        expect(steal.status()).toBe(409);
        await build(a,'m14-guild-caravan','caravan_hub',3);
        const hall=await a.request.get(origin+'/v1/worlds/'+world+'/buildings/m14-guild-caravan',
            {headers:{Authorization:alice.token}});
        expect(hall.status()).toBe(200);
        expect((await hall.json()).data.level).toBe(1);
        const offer={
            id:'m14-guild-offer',
            type:'market.create',
            payload:{offer:'m14-guild-offer',seller:alice.account,
                giveResource:'spice',giveUnits:3,wantResource:'silver',wantUnits:6}
        };
        const sale=await a.request.post(url,{headers:{Authorization:alice.token},data:offer});
        expect(sale.status(),await sale.text()).toBe(200);
        const purchase={id:'m14-guild-buy',type:'market.accept',
            payload:{offer:'m14-guild-offer',seller:alice.account,buyer:bob.account}};
        const settled=await b.request.post(url,{headers:{Authorization:bob.token},data:purchase});
        expect(settled.status(),await settled.text()).toBe(200);
        const again=await b.request.post(url,{headers:{Authorization:bob.token},data:purchase});
        expect(again.status()).toBe(200);
        expect(await again.json()).toEqual(await settled.json());
        const market=await a.request.get(origin+'/v1/worlds/'+world+'/economy/market/history/'+alice.account,
            {headers:{Authorization:alice.token}});
        expect(market.status()).toBe(200);
        expect(JSON.stringify(await market.json())).toContain('filled');
        const upgrade=await a.request.post(url,{headers:{Authorization:alice.token},data:{
            id:'m14-guild-upgrade',type:'economy.upgrade',
            payload:{account:alice.account,resource:'silver'}
        }});
        expect(upgrade.status(),await upgrade.text()).toBe(200);
        await a.waitForTimeout(5000);
        expect(worker()).toBeGreaterThanOrEqual(1);
        const status=await a.request.get(origin+'/v1/worlds/'+world+'/economy/overview/'+alice.account+'/silver',
            {headers:{Authorization:alice.token}});
        expect(status.status()).toBe(200);
        expect((await status.json()).data.level).toBe(1);
        const wrongWorld=await a.request.get(origin+'/v1/worlds/e2e-medieval/economy/balances/'+
            alice.account+'/wood',{headers:{Authorization:alice.token}});
        expect(wrongWorld.status()).toBe(409);
    }finally {
        await a.close();
        await b.close();
    }
});
