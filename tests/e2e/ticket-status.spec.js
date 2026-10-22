const { test, expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const { mailIDs } = require('./status-helpers');
let capturedMail;
test.beforeEach(async ({request}) => { capturedMail = await mailIDs(request); });
test.afterEach(async ({request}, info) => {
 if (/stale|forged|UPDATE alone|profile lifecycle/.test(info.title)) expect(await mailIDs(request)).toEqual(capturedMail);
});
const compose = (...args) => execFileSync('docker', ['--context', process.env.TICKETMAILER_DOCKER_CONTEXT || 'rootless', 'compose', ...args], { cwd: path.resolve(__dirname, '../..'), encoding: 'utf8' });
const sql = query => compose('exec', '-T', 'db', 'mariadb', '-uglpi', '-pglpi', '--batch', '--skip-column-names', 'glpi', '-e', query).trim();
async function fixture(page, status) {
 const f = JSON.parse(compose('exec', '-T', '--user', 'www-data', 'glpi', 'php', '/usr/local/bin/e2e-fixture.php'));
 sql('UPDATE glpi_tickets SET status=' + status + ' WHERE id=' + f.ticket);
 await page.goto('/');
 await page.locator('#login_name').fill('glpi');
 await page.locator('#login_password').fill('glpi');
 await page.locator('[name=submit]').click();
 await page.goto('/front/ticket.form.php?id=' + f.ticket);
 return f;
}
async function open(page, mode='reply') {
 await page.locator('.ticketmailer-action-' + mode).click();
 const modal=page.locator('[data-ticketmailer-compose-modal]:visible');
 await expect(modal.locator('[data-ticketmailer-delivery]')).toBeVisible();
 return modal;
}
async function fillNote(panel) {
 await panel.frameLocator('iframe').locator('body').fill('Synthetic private status '+Date.now());
 await panel.frameLocator('iframe').locator('body').press('Space');
}
async function fillEmail(modal, subject) {
 const form=modal.locator('form.ticketmailer-compose');
 const to=form.getByRole('combobox',{name:'To',exact:true});
 await to.locator('..').locator('.ticketmailer-recipient-clear').click();
 await to.fill('status@example.test'); await to.press('Enter');
 await form.locator('[name=subject]').fill(subject);
 await form.frameLocator('iframe[id^=ticketmailer-body-html]').locator('body').fill('Synthetic status test');
 return form;
}
for (const status of [1,4,5]) {
 test('email without status action preserves status ' + status, async ({page}) => {
  const f=await fixture(page,status);
  if(status===5) {
   const button=page.locator('.ticketmailer-action-solution');
   await expect(button).toBeDisabled(); await expect(button).toHaveText('Solved');
   const tooltipTarget=button.locator('..');
   await expect(tooltipTarget).toHaveAttribute('aria-label','This ticket is already solved.');
   await tooltipTarget.hover();
   await expect(page.getByRole('tooltip')).toHaveText('This ticket is already solved.');
   await tooltipTarget.focus();
   await expect(page.getByRole('tooltip')).toBeVisible();
   const order=await page.locator('.ticketmailer-timeline-action button').evaluateAll(nodes=>nodes.map(n=>n.className));
   expect(order[0]).toContain('action-reply'); expect(order[1]).toContain('action-solution'); expect(order[2]).toContain('action-internal_note');
   await page.screenshot({path:'test-results/status-solved-actions.png'});
  }
  const modal=await open(page);
  if(status!==1) await expect(modal.locator('[name=set_waiting]')).toHaveCount(0);
  if(status===5) await expect(modal.locator('[name=set_solved]')).toHaveCount(0);
  const form=await fillEmail(modal,'Status preserve '+Date.now());
  for(const name of ['set_waiting','set_solved']) if(await form.locator('[name='+name+']').count()) await form.locator('[name='+name+']').uncheck();
  await form.getByRole('button',{name:'Send',exact:true}).click();
  await expect(page).toHaveURL(new RegExp('/front/ticket[.]form[.]php[?]id='+f.ticket));
  expect(sql('SELECT status FROM glpi_tickets WHERE id='+f.ticket)).toBe(String(status));
 });
 test('private note without status action preserves status ' + status, async ({page}) => {
  const f=await fixture(page,status); const modal=await open(page,'internal_note');
  const panel=modal.locator('[data-ticketmailer-panel=internal_note]');
  await expect(panel).toBeVisible();
  if(status!==1) await expect(panel.locator('[name=pending]')).toHaveCount(0);
  if(status===5) await expect(panel.locator('[name=_ticketmailer_set_solved]')).toHaveCount(0);
  for(const input of await panel.locator('input[name=pending][type=checkbox]').all()) await input.uncheck();
  await fillNote(panel);
  await panel.locator('button[name=add]').click();
  await expect(page).toHaveURL(new RegExp('/front/ticket[.]form[.]php[?]id='+f.ticket));
  expect(sql('SELECT status FROM glpi_tickets WHERE id='+f.ticket)).toBe(String(status));
  await expect.poll(()=>sql('SELECT is_private FROM glpi_itilfollowups WHERE items_id='+f.ticket+' AND itemtype="Ticket" ORDER BY id DESC LIMIT 1')).toBe('1');
  expect(sql('SELECT status FROM glpi_tickets WHERE id='+f.ticket)).toBe(String(status));
 });
}
test('stale email is rejected before audit and SMTP', async ({page})=>{
 const f=await fixture(page,1); const modal=await open(page); const form=await fillEmail(modal,'Stale '+Date.now());
 sql('UPDATE glpi_tickets SET status=5 WHERE id='+f.ticket);
 await form.getByRole('button',{name:'Send',exact:true}).click();
 await expect(page.locator('body')).toContainText('The ticket status has changed.');
 expect(sql('SELECT COUNT(*) FROM glpi_plugin_ticketmailer_logs WHERE tickets_id='+f.ticket)).toBe('0');
 expect(sql('SELECT status FROM glpi_tickets WHERE id='+f.ticket)).toBe('5');
});

for (const mode of ['email','internal_note']) {
 for (const target of [4,5]) {
  test(mode+' explicitly changes open ticket to '+target, async ({page})=>{
   const f=await fixture(page,1); const modal=await open(page,mode==='email'?'reply':'internal_note');
   if(mode==='email') {
    const form=await fillEmail(modal,'Explicit status '+Date.now());
    await form.locator('[name='+ (target===4?'set_waiting':'set_solved')+']').check();
    await form.getByRole('button',{name:'Send',exact:true}).click();
    await expect.poll(()=>sql('SELECT COUNT(*) FROM glpi_plugin_ticketmailer_logs WHERE status="sent" AND tickets_id='+f.ticket)).toBe('1');
   } else {
    const panel=modal.locator('[data-ticketmailer-panel=internal_note]'); await fillNote(panel);
    await panel.locator(target===4?'[name=pending][type=checkbox]':'[name=_ticketmailer_set_solved]').check();
    await panel.locator('button[name=add]').click();
    await expect.poll(()=>sql('SELECT COUNT(*) FROM glpi_itilfollowups WHERE itemtype="Ticket" AND items_id='+f.ticket)).toBe('1');
   }
   await expect.poll(()=>sql('SELECT status FROM glpi_tickets WHERE id='+f.ticket)).toBe(String(target));
  });
 }
}
test('forged waiting action on solved ticket sends nothing',async ({page})=>{
 const f=await fixture(page,5); const modal=await open(page);
 const token=await modal.locator('form.ticketmailer-compose [name=_glpi_csrf_token]').inputValue();
 const response=await page.request.post('/plugins/ticketmailer/front/send.php',{form:{
  _glpi_csrf_token:token,tickets_id:String(f.ticket),expected_status:'5',delivery_mode:'email',
  recipients_to:'status@example.test',subject:'Forbidden status',body_html:'<p>Forbidden status</p>',set_waiting:'1'
 }});
 expect(await response.text()).toContain('GLPI does not allow the selected ticket status change.');
 expect(sql('SELECT COUNT(*) FROM glpi_plugin_ticketmailer_logs WHERE tickets_id='+f.ticket)).toBe('0');
 expect(sql('SELECT status FROM glpi_tickets WHERE id='+f.ticket)).toBe('5');
});
test('stale native note is rejected without a followup',async ({page})=>{
 const f=await fixture(page,1); const modal=await open(page,'internal_note');
 const panel=modal.locator('[data-ticketmailer-panel=internal_note]'); await fillNote(panel);
 sql('UPDATE glpi_tickets SET status=5 WHERE id='+f.ticket);
 await panel.locator('button[name=add]').click();
 await expect(page.locator('body')).toContainText('The ticket status has changed.');
 expect(sql('SELECT COUNT(*) FROM glpi_itilfollowups WHERE itemtype="Ticket" AND items_id='+f.ticket)).toBe('0');
 expect(sql('SELECT status FROM glpi_tickets WHERE id='+f.ticket)).toBe('5');
});
