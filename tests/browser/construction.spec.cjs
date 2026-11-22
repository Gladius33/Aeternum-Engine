const {test,expect}=require('@playwright/test');
const {execFileSync}=require('node:child_process');
const base='http://127.0.0.1:8765';
const commandUrl=base+'/v1/worlds/e2e-build/economy/commands';
const password='browser-only test password';
test('M04 pinned two-player construction debits, completes and isolates actor state',async ({browser})=>{
    test.setTimeout(280000);
    const one=await browser.newPage(),two=await browser.newPage();
    async function signIn(page,login) {
        await page.goto(base+'/play.html');
        await page.getByLabel('Login',{exact:true}).fill(login);
        for(let i=0;i<3;i++) {
            await page.getByLabel('Password',{exact:true}).fill(password);
            const received=page.waitForResponse(r=>r.url().endsWith('/v1/auth/login') && r.request().method()==='POST',{timeout:15000});
            await page.getByRole('button',{name:'Sign in',exact:true}).click();
            const response=await received;
            if(response.status()===200)break;
            expect(response.status()).toBe(429);
            if(i===2)throw new Error('Test user login rate limited across three windows');
            await page.waitForTimeout(65000);
        }
        await expect(page.locator('#balance select[name=world] option')).toHaveCount(1,{timeout:15000});
        await expect(page.locator('#balance select[name=world]')).toHaveValue('e2e-build');
    }
    await signIn(one,'browser-build-one');
    await signIn(two,'browser-build-two');
    // M04: both users place their own foundation through the actual UI and
    // the server-side actor/territory/entity checks, without operator CLI.
    for (const [page,id,plot] of [
        [one,'browser-own-mill-1','builder-1-plot'],
        [two,'browser-own-mill-2','builder-2-plot']
    ]) {
        const choices=page.waitForResponse(r=>r.url().endsWith('/construction/options'));
        await page.getByRole('button',{name:'Find construction sites'}).click();
        expect((await choices).status()).toBe(200);
        await expect(page.locator('#foundation-site option')).toHaveCount(1);
        await expect(page.locator('#foundation-site option')).toContainText(plot);
        await page.getByLabel('Building type',{exact:true}).selectOption('mill');
        await page.getByLabel('New building identifier (optional)').fill(id);
        const placed=page.waitForResponse(r=>r.url()===commandUrl &&
            r.request().postDataJSON().type==='building.place',{timeout:15000});
        await page.getByRole('button',{name:'Place foundation'}).click();
        expect((await placed).status()).toBe(200);
        await expect(page.locator('#building-details')).toContainText(id);
        await expect(page.locator('#building-details')).toContainText('Ready');
        await expect(page.locator('#owned-building-list')).toContainText(id);
    }

    await one.getByLabel('Owned building ID').fill('builder-1-mill');
    const ownRequest=one.waitForRequest(r=>r.url().endsWith('/buildings/builder-1-mill'));
    await one.getByRole('button',{name:'Inspect building plan'}).click();
    const own=await ownRequest;
    const token=(await own.allHeaders()).authorization;
    expect(token).toMatch(/^Bearer [a-f0-9]{64}$/);
    await expect(one.locator('#building-details')).toContainText('11 grain, 7 wood');
    await expect(one.locator('#building-details')).toContainText('Ready');
    await expect(one.getByRole('button',{name:'Begin construction'})).toBeVisible();
    await two.getByLabel('Owned building ID').fill('builder-2-mill');
    const twoRead=two.waitForRequest(r=>r.url().endsWith('/buildings/builder-2-mill'));
    await two.getByRole('button',{name:'Inspect building plan'}).click();
    const twoToken=(await (await twoRead).allHeaders()).authorization;
    expect(twoToken).toMatch(/^Bearer [a-f0-9]{64}$/);
    await expect(two.locator('#building-details')).toContainText('builder-2-mill');
    const ownAccount=await one.locator('#balance select[name=account]').inputValue();
    const twoAccount=await two.locator('#balance select[name=account]').inputValue();
    expect(ownAccount).not.toBe(twoAccount);
    const foreignRead=await one.request.get(base+'/v1/worlds/e2e-build/buildings/builder-2-mill',{headers:{Authorization:token}});
    expect(foreignRead.status()).toBe(404);
    const foreignWrite=await one.request.post(commandUrl,{headers:{Authorization:token},data:{
        id:'browser-build-steal',type:'building.start',
        payload:{building:'builder-2-mill',account:ownAccount,type:'mill'}
    }});
    expect(foreignWrite.status()).toBe(409);
    const responseReady=one.waitForResponse(r=>r.url()===commandUrl && r.request().method()==='POST');
    await one.getByRole('button',{name:'Begin construction'}).click();
    const accepted=await responseReady;
    expect(accepted.status()).toBe(200);
    const requestEnvelope=accepted.request().postDataJSON();
    const receipt=await accepted.json();
    expect(receipt.data.scheduledIds).toHaveLength(1);
    await expect(one.locator('#building-details')).toContainText('Construction pending');
    await expect(one.getByRole('button',{name:'Begin construction'})).toBeHidden();
    await expect(one.getByRole('button',{name:'Cancel construction'})).toBeVisible();
    const retry=await one.request.post(commandUrl,{headers:{Authorization:token},data:requestEnvelope});
    expect(retry.status()).toBe(200);
    expect(await retry.json()).toEqual(receipt);
    const duplicateStart=await one.request.post(commandUrl,{headers:{Authorization:token},data:{
        id:'browser-build-second-attempt',type:'building.start',
        payload:{building:'builder-1-mill',account:ownAccount,type:'mill'}
    }});
    expect(duplicateStart.status()).toBe(409);
    await one.selectOption('select[name=resource]','wood');
    const woodResponse=one.waitForResponse(r=>r.url().includes('/economy/overview/'));
    await one.getByRole('button',{name:'Refresh stock'}).click();
    expect((await (await woodResponse).json()).data.stock).toBe(93);
    await one.selectOption('select[name=resource]','grain');
    const grainResponse=one.waitForResponse(r=>r.url().includes('/economy/overview/'));
    await one.getByRole('button',{name:'Refresh stock'}).click();
    expect((await (await grainResponse).json()).data.stock).toBe(69);
    // This job uses only the approved disposable world registry, never a
    // network deployment, and calls the same production worker entrypoint.
    await one.waitForTimeout(5500);
    expect(process.env.AETERNUM_E2E_REGISTRY).toBeTruthy();
    const workerOutput=execFileSync('php',['bin/worker.php','m04-construction-fixture','e2e-build'],{
        encoding:'utf8',env:{...process.env,AETERNUM_MEDIEVAL_WORLD:'',AETERNUM_INDUSTRIAL_WORLD:'',
            AETERNUM_WORLD_REGISTRY:process.env.AETERNUM_E2E_REGISTRY}
    });
    expect(JSON.parse(workerOutput).processed).toBeGreaterThanOrEqual(1);
    await one.getByRole('button',{name:'Inspect building plan'}).click();
    await expect(one.locator('#building-details dd').nth(3)).toHaveText('1');
    await expect(one.locator('#building-details')).toContainText('30 grain, 12 wood');
    await expect(one.locator('#building-details')).toContainText('workshop level 1');
    // Missing a prerequisite must not trigger an additional stock debit.
    const denied=one.waitForResponse(r=>r.url()===commandUrl && r.request().method()==='POST');
    await one.getByRole('button',{name:'Begin construction'}).click();
    expect((await denied).status()).toBe(409);
    await expect(one.locator('#status')).toHaveText('command_or_access_conflict');
    const unchanged=await one.request.get(base+'/v1/worlds/e2e-build/economy/balances/'+ownAccount+'/wood',{
        headers:{Authorization:token}
    });
    expect(unchanged.status()).toBe(200);
    expect((await unchanged.json()).data.stock).toBe(93);
    // Independently test compensating cancellation through the second player's
    // own authenticated client. Canceled events still complete as safe no-ops.
    const bobStartWait=two.waitForResponse(r=>r.url()===commandUrl && r.request().method()==='POST');
    await two.getByRole('button',{name:'Begin construction'}).click();
    const bobStarted=await bobStartWait;
    expect(bobStarted.status()).toBe(200);
    await expect(two.getByRole('button',{name:'Cancel construction'})).toBeVisible();
    const bobCancelWait=two.waitForResponse(r=>r.url()===commandUrl && r.request().method()==='POST');
    await two.getByRole('button',{name:'Cancel construction'}).click();
    const bobCancelled=await bobCancelWait;
    expect(bobCancelled.status()).toBe(200);
    await expect(two.getByRole('button',{name:'Cancel construction'})).toBeHidden();
    await expect(two.locator('#building-details')).toContainText('Ready');
    const bobRefund=await two.request.get(base+'/v1/worlds/e2e-build/economy/balances/'+twoAccount+'/wood',{
        headers:{Authorization:twoToken}
    });
    expect(bobRefund.status()).toBe(200);
    expect((await bobRefund.json()).data.stock).toBe(100);
    const cancelRetry=await two.request.post(commandUrl,{
        headers:{Authorization:twoToken},data:bobCancelled.request().postDataJSON()
    });
    expect(cancelRetry.status()).toBe(200);
    expect(await cancelRetry.json()).toEqual(await bobCancelled.json());
    await two.waitForTimeout(5500);
    const canceledWorker=execFileSync('php',['bin/worker.php','m04-construction-fixture','e2e-build'],{
        encoding:'utf8',env:{...process.env,AETERNUM_MEDIEVAL_WORLD:'',AETERNUM_INDUSTRIAL_WORLD:'',
            AETERNUM_WORLD_REGISTRY:process.env.AETERNUM_E2E_REGISTRY}
    });
    expect(JSON.parse(canceledWorker).processed).toBeGreaterThanOrEqual(1);
    await two.getByRole('button',{name:'Inspect building plan'}).click();
    await expect(two.locator('#building-details dd').nth(3)).toHaveText('0');

    // Both players now develop their SELF-PLACED buildings and persist the
    // resulting levels after a real CLI worker cycle.
    const firstAccount=await one.locator('#balance select[name=account]').inputValue();
    const secondAccount=await two.locator('#balance select[name=account]').inputValue();
    const invalidSite=await one.request.post(commandUrl,{headers:{Authorization:token},data:{
        id:'browser-foreign-site',type:'building.place',payload:{
            account:firstAccount,building:'foreign-site-illegal',entity:firstAccount,
            territory:'builder-2-plot',type:'mill'
        }
    }});
    expect(invalidSite.status()).toBe(409);
    for (const [page,id] of [[one,'browser-own-mill-1'],[two,'browser-own-mill-2']]) {
        await page.getByLabel('Owned building ID').fill(id);
        await page.getByRole('button',{name:'Inspect building plan'}).click();
        const started=page.waitForResponse(r=>r.url()===commandUrl &&
            r.request().postDataJSON().type==='building.start');
        await page.getByRole('button',{name:'Begin construction'}).click();
        expect((await started).status()).toBe(200);
        await expect(page.locator('#building-details')).toContainText('Construction pending');
    }
    await one.waitForTimeout(5500);
    const finalWorker=execFileSync('php',['bin/worker.php','m04-construction-fixture','e2e-build'],{
        encoding:'utf8',env:{...process.env,AETERNUM_MEDIEVAL_WORLD:'',AETERNUM_INDUSTRIAL_WORLD:'',
            AETERNUM_WORLD_REGISTRY:process.env.AETERNUM_E2E_REGISTRY}
    });
    expect(JSON.parse(finalWorker).processed).toBeGreaterThanOrEqual(2);
    for (const [page,id] of [[one,'browser-own-mill-1'],[two,'browser-own-mill-2']]) {
        await page.getByLabel('Owned building ID').fill(id);
        await page.getByRole('button',{name:'Inspect building plan'}).click();
        await expect(page.locator('#building-details dd').nth(3)).toHaveText('1');
    }
    await one.close();await two.close();
});
