const {test,expect}=require('@playwright/test');
const {execFileSync}=require('node:child_process');
const origin='http://127.0.0.1:8765';
const pass='browser-only test password';
const world='e2e-medieval';
const root=origin+'/v1/worlds/'+world+'/social';
async function signin(page,login) {
  await page.goto(origin+'/play.html');
  await page.getByLabel('Login',{exact:true}).fill(login);
  await page.getByLabel('Password',{exact:true}).fill(pass);
  for(let attempt=0;attempt<3;attempt++) {
    const loginResponse=page.waitForResponse(response=>response.url().endsWith('/v1/auth/login'));
    await page.getByRole('button',{name:'Sign in',exact:true}).click();
    const response=await loginResponse;
    if(response.status()===200)break;
    expect(response.status()).toBe(429);
    if(attempt===2)throw new Error('M09 browser login quota exhausted');
    await page.waitForTimeout(65000);
  }
  await expect(page.locator('#game')).toBeVisible();
  await expect(page.locator('#balance select[name=world]')).toHaveValue(world);
  return page.locator('#balance select[name=account]').inputValue();
}
test('M09 real Chromium: world-scoped alliance invite, role, transfer and logout',async({browser})=>{
  test.setTimeout(240000);
  const owner=await browser.newPage(),member=await browser.newPage();
  const ownerActor=await signin(owner,'browser-social-owner');
  const memberActor=await signin(member,'browser-social-member');
  expect(ownerActor).not.toBe(memberActor);
  await owner.getByLabel('New alliance ID').fill('browser-crew');
  await owner.getByLabel('Alliance name').fill('<img src=x onerror=alert(1)>');
  const created=owner.waitForResponse(r=>r.url()===root+'/alliances'&&r.request().method()==='POST');
  await owner.getByRole('button',{name:'Create alliance'}).click();
  expect((await created).status()).toBe(200);
  await expect(owner.locator('#social-directory')).toContainText('<img src=x onerror=alert(1)>');
  await expect(owner.locator('#social-directory img')).toHaveCount(0);
  await expect(owner.locator('#social-member-list')).toContainText(ownerActor);
  await owner.getByLabel('Invite actor ID').fill(memberActor);
  const invited=owner.waitForResponse(r=>r.url()===root+'/alliances/browser-crew/invite');
  await owner.getByRole('button',{name:'Invite player'}).click();
  expect((await invited).status()).toBe(200);
  const inbox=member.waitForResponse(r=>r.url()===root+'/invitations');
  await member.getByRole('button',{name:'Refresh invitations'}).click();
  expect((await inbox).status()).toBe(200);
  await expect(member.locator('#social-inbox option')).toHaveCount(2);
  await member.getByLabel('Pending invitations').selectOption('browser-crew');
  const accepted=member.waitForResponse(r=>r.url()===root+'/alliances/browser-crew/respond');
  await member.getByRole('button',{name:'Accept invitation'}).click();
  expect((await accepted).status()).toBe(200);
  await expect(member.locator('#social-member-list')).toContainText(memberActor+' — member');
  await owner.getByLabel('Recipient actor ID').fill(memberActor);
  await owner.getByLabel('Private message').fill('Welcome to the alliance <script>unsafe</script>');
  const sent=owner.waitForResponse(r=>r.url()===root+'/messages'&&r.request().method()==='POST');
  await owner.getByRole('button',{name:'Send private message'}).click();
  const sentResponse=await sent;
  expect(sentResponse.status()).toBe(200);
  const mailId=sentResponse.request().postDataJSON().id;
  expect(mailId).toMatch(/^[0-9a-f-]{36}$/);
  const delivered=member.waitForResponse(r=>r.url()===root+'/messages'&&r.request().method()==='GET');
  await member.getByRole('button',{name:'Refresh messages'}).click();
  expect((await delivered).status()).toBe(200);
  await expect(member.locator('#social-messages-list')).toContainText('Welcome to the alliance <script>unsafe</script>');
  await expect(member.locator('#social-messages-list script')).toHaveCount(0);
  await member.getByRole('button',{name:'Refresh notifications'}).click();
  await expect(member.locator('#social-notifications-list')).toContainText(mailId+' (unread)');
  const choices=member.locator('#social-notification-id option');
  // Invitation and direct-message notifications coexist; select the message
  // by its durable subject instead of assuming an inbox ordering/count.
  await expect(choices).toHaveCount(3);
  const notificationId=await choices.filter({hasText:'message — '+mailId}).getAttribute('value');
  expect(notificationId).toBeTruthy();
  await member.getByLabel('Unread notification').selectOption(notificationId);
  const read=member.waitForResponse(r=>r.url()===root+'/notifications/'+notificationId+'/read');
  await member.getByRole('button',{name:'Mark notification read'}).click();
  expect((await read).status()).toBe(200);
  await expect(member.locator('#social-notifications-list')).toContainText('(read)');
  await member.getByRole('button',{name:'Report this message'}).click();
  await expect(member.getByLabel('Message ID to report')).toHaveValue(mailId);
  await member.getByLabel('Report reason').fill('Unwanted contact in this test');
  const reported=member.waitForResponse(r=>r.url()===root+'/reports');
  await member.getByRole('button',{name:'Report message'}).click();
  const reportResponse=await reported;
  expect(reportResponse.status()).toBe(200);
  const reportId=reportResponse.request().postDataJSON().id;
  expect(reportId).toMatch(/^[0-9a-f-]{36}$/);
  await member.getByLabel('Blocked actor ID').fill(ownerActor);
  const blocked=member.waitForResponse(r=>r.url()===root+'/blocks'&&r.request().method()==='POST');
  await member.getByRole('button',{name:'Block player'}).click();
  expect((await blocked).status()).toBe(200);
  await expect(member.locator('#social-messages-list li')).toHaveCount(0);
  await expect(member.locator('#social-block-list')).toContainText(ownerActor);
  const unblocked=member.waitForResponse(r=>r.url()===root+'/blocks/remove');
  await member.getByRole('button',{name:'Unblock player'}).click();
  expect((await unblocked).status()).toBe(200);
  await expect(member.locator('#social-messages-list')).toContainText(mailId);
  // Real operator-only CLI processes the report against the same CI MariaDB
  // used by the browser API. No public admin endpoint or mock is introduced.
  const adjudication=execFileSync('php',
    ['bin/social-moderate.php',world,reportId,'hide'],{
      input:'Verified prohibited message in isolated browser test\\n',encoding:'utf8',
      env:{...process.env,AETERNUM_SOCIAL_MODERATOR:'ci-browser-operator'},
      timeout:15000
    });
  expect(JSON.parse(adjudication).reviewed).toBe(true);
  const hidden=member.waitForResponse(r=>r.url()===root+'/messages'&&r.request().method()==='GET');
  await member.getByRole('button',{name:'Refresh messages'}).click();
  expect((await hidden).status()).toBe(200);
  await expect(member.locator('#social-messages-list li')).toHaveCount(0);
  await member.getByRole('button',{name:'Show alliance leaderboard'}).click();
  await expect(member.locator('#social-leaderboard-list')).toContainText('browser-crew');
  await owner.getByRole('button',{name:'Inspect my alliance'}).click();
  await expect(owner.locator('#social-member-list')).toContainText(memberActor);
  await owner.getByLabel('Member actor ID').fill(memberActor);
  await owner.getByLabel('Role',{exact:true}).selectOption('officer');
  const promoted=owner.waitForResponse(r=>r.url()===root+'/alliances/browser-crew/role');
  await owner.getByRole('button',{name:'Update role'}).click();
  expect((await promoted).status()).toBe(200);
  await expect(owner.locator('#social-member-list')).toContainText(memberActor+' — officer');
  const transferred=owner.waitForResponse(r=>r.url()===root+'/alliances/browser-crew/owner');
  await owner.getByRole('button',{name:'Transfer ownership'}).click();
  expect((await transferred).status()).toBe(200);
  await expect(owner.locator('#social-member-list')).toContainText(memberActor+' — owner');
  const privateRequest=member.waitForRequest(request=>
    request.url()===root+'/alliances/browser-crew'&&request.method()==='GET');
  await member.getByRole('button',{name:'Inspect my alliance'}).click();
  await expect(member.locator('#social-member-list')).toContainText(memberActor+' — owner');
  // allHeaders includes authorization even where the compact projection omits it.
  const memberBearer=(await (await privateRequest).allHeaders()).authorization;
  expect(memberBearer).toMatch(/^Bearer [a-f0-9]{64}$/);
  const foreign=await member.request.get(origin+'/v1/worlds/e2e-industrial/social/alliances',
    {headers:{Authorization:memberBearer}});
  expect(foreign.status()).toBe(404);
  await owner.getByRole('button',{name:'Leave alliance'}).click();
  await expect(owner.locator('#social-member-list')).toHaveCount(0);
  await owner.getByRole('button',{name:'Sign out',exact:true}).click();
  await expect(owner.locator('#login')).toBeVisible();
  await signin(owner,'browser-social-owner');
  await owner.getByRole('button',{name:'Browse alliances'}).click();
  await expect(owner.locator('#social-directory')).toContainText('browser-crew');
  await owner.close();await member.close();
});
