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
