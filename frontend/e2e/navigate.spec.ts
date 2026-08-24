import { expect, test } from '@playwright/test';

test('hubs catalog opens from nav and lists hubs', async ({ page }) => {
  await page.goto('/hubs');

  await expect(page.getByRole('link', { name: 'Программирование' }).first()).toBeVisible({ timeout: 10_000 });
});

test('hub page shows header and publications', async ({ page }) => {
  await page.goto('/');
  await page.locator('.pub-meta a[href^="/hubs/"]').first().click();

  await expect(page.locator('.text-h5')).toBeVisible();
  await expect(page.getByTestId('subscribe')).toBeVisible();

  const cards = page.locator('.habr-card').filter({ has: page.locator('.pub-title') });
  await expect(cards.first()).toBeVisible();
});

test('profile opens by author link and shows karma with tabs', async ({ page }) => {
  await page.goto('/');
  await page.locator('.pub-meta a[href^="/users/"]').first().click();

  await expect(page.getByText('карма')).toBeVisible();
  await expect(page.getByRole('tab', { name: 'Публикации' })).toBeVisible();

  await page.getByRole('tab', { name: 'Комментарии' }).click();
  await page.waitForTimeout(500);
  await page.getByRole('tab', { name: 'Подписчики' }).click();
  await expect(page.locator('.habr-card').last()).toBeVisible();
});
