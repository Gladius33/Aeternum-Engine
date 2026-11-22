const {test, expect}=require('@playwright/test');
test.describe.configure({mode:'default'});
const {execFileSync}=require('node:child_process');
const url='http://127.0.0.1:8765';
const password='browser-only test password';
const scenarios=[
    {pack:'reference-medieval',login:'browser-medieval',world:'e2e-medieval',resource:'grain',initial:50,rate:12,cost:25,upgraded:20,duration:60},
    {pack:'reference-industrial',login:'browser-industrial',world:'e2e-industrial',resource:'steel',initial:200,rate:75,cost:120,upgraded:110,duration:180}
];
async function signIn(page, scenario) {
    await page.goto(url+'/play.html');
    await page.getByLabel('Login',{exact:true}).fill(scenario.login);
    await page.getByLabel('Password',{exact:true}).fill(password);
    const discovery=page.waitForResponse(r=>r.url().endsWith('/v1/me/worlds'));
    await page.getByRole('button',{name:'Sign in',exact:true}).click();
    expect((await discovery).status()).toBe(200);
    await expect(page.locator('#status')).toHaveText('Success');
    await expect(page.locator('select[name=world] option')).toHaveCount(1);
    await expect(page.locator('select[name=world]')).toHaveValue(scenario.world);
    await page.selectOption('select[name=resource]',scenario.resource);
}
async function refresh(page, allowDueConflict=false) {
    const received=page.waitForResponse(r=>r.url().includes('/economy/overview/'));
    await page.getByRole('button',{name:'Refresh stock'}).click();
    const response=await received;
    if (allowDueConflict && response.status()===409) {
        await expect(page.locator('#status')).toHaveText('command_or_access_conflict');
        return null;
    }
    expect(response.status()).toBe(200);
    const {data}=await response.json();
    await expect(page.locator('#status')).toHaveText('Success');
    await expect(page.locator('#stock dd').nth(0)).toHaveText(String(data.stock));
    return data;
}

async function marketPage(page) {
    const received=page.waitForResponse(r=>r.url().endsWith('/economy/market') && r.request().method()==='GET');
    await page.getByRole('button',{name:'Reload market',exact:true}).click();
    const response=await received;
    expect(response.status()).toBe(200);
    await expect(page.locator('#status')).toHaveText('Success');
    return (await response.json()).data;
}
async function marketCommand(page,button,type) {
    const received=page.waitForResponse(r=>r.url().endsWith('/economy/commands') && r.request().method()==='POST' && r.request().postDataJSON().type===type);
    await page.getByRole('button',{name:button,exact:true}).click();
    const response=await received;
    expect(response.status()).toBe(200);
    await expect(page.locator('#status')).toHaveText('Success');
    return {body:response.request().postDataJSON(),receipt:(await response.json()).data,authorization:(await response.request().allHeaders()).authorization};
}
async function industrialMarketSmoke(page) {
    await expect(page.getByRole('heading',{name:'Market',exact:true})).toBeVisible();
    expect((await marketPage(page)).offers).toEqual([]);
    await expect(page.locator('#market-notice')).toHaveText('No open offers.');
    await page.getByLabel('Offered resource',{exact:true}).selectOption('energy');
    await page.getByLabel('Requested resource',{exact:true}).selectOption('steel');
    await page.getByLabel('Offered units',{exact:true}).fill('1');
    await page.getByLabel('Requested units',{exact:true}).fill('1');
    const created=await marketCommand(page,'Create offer','market.create');
    expect(created.body.payload.offer).toBe(created.body.id);
    const pageData=await marketPage(page);
    expect(pageData.offers).toHaveLength(1);
    expect(pageData.offers[0].id).toBe(created.body.id);
    await page.getByLabel('Open offers',{exact:true}).selectOption(created.body.id);
    const cancelled=await marketCommand(page,'Cancel selected offer','market.cancel');
    expect(cancelled.body.payload.offer).toBe(created.body.id);
    expect((await marketPage(page)).offers).toEqual([]);
    await expect(page.locator('#market-next')).toBeHidden();
    await expect(page.locator('#market-notice')).toHaveText('No open offers.');
}

test('real multiplayer transfer and foreign balance denial', async ({browser})=>{
    const senderContext=await browser.newContext(), receiverContext=await browser.newContext();
    try {
        const sender=await senderContext.newPage(), receiver=await receiverContext.newPage();
        const s=scenarios[0];
        await signIn(sender,{...s,login:'browser-medieval-sender'});
        await signIn(receiver,{...s,login:'browser-medieval-receiver'});
        const from=await sender.locator('select[name=account]').inputValue();
        const to=await receiver.locator('select[name=account]').inputValue();
        expect(from).not.toBe(to);
        // Keep this scenario on separate accounts; no upgrade events are created here.
        const beforeSender=await refresh(sender,true), beforeReceiver=await refresh(receiver,true);
        expect(beforeSender).not.toBeNull();
        expect(beforeReceiver).not.toBeNull();
        await sender.selectOption('select[name=type]','economy.transfer');
        const directoryResponse=sender.waitForResponse(r=>r.url().endsWith('/recipients'));
        await sender.getByRole('button',{name:'Find recipients'}).click();
        expect((await directoryResponse).status()).toBe(200);
        await expect(sender.locator('#status')).toHaveText('Success');
        await expect(sender.locator('#recipient-directory option').filter({hasText:to})).toHaveCount(1);
        expect(await sender.locator('#recipient-directory option').evaluateAll(options=>options.map(o=>o.value))).not.toContain(from);
        await sender.getByLabel('Available recipients').selectOption(to);
        await expect(sender.getByLabel('Recipient account')).toHaveValue(to);
        await sender.getByLabel('Units',{exact:true}).fill('5');
        const command=sender.waitForResponse(r=>r.url().endsWith('/economy/commands'));
        await sender.getByRole('button',{name:'Submit action'}).click();
        expect((await command).status()).toBe(200);
        await expect(sender.locator('#status')).toHaveText('Success');
        const afterSender=await refresh(sender), afterReceiver=await refresh(receiver);
        // Lazy production may accrue between snapshots, therefore check the
        // debit via the committed stock and the credit via its lower bound.
        expect(afterReceiver.stock).toBeGreaterThanOrEqual(beforeReceiver.stock+5);
        expect(afterSender.stock).toBeLessThanOrEqual(afterSender.capacity);
        const capture=sender.waitForRequest(r=>r.url().includes('/economy/overview/'));
        await sender.getByRole('button',{name:'Refresh stock'}).click();
        const req=await capture;
        const authorization=(await req.allHeaders()).authorization;
        const foreign=await sender.request.get(url+'/v1/worlds/'+s.world+'/economy/overview/'+encodeURIComponent(to)+'/'+s.resource,{headers:{Authorization:authorization}});
        expect(foreign.status()).toBe(409);
    } finally {
        await senderContext.close();
        await receiverContext.close();
    }
});

for (const s of scenarios) {
    test(s.world+' real login, debit, reload and timed completion',async({page,request})=>{
        test.setTimeout(300000);
        const errors=[];
        page.on('pageerror',e=>errors.push(e.message));
        expect((await request.get(url+'/v1/me/worlds')).status()).toBe(401);
        await signIn(page,s);
        if (s.pack==='reference-industrial') await industrialMarketSmoke(page);
        const before=await refresh(page);
        await page.selectOption('select[name=type]','economy.transfer');
        await expect(page.getByLabel('Recipient account')).toBeVisible();
        if (s.pack==='reference-industrial') {
            await page.getByRole('button',{name:'Find recipients'}).click();
            await expect(page.locator('#recipient-notice')).toHaveText('No recipients found.');
            await expect(page.locator('#recipient-directory option')).toHaveCount(1);
        }
        await page.getByLabel('Recipient account').fill('absent-recipient');
        await page.getByLabel('Units',{exact:true}).fill('1');
        const denied=page.waitForResponse(r=>r.url().endsWith('/economy/commands'));
        await page.getByRole('button',{name:'Submit action'}).click();
        expect((await denied).status()).toBe(409);
        await expect(page.locator('#status')).toHaveText('command_or_access_conflict');
        await page.selectOption('select[name=type]','economy.upgrade');
        await expect(page.getByLabel('Recipient account')).toBeHidden();
        expect(before.amount).toBe(s.rate);
        expect(before.level).toBe(0);
        expect(before.next.cost).toBe(s.cost);
        expect(before.next.duration).toBe(s.duration);
        await expect(page.locator('#stock')).toContainText('Next level 1');
        expect(before.stock).toBeGreaterThanOrEqual(s.initial);
        await page.selectOption('select[name=type]','economy.upgrade');
        const receipt=page.waitForResponse(r=>r.url().endsWith('/economy/commands') && r.request().method()==='POST');
        await page.getByRole('button',{name:'Submit action'}).click();
        const command=await receipt;
        expect(command.status()).toBe(200);
        const {data}=await command.json();
        expect(data.scheduledIds).toHaveLength(1);
        await expect(page.locator('#status')).toHaveText('Success');
        await signIn(page,s);
        const persisted=await refresh(page);
        expect(persisted.stock).toBeGreaterThanOrEqual(before.stock-s.cost);
        expect(persisted.stock).toBeLessThan(before.stock);
        expect(persisted.amount).toBe(s.rate);
        expect(persisted.pending.level).toBe(1);
        expect(persisted.pending.dueAt).toBe(data.effectiveAt+s.duration);
        expect(persisted.next).toBeNull();
        await expect(page.locator('#stock')).toContainText('Pending level 1');
        await expect(page.locator('#stock')).toContainText(new Date(persisted.pending.dueAt*1000).toISOString());
        // Reads cannot overtake due events. Run the actual bounded CLI worker,
        // as CRON would; a due boundary between worker and read may return 409.
        await expect.poll(async()=> {
            const output=execFileSync('php',['bin/worker.php',s.pack,s.world],{encoding:'utf8',timeout:15000});
            const report=JSON.parse(output);
            expect(report.world).toBe(s.world);
            expect(Number.isInteger(report.processed)).toBe(true);
            return (await refresh(page,true))?.amount ?? null;
        },{timeout:(s.duration+45)*1000,intervals:[5000]}).toBe(s.upgraded);
        const completed=await refresh(page);
        expect(completed.level).toBe(1);
        expect(completed.pending).toBeNull();
        expect(completed.next).toBeNull();
        await expect(page.locator('#stock')).toContainText('Maximum level');
        await page.getByRole('button',{name:'Sign out'}).click();
        await expect(page.getByRole('button',{name:'Sign in',exact:true})).toBeVisible();
        expect(errors).toEqual([]);
    });
}

test('expired authorization clears private UI and returns to login', async ({page})=>{
    await signIn(page,scenarios[0]);
    // Revoke the real bearer session through the real API while retaining the browser's in-memory token.
    const requestPromise=page.waitForRequest(r=>r.url().endsWith('/v1/me/worlds'));
    await page.getByRole('button',{name:'Reload available worlds'}).click();
    const req=await requestPromise;
    const authorization=(await req.allHeaders()).authorization;
    expect(authorization).toMatch(/^Bearer /);
    await expect(page.locator('#status')).toHaveText('Success');
    const response=await page.request.post(url+'/v1/auth/logout',{headers:{Authorization:authorization}});
    expect(response.status()).toBe(200);
    await page.getByRole('button',{name:'Reload available worlds'}).click();
    await expect(page.getByRole('button',{name:'Sign in',exact:true})).toBeVisible();
    await expect(page.locator('#stock')).toBeEmpty();
    await expect(page.locator('#market-offer option')).toHaveCount(1);
    await expect(page.locator('#market-notice')).toBeEmpty();
    await expect(page.locator('#game')).toBeHidden();
});
