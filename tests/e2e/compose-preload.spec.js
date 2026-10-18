const { test, expect } = require('@playwright/test');
const ticket = Number(process.env.TICKET_ID || 137);
async function login(page) {
  await page.goto('/');
  await page.locator('#login_name').fill('glpi');
  await page.locator('#login_password').fill('glpi');
  await page.locator('[name=submit]').click();
  await expect(page.locator('#login_name')).toHaveCount(0);
}
async function ready(page) {
  await page.waitForFunction(() => {
    const form = document.querySelector('[data-ticketmailer-compose-modal][id$=_email] .ticketmailer-compose');
    const editor = window.tinymce?.get(form?.dataset.editorId);
    return editor?.initialized && editor.getElement().isConnected;
  });
}
for (const viewport of [{width:1440,height:1000},{width:390,height:844}]) {
  test('prepares without opening, retains drafts and editor size ' + viewport.width, async ({page}) => {
    await page.setViewportSize(viewport);
    await login(page);
    const errors = [];
    const requests = [];
    page.on('pageerror', e => errors.push(e.message));
    page.on('request', r => { if(r.url().includes('/front/compose.php') && new URL(r.url()).searchParams.get('initial_mode')==='email') requests.push(r); });
    await page.goto('/front/ticket.form.php?id=' + ticket);
    await ready(page);
    await expect(page.locator('[data-ticketmailer-compose-modal][id$=_email]')).toBeHidden();
    await expect(page.locator('.modal-backdrop')).toHaveCount(0);
    const url = page.url();
    await page.locator('.ticketmailer-action-reply').click();
    const modal = page.locator('[data-ticketmailer-compose-modal][id$=_email]');
    await expect(modal).toBeVisible();
    await expect(modal.locator('.tox-tinymce').first()).toBeVisible();
    const box = await modal.locator('.tox-tinymce').first().boundingBox();
    expect(box.width).toBeGreaterThan(200);
    await modal.locator('[name=subject]').fill('Synthetic retained preload draft');
    await page.evaluate(() => {
      const form=document.querySelector('.modal.show .ticketmailer-compose');
      tinymce.get(form.dataset.editorId).setContent('<p>Synthetic email draft</p>');
    });
    for(let i=0;i<5;i++) {
      await modal.locator('[data-delivery-mode][value=internal_note]').check();
      await expect(modal.locator('[data-ticketmailer-panel=internal_note] form')).toBeVisible();
      await modal.locator('[data-delivery-mode][value=email]').check();
      await modal.locator('.modal-header .btn-close').click();
      await page.locator('.ticketmailer-action-reply').click();
    }
    await expect(modal.locator('[name=subject]')).toHaveValue('Synthetic retained preload draft');
    expect(await page.evaluate(() => {
      const form=document.querySelector('.modal.show .ticketmailer-compose');
      return tinymce.get(form.dataset.editorId).getContent();
    })).toContain('Synthetic email draft');
    expect(page.url()).toBe(url);
    expect(requests).toHaveLength(1);
    expect(errors).toEqual([]);
    // A ticket navigation must not reuse the previous ticket's draft.
    await page.goto('/front/ticket.form.php?id=' + ticket);
    await ready(page);
    expect(requests).toHaveLength(2);
    await expect(page.locator('[data-ticketmailer-compose-modal][id$=_email] [name=subject]')).not.toHaveValue('Synthetic retained preload draft');
  });
}
test('early click shares in-flight preload and retries failed prefetch', async ({page}) => {
  await login(page);
  let requests = 0;
  let release;
  const held = new Promise(resolve=>{release=resolve;});
  await page.route('**/plugins/ticketmailer/front/compose.php*', async route => {
    if(new URL(route.request().url()).searchParams.get('initial_mode')!=='email') return route.continue();
    requests++;
    if(requests===1) return route.abort('failed');
    await held;
    await route.continue();
  });
  await page.goto('/front/ticket.form.php?id=' + ticket);
  await expect.poll(()=>requests).toBe(1);
  await page.waitForLoadState('networkidle');
  await page.locator('.ticketmailer-action-reply').click();
  await expect.poll(()=>requests).toBe(2);
  // Double click while loading must not start another request.
  await page.evaluate(()=>document.querySelector('.ticketmailer-action-reply').click());
  expect(requests).toBe(2);
  release();
  await ready(page);
  await expect(page.locator('.modal.show .ticketmailer-compose')).toBeVisible();
  expect(requests).toBe(2);
});

test('AJAX replacement tears down an open modal', async ({page}) => {
  await login(page);
  await page.goto('/front/ticket.form.php?id=' + ticket);
  await ready(page);
  await page.locator('.ticketmailer-action-reply').click();
  await expect(page.locator('.modal-backdrop')).toHaveCount(1);
  await page.evaluate(() => {
    const modal=document.querySelector('[data-ticketmailer-compose-modal][id$=_email]');
    const walker=document.createTreeWalker(document.body, NodeFilter.SHOW_COMMENT);
    while(walker.nextNode()) {
      if(walker.currentNode.textContent==='ticketmailer compose owner') {
        walker.currentNode.remove();break;
      }
    }
    $(document).trigger('ajaxComplete');
  });
  await expect(page.locator('[data-ticketmailer-compose-modal][id$=_email]')).toHaveCount(0);
  await expect(page.locator('.modal-backdrop')).toHaveCount(0);
  await expect(page.locator('body')).not.toHaveClass(/modal-open/);
  const editorCount = await page.evaluate(()=>Object.keys(window.tinymce_editor_configs||{}).filter(id=>tinymce.get(id)?.getElement()?.isConnected===false).length);
  expect(editorCount).toBe(0);
});
