const {test,expect}=require('@playwright/test');
test('browser can load enrollment and approved worlds over real loopback TLS',async({browser})=>{
    const context=await browser.newContext({ignoreHTTPSErrors:true});
    try {
        const page=await context.newPage();
        await page.goto('https://127.0.0.1:8777/play.html');
        expect(new URL(page.url()).protocol).toBe('https:');
        await expect(page.getByRole('heading',{name:'Aeternum',exact:true})).toBeVisible();
        await expect(page.locator('#register')).toBeVisible();
        await expect(page.locator('#register select option')).toHaveCount(2);
        const worlds=await context.request.get('https://127.0.0.1:8777/v1/public/worlds');
        expect(worlds.status()).toBe(200);
        expect((await worlds.json()).data.worlds).toHaveLength(2);
        // The proxy MUST discard spoofed client headers and write its observed
        // source address. This diagnostic exists only in a loopback CI router.
        const headers=await context.request.get('https://127.0.0.1:8777/__test/proxy-headers',{
            headers:{'X-Forwarded-For':'198.51.100.220','X-Real-IP':'198.51.100.221'}
        });
        expect(headers.status()).toBe(200);
        expect(await headers.json()).toEqual({forwarded:'127.0.0.1',realIp:null});
    } finally {await context.close();}
});
