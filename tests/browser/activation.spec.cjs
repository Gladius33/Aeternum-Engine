const {test,expect}=require('@playwright/test');
const fs=require('node:fs');
const {createHash}=require('node:crypto');
const path=require('node:path');
const url='https://127.0.0.1:8778';
// All loopback test servers deliberately share the same real REMOTE_ADDR
// and one durable peer quota. An HTTP 429 is a legitimate temporary refusal:
// wait for the real fixed-window rollover instead of weakening admission.
async function postAfterPeerWindow(request,endpoint,options) {
    for(let attempt=0;attempt<3;attempt++) {
        const response=await request.post(endpoint,options);
        if(response.status()!==429)return response;
        if(attempt===2)throw new Error('Shared auth peer quota did not reset');
        await new Promise(resolve=>setTimeout(resolve,65000));
    }
    throw new Error('Unreachable peer quota path');
}
test('HTTPS self-service mailbox proof creates no premature bearer, then player persists',async({browser})=>{
    test.setTimeout(380000);
    const context=await browser.newContext({ignoreHTTPSErrors:true});
    const login='browser-mailbox-verified';
    const password='browser verified mailbox strong password';
    const email='verified-browser@example.org';
    try {
        const page=await context.newPage();
        await page.goto(url+'/play.html?view=classic');
        expect(new URL(page.url()).protocol).toBe('https:');
        await expect(page.getByLabel('Email address for verification')).toBeVisible();
        await page.getByLabel('New login',{exact:true}).fill(login);
        await page.getByLabel('New password (12 characters minimum)',{exact:true}).fill(password);
        await page.getByLabel('Confirm new password',{exact:true}).fill(password);
        await page.getByLabel('World to join',{exact:true}).selectOption('e2e-medieval');
        await page.getByLabel('Email address for verification').fill(email);
        const signupPromise=page.waitForResponse(r=>r.url().endsWith('/v1/auth/register')&&r.request().method()==='POST');
        await page.getByRole('button',{name:'Create player',exact:true}).click();
        const signup=await signupPromise;
        expect(signup.status()).toBe(202);
        expect(await signup.json()).toEqual({data:{activationRequired:true}});
        await expect(page.locator('#activate')).toBeVisible();
        await expect(page.locator('#activation-resend')).toBeVisible();
        await expect(page.locator('#game')).toBeHidden();
        // Read a non-public, private-directory mail sink used ONLY in loopback CI.
        const sink=process.env.AETERNUM_TEST_ACTIVATION_OUTBOX;
        expect(sink).toBeTruthy();
        const codeFile=path.join(sink,createHash('sha256').update(login).digest('hex')+'.json');
        const delivered=JSON.parse(fs.readFileSync(codeFile,'utf8'));
        expect(delivered.login).toBe(login);
        expect(delivered.code).toMatch(/^[a-f0-9]{64}$/);
        const blocked=await postAfterPeerWindow(context.request,url+'/v1/auth/login',{data:{login,password}});
        expect(blocked.status()).toBe(401);
        await page.getByLabel('Activation login',{exact:true}).fill(login);
        let verified;
        for(let attempt=0;attempt<3;attempt++) {
            // The UI intentionally clears both secrets after each submission.
            // A shared real per-peer quota may have been consumed by the
            // preceding two-player and timed-offer browser scenarios.
            await page.getByLabel('Activation password',{exact:true}).fill(password);
            await page.getByLabel('Email activation code',{exact:true}).fill(delivered.code);
            const responsePromise=page.waitForResponse(r=>r.url().endsWith('/v1/auth/activate')&&r.request().method()==='POST');
            await page.getByRole('button',{name:'Activate player',exact:true}).click();
            const response=await responsePromise;
            if(response.status()===200) {verified=response;break;}
            expect(response.status()).toBe(429);
            if(attempt===2)throw new Error('Mailbox activation quota still exhausted after fixed-window retries');
            await page.waitForTimeout(65000);
        }
        expect(verified).toBeDefined();
        const {data:player}=await verified.json();
        expect(player.account).toMatch(/^[a-f0-9]{64}$/);
        expect(player.recoveryCode).toMatch(/^[a-f0-9]{64}$/);
        await expect(page.locator('#game')).toBeVisible();
        await expect(page.locator('#balance select[name=account]')).toHaveValue(player.account);
        await page.locator('select[name=resource]').selectOption('wood');
        const before=page.waitForResponse(r=>r.url().includes('/economy/overview/')&&r.request().method()==='GET');
        await page.getByRole('button',{name:'Refresh stock'}).click();
        expect((await before).status()).toBe(200);
        const command=page.waitForResponse(r=>r.url().endsWith('/economy/commands')&&r.request().method()==='POST');
        await page.getByRole('button',{name:'Submit action',exact:true}).click();
        expect((await command).status()).toBe(200);
        await expect(page.locator('#status')).toHaveText('Success');
        const replay=await postAfterPeerWindow(context.request,url+'/v1/auth/activate',{data:{login,password,code:delivered.code}});
        expect(replay.status()).toBe(401);
        // Reload and sign back in, ensuring the ready actor and world are durable.
        await page.goto(url+'/play.html?view=classic');
        await page.getByLabel('Login',{exact:true}).fill(login);
        await page.getByLabel('Password',{exact:true}).fill(password);
        for(let attempt=0;attempt<3;attempt++){
            const logged=page.waitForResponse(r=>r.url().endsWith('/v1/auth/login')&&r.request().method()==='POST');
            await page.getByRole('button',{name:'Sign in',exact:true}).click();
            const res=await logged;
            if(res.status()===200)break;
            expect(res.status()).toBe(429);
            if(attempt===2)throw new Error('Verified mailbox login quota remained exhausted');
            await page.waitForTimeout(65000);
            await page.getByLabel('Password',{exact:true}).fill(password);
        }
        await expect(page.locator('#game')).toBeVisible();
        await expect(page.locator('#balance select[name=account]')).toHaveValue(player.account);
        await expect(page.locator('#activate')).toBeHidden();
        await expect(page.locator('#activation-resend')).toBeHidden();
        await expect(page.locator('#recovery-secret')).toBeHidden();
        expect((await context.request.get(url+'/v1/me/worlds',{headers:{Authorization:'Bearer '+player.token}})).status()).toBe(200);
    } finally {await context.close();}
});


test('public enrollment without invitation or mailbox verification is disabled by default',async({request})=>{
    test.setTimeout(230000);
    const unsafeUrl='http://127.0.0.1:8768';
    const worlds=await request.get(unsafeUrl+'/v1/public/worlds');
    expect(worlds.status()).toBe(404);
    const denied=await postAfterPeerWindow(request,unsafeUrl+'/v1/auth/register',{data:{
        login:'browser-unsafe-mode',password:'strong unsafe attempted registration',world:'e2e-medieval'
    }});
    expect(denied.status()).toBe(404);
    expect((await denied.json()).error).toBe('not_found');
});
