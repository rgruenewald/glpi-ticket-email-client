const { test, expect } = require('@playwright/test');
const { compose } = require('./status-helpers');

async function login(page) {
  await page.goto('/');
  await page.locator('#login_name').fill('glpi');
  await page.locator('#login_password').fill('glpi');
  await page.locator('[name=submit]').click();
  await expect(page.locator('#login_name')).toHaveCount(0);
}

for (const mode of ['email', 'internal_note']) {
 test('Escape closes compose from the ' + mode + ' editor', async ({ page }) => {
  const { ticket } = JSON.parse(compose('exec', '-T', '--user', 'www-data', 'glpi', 'php', '/usr/local/bin/e2e-fixture.php', 'basic'));
  await login(page);
  await page.goto('/front/ticket.form.php?id=' + ticket);
  await page.locator('.ticketmailer-action-reply').click();
  const modal = page.locator('[data-ticketmailer-compose-modal][id$=_email]');
  await modal.locator('[data-delivery-mode][value=' + mode + ']').check();
  const body = modal.locator('[data-ticketmailer-panel=' + mode + ']').frameLocator('iframe').first().locator('body');
  await body.fill('Synthetic retained text');
  await body.press('Escape');
  await expect(modal).toBeHidden();
  await page.locator('.ticketmailer-action-reply').click();
  await expect(body).toContainText('Synthetic retained text');
 });
}

for (const theme of ['light', 'dark']) {
  test('sender and native options remain readable in ' + theme + ' theme', async ({ page }) => {
    await login(page);
    await page.goto('/plugins/ticketmailer/front/new.php');
    await page.evaluate(theme => document.documentElement.setAttribute('data-bs-theme', theme), theme);
    const sender = page.locator('select[name=sender_entity]');
    await expect(sender).toBeVisible();
    const colors = await sender.evaluate(select => [select, ...select.options].map(el => {
      const style = getComputedStyle(el);
      return { color: style.color, background: style.backgroundColor };
    }));
    for (const pair of colors) {
      expect(pair.background).not.toBe('rgba(0, 0, 0, 0)');
      const luminance = color => {
        const rgb = color.match(/[\d.]+/g).slice(0, 3).map(Number).map(v => {
          v /= 255;
          return v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
        });
        return rgb[0] * 0.2126 + rgb[1] * 0.7152 + rgb[2] * 0.0722;
      };
      const values = [luminance(pair.color), luminance(pair.background)].sort((a, b) => a - b);
      expect((values[1] + 0.05) / (values[0] + 0.05)).toBeGreaterThanOrEqual(4.5);
    }
  });
}
