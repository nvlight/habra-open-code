import { expect, test } from '@playwright/test';

test('login with seeded admin works', async ({ page }) => {
  await page.goto('/login');

  await page.getByTestId('login-field').fill('admin');
  await page.getByTestId('password-field').fill('password');
  await page.getByTestId('login-submit').click();

  await expect(page.locator('.habr-container').first()).toContainText('Админ', { timeout: 10_000 });
});

test('logout clears session', async ({ page }) => {
  await page.goto('/login');
  await page.getByTestId('login-field').fill('admin');
  await page.getByTestId('password-field').fill('password');
  await page.getByTestId('login-submit').click();
  await expect(page.locator('.q-avatar')).toBeVisible();

  await page.locator('.q-avatar').click();
  await page.getByTestId('logout').click();

  await expect(page.getByTestId('login-link')).toBeVisible();
});

test('register creates account and logs in', async ({ page, }, testInfo) => {
  const unique = Date.now();
  await page.goto('/register');

  await page.getByLabel('Имя').fill(`Тестер ${unique}`);
  await page.getByLabel('Логин').fill(`tester_${unique}`);
  await page.getByLabel('Email').fill(`tester_${unique}@test.dev`);
  await page.getByLabel('Пароль (мин. 8 символов)', { exact: true }).fill('secret1234');
  await page.getByLabel('Повторите пароль').fill('secret1234');
  await page.getByTestId('register-submit').click();

  await expect(page.locator('.habr-container').first()).toContainText(`Тестер ${unique}`, { timeout: 10_000 });
});
