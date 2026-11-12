const { test, expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
let ticket;

test.beforeAll(() => {
  // Use the selected disposable stack's synthetic fixture, never a fixed ticket ID.
  const fixture = execFileSync('docker', [
    '--context', process.env.TICKETMAILER_DOCKER_CONTEXT || 'rootless', 'compose',
    'exec', '-T', '--user', 'www-data', 'glpi', 'php', '/usr/local/bin/e2e-fixture.php',
  ], { cwd: root, encoding: 'utf8' });
  ticket = JSON.parse(fixture).ticket;
});

for (const width of [390, 1440, 1920]) {
  test('timeline actions match the Save button height at ' + width, async ({ page }) => {
    await page.setViewportSize({ width, height: 1000 });
    await page.goto('/');
    await page.locator('#login_name').fill('glpi');
    await page.locator('#login_password').fill('glpi');
    await page.locator('[name=submit]').click();
    await expect(page.locator('#login_name')).toHaveCount(0);
    await page.goto('/front/ticket.form.php?id=' + ticket);
    const footer = page.locator('#itil-footer');
    await expect(footer.locator('.ticketmailer-action-reply')).toBeVisible();
    const save = footer.getByRole('button', { name: 'Save', exact: true });
    await expect(save).toBeVisible();
    const saveBox = await save.boundingBox();
    const actions = footer.locator('.main-actions .btn:visible, .ticketmailer-timeline-action .btn:visible');
    expect(await actions.count()).toBeGreaterThanOrEqual(5);
    for (const action of await actions.all()) {
      const box = await action.boundingBox();
      expect(box).not.toBeNull();
      expect(Math.abs(box.height - saveBox.height)).toBeLessThanOrEqual(1);
    }
  });
}
