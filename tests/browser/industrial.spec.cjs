const {test,expect}=require('@playwright/test');
const {captureScene}=require('./visual-capture.cjs');
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
    await expect(page.locator('#world-scene')).toHaveAttribute('data-theme','industrial');
    return page.locator('#balance select[name=account]').inputValue();
}
async function foundation(page,type,id) {
    const choices=page.waitForResponse(r=>r.url().endsWith('/construction/options'));
    await page.getByRole('button',{name:'Find construction sites'}).click();
    expect((await choices).status()).toBe(200);
    await expect(page.locator('#foundation-site option')).toHaveCount(1);
    await page.locator('#world-scene [data-catalogue="'+type+'"]').click();
    await page.locator('#scene-foundation-reference').fill(id);
    const accepted=page.waitForResponse(r=>r.url()===url&&r.request().postDataJSON().type==='building.place');
    await page.locator('#world-scene [data-label=place]').click();
    expect((await accepted).status()).toBe(200);
    await expect(page.locator('#building-details')).toContainText(id);
}
async function build(page,id,level,seconds) {
    await page.getByLabel('Owned building ID').fill(id);
    await page.getByRole('button',{name:'Inspect building plan'}).click();
    const accepted=page.waitForResponse(r=>r.url()===url&&r.request().postDataJSON().type==='building.start');
    await page.locator('#world-scene [data-building="'+id+'"]').click();
    await page.locator('#world-scene [data-label=startStage]').click();
    const result=await accepted;
    expect(result.status(),id+' stage '+level+': '+await result.text()).toBe(200);
    await page.waitForTimeout(seconds*1000+2100);
    worker();
    await page.getByRole('button',{name:'Inspect building plan'}).click();
    await expect(page.locator('#building-details dd').nth(3)).toHaveText(String(level));
    await expect(page.locator('#world-scene [data-building="'+id+'"]')).toHaveAttribute('data-level',String(level));
    await expect(page.locator('#world-scene [data-building="'+id+'"]')).toHaveAttribute('data-stage','built');
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
  // Genuine server-backed gameplay action at mobile viewport, not a CSS-only screenshot.
  await a.setViewportSize({width:375,height:812});
  await a.getByRole('button',{name:'Review industrial network'}).click();
  await expect(a.locator('#industrial-details')).toContainText('Grid power supply');
  await expect(a.locator('[data-stat=grid] strong')).not.toBeEmpty();
  const viewport=await a.evaluate(()=>({scroll:document.documentElement.scrollWidth,width:innerWidth}));
  expect(viewport.scroll).toBeLessThanOrEqual(viewport.width);
  await a.setViewportSize({width:1280,height:800});
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
    await a.locator('#world-scene [data-label=sync]').click();
    await expect(a.locator('#status')).toHaveText('Success');
    const territory=a.locator('#world-scene [data-territory]').first();
    await expect(territory).toBeVisible();
    await territory.click();
    await a.locator('#world-scene [data-label=from]').click();
    await expect(a.locator('#map-location')).not.toHaveValue('');
    await a.locator('#world-scene [data-label=zoomIn]').click();
    await expect(a.locator('.scene-atlas')).not.toHaveAttribute('viewBox','0 0 900 460');
    await a.locator('#world-scene [data-label=reset]').click();
    await test.info().attach('industrial-atlas-desktop',{body:await a.locator('#world-scene').screenshot({path:'browser-captures/industrial-atlas-desktop.png'}),contentType:'image/png'});
    await a.setViewportSize({width:375,height:812});
    await test.info().attach('industrial-atlas-mobile',{body:await a.locator('#world-scene').screenshot({path:'browser-captures/industrial-atlas-mobile.png'}),contentType:'image/png'});
    await a.setViewportSize({width:1280,height:800});
    await expect(a.locator('[data-building=m06-power]')).toBeVisible();
    await expect(a.locator('.command-confirmed')).toContainText('Foundation placed');
    await a.getByLabel('Language').selectOption('fr');
    await expect(a.locator('.command-confirmed')).toHaveText('Action confirmée: Fondation créée');
    await a.getByLabel('Langue').selectOption('en');
    // Independent player places a foundation via French UI, then resumes in English.
    await b.getByLabel('Language').selectOption('fr');
    const frChoices=b.waitForResponse(r=>r.url().endsWith('/construction/options'));
    await b.getByRole('button',{name:'Rechercher des sites de construction'}).click();
    expect((await frChoices).status()).toBe(200);
    await b.getByLabel('Type de bâtiment',{exact:true}).selectOption('power_plant');
    await b.getByLabel('Identifiant du nouveau bâtiment (facultatif)').fill('m06-b-power');
    const frPlaced=b.waitForResponse(r=>r.url()===url&&r.request().postDataJSON().type==='building.place');
    await b.getByRole('button',{name:'Placer la fondation'}).click();
    expect((await frPlaced).status()).toBe(200);
    await expect(b.locator('.command-confirmed')).toHaveText('Action confirmée: Fondation créée');
    await expect(b.locator('#building-details')).toContainText('m06-b-power');
    await b.getByLabel('Langue').selectOption('en');
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
    await a.locator('#world-scene [data-label=allStages]').click();
    await expect(a.locator('#status')).toHaveText('Success');
    await a.locator('#world-scene [data-label=allStocks]').click();
    await expect(a.locator('#status')).toHaveText('Success');
    await expect(a.locator('[data-reserve]').first()).toBeVisible();
    await expect(a.locator('[data-objective][data-achieved=false]')).toHaveCount(0);
    await expect(a.locator('[data-building=m06-power]')).toHaveAttribute('data-level','2');
    await captureScene(a,'industrial-victory');
    await b.getByRole('button',{name:'Review industrial network'}).click();
    await expect(b.locator('#industrial-victory')).not.toContainText('VICTORY');
    await expect(b.locator('#industrial-details')).toContainText('Goods delivered0 / 45');
    await build(b,'m06-b-power',1,3);
    await expect(b.locator('#industrial-victory')).not.toContainText('VICTORY');
    await a.getByRole('button',{name:'Sign out'}).click();
    await expect(a.locator('#login')).toBeVisible();
    await expect(a.locator('.command-confirmed')).toHaveCount(0);
    await expect(a.getByRole('navigation',{name:'Player navigation'}).getByRole('link',{name:'Sign in'})).toBeVisible();
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
