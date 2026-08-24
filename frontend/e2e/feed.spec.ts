import { expect, test } from '@playwright/test';

test('feed loads publication cards', async ({ page }) => {
  await page.goto('/');

  const cards = page.locator('.habr-card').filter({ has: page.locator('.pub-title') });
  await expect(cards.first()).toBeVisible();
  expect(await cards.count()).toBeGreaterThan(3);
});

test('switching to "Лучшие" tab keeps feed rendered', async ({ page }) => {
  await page.goto('/');

  await page.getByRole('tab', { name: 'Лучшие' }).click();

  const cards = page.locator('.habr-card').filter({ has: page.locator('.pub-title') });
  await expect(cards.first()).toBeVisible();
  expect(await cards.count()).toBeGreaterThan(0);
});

test('filtering by type news shows only news', async ({ page }) => {
  await page.goto('/');

  await page.getByLabel('Тип').click();
  await page.getByRole('option', { name: 'Новости' }).click();

  await expect(page.locator('.badge-type').first()).toHaveText(/Новость/i, { timeout: 10_000 });
});

test('publication page opens from feed', async ({ page }) => {
  await page.goto('/');

  const firstTitle = page.locator('.pub-title a').first();
  const title = await firstTitle.textContent();
  await firstTitle.click();

  await expect(page.locator('h1')).toHaveText(title ?? '');
  await expect(page.locator('.publication-body')).toBeVisible();
  await expect(page.getByText(/Комментарии/)).toBeVisible();
});
