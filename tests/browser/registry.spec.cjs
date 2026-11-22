const {test,expect}=require('@playwright/test');
const base='http://127.0.0.1:8765';
test('third test pack is routed by registry through real browser, API and MariaDB',async ({page})=>{
    test.setTimeout(200000); // Preserve real authentication quotas across the preceding browser suites.
    await page.goto(base+'/play.html');
    const advertised=await page.request.get(base+'/v1/public/worlds');
    expect(advertised.status()).toBe(200);
    const configured=(await advertised.json()).data.worlds;
    expect(configured.some(w=>w.world==='e2e-sdk' && w.pack==='sdk-fixture')).toBe(true);
    await page.getByLabel('Login',{exact:true}).fill('browser-sdk');
    for(let attempt=0;attempt<3;attempt++) {
        await page.getByLabel('Password',{exact:true}).fill('browser-only test password');
        const received=page.waitForResponse(r=>r.url().endsWith('/v1/auth/login') && r.request().method()==='POST',{timeout:15000});
        await page.getByRole('button',{name:'Sign in',exact:true}).click();
        const response=await received;
        if(response.status()===200)break;
        expect(response.status()).toBe(429);
        if(attempt===2)throw new Error('Third pack login denied across three quota windows');
        await page.waitForTimeout(65000);
    }
    await expect(page.locator('#balance select[name=world] option')).toHaveCount(1,{timeout:15000});
    await expect(page.locator('#balance select[name=world]')).toHaveValue('e2e-sdk');
    await expect(page.locator('select[name=resource] option[value=amber]')).toHaveCount(1);
    await page.selectOption('select[name=resource]','amber');
    const account=await page.locator('select[name=account]').inputValue();
    const requestSeen=page.waitForRequest(r=>r.url().includes('/economy/overview/'));
    const responseSeen=page.waitForResponse(r=>r.url().includes('/economy/overview/'));
    await page.getByRole('button',{name:'Refresh stock',exact:true}).click();
    const req=await requestSeen;
    const response=await responseSeen;
    expect(response.status()).toBe(200);
    expect((await response.json()).data.stock).toBeGreaterThanOrEqual(90);
    const token=(await req.allHeaders()).authorization;
    expect(token).toMatch(/^Bearer [0-9a-f]{64}$/);
    const action={id:'m02-registry-browser-upgrade',type:'economy.upgrade',payload:{account,resource:'amber'}};
    const url=base+'/v1/worlds/e2e-sdk/economy/commands';
    const first=await page.request.post(url,{headers:{Authorization:token},data:action});
    expect(first.status()).toBe(200);
    const again=await page.request.post(url,{headers:{Authorization:token},data:action});
    expect(again.status()).toBe(200);
    expect(await again.json()).toEqual(await first.json());
    const foreign=await page.request.get(base+'/v1/worlds/e2e-medieval/economy/balances/'+account+'/wood',{headers:{Authorization:token}});
    expect(foreign.status()).toBe(409);
});
