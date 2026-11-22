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
    await expect(page.locator('#balance select[name=world] option')).toHaveCount(1);
    await expect(page.locator('#balance select[name=world]')).toHaveValue(scenario.world);
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
    const received=page.waitForResponse(r=>r.url().endsWith('/economy/market') && r.request().method()==='GET',{timeout:15000});
    await page.getByRole('button',{name:'Reload market',exact:true}).click();
    const response=await received;
    expect(response.status()).toBe(200);
    await expect(page.locator('#status')).toHaveText('Success');
    return (await response.json()).data;
}
async function marketCommand(page,button,type) {
    const received=page.waitForResponse(r=>r.url().endsWith('/economy/commands') && r.request().method()==='POST' && r.request().postDataJSON().type===type,{timeout:15000});
    await page.getByRole('button',{name:button,exact:true}).click();
    const response=await received;
    expect(response.status()).toBe(200);
    await expect(page.locator('#status')).toHaveText('Success');
    return {body:response.request().postDataJSON(),receipt:(await response.json()).data,authorization:(await response.request().allHeaders()).authorization};
}
async function industrialMarketSmoke(page) {
    page.setDefaultTimeout(15000);
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
    // Commit a real cancellation and deliberately lose its browser acknowledgement.
    let lostBody,lostReceipt,resolveLost,rejectLost;
    const lost=new Promise((resolve,reject)=>{resolveLost=resolve;rejectLost=reject;});
    await page.route('**/economy/commands',async route=>{
        try {
            lostBody=route.request().postDataJSON();
            expect(lostBody.type).toBe('market.cancel');
            const committed=await route.fetch();
            expect(committed.status()).toBe(200);
            lostReceipt=(await committed.json()).data;
            await route.abort('failed');
            resolveLost();
        } catch(e) {rejectLost(e); await route.abort('failed').catch(()=>{});}
    },{times:1});
    await page.getByRole('button',{name:'Cancel selected offer',exact:true}).click();
    await lost;
    await expect(page.locator('#status')).toHaveText('Failed to fetch');
    await expect(page.getByRole('button',{name:'Retry pending action',exact:true})).toBeVisible();
    // Reload removes the cancelled offer, but the saved command remains retryable.
    expect((await marketPage(page)).offers).toEqual([]);
    await expect(page.locator('#market-offer option')).toHaveCount(1);
    const cancelled=await marketCommand(page,'Retry pending action','market.cancel');
    expect(cancelled.body).toEqual(lostBody);
    expect(cancelled.receipt).toEqual(lostReceipt);
    expect(cancelled.body.payload.offer).toBe(created.body.id);
    await expect(page.getByRole('button',{name:'Retry pending action',exact:true})).toBeHidden();
    expect((await marketPage(page)).offers).toEqual([]);
    await expect(page.locator('#market-next')).toBeHidden();
    await expect(page.locator('#market-notice')).toHaveText('No open offers.');
}


test('two real browser players settle a persisted market barter exactly once',async ({browser})=>{
    const sellerContext=await browser.newContext(), buyerContext=await browser.newContext();
    try {
        const seller=await sellerContext.newPage(), buyer=await buyerContext.newPage();
        const scenario=scenarios[0];
        await signIn(seller,{...scenario,login:'browser-medieval-sender'});
        await signIn(buyer,{...scenario,login:'browser-medieval-receiver'});
        const sellerAccount=await seller.locator('select[name=account]').inputValue();
        const buyerAccount=await buyer.locator('select[name=account]').inputValue();
        expect(sellerAccount).not.toBe(buyerAccount);
        await buyer.selectOption('select[name=resource]','wood');
        const beforeBuyer=await refresh(buyer,true);
        expect(beforeBuyer).not.toBeNull();
        await seller.selectOption('select[name=resource]','grain');
        const beforeSeller=await refresh(seller,true);
        expect(beforeSeller).not.toBeNull();
        await seller.getByLabel('Offered resource',{exact:true}).selectOption('wood');
        await seller.getByLabel('Offered units',{exact:true}).fill('5');
        await seller.getByLabel('Requested resource',{exact:true}).selectOption('grain');
        await seller.getByLabel('Requested units',{exact:true}).fill('3');
        const created=await marketCommand(seller,'Create offer','market.create');
        expect(created.body.payload.seller).toBe(sellerAccount);
        expect(created.body.payload.offer).toBe(created.body.id);
        const own=(await marketPage(seller)).offers;
        expect(own).toHaveLength(1);
        await seller.getByLabel('Open offers',{exact:true}).selectOption(created.body.id);
        await seller.getByRole('button',{name:'Accept selected offer'}).click();
        await expect(seller.locator('#status')).toHaveText('Cannot buy own offer');
        const offers=(await marketPage(buyer)).offers;
        expect(offers).toHaveLength(1);
        expect(offers[0]).toMatchObject({id:created.body.id,seller:sellerAccount,giveResource:'wood',giveUnits:5,wantResource:'grain',wantUnits:3});
        await buyer.getByLabel('Open offers',{exact:true}).selectOption(created.body.id);
        await buyer.getByRole('button',{name:'Cancel selected offer'}).click();
        await expect(buyer.locator('#status')).toHaveText('Select the seller account to cancel');
        const accepted=await marketCommand(buyer,'Accept selected offer','market.accept');
        expect(accepted.body.payload).toEqual({offer:created.body.id,seller:sellerAccount,buyer:buyerAccount});
        const retry=await buyer.request.post(url+'/v1/worlds/'+scenario.world+'/economy/commands',{headers:{Authorization:accepted.authorization},data:accepted.body});
        expect(retry.status()).toBe(200);
        expect((await retry.json()).data).toEqual(accepted.receipt);
        const incompatible={...accepted.body,payload:{...accepted.body.payload,offer:'not-the-same-offer'}};
        const conflicting=await buyer.request.post(url+'/v1/worlds/'+scenario.world+'/economy/commands',{headers:{Authorization:accepted.authorization},data:incompatible});
        expect(conflicting.status()).toBe(409);
        expect((await marketPage(buyer)).offers).toEqual([]);
        const foreignWorld=await buyer.request.get(url+'/v1/worlds/e2e-industrial/economy/market',{headers:{Authorization:accepted.authorization}});
        expect(foreignWorld.status()).toBe(409);
        const afterBuyer=await refresh(buyer);
        const afterSeller=await refresh(seller);
        // Production accrues lazily; the delivered units are a strict lower bound.
        expect(afterBuyer.stock).toBeGreaterThanOrEqual(beforeBuyer.stock+5);
        expect(afterSeller.stock).toBeGreaterThanOrEqual(beforeSeller.stock+3);
        await signIn(buyer,{...scenario,login:'browser-medieval-receiver'});
        expect((await marketPage(buyer)).offers).toEqual([]);
    } finally {
        await sellerContext.close();
        await buyerContext.close();
    }
});

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


test('invitation-only live server enforces one-use code through the real browser',async({page})=>{
    test.setTimeout(90000);
    const fs=require('node:fs');
    const invitation=fs.readFileSync('tests/browser/.e2e-invite-token','utf8').trim();
    expect(invitation).toMatch(/^[a-f0-9]{64}$/);
    const invitedUrl='http://127.0.0.1:8766';
    await page.goto(invitedUrl+'/play.html');
    await expect(page.locator('#register')).toBeVisible();
    await expect(page.locator('#invite-wrap')).toBeVisible();
    await expect(page.getByLabel('Invitation code',{exact:true})).toHaveAttribute('required','');
    await page.getByLabel('New login',{exact:true}).fill('browser-invited-first');
    await page.getByLabel('New password (12 characters minimum)',{exact:true}).fill(password);
    await page.getByLabel('Confirm new password',{exact:true}).fill(password);
    await page.getByLabel('World to join',{exact:true}).selectOption('e2e-industrial');
    await page.getByLabel('Invitation code',{exact:true}).fill(invitation);
    const received=page.waitForResponse(r=>r.url().endsWith('/v1/auth/register') && r.request().method()==='POST');
    await page.getByRole('button',{name:'Create player',exact:true}).click();
    const response=await received;
    expect(response.status()).toBe(201);
    const {data}=await response.json();
    expect(data.world).toBe('e2e-industrial');
    await expect(page.locator('#game')).toBeVisible();
    await expect(page.locator('#balance select[name=account]')).toHaveValue(data.account);
    const denied=await page.request.post(invitedUrl+'/v1/auth/register',{data:{
        login:'browser-invited-thief',password,world:'e2e-industrial',inviteCode:invitation
    }});
    expect(denied.status()).toBe(409);
    await page.getByRole('button',{name:'Refresh stock'}).click();
    await expect(page.locator('#stock')).toContainText('stock');
});

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
    await expect(page.locator('#retry-command')).toBeHidden();
    await expect(page.locator('#game')).toBeHidden();
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
            await expect(page.locator('#recipient-notice')).toHaveText('Choose a recipient or enter an account ID manually.');
            await expect(page.locator('#recipient-directory option')).toHaveCount(2);
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

test('public signup provisions real players, supports retry, reconnect and account isolation',async ({browser})=>{
    test.setTimeout(90000);
    const context=await browser.newContext(), page=await context.newPage();
    try {
        await page.goto(url+'/play.html');
        await expect(page.locator('#register')).toBeVisible();
        await expect(page.locator('#register select option')).toHaveCount(2);
        await page.getByLabel('New login',{exact:true}).fill('browser-public-first');
        await page.getByLabel('New password (12 characters minimum)',{exact:true}).fill(password);
        await page.getByLabel('Confirm new password',{exact:true}).fill(password);
        await page.getByLabel('World to join',{exact:true}).selectOption('e2e-industrial');
        const received=page.waitForResponse(r=>r.url().endsWith('/v1/auth/register') && r.request().method()==='POST');
        await page.getByRole('button',{name:'Create player',exact:true}).click();
        const signup=await received;
        expect(signup.status()).toBe(201);
        const {data:first}=await signup.json();
        expect(first.token).toMatch(/^[a-f0-9]{64}$/);
        expect(first.world).toBe('e2e-industrial');
        expect(first.account).toMatch(/^[a-f0-9]{64}$/);
        await expect(page.locator('#game')).toBeVisible();
        await expect(page.locator('select[name=account]')).toHaveValue(first.account);
        const own=await refresh(page);
        expect(own.stock).toBeGreaterThanOrEqual(0);
        await page.getByRole('button',{name:'Sign out'}).click();
        await signIn(page,{...scenarios[1],login:'browser-public-first'});
        await expect(page.locator('select[name=account]')).toHaveValue(first.account);
        const second=await page.request.post(url+'/v1/auth/register',{data:{login:'browser-public-second',password,world:'e2e-industrial'}});
        expect(second.status()).toBe(201);
        const {data:other}=await second.json();
        expect(other.account).not.toBe(first.account);
        const denied=await page.request.get(url+'/v1/worlds/e2e-industrial/economy/overview/'+first.account+'/steel',{
            headers:{Authorization:'Bearer '+other.token}
        });
        expect(denied.status()).toBe(409);
        const granted=await page.request.get(url+'/v1/worlds/e2e-industrial/economy/overview/'+other.account+'/steel',{
            headers:{Authorization:'Bearer '+other.token}
        });
        expect(granted.status()).toBe(200);
        // Use the one-time recovery code issued after registration.
        expect(first.recoveryCode).toMatch(/^[a-f0-9]{64}$/);
        // Reload the client without a second logout: recovery must work even while an old bearer exists.
        await page.goto(url+'/play.html');
        await page.getByLabel('Recovery login',{exact:true}).fill('browser-public-first');
        await page.getByLabel('Recovery code',{exact:true}).fill(first.recoveryCode);
        await page.getByLabel('Replacement password',{exact:true}).fill('browser new password after recovery');
        await page.getByLabel('Confirm replacement password',{exact:true}).fill('browser new password after recovery');
        const resetResponse=page.waitForResponse(r=>r.url().endsWith('/v1/auth/recover') && r.request().method()==='POST');
        await page.getByRole('button',{name:'Reset password',exact:true}).click();
        const reset=await resetResponse;
        expect(reset.status()).toBe(200);
        const {data:recovered}=await reset.json();
        await expect(page.locator('#recovery-secret')).toContainText(recovered.recoveryCode);
        expect((await page.request.post(url+'/v1/auth/login',{data:{login:'browser-public-first',password}})).status()).toBe(401);
        await page.getByLabel('Login',{exact:true}).fill('browser-public-first');
        await page.getByLabel('Password',{exact:true}).fill('browser new password after recovery');
        await page.getByRole('button',{name:'Sign in',exact:true}).click();
        await expect(page.locator('#game')).toBeVisible();
        await expect(page.locator('#balance select[name=account]')).toHaveValue(first.account);
    } finally {
        await context.close();
    }
});
