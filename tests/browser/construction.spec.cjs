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
    await two.getByRole('button',{name:'Inspect building plan'}).click();
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
    await one.close();await two.close();
});
