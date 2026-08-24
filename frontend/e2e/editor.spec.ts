import { expect, test } from '@playwright/test';

async function loginAsAdmin(page: import('@playwright/test').Page): Promise<void> {
  await page.goto('/login');
  await page.getByTestId('login-field').fill('admin');
  await page.getByTestId('password-field').fill('password');
  await page.getByTestId('login-submit').click();
  await expect(page.locator('.q-avatar')).toBeVisible({ timeout: 10_000 });
}

test('editor requires auth', async ({ page }) => {
  await page.goto('/editor');

  await expect(page).toHaveURL(/\/login/);
});

test('create draft via editor, then publish it', async ({ page }, testInfo) => {
  const title = `e2e-статья ${Date.now()}`;

  await loginAsAdmin(page);
  await page.goto('/editor');

  await page.getByTestId('editor-title').fill(title);
  await page.getByTestId('editor-body').fill('# Заголовок\n\nТекст e2e-статьи про тестирование.');
  await page.getByTestId('editor-hubs').click();
  await page.getByRole('option', { name: 'Программирование' }).first().click();
  await page.keyboard.press('Escape');

  await page.getByTestId('editor-submit').click();

  await expect(page.getByText('Редактирование публикации')).toBeVisible({ timeout: 10_000 });

  await page.getByTestId('publish-button').click();
  await expect(page.locator('.q-badge').filter({ hasText: 'published' })).toBeVisible();
});

test('bookmarks page requires auth and renders empty state', async ({ page }) => {
  await page.goto('/bookmarks');
  await expect(page).toHaveURL(/\/login/);

  await loginAsAdmin(page);
  await page.goto('/bookmarks');
  await expect(page.getByText(/Мои закладки/)).toBeVisible();
});
