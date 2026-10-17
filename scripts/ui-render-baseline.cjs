#!/usr/bin/env node
// Run with PLAYWRIGHT_MODULE pointing to an installed playwright package.
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = process.env.BASE_URL || 'http://127.0.0.1:8080';
const ticket = Number(process.env.TICKET_ID || 137);
const runs = Number(process.env.SAMPLES || 10);
async function measure(page, selector, ready) {
  return page.evaluate(async ({ selector, ready }) => {
    const element = [...document.querySelectorAll(selector)].find(e => e.getClientRects().length);
    if (!element) throw new Error('No visible control: ' + selector);
    const start = performance.now();
    element.click();
    const predicate = new Function('return (' + ready + ')');
    await new Promise((resolve, reject) => {
      const check = () => {
        if (performance.now() - start > 10000) return reject(new Error('Readiness timeout: ' + selector));
        if (predicate()) return requestAnimationFrame(() => requestAnimationFrame(resolve));
        requestAnimationFrame(check);
      };
      check();
    });
    return performance.now() - start;
  }, { selector, ready });
}
function summarize(values) {
  const sorted = [...values].sort((a,b) => a-b);
  return { samples_ms: values.map(n => +n.toFixed(1)), median_ms: +sorted[Math.floor(sorted.length/2)].toFixed(1), p95_ms: +sorted[Math.ceil(sorted.length*.95)-1].toFixed(1) };
}
(async () => {
  const browser = await chromium.launch({headless:true});
  const result = { base, ticket, samples:runs, browser:browser.version(), measurements:[] };
  try {
    for (const viewport of [{width:1440,height:1000},{width:390,height:844}]) {
      const page = await browser.newPage({viewport});
      await page.goto(base);
      await page.locator('#login_name').fill(process.env.GLPI_USER || 'glpi');
      await page.locator('#login_password').fill(process.env.GLPI_PASSWORD || 'glpi');
      await page.locator('[name=submit]').click();
      await page.waitForLoadState('networkidle');
      await page.goto(base + '/front/ticket.form.php?id=' + ticket);
      await page.waitForLoadState('networkidle');
      const samples = {};
      const record = (name, value) => (samples[name] ||= []).push(value);
      const emailReady = '!!document.querySelector(".modal.show [data-ticketmailer-delivery][data-ticketmailer-initialized] .ticketmailer-compose:not([hidden])") && [...document.querySelectorAll(".modal.show .ticketmailer-compose textarea")].some(e => window.tinymce?.get(e.id)?.initialized && window.tinymce.get(e.id).getBody()?.isConnected && window.tinymce.get(e.id).getElement() === e)';
      for(let i=0;i<runs;i++) {
        record(i === 0 ? 'first_open_editor' : 'reopen_editor', await measure(page,'.ticketmailer-action-reply',emailReady));
        record('internal_note', await measure(page,'.modal.show [data-delivery-mode][value=internal_note]',
          '!!document.querySelector(".modal.show [data-ticketmailer-panel=internal_note]:not([hidden]) form") && [...document.querySelectorAll(".modal.show [data-ticketmailer-panel=internal_note] textarea")].some(e => window.tinymce?.get(e.id)?.initialized)'));
        record('email_mode', await measure(page,'.modal.show [data-delivery-mode][value=email]',emailReady));
        await page.locator('.modal.show > .modal-dialog > .modal-content > .modal-header .btn-close').click();
        await page.locator('.modal.show').waitFor({state:'hidden'});
      }
      result.measurements.push({viewport,actions:Object.fromEntries(Object.entries(samples).map(([key,values])=>[key,summarize(values)]))});
      await page.close();
    }
    console.log(JSON.stringify(result,null,2));
  } finally { await browser.close(); }
})().catch(e=>{console.error(e.message);process.exitCode=1;});
