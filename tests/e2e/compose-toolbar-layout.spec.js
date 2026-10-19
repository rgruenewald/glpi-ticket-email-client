const { test, expect } = require('@playwright/test');
const ticket = Number(process.env.TICKET_ID || 137);

for (const width of [1440, 390]) {
  test('email and internal note share toolbar geometry at ' + width, async ({ page }) => {
    await page.setViewportSize({ width, height: 1000 });
    await page.goto('/');
    await page.locator('#login_name').fill('glpi');
    await page.locator('#login_password').fill('glpi');
    await page.locator('[name=submit]').click();
    await page.goto('/front/ticket.form.php?id=' + ticket);
    await page.locator('.ticketmailer-action-reply').click();
    const modal = page.locator('[data-ticketmailer-compose-modal][id$=_email]');
    const email = modal.locator('.ticketmailer-mail-row--followup-meta');
    const note = modal.locator('.ticketmailer-note-options > .row');
    async function geometry(toolbar, selector) {
      return toolbar.evaluate((row, selector) => {
        const parent = row.getBoundingClientRect();
        return [...row.querySelectorAll(selector)].filter(el => el.getBoundingClientRect().width).map(el => {
          const box = el.getBoundingClientRect();
          return { x: box.x - parent.x, y: box.y - parent.y, width: box.width, height: box.height };
        });
      }, selector);
    }
    for (let cycle = 0; cycle < 2; cycle++) {
      await modal.locator('[data-delivery-mode][value=email]').check();
      await expect(email).toBeVisible();
      const mailBoxes = await geometry(email, '.ticketmailer-mail-field');
      expect(mailBoxes).toHaveLength(4);
      await modal.locator('[data-delivery-mode][value=internal_note]').check();
      await expect(note.locator('.ticketmailer-note-template')).toBeVisible();
      // Native fields use a different DOM order; compare semantic order.
      const noteBoxes = [];
      for (const cls of ['template', 'solution-template', 'source', 'knowledge']) {
        noteBoxes.push((await geometry(note, '.ticketmailer-note-' + cls))[0]);
      }
      for (let i = 0; i < 4; i++) {
        expect(Math.abs(noteBoxes[i].x - mailBoxes[i].x)).toBeLessThanOrEqual(2);
        // Email has a 1px panel border on either side.
        expect(Math.abs(noteBoxes[i].width - mailBoxes[i].width)).toBeLessThanOrEqual(2);
        if (width > 640) {
          expect(Math.abs(noteBoxes[i].y - noteBoxes[0].y)).toBeLessThan(2);
          expect(Math.abs(mailBoxes[i].y - mailBoxes[0].y)).toBeLessThan(2);
        } else if (i > 0) {
          expect(noteBoxes[i].y).toBeGreaterThan(noteBoxes[i - 1].y);
          expect(mailBoxes[i].y).toBeGreaterThan(mailBoxes[i - 1].y);
        }
      }
    }
  });
}
