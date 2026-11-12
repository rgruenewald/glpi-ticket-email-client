const { test, expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const { mailIDs } = require('./status-helpers');
let capturedMail;
test.beforeEach(async ({request}) => { capturedMail = await mailIDs(request); });
test.afterEach(async ({request}, info) => {
 if (/stale|forged|UPDATE alone|profile lifecycle/.test(info.title)) expect(await mailIDs(request)).toEqual(capturedMail);
});
const compose=(...args)=>execFileSync('docker',['--context',process.env.TICKETMAILER_DOCKER_CONTEXT||'rootless','compose',...args],{cwd:path.resolve(__dirname,'../..'),encoding:'utf8'});
const sql=query=>compose('exec','-T','db','mariadb','-uglpi','-pglpi','--batch','--skip-column-names','glpi','-e',query).trim();
const fixture=()=>JSON.parse(compose('exec','-T','--user','www-data','glpi','php','/usr/local/bin/e2e-fixture.php','acl'));
async function login(page,user,ticket) {
 await page.goto('/'); await page.locator('#login_name').fill(user);
 await page.locator('#login_password').fill('E2E-local-only-123!'); await page.locator('[name=submit]').click();
 await page.goto('/front/ticket.form.php?id='+ticket);
}
function sendInput(token,ticket,extra={}) {
 return {_glpi_csrf_token:token,tickets_id:String(ticket),expected_status:'1',delivery_mode:'email',recipients_to:'status@example.test',subject:'Denied operation',body_html:'<p>Synthetic permissions test</p>',...extra};
}
test('German solved button and tooltip are translated',async({page})=>{
 const f=fixture();
 sql("UPDATE glpi_users SET language='de_DE' WHERE name='"+f.users.writer.login+"'");
 sql('UPDATE glpi_tickets SET status=5 WHERE id='+f.ownTicket);
 await login(page,f.users.writer.login,f.ownTicket);
 const button=page.locator('.ticketmailer-action-solution'); await expect(button).toBeDisabled();
 await expect(button).toHaveText('Gelöst'); await button.locator('..').hover();
 await expect(page.getByRole('tooltip')).toHaveText('Dieses Ticket ist bereits gelöst.');
});
test('ticket UPDATE alone cannot bypass followup CREATE permission',async({page})=>{
 const f=fixture();
 sql("UPDATE glpi_profilerights SET rights=rights | 2 WHERE profiles_id="+f.users.reader.profile+" AND name='ticket'");
 await login(page,f.users.reader.login,f.ownTicket);
 await expect(page.locator('.ticketmailer-action-reply')).toHaveCount(0);
 const response=await page.request.get('/plugins/ticketmailer/front/compose.php?tickets_id='+f.ownTicket);
 expect(await response.text()).not.toContain('class="ticketmailer-compose"');
 const token=await page.locator('input[name=_glpi_csrf_token]').first().inputValue();
 await page.request.post('/plugins/ticketmailer/front/send.php',{form:sendInput(token,f.ownTicket)});
 expect(sql('SELECT COUNT(*) FROM glpi_plugin_ticketmailer_logs WHERE tickets_id='+f.ownTicket)).toBe('0');
});
test('profile lifecycle hides status actions and rejects forged waiting',async({page})=>{
 const f=fixture();
 sql("UPDATE glpi_profilerights SET rights=rights | 2 WHERE profiles_id="+f.users.writer.profile+" AND name='ticket'");
 sql("UPDATE glpi_profiles SET ticket_status='"+JSON.stringify({1:{4:0,5:0}})+"' WHERE id="+f.users.writer.profile);
 await login(page,f.users.writer.login,f.ownTicket);
 await page.locator('.ticketmailer-action-reply').click();
 const modal=page.locator('[data-ticketmailer-compose-modal]:visible');
 await expect(modal.locator('form.ticketmailer-compose')).toBeVisible();
 await expect(modal.locator('[name=set_waiting]')).toHaveCount(0); await expect(modal.locator('[name=set_solved]')).toHaveCount(0);
 const token=await modal.locator('form.ticketmailer-compose [name=_glpi_csrf_token]').inputValue();
 const response=await page.request.post('/plugins/ticketmailer/front/send.php',{form:sendInput(token,f.ownTicket,{set_waiting:'1'})});
 expect(await response.text()).toContain('GLPI does not allow the selected ticket status change.');
 expect(sql('SELECT COUNT(*) FROM glpi_plugin_ticketmailer_logs WHERE tickets_id='+f.ownTicket)).toBe('0');
});

for (const allowed of [false,true]) {
 test('closed ticket with reopening '+allowed,async({page,request})=>{
  const before=await mailIDs(request); const f=fixture(); const profile=f.users.writer.profile;
  sql('UPDATE glpi_tickets SET status=6 WHERE id='+f.ownTicket);
  sql("UPDATE glpi_profilerights SET rights=rights | 4 WHERE profiles_id="+profile+" AND name='followup'");
  sql("UPDATE glpi_profiles SET ticket_status='"+JSON.stringify({6:{1:allowed?1:0,2:allowed?1:0}})+"' WHERE id="+profile);
  await login(page,f.users.writer.login,f.ownTicket);
  if (!allowed) {
   await expect(page.locator('.ticketmailer-action-reply')).toHaveCount(0);
   const token=await page.locator('input[name=_glpi_csrf_token]').first().inputValue();
   await page.request.post('/plugins/ticketmailer/front/send.php',{form:sendInput(token,f.ownTicket,{expected_status:'6'})});
   expect(sql('SELECT COUNT(*) FROM glpi_plugin_ticketmailer_logs WHERE tickets_id='+f.ownTicket)).toBe('0');
   expect(await mailIDs(request)).toEqual(before);
  } else {
   await page.locator('.ticketmailer-action-reply').click();
   const form=page.locator('form.ticketmailer-compose:visible'); await expect(form).toBeVisible();
   await expect(form.locator('[name=set_waiting],[name=set_solved]')).toHaveCount(0);
   const token=await form.locator('[name=_glpi_csrf_token]').inputValue();
   await page.request.post('/plugins/ticketmailer/front/send.php',{form:sendInput(token,f.ownTicket,{expected_status:'6'})});
   expect(sql('SELECT timeline_status FROM glpi_plugin_ticketmailer_logs WHERE tickets_id='+f.ownTicket)).toBe('recorded');
   expect((await mailIDs(request)).length).toBe(before.length+1);
  }
  expect(sql('SELECT status FROM glpi_tickets WHERE id='+f.ownTicket)).toBe('6');
 });
}
test('requester email on solved ticket does not reopen it',async({page,request})=>{
 const f=fixture();
 const user=sql("SELECT id FROM glpi_users WHERE name='"+f.users.writer.login+"'");
 sql('UPDATE glpi_tickets SET status=5,users_id_recipient='+user+' WHERE id='+f.ownTicket);
 sql('INSERT INTO glpi_tickets_users (tickets_id,users_id,type) VALUES ('+f.ownTicket+','+user+',1)');
 await login(page,f.users.writer.login,f.ownTicket); await page.locator('.ticketmailer-action-reply').click();
 const form=page.locator('form.ticketmailer-compose:visible'); await expect(form).toBeVisible();
 const token=await form.locator('[name=_glpi_csrf_token]').inputValue(); const before=await mailIDs(request);
 await page.request.post('/plugins/ticketmailer/front/send.php',{form:sendInput(token,f.ownTicket,{expected_status:'5'})});
 expect(sql('SELECT timeline_status FROM glpi_plugin_ticketmailer_logs WHERE tickets_id='+f.ownTicket)).toBe('recorded');
 expect(sql('SELECT status FROM glpi_tickets WHERE id='+f.ownTicket)).toBe('5');
 expect((await mailIDs(request)).length).toBe(before.length+1);
});
test('missing mandatory location hides solving and rejects forged solution',async({page,request})=>{
 const f=JSON.parse(compose('exec','-T','--user','www-data','glpi','php','/usr/local/bin/e2e-fixture.php','status-required'));
 sql("UPDATE glpi_profilerights SET rights=rights | 2 WHERE profiles_id="+f.users.writer.profile+" AND name='ticket'");
 await login(page,f.users.writer.login,f.ownTicket); await page.locator('.ticketmailer-action-reply').click();
 const form=page.locator('form.ticketmailer-compose:visible'); await expect(form).toBeVisible();
 await expect(form.locator('[name=set_solved]')).toHaveCount(0);
 const token=await form.locator('[name=_glpi_csrf_token]').inputValue(); const before=await mailIDs(request);
 const response=await page.request.post('/plugins/ticketmailer/front/send.php',{form:sendInput(token,f.ownTicket,{set_solved:'1'})});
 expect(await response.text()).toContain('GLPI does not allow the selected ticket status change.');
 expect(sql('SELECT COUNT(*) FROM glpi_plugin_ticketmailer_logs WHERE tickets_id='+f.ownTicket)).toBe('0');
 expect(sql('SELECT status FROM glpi_tickets WHERE id='+f.ownTicket)).toBe('1');
 expect(await mailIDs(request)).toEqual(before);
 // A filled required field restores the action; the fixture has solution rights.
 sql('UPDATE glpi_tickets SET locations_id='+f.location+' WHERE id='+f.ownTicket);
 await page.goto('/front/ticket.form.php?id='+f.ownTicket);
 await page.locator('.ticketmailer-action-reply').click();
 await expect(page.locator('form.ticketmailer-compose:visible [name=set_solved]')).toHaveCount(1);
});
