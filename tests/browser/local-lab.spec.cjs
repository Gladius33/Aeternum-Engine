const {test,expect}=require('@playwright/test');
const {captureScene}=require('./visual-capture.cjs');
const origin='https://127.0.0.1:8443';
const password='aeternum-local-playtest-only';
for(const [pack,theme,type,resource] of [['medieval','medieval','lumberyard','wood'],['industrial','industrial','power_plant','steel'],['guild','guild','market_hall','silver']]){
    test('local lab '+pack+' real browser and autonomous worker',async({browser})=>{
        test.setTimeout(270000);
        const context=await browser.newContext({ignoreHTTPSErrors:true});
        try{
            const page=await context.newPage();
            await page.goto(origin+'/play.html');
            await page.getByLabel('Login',{exact:true}).fill(pack+'-alice');
            await page.getByLabel('Password',{exact:true}).fill(password);
            await page.getByRole('button',{name:'Sign in',exact:true}).click();
            await expect(page.locator('#world-scene')).toHaveAttribute('data-theme',theme);
            await expect(page.locator('.scene-district')).toBeVisible({timeout:30000});
            await expect(page.locator('[data-resource-tile='+resource+'] strong')).not.toHaveText('—');
            await expect(page.locator('[data-workspace-panel=construction]')).toBeHidden();
            await expect(page.locator('[data-bot-count=10]')).toBeVisible({timeout:30000});
            await page.locator('[data-bot-count=10]').click();
            await expect(page.locator('[data-bot-name]')).toHaveCount(10);
            await page.locator('[data-bot-count=10]').click();
            await page.locator('[data-game-nav=construction]').click();
            await page.locator('[data-catalogue='+type+']').click();
            const command=origin+'/v1/worlds/lab-'+pack+'/economy/commands';
            const placed=page.waitForResponse(r=>r.url()===command&&r.request().postDataJSON()?.type==='building.place');
            await page.locator('#world-scene [data-label=place]').click();
            expect((await placed).status()).toBe(200);
            await expect(page.locator('#status')).toHaveText('Success');
            await page.locator('[data-game-nav=domain]').click();
            const building=page.locator('[data-district-building]').first();
            await expect(building).toBeVisible();
            await building.click();
            const started=page.waitForResponse(r=>r.url()===command&&r.request().postDataJSON()?.type==='building.start');
            await page.locator('#world-scene [data-label=startStage]').click();
            expect((await started).status()).toBe(200);
            await expect(page.locator('#status')).toHaveText('Success');
            await expect.poll(async()=>{
                await page.locator('#world-scene [data-label=districtRefresh]').click();
                await expect(page.locator('#status')).toHaveText('Success');
                return building.getAttribute('data-district-level');
            },{timeout:90000,intervals:[3000,5000]}).toBe('1');
            await captureScene(page,'local-'+pack+'-domain');
            await expect.poll(async()=>{const response=await context.request.get(origin+'/lab-bots/roster.json');expect(response.status()).toBe(200);const rows=(await response.json()).worlds['lab-'+pack];return rows.filter(b=>b.built>0&&b.lastAction!==null).length;},{timeout:120000,intervals:[3000,5000]}).toBe(10);
            await page.locator('[data-game-nav=construction]').click();
            await captureScene(page,'local-'+pack+'-construction');
            expect((await context.request.get(origin+'/v1/public/worlds')).status()).toBe(404);
            expect((await context.request.get(origin+'/lab/init.sh')).status()).toBe(404);
        }finally{await context.close();}
    });
}
