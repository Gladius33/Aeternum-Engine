const {expect}=require('@playwright/test');
async function captureScene(page,name){
    const original=page.viewportSize();
    try{
        for(const [device,width,height] of [['desktop',1440,900],['mobile',390,844]]){
            await page.setViewportSize({width,height});
            await page.locator('#world-scene').scrollIntoViewIfNeeded();
            await expect(page.locator('#world-scene')).toBeVisible();
            expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
            await page.locator('#world-scene').screenshot({path:'browser-captures/'+name+'-'+device+'.png',animations:'disabled'});
        }
    }finally{if(original)await page.setViewportSize(original);}
}
module.exports={captureScene};
