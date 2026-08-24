import { expect, test } from '@playwright/test';

async function loginAsAdmin(page: import('@playwright/test').Page): Promise<void> {
  await page.goto('/login');
  await page.getByTestId('login-field').fill('admin');
  await page.getByTestId('password-field').fill('password');
  await page.getByTestId('login-submit').click();
  await expect(page.locator('.q-avatar')).toBeVisible({ timeout: 10_000 });
}

test('authenticated user can vote for publication', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/');
  await page.locator('.pub-title a').first().click();

  await page.locator('[data-testid="pub-vote"] [data-testid="vote-up"]').click();

  const upArrow = page.locator('[data-testid="pub-vote"] [data-testid="vote-up"]');
  await expect(upArrow).toHaveClass(/vote-arrow--active-up/, { timeout: 10_000 });

  const ratingAfterVote = await page.locator('[data-testid="pub-vote"] .text-weight-bold').textContent();

  await page.reload();
  await expect(page.locator('.publication-body')).toBeVisible();
  const ratingPersisted = await page.locator('[data-testid="pub-vote"] .text-weight-bold').textContent();

  expect(Number(ratingPersisted)).toBe(Number(ratingAfterVote));
});

test('user can post a comment and see it in the tree', async ({ page }, testInfo) => {
  const body = `e2e-комментарий ${Date.now()}`;

  await loginAsAdmin(page);
  await page.goto('/');
  await page.locator('.pub-title a').first().click();

  await page.getByTestId('comment-input').fill(body);
  await page.getByRole('button', { name: 'Отправить' }).first().click();

  await expect(page.locator('.comment-body').filter({ hasText: body }).first()).toBeVisible({ timeout: 10_000 });
});
