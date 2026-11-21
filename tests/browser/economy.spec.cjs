const {test, expect}=require('@playwright/test');
const url='http://127.0.0.1:8765';
const password='browser-only test password';
const scenarios=[
    {login:'browser-medieval',world:'e2e-medieval',resource:'grain',initial:50,rate:12,cost:25,upgraded:20,duration:60},
    {login:'browser-industrial',world:'e2e-industrial',resource:'steel',initial:200,rate:75,cost:120,upgraded:110,duration:180}
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
async function refresh(page) {
    const received=page.waitForResponse(r=>r.url().includes('/economy/balances/'));
    await page.getByRole('button',{name:'Refresh stock'}).click();
    const response=await received;
    expect(response.status()).toBe(200);
    const {data}=await response.json();
    await expect(page.locator('#status')).toHaveText('Success');
    await expect(page.locator('#stock dd').nth(0)).toHaveText(String(data.stock));
    return data;
}
for (const s of scenarios) {
    test(s.world+' real login, debit, reload and timed completion',async({page,request})=>{
        test.setTimeout(300000);
        const errors=[];
        page.on('pageerror',e=>errors.push(e.message));
        expect((await request.get(url+'/v1/me/worlds')).status()).toBe(401);
        await signIn(page,s);
        const before=await refresh(page);
        expect(before.amount).toBe(s.rate);
        expect(before.stock).toBeGreaterThanOrEqual(s.initial);
        await page.selectOption('select[name=type]','economy.upgrade');
        const receipt=page.waitForResponse(r=>r.url().endsWith('/economy/commands') && r.request().method()==='POST');
        await page.getByRole('button',{name:'Submit action'}).click();
        const command=await receipt;
        expect(command.status()).toBe(200);
        const {data}=await command.json();
        expect(data.scheduledIds).toHaveLength(1);
        await expect(page.locator('#status')).toHaveText('Success');
        await page.reload();
        await signIn(page,s);
        const persisted=await refresh(page);
        expect(persisted.stock).toBeGreaterThanOrEqual(before.stock-s.cost);
        expect(persisted.amount).toBe(s.rate);
        // The live backend processes due events on reads; no mocked time or API responses.
        await expect.poll(async()=> (await refresh(page)).amount,{timeout:(s.duration+35)*1000,intervals:[5000]}).toBe(s.upgraded);
        await page.getByRole('button',{name:'Sign out'}).click();
        await expect(page.getByRole('button',{name:'Sign in',exact:true})).toBeVisible();
        expect(errors).toEqual([]);
    });
}
