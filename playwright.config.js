const { defineConfig } = require('@playwright/test');
const path = require('node:path');
const reportDir = process.env.TICKETMAILER_E2E_REPORT_DIR || path.join(__dirname, '.cache', 'playwright');
module.exports = defineConfig({
  testDir: './tests/e2e',
  workers: 1,
  timeout: 60000,
  outputDir: path.join(reportDir, 'artifacts'),
  reporter: [
    ['list'],
    ['junit', { outputFile: path.join(reportDir, 'junit.xml') }],
    ['html', { outputFolder: path.join(reportDir, 'html'), open: 'never' }],
  ],
  use: { baseURL: process.env.TICKETMAILER_BASE_URL || 'http://127.0.0.1:8080', headless: true,
    screenshot: 'only-on-failure', trace: 'retain-on-failure' },
});
