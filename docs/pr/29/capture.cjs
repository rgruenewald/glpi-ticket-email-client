// Run with NODE_PATH pointing to an installed Playwright package.
// This creates a synthetic ticket and sends only to the configured test SMTP sink.
const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require('playwright');
const base = process.env.GLPI_TEST_URL || 'http://127.0.0.1:18089';
async function assertLayout(page) {
    const metrics = await page.evaluate(() => ({
        viewport: innerWidth, width: document.documentElement.scrollWidth,
        panels: Array.from(document.querySelectorAll('.itil-left-side,.itil-right-side')).map(el => {
            const rect = el.getBoundingClientRect();
            return { left: rect.left, right: rect.right };
        }),
    }));
    assert.ok(metrics.width <= metrics.viewport + 1, JSON.stringify(metrics));
    assert.equal(metrics.panels.length, 2);
    for (const panel of metrics.panels) {
        assert.ok(panel.left >= 0 && panel.right <= metrics.viewport + 1, JSON.stringify(metrics));
    }
}
(async () => {
    const browser = await chromium.launch();
    try {
        const page = await browser.newPage({ viewport: { width: 1600, height: 1200 }, deviceScaleFactor: 1 });
        await page.goto(base);
        await page.locator('#login_name').fill(process.env.GLPI_TEST_USER || 'glpi');
        await page.locator('#login_password').fill(process.env.GLPI_TEST_PASSWORD || 'glpi');
        await page.locator('[name=submit]').click();
        await page.waitForLoadState('networkidle');
        await page.goto(base + '/plugins/ticketmailer/front/new.php');
        await page.waitForFunction(() => document.querySelector('.ticketmailer-compose')?.dataset.ticketmailerInitialized === 'true' && window.tinymce?.get('ticketmailer-new-body'));
        for (const [field, email] of [['to','customer@example.test'], ['cc','service@example.test']]) {
            const input = page.locator('#ticketmailer-compose-recipients_' + field + '-input');
            await input.fill(email);
            await input.press('Enter');
        }
        await page.locator('[name=subject]').fill('Planned maintenance: confirm your appointment');
        await page.evaluate(() => window.tinymce.get('ticketmailer-new-body').setContent('<p>Hello Alex,</p><p>We would like to schedule the maintenance of your workstation for <strong>Tuesday at 10:00</strong>.</p><p>Please reply to confirm the appointment or suggest an alternative.</p><p>Kind regards,<br>Service Desk</p>'));
        // Viewport-only captures avoid resizing GLPI's vh-based animated columns.
        await page.screenshot({ path: path.join(__dirname, 'new-email-compose.png'), fullPage: false, animations: 'disabled' });
        await page.getByRole('button', { name: 'Send', exact: true }).click();
        await page.waitForURL(/ticket.form.php/);
        await page.waitForLoadState('networkidle');
        await assertLayout(page);
        await page.screenshot({ path: path.join(__dirname, 'new-email-ticket.png'), fullPage: false, animations: 'disabled' });
        await assertLayout(page);
        console.log('PASS viewport capture: both ticket panels stay within the viewport before and after screenshot');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
