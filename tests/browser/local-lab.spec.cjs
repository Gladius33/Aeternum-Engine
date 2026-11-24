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
            await expect(page.locator('[data-choose-game]')).toHaveCount(3);
            if(pack==='medieval'){
                for(const [device,width,height] of [['desktop',1440,900],['mobile',390,844]]){
                    await page.setViewportSize({width,height});
                    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
                    await page.screenshot({path:'browser-captures/library-'+device+'.png',animations:'disabled',fullPage:true});
                }
                await page.setViewportSize({width:1440,height:900});
            }
            await page.locator('[data-choose-game='+pack+']').click();
            await expect(page.locator('.library-profiles')).toBeVisible();
            await page.locator('[data-profile=bob]').click();
            await expect(page.locator('#login input[name=login]')).toHaveValue(pack+'-bob');
            await page.locator('[data-profile=alice]').click();
            await expect(page.locator('#login input[name=login]')).toHaveValue(pack+'-alice');
            await page.getByLabel('Password',{exact:true}).fill(password);
            await page.getByRole('button',{name:'Sign in',exact:true}).click();
            await expect(page.locator('#world-scene')).toHaveAttribute('data-theme',theme);
            await expect(page.locator('.scene-district')).toBeVisible({timeout:30000});
            await expect(page.locator('[data-resource-tile='+resource+'] strong')).not.toHaveText('—',{timeout:30000});
            await expect(page.locator('[data-workspace-panel=construction]')).toBeHidden();
            await expect(page.locator('[data-bot-count="10"]')).toBeVisible({timeout:30000});
            await page.locator('[data-bot-count="10"]').click();
            await expect(page.locator('[data-bot-name]')).toHaveCount(10);
            await page.locator('[data-bot-count="10"]').click();
            await expect(page.locator('#game-library')).toBeHidden();
            await expect(page.locator('[data-mission-step=place]')).toBeVisible();
            await expect(page.locator('#world-scene .game-ui-stage > .scene-toolbar')).toBeHidden();
            await expect(page.locator('#world-scene [data-landscape='+pack+']')).toBeAttached();
            await captureScene(page,'local-'+pack+'-first-visit');
            await page.locator('#locale-select').selectOption('fr');
            await expect(page.locator('[data-first-mission]')).toHaveText('Choisir le bâtiment');
            await page.locator('#locale-select').selectOption('en');
            await page.locator('[data-first-mission]').click();
            await expect(page.locator('[data-catalogue='+type+']')).toHaveAttribute('aria-pressed','true');
            await captureScene(page,'local-'+pack+'-first-build');
            const command=origin+'/v1/worlds/lab-'+pack+'/economy/commands';
            const isFinal=async(r,type)=>r.url()===command&&r.request().postDataJSON()?.type===type&&(r.status()!==409||!(await r.json()).retryable);
            const placed=page.waitForResponse(r=>isFinal(r,'building.place'));
            await page.locator('#world-scene [data-label=place]').click();
            expect((await placed).status()).toBe(200);
            await expect(page.locator('#status')).toHaveText('Success',{timeout:30000});
            await expect(page.locator('[data-mission-step=start]')).toBeVisible();
            await page.locator('[data-first-mission]').click();
            const building=page.locator('[data-district-building]').first();
            await expect(building).toBeVisible();
            await expect(building).toHaveAttribute('aria-pressed','true');
            const started=page.waitForResponse(r=>isFinal(r,'building.start'));
            await page.locator('#world-scene [data-label=startStage]').click();
            const startResponse=await started;
            // Preserve the response on failure, rather than masking a server rejection.
            expect(startResponse.status(),await startResponse.text()).toBe(200);
            await expect(page.locator('#status')).toHaveText('Success',{timeout:30000});
            // Exercise G11's automatic read-only refresh, not a competing
            // manual refresh that can collide with the same busy lock.
            await expect(building).toHaveAttribute('data-district-level','1',{timeout:90000});
            await expect(page.locator('#world-scene')).toHaveAttribute('aria-busy','false',{timeout:30000});
            await expect(page.locator('#status')).toHaveText('Success',{timeout:30000});
            await expect(page.locator('[data-mission-step=next]')).toBeVisible();
            await expect(page.locator('[data-district-decor='+type+']')).toBeAttached();
            await expect(page.locator('[data-district-site=approved]')).toBeVisible();
            await captureScene(page,'local-'+pack+'-domain');
            const actionType={medieval:'settlement.work',industrial:'industry.generate',guild:'market.create'}[pack];
            const actionKey={medieval:'work',industrial:'generate',guild:'trade'}[pack];
            const action=page.locator('[data-domain-action='+actionKey+']');
            await expect(action).toBeEnabled();
            if(pack==='guild'){
                await action.click();
                await expect(page.locator('[data-workspace-panel=market]')).toBeVisible();
                await expect(page.locator('#status')).toHaveText('Success',{timeout:30000});
                await page.locator('[data-trade-field=giveResource]').selectOption('silver');
                await page.locator('[data-trade-field=wantResource]').selectOption('grain');
                const traded=page.waitForResponse(r=>isFinal(r,actionType));
                await page.locator('.scene-trade-composer button').click();
                expect((await traded).status()).toBe(200);
            }else{
                const produced=page.waitForResponse(r=>isFinal(r,actionType));
                await action.click();
                expect((await produced).status()).toBe(200);
            }
            await expect(page.locator('#status')).toHaveText('Success',{timeout:30000});
            await page.locator('[data-game-nav=domain]').click();
            await captureScene(page,'local-'+pack+'-productive-domain');
            await expect.poll(async()=>{const response=await context.request.get(origin+'/lab-bots/roster.json',{maxRetries:2,timeout:10000,headers:{'Cache-Control':'no-cache','Connection':'close'}});expect(response.status()).toBe(200);const rows=(await response.json()).worlds['lab-'+pack];return rows.filter(b=>b.built>0&&b.lastAction!==null).length;},{timeout:120000,intervals:[3000,5000]}).toBe(10);
            await page.locator('[data-game-nav=construction]').click();
            await captureScene(page,'local-'+pack+'-construction');
            expect((await context.request.get(origin+'/v1/public/worlds')).status()).toBe(404);
            expect((await context.request.get(origin+'/lab/init.sh')).status()).toBe(404);
        }finally{await context.close();}
    });
}
