const {test,expect}=require('@playwright/test');
const {execFileSync}=require('node:child_process');
const fs=require('node:fs');
const origin='https://127.0.0.1:8443';
const password='aeternum-local-playtest-only';
const fixtures=[['medieval','wood','grain'],['industrial','energy','steel'],['guild','spice','silver']];
const idle=async page=>await expect(page.locator('#world-scene')).toHaveAttribute('aria-busy','false',{timeout:30000});
async function enter(page,pack,profile){
    await page.goto(origin+'/play.html');
    await page.locator('[data-choose-game='+pack+']').click();
    await page.locator('[data-profile='+profile+']').click();
    await page.locator('#login input[name=password]').fill(password);
    let response;
    for(let attempt=0;attempt<2;attempt++){
        const reply=page.waitForResponse(r=>r.url()===origin+'/v1/auth/login');
        await page.locator('#login button').click();
        response=await reply;
        if(response.status()===200)break;
        expect(response.status()).toBe(429);
        if(attempt===0){
            // Respect the real one-minute peer quota; login clears password.
            await page.waitForTimeout(61000);
            await page.locator('#login input[name=password]').fill(password);
        }
    }
    expect(response.status()).toBe(200);
    const token=(await response.json()).data.token;
    await expect(page.locator('#world-scene')).toHaveAttribute('data-theme',pack);
    await expect(page.locator('.scene-district')).toBeVisible({timeout:30000});
    await idle(page);
    return {actor:await page.locator('#balance select[name=account]').inputValue(),auth:'Bearer '+token};
}
async function nav(page,key){
    await idle(page);await page.locator('[data-game-nav='+key+']').click();await idle(page);
}
async function post(page,url,click){
    await idle(page);
    const reply=page.waitForResponse(async r=>r.url()===url&&r.request().method()==='POST'&&
        !(url.endsWith('/economy/commands')&&[409,503].includes(r.status())&&(await r.json()).retryable===true));
    await click();
    const r=await reply;expect(r.status()).toBe(200);
    await idle(page);return r;
}
async function escrow(page,world,who,give,want){
    // Ordinary escrow, not a stock grant. Reserve enough of the incoming
    // resource to leave headroom even after a long preceding playtest.
    const base=origin+'/v1/worlds/'+world+'/economy/';
    const headers={Authorization:who.auth};
    const stock=await page.request.get(base+'overview/'+who.actor+'/'+give,{headers});
    expect(stock.status()).toBe(200);
    const balance=(await stock.json()).data;
    const units=Math.min(Math.floor(balance.stock/2),Math.floor(balance.capacity/4),450);
    expect(units).toBeGreaterThanOrEqual(1);
    const id='f05-hold-'+give+'-'+who.actor.slice(0,12);
    const payload={offer:id,seller:who.actor,giveResource:give,giveUnits:units,wantResource:want,wantUnits:units+1};
    for(let attempt=0;attempt<8;attempt++){
        const r=await page.request.post(base+'commands',{headers,data:{id,type:'market.create',payload}});
        if(r.status()===200)return;
        const data=await r.json();expect([409,503]).toContain(r.status());expect(data.retryable).toBe(true);
        await page.waitForTimeout(1000);
    }
    throw Error('Failed to reserve receiving capacity through normal escrow');
}
for(const [pack,give,want] of fixtures)test('F05 '+pack+' independent players, accessible UI and recovery',async({browser})=>{
    test.setTimeout(240000);
    const ca=await browser.newContext({ignoreHTTPSErrors:true,locale:'en-US',viewport:{width:1440,height:900},hasTouch:true});
    const cb=await browser.newContext({ignoreHTTPSErrors:true,locale:'en-US',viewport:{width:1440,height:900},hasTouch:true});
    try{
        const a=await ca.newPage(),b=await cb.newPage();
        const alice=await enter(a,pack,'alice'),bob=await enter(b,pack,'bob');
        expect(alice.actor).not.toBe(bob.actor);
        const world='lab-'+pack+'-f04',root=origin+'/v1/worlds/'+world;
        const command=root+'/economy/commands',social=root+'/social';
        // One real trade in each of the three themes. The first HTTP reply
        // is deliberately lost after a verified server success, not mocked.
        await escrow(a,world,alice,want,give);await escrow(b,world,bob,give,want);
        await nav(a,'market');
        await a.locator('[data-trade-field=giveResource]').selectOption(give);
        await a.locator('[data-trade-field=wantResource]').selectOption(want);
        await a.locator('[data-trade-field=giveUnits]').fill('1');
        await a.locator('[data-trade-field=wantUnits]').fill('2');
        let saved=null,committed=false;
        const intercept=async route=>{
            if(route.request().method()!=='POST'||route.request().postDataJSON()?.type!=='market.create'){await route.continue();return;}
            const response=await route.fetch();
            if(response.status()!==200){await route.fulfill({response});return;}
            saved=route.request().postDataJSON();committed=true;
            await route.abort('failed');
        };
        await a.route(command,intercept);
        await a.locator('.scene-trade-composer button').click();
        await expect(a.locator('[data-game-retry]')).toBeVisible({timeout:30000});
        await idle(a);
        expect(committed).toBe(true);expect(saved?.id).toBeTruthy();
        await expect(a.locator('.game-ui-status')).toContainText('Connection interrupted');
        await a.unroute(command,intercept);
        const receipt=await post(a,command,()=>a.locator('[data-game-retry]').click());
        expect(receipt.request().postDataJSON()).toEqual(saved);
        await expect(a.locator('[data-game-retry]')).toBeHidden();
        await nav(b,'market');
        await b.locator('[data-commerce-refresh]').click();await idle(b);
        const offer=b.locator('[data-offer="'+saved.payload.offer+'"]');
        await expect(offer).toHaveCount(1);await offer.click();
        const accepted=await post(b,command,()=>b.locator('[data-label=acceptTrade]').click());
        expect(accepted.request().postDataJSON().payload.offer).toBe(saved.payload.offer);
        await b.locator('[data-commerce-history=load]').click();await idle(b);
        await expect(b.locator('[data-trade-status=filled]')).not.toHaveCount(0);
        // Modern council: independent sessions and actual membership changes.
        await nav(a,'relations');await nav(b,'relations');
        const alliance='f05-'+pack+'-council';
        await a.locator('#social-new-id').fill(alliance);
        await a.locator('#social-new-name').fill('F05 council <img src=x>');
        await post(a,social+'/alliances',()=>a.locator('#social-create button[type=submit]').click());
        await expect(a.locator('#social-directory img')).toHaveCount(0);
        await a.locator('#social-invite-actor').fill(bob.actor);
        await post(a,social+'/alliances/'+alliance+'/invite',()=>a.locator('#social-invite-form button').click());
        await b.locator('#locale-select').selectOption('fr');
        await b.locator('#social-inbox-load').click();await idle(b);
        await b.locator('#social-inbox').selectOption(alliance);
        await post(b,social+'/alliances/'+alliance+'/respond',()=>b.locator('#social-accept').click());
        await expect(b.locator('#social-member-list')).toContainText(bob.actor+' — membre');
        const contact=b.locator('#social-member-list li').filter({hasText:alice.actor}).locator('[data-social-contact=message]');
        await contact.click();
        await expect(b.locator('[data-relations-view=messages]')).toBeVisible();
        await expect(b.locator('#social-recipient')).toHaveValue(alice.actor);
        const message='F05 '+pack+' verified <script>literal</script>';
        await b.locator('#social-message-text').fill(message);
        await post(b,social+'/messages',()=>b.locator('#social-send-form button').click());
        await a.locator('[data-relations-tab=messages]').click();
        await a.locator('#social-messages-refresh').click();await idle(a);
        const mail=a.locator('#social-messages-list li').filter({hasText:message});
        await expect(mail).toBeVisible();await expect(mail.locator('script')).toHaveCount(0);
        await mail.locator('[data-social-contact=block]').click();
        await post(a,social+'/blocks',()=>a.locator('#social-block-form button[type=submit]').click());
        await expect(a.locator('#social-block-list')).toContainText(bob.actor);
        await post(a,social+'/blocks/remove',()=>a.locator('#social-unblock').click());
        await a.locator('[data-relations-tab=messages]').click();
        await a.locator('#social-messages-refresh').click();await idle(a);
        await expect(mail).toBeVisible();await mail.locator('[data-social-report]').click();
        await a.locator('#social-report-reason').fill('Isolated moderation fixture');
        const report=await post(a,social+'/reports',()=>a.locator('#social-report-form button[type=submit]').click());
        const reportId=report.request().postDataJSON().id;
        // Existing operator-only console, on this test's isolated stack.
        const adjudication=execFileSync('docker',['compose','-f','compose.lab.yml','exec','-T',
            '-e','AETERNUM_SOCIAL_MODERATOR=ci-f05-reviewer','web','php','bin/social-moderate.php',world,reportId,'hide'],
            {input:'Confirmed isolated browser moderation fixture\n',encoding:'utf8',timeout:15000});
        expect(JSON.parse(adjudication).reviewed).toBe(true);
        await a.locator('[data-relations-tab=messages]').click();
        await a.locator('#social-messages-refresh').click();await idle(a);
        await expect(mail).toHaveCount(0);
        await b.locator('[data-game-exit]').click();
        await expect(b.locator('#login')).toBeVisible();
        await expect(b.locator('#social-message-text')).toHaveValue('');
        await expect(b.locator('#social-recipient')).toHaveValue('');
        const again=await enter(b,pack,'bob');
        expect(again.actor).toBe(bob.actor);
        await nav(b,'relations');
        await b.locator('#social-alliance-id').fill(alliance);
        await b.locator('#social-detail-form button[type=submit]').click();await idle(b);
        await expect(b.locator('#social-member-list')).toContainText(bob.actor);
        // Keyboard navigation and measured visual acceptance, not hardware FPS.
        const metrics=[];
        for(const [device,width,height] of [['desktop',1440,900],['mobile',390,844]]){
            await a.setViewportSize({width,height});await a.emulateMedia({reducedMotion:'reduce'});
            await idle(a);await nav(a,'domain');
            const first=a.locator('[data-game-nav=domain]'),second=a.locator('[data-game-nav=atlas]');
            await first.focus();await first.press('ArrowRight');await expect(second).toBeFocused();
            await second.press('Home');await expect(first).toBeFocused();
            const sample=await a.evaluate(async()=>{
                const scene=document.querySelector('.scene-district');
                const buttons=[...document.querySelectorAll('.game-ui-nav-button')];
                const color=s=>s.match(/\d+(?:\.\d+)?/g).map(Number);
                const luminance=rgb=>rgb.map(v=>v/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4).reduce((s,v,i)=>s+v*[.2126,.7152,.0722][i],0);
                const background=node=>{
                    const c=color(getComputedStyle(node).backgroundColor);
                    if(c.length<4||c[3]===1)return c.slice(0,3);
                    const under=node.parentElement?background(node.parentElement):[255,255,255];
                    return under.map((v,i)=>c[i]*c[3]+v*(1-c[3]));
                };
                const contrast=buttons.map(b=>{const style=getComputedStyle(b),f=luminance(color(style.color).slice(0,3)),bg=luminance(background(b));return (Math.max(f,bg)+.05)/(Math.min(f,bg)+.05);});
                const intervals=[];let last=await new Promise(requestAnimationFrame);
                for(let i=0;i<30;i++){const now=await new Promise(requestAnimationFrame);intervals.push(now-last);last=now;}
                intervals.sort((a,b)=>a-b);
                return {nodes:scene.querySelectorAll('*').length,frameP95Ms:intervals[Math.ceil(intervals.length*.95)-1],
                    overflow:document.documentElement.scrollWidth>innerWidth,minNavHeight:Math.min(...buttons.map(b=>b.getBoundingClientRect().height)),
                    minNavContrast:Math.min(...contrast),badBadges:[...scene.querySelectorAll('.scene-level-seal,.scene-level-badge')].filter(n=>getComputedStyle(n).display!=='none').length};
            });
            expect(sample.overflow).toBe(false);expect(sample.nodes).toBeLessThan(10000);
            expect(sample.frameP95Ms).toBeLessThan(250);expect(sample.minNavHeight).toBeGreaterThanOrEqual(44);
            expect(sample.minNavContrast).toBeGreaterThanOrEqual(4.5);expect(sample.badBadges).toBe(0);
            const started=Date.now();await nav(a,'objectives');
            const navMs=Date.now()-started;expect(navMs).toBeLessThan(30000);
            await expect(a.locator('[data-campaign-victory=true]')).toBeVisible();
            await expect(a.locator('.game-ui-stage')).toBeHidden();
            await a.locator('[data-campaign-refresh]').click();await idle(a);
            await a.evaluate(()=>window.scrollTo(0,0));
            await a.screenshot({path:'browser-captures/f05-'+pack+'-campaign-'+device+'.png',animations:'disabled'});
            await nav(a,'domain');await a.locator('.scene-district').screenshot({path:'browser-captures/f05-'+pack+'-district-'+device+'.png',animations:'disabled'});
            metrics.push({device,width,height,navMs,...sample});
        }
        fs.writeFileSync('browser-captures/f05-'+pack+'-experience.json',JSON.stringify({
            pack,independentActors:true,tradeCommitted:true,sameIdAfterLostReply:true,allianceAccepted:true,
            messageEscaped:true,blockAndUnblock:true,operatorModeration:true,reconnected:true,keyboard:true,measurements:metrics
        },null,2)+'\n');
    }finally{await Promise.allSettled([ca.close(),cb.close()]);}
});
