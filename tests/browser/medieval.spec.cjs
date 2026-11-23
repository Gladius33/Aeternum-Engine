const {test,expect}=require('@playwright/test');
const {execFileSync}=require('node:child_process');
const origin='http://127.0.0.1:8765';
const world='e2e-medieval-v3';
const password='browser-only test password';
const commandUrl=origin+'/v1/worlds/'+world+'/economy/commands';
function executeWorker() {
    expect(process.env.AETERNUM_E2E_REGISTRY).toBeTruthy();
    const output=execFileSync('php',['bin/worker.php','reference-medieval',world],{
        encoding:'utf8',
        env:{...process.env,AETERNUM_MEDIEVAL_WORLD:'',AETERNUM_INDUSTRIAL_WORLD:'',
            AETERNUM_WORLD_REGISTRY:process.env.AETERNUM_E2E_REGISTRY},
        timeout:30000
    });
    expect(JSON.parse(output).processed).toBeGreaterThanOrEqual(1);
}
async function register(page,login) {
    await page.goto(origin+'/play.html');
    await expect(page.locator('#register')).toBeVisible();
    await page.getByLabel('New login',{exact:true}).fill(login);
    await page.getByLabel('New password (12 characters minimum)',{exact:true}).fill(password);
    await page.getByLabel('Confirm new password',{exact:true}).fill(password);
    await page.getByLabel('World to join',{exact:true}).selectOption(world);
    // Other browser suites share the genuine per-peer auth quota. Respect
    // each rejected window rather than disabling the limiter for this test.
    for(let attempt=0;attempt<3;attempt++) {
        const reply=page.waitForResponse(r=>r.url().endsWith('/v1/auth/register')&&r.request().method()==='POST');
        await page.getByRole('button',{name:'Create player',exact:true}).click();
        const response=await reply;
        if(response.status()===201)break;
        expect(response.status()).toBe(429);
        if(attempt===2)throw new Error('M05 signup quota exhausted after three windows');
        await page.waitForTimeout(65000);
    }
    await expect(page.locator('#game')).toBeVisible();
    await expect(page.locator('#medieval-panel')).toBeVisible();
    await expect(page.locator('#balance select[name=world]')).toHaveValue(world);
    return page.locator('#balance select[name=account]').inputValue();
}
async function foundation(page,type,id) {
    const request=page.waitForResponse(r=>r.url().endsWith('/construction/options'));
    await page.getByRole('button',{name:'Find construction sites'}).click();
    expect((await request).status()).toBe(200);
    await expect(page.locator('#foundation-site option')).toHaveCount(1);
    await page.getByLabel('Building type',{exact:true}).selectOption(type);
    await page.getByLabel('New building identifier (optional)').fill(id);
    const placed=page.waitForResponse(r=>r.url()===commandUrl
        && r.request().postDataJSON().type==='building.place');
    await page.getByRole('button',{name:'Place foundation'}).click();
    expect((await placed).status()).toBe(200);
    await expect(page.locator('#building-details')).toContainText(id);
}
async function advanceBuilding(page,id,expectedLevel,seconds) {
    await page.getByLabel('Owned building ID').fill(id);
    await page.getByRole('button',{name:'Inspect building plan'}).click();
    const accepted=page.waitForResponse(r=>r.url()===commandUrl
        && r.request().postDataJSON().type==='building.start');
    await page.getByRole('button',{name:'Begin construction'}).click();
    expect((await accepted).status()).toBe(200);
    await expect(page.locator('#building-details')).toContainText('Construction pending');
    // No test-only timing shortcut: process genuinely due events after the
    // pinned rule's server-clock duration through the production CLI worker.
    await page.waitForTimeout(seconds*1000+2100);
    executeWorker();
    await page.getByRole('button',{name:'Inspect building plan'}).click();
    await expect(page.locator('#building-details dd').nth(3)).toHaveText(String(expectedLevel));
}
async function settle(page,type) {
    const reply=page.waitForResponse(r=>r.url()===commandUrl &&
        r.request().postDataJSON().type===type);
    const button=type==='settlement.feed'?'Feed and welcome one resident':'Organize lumber work';
    await page.getByRole('button',{name:button}).click();
    expect((await reply).status()).toBe(200);
    await expect(page.locator('#status')).toHaveText('Success');
}
test('M05 two newly registered players complete an isolated browser colony campaign',async({browser})=>{
    test.setTimeout(480000);
    const a=await browser.newPage(),b=await browser.newPage();
    const alice=await register(a,'m05-browser-alice');
    const bob=await register(b,'m05-browser-bob');
    expect(alice).not.toBe(bob);
    await a.getByRole('button',{name:'Review your colony'}).click();
    await expect(a.locator('#medieval-details')).toContainText('Residents1 / 1');
    await expect(a.locator('#medieval-victory')).not.toContainText('VICTORY');
    await foundation(a,'lumberyard','m05-wood');
    await foundation(a,'farm','m05-farm');
    await foundation(a,'granary','m05-granary');
    await foundation(a,'keep','m05-keep');
    await foundation(b,'farm','m05-b-farm');
    const collect=a.waitForRequest(r=>r.url().includes('/settlement/status/'));
    await a.getByRole('button',{name:'Review your colony'}).click();
    const bearer=(await (await collect).allHeaders()).authorization;
    expect(bearer).toMatch(/^Bearer [a-f0-9]{64}$/);
    const foreign=await a.request.get(origin+'/v1/worlds/'+world+'/settlement/status/'+bob,{
        headers:{Authorization:bearer}
    });
    expect(foreign.status()).toBe(404);
    const illegal=await a.request.post(commandUrl,{headers:{Authorization:bearer},data:{
        id:'m05-browser-steal',type:'settlement.feed',payload:{account:bob}
    }});
    expect(illegal.status()).toBe(409);
    await advanceBuilding(a,'m05-wood',1,8);
    await advanceBuilding(a,'m05-farm',1,10);
    await settle(a,'settlement.feed');
    await expect(a.locator('#medieval-details')).toContainText('Residents2 / 4');
    await settle(a,'settlement.work');
    await advanceBuilding(a,'m05-wood',2,14);
    await advanceBuilding(a,'m05-farm',2,14);
    await advanceBuilding(a,'m05-granary',1,12);
    await advanceBuilding(a,'m05-keep',1,16);
    await advanceBuilding(a,'m05-keep',2,20);
    await a.getByRole('button',{name:'Review your colony'}).click();
    await expect(a.locator('#medieval-victory')).not.toContainText('VICTORY');
    for(let i=0;i<4;i++) await settle(a,'settlement.feed');
    await expect(a.locator('#medieval-details')).toContainText('Residents6 / 9');
    await expect(a.locator('#medieval-victory')).toContainText('VICTORY');
    await b.getByRole('button',{name:'Review your colony'}).click();
    await expect(b.locator('#medieval-victory')).not.toContainText('VICTORY');
    await expect(b.locator('#medieval-details')).toContainText('Residents1 / 1');
    await advanceBuilding(b,'m05-b-farm',1,10);
    await settle(b,'settlement.feed');
    await expect(b.locator('#medieval-details')).toContainText('Residents2 / 4');
    await expect(b.locator('#medieval-victory')).not.toContainText('VICTORY');
    // Reconnect via the actual login screen, not a retained local session.
    await a.getByRole('button',{name:'Sign out'}).click();
    await expect(a.locator('#login')).toBeVisible();
    await a.getByLabel('Login',{exact:true}).fill('m05-browser-alice');
    await a.getByLabel('Password',{exact:true}).fill(password);
    const login=a.waitForResponse(r=>r.url().endsWith('/v1/auth/login'));
    await a.getByRole('button',{name:'Sign in',exact:true}).click();
    expect((await login).status()).toBe(200);
    await expect(a.locator('#medieval-panel')).toBeVisible();
    await a.getByRole('button',{name:'Review your colony'}).click();
    await expect(a.locator('#medieval-victory')).toContainText('VICTORY');
    await a.close();await b.close();
});
