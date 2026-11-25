const {expect}=require('@playwright/test');
async function captureScene(page,name){
    const original=page.viewportSize();
    try{
        for(const [device,width,height] of [['desktop',1440,900],['mobile',390,844]]){
            await page.setViewportSize({width,height});
            await page.evaluate(()=>window.scrollTo(0,0));
            await expect(page.locator('#world-scene')).toBeVisible();
            expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
            const scene=page.locator('#world-scene');
            // No opaque internal identifiers in the player-facing scene, even in rendered controls.
            expect((await scene.innerText())+' '+(await scene.locator('svg:not([hidden]) text').allTextContents()).join(' ')).not.toMatch(/\b(?:[a-f0-9]{24,128}|[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12})\b/i);
            await scene.screenshot({path:'browser-captures/'+name+'-'+device+'.png',animations:'disabled'});
        }
    }finally{if(original)await page.setViewportSize(original);}
}
module.exports={captureScene};
