const {test,expect}=require('@playwright/test');
const {execFileSync}=require('node:child_process');
const origin='http://127.0.0.1:8765';
const world='e2e-guild-scored';
const commandUrl=origin+'/v1/worlds/'+world+'/economy/commands';
const password='browser-only test password';
async function enter(page,login) {
    await page.goto(origin+'/play.html');
    await page.getByLabel('Login',{exact:true}).fill(login);
    await page.getByLabel('Password',{exact:true}).fill(password);
    const result=page.waitForResponse(r=>r.url().endsWith('/v1/auth/login')
        && r.request().method()==='POST');
    await page.getByRole('button',{name:'Sign in',exact:true}).click();
    expect((await result).status()).toBe(200);
    await expect(page.locator('#balance select[name=world]')).toHaveValue(world);
    await expect(page.locator('#progression-panel')).toBeVisible();
    const account=await page.locator('#balance select[name=account]').inputValue();
    const request=page.waitForRequest(r=>r.url().includes('/progression/'+account));
    await page.getByRole('button',{name:'Review my score'}).click();
    const auth=(await (await request).allHeaders()).authorization;
    expect(auth).toMatch(/^Bearer [a-f0-9]{64}$/);
    await expect(page.locator('#progression-details dd')).toHaveCount(3);
    await expect(page.locator('#progression-details dd').nth(2)).toHaveText('0');
    return {account,auth};
}
test('M15 authoritative guild scores: two browser actors, idempotent trade and delayed upgrade',async({browser})=>{
    test.setTimeout(140000);
    const a=await browser.newPage(),b=await browser.newPage();
    try {
        const alice=await enter(a,'m15-score-alice');
        const bob=await enter(b,'m15-score-bob');
        expect(alice.account).not.toBe(bob.account);
        await a.getByRole('button',{name:'Review my rank'}).click();
        await b.getByRole('button',{name:'Review my rank'}).click();
        await expect(a.locator('#progression-rank-result')).toHaveText('1 / 2');
        await expect(b.locator('#progression-rank-result')).toHaveText('1 / 2');
        const blocked=await b.request.get(origin+'/v1/worlds/'+world+'/progression/'+alice.account,
            {headers:{Authorization:bob.auth}});
        expect(blocked.status()).toBe(404);
        const blockedRank=await b.request.get(origin+'/v1/worlds/'+world
            +'/progression/'+alice.account+'/rank',{headers:{Authorization:bob.auth}});
        expect(blockedRank.status()).toBe(404);
        const mutation=await a.request.post(origin+'/v1/worlds/'+world+'/progression/'+alice.account,
            {headers:{Authorization:alice.auth},data:{global:100000000}});
        expect(mutation.status()).toBe(405);
        const seller={id:'m15-browser-sell',type:'market.create',
            payload:{offer:'m15-browser-offer',seller:alice.account,
                giveResource:'spice',giveUnits:3,wantResource:'silver',wantUnits:6}};
        const open=await a.request.post(commandUrl,{headers:{Authorization:alice.auth},data:seller});
        expect(open.status(),await open.text()).toBe(200);
        const buyer={id:'m15-browser-buy',type:'market.accept',
            payload:{offer:'m15-browser-offer',seller:alice.account,buyer:bob.account}};
        const first=await b.request.post(commandUrl,{headers:{Authorization:bob.auth},data:buyer});
        expect(first.status(),await first.text()).toBe(200);
        const again=await b.request.post(commandUrl,{headers:{Authorization:bob.auth},data:buyer});
        expect(again.status()).toBe(200);
        expect(await again.json()).toEqual(await first.json());
        await a.getByRole('button',{name:'Review my score'}).click();
        await b.getByRole('button',{name:'Review my score'}).click();
        await expect(a.locator('#progression-details dd').nth(0)).toHaveText('6');
        await expect(b.locator('#progression-details dd').nth(0)).toHaveText('6');
        const upgrade={id:'m15-browser-upgrade',type:'economy.upgrade',
            payload:{account:alice.account,resource:'silver'}};
        const accepted=await a.request.post(commandUrl,{headers:{Authorization:alice.auth},data:upgrade});
        expect(accepted.status(),await accepted.text()).toBe(200);
        await a.getByRole('button',{name:'Review my score'}).click();
        await expect(a.locator('#progression-details dd').nth(2)).toHaveText('6');
        await a.waitForTimeout(4600);
        const worker=execFileSync('php',['bin/worker.php','reference-guild',world],{
            encoding:'utf8',timeout:30000,
            env:{...process.env,AETERNUM_WORLD_REGISTRY:process.env.AETERNUM_E2E_REGISTRY}
        });
        expect(JSON.parse(worker).processed).toBeGreaterThanOrEqual(1);
        const req=a.waitForResponse(r=>r.url().includes('/progression/'+alice.account));
        await a.getByRole('button',{name:'Review my score'}).click();
        expect((await req).status()).toBe(200);
        await expect(a.locator('#progression-details dd').nth(0)).toHaveText('14');
        await expect(a.locator('#progression-details dd').nth(1)).toHaveText('0');
        await expect(a.locator('#progression-details dd').nth(2)).toHaveText('14');
        await b.getByRole('button',{name:'Review my score'}).click();
        await expect(b.locator('#progression-details dd').nth(2)).toHaveText('6');
        await a.getByRole('button',{name:'Review my rank'}).click();
        await b.getByRole('button',{name:'Review my rank'}).click();
        await expect(a.locator('#progression-rank-result')).toHaveText('1 / 2');
        await expect(b.locator('#progression-rank-result')).toHaveText('2 / 2');
    } finally {
        await a.close();await b.close();
    }
});
