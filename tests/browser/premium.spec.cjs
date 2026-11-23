const {test,expect}=require('@playwright/test');
const {execFileSync}=require('node:child_process');
const origin='http://127.0.0.1:8765';
const world='e2e-medieval';
const path='/v1/worlds/'+world+'/premium/me';
const password='browser-only test password';
async function login(page,name) {
  await page.goto(origin+'/play.html');
  await page.getByLabel('Login',{exact:true}).fill(name);
  await page.getByLabel('Password',{exact:true}).fill(password);
  for(let i=0;i<3;i++) {
    const responsePromise=page.waitForResponse(r=>r.url().endsWith('/v1/auth/login'));
    await page.getByRole('button',{name:'Sign in',exact:true}).click();
    const response=await responsePromise;
    if(response.status()===200)break;
    expect(response.status()).toBe(429);
    if(i===2)throw new Error('M10 login rate quota exhausted');
    await page.waitForTimeout(65000);
  }
  await expect(page.locator('#game')).toBeVisible();
  // The shell becomes visible before asynchronous world/account discovery finishes.
  // Wait for the real server-selected membership, not an empty select value.
  await expect(page.locator('#balance select[name=world]')).toHaveValue(world);
  const accountSelect=page.locator('#balance select[name=account]');
  await expect(accountSelect).toHaveValue(/^[a-f0-9]{64}$/);
  return accountSelect.inputValue();
}
test('M10 cosmetic features are private, simulated and revocable in real Chromium',async({browser})=>{
  test.setTimeout(210000);
  const owner=await browser.newPage(),other=await browser.newPage();
  const actor=await login(owner,'browser-social-owner');
  await login(other,'browser-social-member');
  // No prior premium. Distinct browsers have distinct opaque sessions.
  const initial=owner.waitForRequest(r=>r.url().endsWith(path));
  await owner.getByRole('button',{name:'Refresh my cosmetics'}).click();
  const headers=await (await initial).allHeaders();
  expect(headers.authorization).toMatch(/^Bearer [a-f0-9]{64}$/);
  await expect(owner.locator('#premium-notice')).toHaveText('No cosmetic extras active.');
  await expect(owner.locator('#premium-badge')).toBeHidden();
  const noWrite=await owner.request.post(origin+path,{
    headers:{Authorization:headers.authorization},data:{feature:'profile_badge'}
  });
  expect(noWrite.status()).toBe(405);
  const noOther=await owner.request.get(origin+'/v1/worlds/e2e-industrial/premium/me',
    {headers:{Authorization:headers.authorization}});
  expect(noOther.status()).toBe(404);
  // An isolated, trusted setup operation (only in this disposable CI DB),
  // NEVER an HTTP request or user-controlled browser parameter.
  const seed=[
    "require 'vendor/autoload.php';",
    "$pdo=new PDO(getenv('AETERNUM_DSN'),getenv('AETERNUM_DB_USER'),getenv('AETERNUM_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);",
    "$s=$pdo->prepare('INSERT INTO engine_admin_scopes(world_id,operator_id,role,active,changed_at) VALUES (?,?,?,?,?)');",
    "$s->execute(['e2e-medieval','m10-browser-manager','manager',1,time()]);"
  ].join('');
  execFileSync('php',['-r',seed],{encoding:'utf8',timeout:15000});
  const adminEnv={...process.env,AETERNUM_ADMIN_OPERATOR:'m10-browser-manager',AETERNUM_PAYMENT_MODE:'test'};
  const invoke=(event,feature,transition)=>{
    const raw=execFileSync('php',['bin/admin-console.php','test-event',world,actor,feature,transition,event],
      {encoding:'utf8',timeout:15000,env:adminEnv});
    return JSON.parse(raw).data;
  };
  expect(invoke('browser-m10-01','profile_badge','grant').applied).toBe(true);
  expect(invoke('browser-m10-02','ui_theme','grant').applied).toBe(true);
  expect(invoke('browser-m10-01','profile_badge','grant').replayed).toBe(true);
  await owner.getByRole('button',{name:'Refresh my cosmetics'}).click();
  await expect(owner.locator('#premium-badge')).toBeVisible();
  await expect(owner.locator('html')).toHaveClass(/premium-theme/);
  await expect(owner.locator('#premium-features li')).toHaveCount(2);
  await other.getByRole('button',{name:'Refresh my cosmetics'}).click();
  await expect(other.locator('#premium-features li')).toHaveCount(0);
  await expect(other.locator('#premium-badge')).toBeHidden();
  expect(invoke('browser-m10-03','profile_badge','revoke').applied).toBe(true);
  expect(invoke('browser-m10-04','ui_theme','revoke').applied).toBe(true);
  // Replay of an older successful grant must NOT restore a revoked item.
  expect(invoke('browser-m10-01','profile_badge','grant').replayed).toBe(true);
  await owner.getByRole('button',{name:'Refresh my cosmetics'}).click();
  await expect(owner.locator('#premium-badge')).toBeHidden();
  await expect(owner.locator('#premium-features li')).toHaveCount(0);
  await expect(owner.locator('html')).not.toHaveClass(/premium-theme/);
  // Switching worlds or terminating the session must clear visual claims.
  await owner.getByRole('button',{name:'Sign out',exact:true}).click();
  await expect(owner.locator('#premium-badge')).toBeHidden();
  await owner.close();await other.close();
});
