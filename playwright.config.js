const { defineConfig } = require('@playwright/test');
module.exports = defineConfig({
  testDir: './tests/e2e',
  workers: 1,
  timeout: 60000,
  reporter: 'list',
  use: { baseURL: process.env.TICKETMAILER_BASE_URL || 'http://127.0.0.1:8080', headless: true,
    screenshot: 'only-on-failure', trace: 'retain-on-failure' },
});
