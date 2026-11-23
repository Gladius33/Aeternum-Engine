const {test,expect}=require('@playwright/test');
const {execFileSync}=require('node:child_process');
const origin='http://127.0.0.1:8769';
const world='e2e-industrial-v3';
const password='browser-only test password';
const url=origin+'/v1/worlds/'+world+'/economy/commands';
function worker() {
    const output=execFileSync('php',['bin/worker.php','reference-industrial',world],{
        encoding:'utf8',
        env:{...process.env,AETERNUM_MEDIEVAL_WORLD:'',AETERNUM_INDUSTRIAL_WORLD:'',
            AETERNUM_DSN:process.env.AETERNUM_E2E_M05_DSN,
            AETERNUM_DB_USER:'aeternum_m05',AETERNUM_DB_PASSWORD:'test-db-only',
            AETERNUM_WORLD_REGISTRY:process.env.AETERNUM_E2E_REGISTRY},
        timeout:30000
    });
    expect(JSON.parse(output).processed).toBeGreaterThanOrEqual(1);
}
async function register(page,login) {
    await page.goto(origin+'/play.html');
    await page.getByLabel('New login',{exact:true}).fill(login);
    await page.getByLabel('New password (12 characters minimum)',{exact:true}).fill(password);
    await page.getByLabel('Confirm new password',{exact:true}).fill(password);
    await page.getByLabel('World to join',{exact:true}).selectOption(world);
    for(let attempt=0;attempt<5;attempt++) {
        const reply=page.waitForResponse(r=>r.url().endsWith('/v1/auth/register')&&r.request().method()==='POST');
        await page.getByRole('button',{name:'Create player',exact:true}).click();
        const result=await reply;
        if(result.status()===201)break;
        expect(result.status()).toBe(429);
        if(attempt===4)throw Error('M06 signup quota windows exhausted');
        await page.waitForTimeout(65000);
    }
    await expect(page.locator('#industrial-panel')).toBeVisible();
    return page.locator('#balance select[name=account]').inputValue();
}
async function foundation(page,type,id) {
    const choices=page.waitForResponse(r=>r.url().endsWith('/construction/options'));
    await page.getByRole('button',{name:'Find construction sites'}).click();
    expect((await choices).status()).toBe(200);
    await expect(page.locator('#foundation-site option')).toHaveCount(1);
    await page.getByLabel('Building type',{exact:true}).selectOption(type);
    await page.getByLabel('New building identifier (optional)').fill(id);
    const accepted=page.waitForResponse(r=>r.url()===url&&r.request().postDataJSON().type==='building.place');
    await page.getByRole('button',{name:'Place foundation'}).click();
    expect((await accepted).status()).toBe(200);
    await expect(page.locator('#building-details')).toContainText(id);
}
async function build(page,id,level,seconds) {
    await page.getByLabel('Owned building ID').fill(id);
    await page.getByRole('button',{name:'Inspect building plan'}).click();
    const accepted=page.waitForResponse(r=>r.url()===url&&r.request().postDataJSON().type==='building.start');
    await page.getByRole('button',{name:'Begin construction'}).click();
    const result=await accepted;
    expect(result.status(),id+' stage '+level+': '+await result.text()).toBe(200);
    await page.waitForTimeout(seconds*1000+2100);
    worker();
    await page.getByRole('button',{name:'Inspect building plan'}).click();
    await expect(page.locator('#building-details dd').nth(3)).toHaveText(String(level));
}
async function operation(page,type,button) {
    const reply=page.waitForResponse(r=>r.url()===url&&r.request().postDataJSON().type===type);
    await page.getByRole('button',{name:button}).click();
    const result=await reply;
    expect(result.status(),type+': '+await result.text()).toBe(200);
}
test('M06 two players complete an isolated industrial network and freight victory',async({browser})=>{
    test.setTimeout(750000);
    const a=await browser.newPage(),b=await browser.newPage();
    const alice=await register(a,'m06-browser-alice');
    const bob=await register(b,'m06-browser-bob');
  await expect(a.getByRole('navigation',{name:'Player navigation'}).getByRole('link',{name:'Game objectives'})).toBeVisible();
  await a.getByLabel('Language').selectOption('fr');
  await expect(a.getByRole('navigation',{name:'Navigation du joueur'}).getByRole('link',{name:'Objectifs de jeu'})).toBeVisible();
  await expect(a.locator('#industrial-title')).toHaveText('Réseau industriel — tutoriel et objectif');
  await a.getByLabel('Langue').selectOption('en');
    expect(alice).not.toBe(bob);
    await a.getByRole('button',{name:'Review industrial network'}).click();
    await expect(a.locator('#industrial-details')).toContainText('Goods delivered0 / 45');
    for(const [type,id] of [
        ['power_plant','m06-power'],['steelworks','m06-steel'],
        ['warehouse','m06-store'],['freight_terminal','m06-freight']
    ])await foundation(a,type,id);
    await foundation(b,'power_plant','m06-b-power');
    const status=a.waitForRequest(r=>r.url().includes('/industrial/status/'));
    await a.getByRole('button',{name:'Review industrial network'}).click();
    const bearer=(await (await status).allHeaders()).authorization;
    expect(bearer).toMatch(/^Bearer [a-f0-9]{64}$/);
    const foreign=await a.request.get(origin+'/v1/worlds/'+world+'/industrial/status/'+bob,
        {headers:{Authorization:bearer}});
    expect(foreign.status()).toBe(404);
    const steal=await a.request.post(url,{headers:{Authorization:bearer},data:{
        id:'m06-steal',type:'industry.generate',payload:{account:bob}
    }});
    expect(steal.status()).toBe(409);
    await build(a,'m06-power',1,3);
    await operation(a,'industry.generate','Generate grid electricity');
    await build(a,'m06-steel',1,4);
    await build(a,'m06-store',1,4);
    await build(a,'m06-power',2,5);
    await operation(a,'industry.generate','Generate grid electricity');
    await build(a,'m06-steel',2,7);
    await build(a,'m06-store',2,6);
    await build(a,'m06-freight',1,8);
    await a.getByRole('button',{name:'Review industrial network'}).click();
    await expect(a.locator('#industrial-details')).toContainText('Grid power supply160');
    await expect(a.locator('#industrial-victory')).not.toContainText('VICTORY');
    await operation(a,'industry.generate','Generate grid electricity');
    await operation(a,'industry.refine','Manufacture goods');
    await operation(a,'industry.ship','Dispatch freight');
    await a.waitForTimeout(3300);
    await operation(a,'industry.refine','Manufacture goods');
    await operation(a,'industry.ship','Dispatch freight');
    await a.waitForTimeout(3300);
    await operation(a,'industry.ship','Dispatch freight');
    await expect(a.locator('#industrial-victory')).toContainText('VICTORY');
    await expect(a.locator('#industrial-details')).toContainText('Goods delivered45 / 45');
    await b.getByRole('button',{name:'Review industrial network'}).click();
    await expect(b.locator('#industrial-victory')).not.toContainText('VICTORY');
    await expect(b.locator('#industrial-details')).toContainText('Goods delivered0 / 45');
    await build(b,'m06-b-power',1,3);
    await expect(b.locator('#industrial-victory')).not.toContainText('VICTORY');
    await a.getByRole('button',{name:'Sign out'}).click();
    await expect(a.locator('#login')).toBeVisible();
    await a.getByLabel('Login',{exact:true}).fill('m06-browser-alice');
    await a.getByLabel('Password',{exact:true}).fill(password);
    const authenticated=a.waitForResponse(r=>r.url().endsWith('/v1/auth/login'));
    await a.getByRole('button',{name:'Sign in',exact:true}).click();
    expect((await authenticated).status()).toBe(200);
    await expect(a.locator('#industrial-panel')).toBeVisible();
    await a.getByRole('button',{name:'Review industrial network'}).click();
    await expect(a.locator('#industrial-victory')).toContainText('VICTORY');
    await a.close();await b.close();
});
