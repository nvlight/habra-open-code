import { defineConfig } from '@playwright/test';

const chromiumPath = process.env.CHROMIUM_PATH;

export default defineConfig({
  testDir: './e2e',
  timeout: 30_000,
  fullyParallel: false,
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://frontend-dev:9000',
    locale: 'ru-RU',
    screenshot: 'only-on-failure',
    ...(chromiumPath ? { launchOptions: { executablePath: chromiumPath } } : {})
  },
  reporter: [['list']]
});
