const {test,expect}=require('@playwright/test');
const {captureScene}=require('./visual-capture.cjs');
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
    await expect(page.locator('#world-scene')).toHaveAttribute('data-theme','guild');
    const account=await page.locator('#balance select[name=account]').inputValue();
    expect(account).toMatch(/^[a-z0-9_-]{1,64}$/);
    await page.locator('#balance select[name=resource]').selectOption('spice');
    const req=page.waitForRequest(r=>r.url().includes('/economy/overview/'));
    await page.locator('#world-scene [data-label=stockSync]').click();
    await expect(page.locator('[data-stat=stock]')).toContainText(/spice/i);
    const token=(await (await req).allHeaders()).authorization;
    expect(token).toMatch(/^Bearer [a-f0-9]{64}$/);
    return {account,token};
}

async function build(page,id,type,seconds){
    const site=page.waitForResponse(r=>r.url().endsWith('/construction/options'));
    await page.getByRole('button',{name:'Find construction sites'}).click();
    expect((await site).status()).toBe(200);
    await page.locator('#world-scene [data-catalogue="'+type+'"]').click();
    await page.locator('#scene-foundation-reference').fill(id);
    const placed=page.waitForResponse(r=>r.url()===url
        && r.request().postDataJSON().type==='building.place');
    await page.locator('#world-scene [data-label=place]').click();
    expect((await placed).status()).toBe(200);
    await page.getByLabel('Owned building ID').fill(id);
    await page.getByRole('button',{name:'Inspect building plan'}).click();
    const started=page.waitForResponse(r=>r.url()===url
        && r.request().postDataJSON().type==='building.start');
    await page.locator('#world-scene [data-building="'+id+'"]').click();
    await page.locator('#world-scene [data-label=startStage]').click();
    const accepted=await started;
    expect(accepted.status(),id+': '+await accepted.text()).toBe(200);
    await page.waitForTimeout((seconds+2)*1000);
    expect(worker()).toBeGreaterThanOrEqual(1);
    await page.getByRole('button',{name:'Inspect building plan'}).click();
    await expect(page.locator('#building-details dd').nth(3)).toHaveText('1');
    await expect(page.locator('#world-scene [data-building="'+id+'"]')).toHaveAttribute('data-level',String(1));
    await expect(page.locator('#world-scene [data-building="'+id+'"]')).toHaveAttribute('data-stage','built');
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
    await a.locator('#world-scene [data-label=sync]').click();
    await expect(a.locator('#status')).toHaveText('Success');
    const territory=a.locator('#world-scene [data-territory]').first();
    await expect(territory).toBeVisible();
    await territory.click();
    await a.locator('#world-scene [data-label=from]').click();
    await expect(a.locator('#map-location')).not.toHaveValue('');
    await a.locator('#world-scene [data-label=zoomIn]').click();
    await expect(a.locator('.scene-atlas')).not.toHaveAttribute('viewBox','0 0 900 460');
    await a.locator('#world-scene [data-label=reset]').click();
    await test.info().attach('guild-atlas-desktop',{body:await a.locator('#world-scene').screenshot({path:'browser-captures/guild-atlas-desktop.png'}),contentType:'image/png'});
    await a.setViewportSize({width:375,height:812});
    await test.info().attach('guild-atlas-mobile',{body:await a.locator('#world-scene').screenshot({path:'browser-captures/guild-atlas-mobile.png'}),contentType:'image/png'});
    await a.setViewportSize({width:1280,height:800});
        await expect(a.locator('[data-building=m14-guild-hall]')).toBeVisible();
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
        await a.locator('#market-give-resource').selectOption('spice');
        await a.locator('#market-give-units').fill('3');
        await a.locator('#market-want-resource').selectOption('silver');
        await a.locator('#market-want-units').fill('6');
        const selling=a.waitForResponse(r=>r.url()===url&&r.request().postDataJSON()?.type==='market.create');
        await a.locator('#market-create button[type=submit],#market-create button:not([type])').click();
        const sale=await selling;
        expect(sale.status(),await sale.text()).toBe(200);
        const offer=sale.request().postDataJSON();
        await b.locator('#world-scene [data-label=marketSync]').click();
        await expect(b.locator('[data-stat=market] strong')).toHaveText('1');
        await b.locator('[data-offer]').filter({hasText:/3 spice/i}).click();
        await captureScene(b,'guild-open-barter');
        const purchasing=b.waitForResponse(r=>r.url()===url&&r.request().postDataJSON()?.type==='market.accept');
        await b.locator('#world-scene [data-label=acceptTrade]').click();
        const settled=await purchasing;
        const purchase=settled.request().postDataJSON();
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
    await a.locator('#world-scene [data-label=allStages]').click();
    await expect(a.locator('#status')).toHaveText('Success');
    await a.locator('#world-scene [data-label=allStocks]').click();
    await expect(a.locator('#status')).toHaveText('Success');
    await expect(a.locator('[data-reserve]').first()).toBeVisible();
        await expect(a.locator('[data-building=m14-guild-caravan]')).toHaveAttribute('data-stage','built');
        await captureScene(a,'guild-developed-holdings');
    await a.locator('#world-scene [data-label=district]').click();
    await expect(a.locator('.scene-district')).toBeVisible();
    await expect(a.locator('[data-district-building=m14-guild-caravan]')).toBeVisible();
    await a.locator('[data-district-building=m14-guild-caravan]').click();
    await expect(a.locator('[data-district-building=m14-guild-caravan]')).toHaveAttribute('aria-pressed','true');
    await captureScene(a,'guild-district');
    await a.locator('#world-scene [data-label=district]').click();
        const wrongWorld=await a.request.get(origin+'/v1/worlds/e2e-medieval/economy/balances/'+
            alice.account+'/wood',{headers:{Authorization:alice.token}});
        // Configured-but-inaccessible worlds return 409, unconfigured worlds 404.
        expect([404,409]).toContain(wrongWorld.status());
    }finally {
        await a.close();
        await b.close();
    }
});
