const {test,expect}=require('@playwright/test');
const origin='http://127.0.0.1:8769';

test('M07: language selection updates accessible headings without changing account input',async ({page})=>{
  await page.goto(origin+'/play.html');
  const login=page.locator('#login input[name=login]');
  await login.fill('local-user');
  await expect(page.locator('html')).toHaveAttribute('lang','en');
  await page.getByLabel('Language').selectOption('fr');
  await expect(page.locator('html')).toHaveAttribute('lang','fr');
  await expect(page.locator('#login h2')).toHaveText('Connexion');
  await expect(page.locator('#map-title')).toHaveText('Carte du monde');
  await expect(login).toHaveValue('local-user');
  await page.getByLabel('Langue').selectOption('en');
  await expect(page.locator('#login h2')).toHaveText('Sign in');
  await expect(page.locator('html')).toHaveAttribute('lang','en');
});

test('M07 Vue shell offers keyboard navigation, translated help, safe mobile width and network feedback',async ({page,context})=>{
  await page.setViewportSize({width:375,height:812});
  await page.goto(origin+'/play.html');
  await expect(page.locator('[data-client="vue-typescript"]')).toBeVisible();
  await expect(page.getByRole('navigation',{name:'Player navigation'}).getByRole('link',{name:'Sign in'})).toBeVisible();
  await page.keyboard.press('Tab');
  await expect(page.locator('.skip-link')).toBeFocused();
  await page.getByLabel('Language').selectOption('fr');
  await expect(page.getByRole('navigation',{name:'Navigation du joueur'}).getByRole('link',{name:'Connexion'})).toBeVisible();
  await page.getByText('Comment jouer',{exact:true}).click();
  await expect(page.getByText('Choisissez un monde et votre compte.',{exact:false})).toBeVisible();
  const measured=await page.evaluate(()=>({width:document.documentElement.scrollWidth, viewport:innerWidth}));
  expect(measured.width).toBeLessThanOrEqual(measured.viewport);
  await context.setOffline(true);
  await expect(page.getByRole('alert')).toContainText('Connexion réseau perdue');
  await context.setOffline(false);
  await expect(page.locator('.connection-restored')).toContainText('Connexion rétablie');
  await page.getByLabel('Langue').selectOption('en');
  await expect(page.locator('html')).toHaveAttribute('lang','en');
});
