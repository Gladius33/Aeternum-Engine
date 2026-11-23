const {test,expect}=require('@playwright/test');
test('browser can load enrollment and approved worlds over real loopback TLS',async({browser})=>{
    const context=await browser.newContext({ignoreHTTPSErrors:true});
    try {
        const page=await context.newPage();
        await page.goto('https://127.0.0.1:8777/play.html');
        expect(new URL(page.url()).protocol).toBe('https:');
        await expect(page.getByRole('heading',{name:'Aeternum',exact:true})).toBeVisible();
        await expect(page.locator('#register')).toBeVisible();
        const expectedWorldIds=['e2e-medieval','e2e-industrial','e2e-sdk'];
        for (const worldId of expectedWorldIds) {
            await expect(page.locator('#register select option[value="'+worldId+'"]')).toHaveCount(1);
        }
        const worlds=await context.request.get('https://127.0.0.1:8777/v1/public/worlds');
        expect(worlds.status()).toBe(200);
        const worldsData=(await worlds.json()).data.worlds;
        for (const worldId of expectedWorldIds) {
            expect(worldsData.filter(w=>w.world===worldId)).toHaveLength(1);
        }
        expect(new Set(worldsData.map(w=>w.world)).size).toBe(worldsData.length);
        // The proxy MUST discard spoofed client headers and write its observed
        // source address. This diagnostic exists only in a loopback CI router.
        const headers=await context.request.get('https://127.0.0.1:8777/__test/proxy-headers',{
            headers:{'X-Forwarded-For':'198.51.100.220','X-Real-IP':'198.51.100.221'}
        });
        expect(headers.status()).toBe(200);
        expect(await headers.json()).toEqual({forwarded:'127.0.0.1',realIp:null});
    } finally {await context.close();}
});


test('real TLS player can register, produce and sign back in without CLI provisioning',async({browser})=>{
    test.setTimeout(260000);
    const context=await browser.newContext({ignoreHTTPSErrors:true});
    const url='https://127.0.0.1:8777';
    const login='browser-tls-m01';
    const password='long TLS browser registration password';
    try {
        const page=await context.newPage();
        await page.goto(url+'/play.html');
        await expect(page.locator('#register')).toBeVisible();
        await page.getByLabel('New login',{exact:true}).fill(login);
        await page.getByLabel('New password (12 characters minimum)',{exact:true}).fill(password);
        await page.getByLabel('Confirm new password',{exact:true}).fill(password);
        await page.getByLabel('World to join',{exact:true}).selectOption('e2e-industrial');
        let signup;
        for(let attempt=0;attempt<3;attempt++){
            const received=page.waitForResponse(r=>r.url().startsWith(url+'/v1/auth/register') && r.request().method()==='POST');
            await page.getByRole('button',{name:'Create player',exact:true}).click();
            const response=await received;
            if(response.status()===201){signup=response;break;}
            expect(response.status()).toBe(429);
            if(attempt===2)throw new Error('HTTPS registration remained rate limited');
            await page.waitForTimeout(65000);
        }
        expect(signup).toBeDefined();
        const {data:player}=await signup.json();
        expect(player.account).toMatch(/^[a-f0-9]{64}$/);
        expect(player.recoveryCode).toMatch(/^[a-f0-9]{64}$/);
        await expect(page.locator('#game')).toBeVisible();
        await expect(page.locator('#balance select[name=account]')).toHaveValue(player.account);
        await page.locator('select[name=resource]').selectOption('steel');
        const before=page.waitForResponse(r=>r.url().includes('/economy/overview/') && r.request().method()==='GET');
        await page.getByRole('button',{name:'Refresh stock'}).click();
        expect((await before).status()).toBe(200);
        const command=page.waitForResponse(r=>r.url().endsWith('/economy/commands') && r.request().method()==='POST');
        await page.getByRole('button',{name:'Submit action',exact:true}).click();
        const confirmed=await command;
        expect(confirmed.status()).toBe(200);
        await expect(page.locator('#status')).toHaveText('Success');
        // Simulate losing the in-memory bearer and the one-time recovery secret.
        await page.goto(url+'/play.html');
        await expect(page.locator('#recovery-secret')).toBeHidden();
        await page.getByLabel('Login',{exact:true}).fill(login);
        for(let attempt=0;attempt<3;attempt++){
            await page.getByLabel('Password',{exact:true}).fill(password);
            const received=page.waitForResponse(r=>r.url().startsWith(url+'/v1/auth/login') && r.request().method()==='POST');
            await page.getByRole('button',{name:'Sign in',exact:true}).click();
            const response=await received;
            if(response.status()===200)break;
            expect(response.status()).toBe(429);
            if(attempt===2)throw new Error('HTTPS reconnect remained rate limited');
            await page.waitForTimeout(65000);
        }
        await expect(page.locator('#game')).toBeVisible();
        await expect(page.locator('#balance select[name=account]')).toHaveValue(player.account);
        const persisted=page.waitForResponse(r=>r.url().includes('/economy/overview/') && r.request().method()==='GET');
        await page.getByRole('button',{name:'Refresh stock'}).click();
        expect((await persisted).status()).toBe(200);
        await expect(page.locator('#stock')).toContainText('stock');
    } finally {await context.close();}
});
