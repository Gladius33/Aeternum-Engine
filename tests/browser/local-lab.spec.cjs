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
            const artResponse=page.waitForResponse(r=>r.url()===origin+'/living-art.js');
            await page.goto(origin+'/play.html');
            expect((await artResponse).status(),'G13 asset must be served by the actual lab image').toBe(200);
            await expect.poll(()=>page.evaluate(()=>typeof window.AeternumLivingArt?.site)).toBe('function');
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
            const commandBar=page.locator('.district-command');
            await expect(commandBar).toBeVisible();
            await expect(commandBar).toHaveAttribute('data-stage','foundation');
            await expect(building.locator('[data-living-site]')).toHaveCount(0);
            await expect(building.locator('[data-architecture]')).toHaveCount(0);
            await expect(page.locator('.living-economy[data-activity]')).toHaveCount(0);
            await expect(commandBar.locator('.district-command-cost')).not.toHaveCount(0);
            await expect(building.locator('.district-stage-marker[data-stage=foundation]')).toBeAttached();
            const primary=commandBar.locator('[data-district-command=primary]');
            await expect(primary).toHaveText('Start construction');
            const started=page.waitForResponse(r=>isFinal(r,'building.start'));
            await primary.click();
            const startResponse=await started;
            // Preserve the response on failure, rather than masking a server rejection.
            expect(startResponse.status(),await startResponse.text()).toBe(200);
            await expect(page.locator('#status')).toHaveText('Success',{timeout:30000});
            // Exercise G11's automatic read-only refresh, not a competing
            // manual refresh that can collide with the same busy lock.
            await expect(building).toHaveAttribute('data-district-level','1',{timeout:90000});
            await expect(page.locator('#world-scene')).toHaveAttribute('aria-busy','false',{timeout:30000});
            await expect(page.locator('#status')).toHaveText('Success',{timeout:30000});
            await expect(building.locator('.district-stage-marker[data-stage=built]')).toBeAttached();
            await expect(building.locator('[data-living-site='+type+']')).toHaveCount(1);
            await expect(building.locator('[data-architecture='+type+']')).toHaveAttribute('data-architecture-level','1');
            await expect(commandBar).toHaveAttribute('data-stage','built');
            await expect(page.locator('[data-mission-step=next]')).toBeVisible();
            for(const width of [390,1440]){
                await page.setViewportSize({width,height:900});
                await expect(commandBar).toBeVisible();
                expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
            }
            await expect(page.locator('[data-district-decor='+type+']')).toBeAttached();
            await expect(page.locator('[data-district-site=approved]')).toBeVisible();
            await captureScene(page,'local-'+pack+'-domain');
            const architecture=page.locator('.architectural-inspector');
            await expect(architecture).toBeVisible();
            await architecture.locator('[data-architecture-toggle]').click();
            await expect(architecture.locator('.architectural-view [data-architecture='+type+']')).toHaveAttribute('data-architecture-level','1');
            await expect(architecture.locator('.architectural-facts')).toContainText('Confirmed level');
            await captureScene(page,'g15-'+pack+'-architecture');
            await architecture.locator('[data-architecture-toggle]').click();
            await expect(architecture.locator('.architectural-body')).toBeHidden();
            if(pack==='medieval'){
                // The pinned campaign starts with one resident; lumber work
                // requires two. Build and populate a farm through the real UI.
                await expect(page.locator('[data-domain-action=work]')).toBeDisabled();
                await page.locator('[data-first-mission]').click();
                await expect(page.locator('[data-catalogue=farm]')).toHaveAttribute('aria-pressed','true');
                const farmPlaced=page.waitForResponse(r=>isFinal(r,'building.place'));
                await page.locator('#world-scene [data-label=place]').click();
                const farmReply=await farmPlaced;expect(farmReply.status(),await farmReply.text()).toBe(200);
                await expect(page.locator('#status')).toHaveText('Success',{timeout:30000});
                await page.locator('[data-first-mission]').click();
                const farm=page.locator('[data-district-building]').filter({has:page.locator('[data-model=farm]')});
                await expect(farm).toHaveAttribute('aria-pressed','true');
                const farmStarted=page.waitForResponse(r=>isFinal(r,'building.start'));
                await page.locator('.district-command [data-district-command=primary]').click();
                const startedReply=await farmStarted;expect(startedReply.status(),await startedReply.text()).toBe(200);
                await expect(farm).toHaveAttribute('data-district-level','1',{timeout:90000});
                await expect(page.locator('#world-scene')).toHaveAttribute('aria-busy','false',{timeout:30000});
                const welcomed=page.waitForResponse(r=>isFinal(r,'settlement.feed'));
                await page.locator('[data-domain-action=feed]').click();
                const welcomedReply=await welcomed;expect(welcomedReply.status(),await welcomedReply.text()).toBe(200);
                await expect(page.locator('#status')).toHaveText('Success',{timeout:30000});
                await expect(page.locator('[data-domain-action=work]')).toBeEnabled();
                await expect(page.locator('.living-economy')).toHaveAttribute('data-activity','settlement.feed');
                await expect(page.locator('[data-living-metric=population] strong')).toHaveText('2 / 4');
            }
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
                const response=await produced;expect(response.status(),await response.text()).toBe(200);
            }
            await expect(page.locator('#status')).toHaveText('Success',{timeout:30000});
            await page.locator('[data-game-nav=domain]').click();
            const living=page.locator('.living-economy');
            await expect(living).toHaveAttribute('data-activity',actionType);
            await expect(living.locator('[data-living-event]')).toHaveCount(1);
            await expect(page.locator('.scene-district [data-living-event="'+actionType+'"]')).toHaveCount(1);
            const operation=page.locator('.operation-panel');
            await expect(operation).toBeVisible();
            await expect(operation).toHaveAttribute('data-operation-type',actionType);
            await expect(operation.locator('[data-operation-scene="'+actionType+'"]')).toHaveCount(1);
            const opHistory=operation.locator('[data-operation-history]');
            await expect(opHistory.locator('option')).toHaveCount(pack==='medieval'?2:1);
            let replayRequests=0;
            const countReplay=r=>{if(r.method()==='POST'&&r.url().includes('/economy/commands'))replayRequests++;};
            page.on('request',countReplay);
            try{
                if(pack==='medieval'){
                    await opHistory.selectOption('1');
                    await expect(operation).toHaveAttribute('data-operation-type','settlement.feed');
                    await opHistory.selectOption('0');
                }
                await operation.locator('[data-operation-replay]').click();
                await expect(operation.locator('[data-operation-scene="'+actionType+'"]')).toHaveCount(1);
                await captureScene(page,'g17-'+pack+'-confirmed-operation');
                expect(replayRequests).toBe(0);
            }finally{page.off('request',countReplay);}

            await page.emulateMedia({reducedMotion:'reduce'});
            await expect(living.locator('.living-art-motion').first()).toHaveCSS('animation-name','none');
            await expect(operation.locator('.operation-motion').first()).toHaveCSS('animation-name','none');
            await captureScene(page,'g13-'+pack+'-living-economy');
            await page.emulateMedia({reducedMotion:'no-preference'});
            await captureScene(page,'local-'+pack+'-productive-domain');
            // G16: changes are purely presentational and must never issue commands.
            const atmosphere=page.locator('.ambience-panel');
            const worldScene=page.locator('#world-scene');
            const district=page.locator('.scene-district');
            const before=await district.locator('[data-district-building]').evaluateAll(nodes=>nodes.map(n=>[n.dataset.districtBuilding,n.dataset.districtLevel]));
            let visualCommands=0;
            const track=request=>{if(request.method()==='POST'&&request.url().includes('/economy/commands'))visualCommands++;};
            page.on('request',track);
            try{
                await expect(atmosphere).toBeVisible();
                await atmosphere.locator('[data-lighting-choice=dusk]').click();
                await expect(worldScene).toHaveAttribute('data-lighting','dusk');
                await expect(district.locator('[data-atmosphere-layer=district]')).toHaveAttribute('data-lighting','dusk');
                await captureScene(page,'g16-'+pack+'-dusk');
                await atmosphere.locator('[data-lighting-choice=night]').click();
                await expect(district.locator('[data-atmosphere-layer=district]')).toHaveAttribute('data-lighting','night');
                await expect(atmosphere.locator('[data-lighting-choice=night]')).toHaveAttribute('aria-pressed','true');
                await expect(district.locator('[data-atmosphere-depth=district]')).toHaveCount(1);
                await captureScene(page,'g16-'+pack+'-moonlight');
                await atmosphere.locator('[data-ambience-motion=toggle]').click();
                await expect(worldScene).toHaveAttribute('data-ambience-motion','off');
                await expect(district.locator('.ambience-motion').first()).toHaveCSS('animation-name','none');
                await atmosphere.locator('[data-ambience-motion=toggle]').click();
                await page.emulateMedia({reducedMotion:'reduce'});
                await expect(worldScene).toHaveAttribute('data-ambience-motion','off');
                await expect(atmosphere.locator('[data-ambience-motion=toggle]')).toBeDisabled();
                await page.emulateMedia({reducedMotion:'no-preference'});
                await expect(worldScene).toHaveAttribute('data-ambience-motion','on');
                expect(await district.locator('[data-district-building]').evaluateAll(nodes=>nodes.map(n=>[n.dataset.districtBuilding,n.dataset.districtLevel]))).toEqual(before);
                expect(visualCommands).toBe(0);
            }finally{page.off('request',track);}

            // G14: explore the actual server territory page, query registered
            // connections, request a route and return to the owned domain.
            await page.locator('[data-game-nav=atlas]').click();
            const atlas=page.locator('.scene-atlas'), exp=page.locator('.atlas-expedition');
            await expect(atlas).toBeVisible();
            await expect(exp).toBeVisible();
            await expect(atlas.locator('[data-atlas-landscape='+theme+']')).toHaveCount(1);
            await expect(atlas.locator('[data-atmosphere-layer=atlas]')).toHaveAttribute('data-lighting','night');
            await atmosphere.locator('[data-lighting-choice=day]').click();
            await expect(atlas.locator('[data-atmosphere-layer=atlas]')).toHaveAttribute('data-lighting','day');
            const territory=atlas.locator('[data-territory]').filter({has:page.locator('[data-atlas-region=own]')}).first();
            await expect(territory).toBeAttached();
            await exp.locator('[data-atlas-directory]').selectOption(await territory.getAttribute('data-territory'));
            await expect(territory).toBeVisible();
            await territory.click();
            await expect(territory).toHaveAttribute('aria-pressed','true');
            await expect(exp.locator('[data-atlas-command=domain]')).toBeVisible();
            await exp.locator('[data-atlas-command=origin]').click();
            const connection=page.waitForResponse(r=>r.url().includes('/map/locations/')&&r.url().endsWith('/links'));
            await exp.locator('[data-atlas-command=connections]').click();
            const linksResponse=await connection;expect(linksResponse.status(),await linksResponse.text()).toBe(200);
            await expect(page.locator('#status')).toHaveText('Success',{timeout:30000});
            await expect(exp.locator('.atlas-route-summary')).toContainText('Registered outgoing connections:');
            const others=atlas.locator('[data-territory][aria-pressed=false]');
            if(await others.count()){
                await exp.locator('[data-atlas-directory]').selectOption(await others.first().getAttribute('data-territory'));
                await exp.locator('[data-atlas-command=destination]').click();
                const planned=page.waitForResponse(r=>r.url().includes('/map/routes/'));
                await exp.locator('[data-atlas-command=route]').click();
                const result=await planned;expect(result.status(),await result.text()).toBe(200);
                const routeData=(await result.json()).data.route;
                await expect(exp).toHaveAttribute('data-route-state',routeData?'found':'none');
                if(routeData)await expect(exp.locator('.atlas-route-summary')).toContainText('Cost '+routeData.cost);
                else await expect(exp.locator('.atlas-route-summary')).toContainText('No directed route found by the server');
                await expect(page.locator('#status')).toHaveText('Success',{timeout:30000});
            }
            await atlas.focus();
            await page.keyboard.press('Home');
            const initialView=await atlas.getAttribute('viewBox');
            await page.keyboard.press('+');
            await expect(atlas).not.toHaveAttribute('viewBox',initialView);
            await page.keyboard.press('Home');
            await captureScene(page,'g14-'+pack+'-strategic-atlas');
            await exp.locator('[data-atlas-directory]').selectOption(await territory.getAttribute('data-territory'));
            await exp.locator('[data-atlas-command=domain]').click();
            await expect(page.locator('.scene-district')).toBeVisible();
            await expect(exp).toBeHidden();
            await expect.poll(async()=>{const response=await context.request.get(origin+'/lab-bots/roster.json',{maxRetries:2,timeout:10000,headers:{'Cache-Control':'no-cache','Connection':'close'}});expect(response.status()).toBe(200);const rows=(await response.json()).worlds['lab-'+pack];return rows.filter(b=>b.built>0&&b.lastAction!==null).length;},{timeout:120000,intervals:[3000,5000]}).toBe(10);
            // G18: real market, illustrations and a non-mutating draft.
            await page.locator('[data-game-nav=market]').click();
            await expect(page.locator('#status')).toHaveText('Success',{timeout:30000});
            const market=page.locator('[data-workspace-panel=market]');
            await expect(market.locator('[data-commerce-hall='+theme+']')).toHaveCount(1);
            await expect(market.locator('.commerce-logistics')).toBeVisible();
            const give=market.locator('[data-trade-field=giveResource]'),want=market.locator('[data-trade-field=wantResource]');
            const resources=await give.locator('option').evaluateAll(nodes=>nodes.map(n=>n.value));
            expect(resources.length).toBeGreaterThan(1);
            await give.selectOption(resources[0]);await want.selectOption(resources[0]);
            await expect(market.locator('.scene-trade-composer button')).toBeDisabled();
            await want.selectOption(resources[1]);
            await expect(market.locator('.commerce-preview [data-cargo-resource]')).toHaveCount(2);
            await expect(market.locator('.scene-trade-composer button')).toBeEnabled();
            const filterGive=market.locator('[data-commerce-filter=give]'),filterWant=market.locator('[data-commerce-filter=want]');
            await filterGive.selectOption(resources[0]);
            await expect(market.locator('[data-commerce-search]')).toBeDisabled();
            await filterWant.selectOption(resources[1]);
            const searched=page.waitForResponse(r=>r.url().includes('/economy/market/search/'));
            await market.locator('[data-commerce-search]').click();
            expect((await searched).status()).toBe(200);
            await expect(page.locator('#status')).toHaveText('Success',{timeout:30000});
            const historyResponse=page.waitForResponse(r=>r.url().includes('/economy/market/history/'));
            await market.locator('[data-commerce-history=load]').click();
            expect((await historyResponse).status()).toBe(200);
            await expect(page.locator('#status')).toHaveText('Success',{timeout:30000});
            await captureScene(page,'g18-'+pack+'-commerce');
            await page.locator('[data-game-nav=construction]').click();
            await captureScene(page,'local-'+pack+'-construction');
            expect((await context.request.get(origin+'/v1/public/worlds')).status()).toBe(404);
            expect((await context.request.get(origin+'/lab/init.sh')).status()).toBe(404);
        }finally{await context.close();}
    });
}
