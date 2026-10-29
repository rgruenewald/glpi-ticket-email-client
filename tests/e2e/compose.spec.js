const { test, expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const compose = (...args) => execFileSync('docker', ['--context', process.env.TICKETMAILER_DOCKER_CONTEXT || 'rootless', 'compose', ...args], { cwd: root, encoding: 'utf8' });
function sql(query) {
  return compose('exec', '-T', 'db', 'mariadb', '-uglpi', '-pglpi', '--batch', '--skip-column-names', 'glpi', '-e', query).trim();
}
async function login(page, user = 'glpi', password = 'glpi') {
  await page.goto('/');
  await page.locator('#login_name').fill(user);
  await page.locator('#login_password').fill(password);
  await page.locator('[name=submit]').click();
  await expect(page.locator('#login_name')).toHaveCount(0);
}
async function composeMail(page, ticket, subject, recipient = 'recipient@example.test') {
  await page.goto('/plugins/ticketmailer/front/compose.php?tickets_id=' + ticket);
  await page.getByRole('combobox', { name: 'To', exact: true }).fill(recipient);
  await page.getByRole('combobox', { name: 'CC', exact: true }).fill('copy@example.test');
  await page.getByRole('combobox', { name: 'BCC', exact: true }).fill('blind@example.test');
  await page.locator('[name=subject]').fill(subject);
  await page.frameLocator('iframe[id^=ticketmailer-body-html]').locator('body').fill('Ticketmailer browser E2E content');
}
test('recipient lookup shows name or login and bracketed email in To, CC and BCC', async ({ page }) => {
  const fixture = JSON.parse(compose('exec', '-T', '--user', 'www-data', 'glpi', 'php', '/usr/local/bin/e2e-fixture.php', 'recipients'));
  await login(page);
  await page.goto('/front/ticket.form.php?id=' + fixture.ticket);
  await page.locator('.ticketmailer-action-reply').click();
  const form = page.locator('form.ticketmailer-compose:visible');
  await expect(form).toBeVisible();
  for (const field of ['To', 'CC', 'BCC']) {
    const input = form.getByRole('combobox', { name: field, exact: true });
    const control = input.locator('..');
    await control.locator('.ticketmailer-recipient-clear').click();
    for (const [index, recipient] of fixture.recipients.entries()) {
      const label = recipient.label + ' <' + recipient.email + '>';
      await input.fill(recipient.query);
      const suggestion = control.getByRole('option', { name: label, exact: true });
      await expect(suggestion).toBeVisible();
      if (index === 0) {
        await input.press('ArrowDown');
        await input.press('Enter');
      } else {
        await suggestion.click();
      }
      await expect(control.locator('.ticketmailer-recipient-chip').last()).toHaveText(label);
    }
    await expect(control.locator('input[type=hidden]')).toHaveValue(fixture.recipients.map(recipient => recipient.email).join(', '));
  }
  await page.screenshot({ path: 'test-results/recipient-lookup.png' });
});

test('browser compose delivers once and creates audit and timeline; anonymous access denied', async ({ page, request, browser, baseURL }) => {
  const fixture = JSON.parse(compose('exec', '-T', '--user', 'www-data', 'glpi', 'php', '/usr/local/bin/e2e-fixture.php'));
  const subject = 'E2E-' + Date.now();
  await login(page);
  await page.goto('/front/ticket.form.php?id=' + fixture.ticket);
  await expect(page.locator('body')).toContainText('Ticketmailer E2E');
  await composeMail(page, fixture.ticket, subject);
  await page.locator('input[type=file].ticketmailer-file').setInputFiles({ name: 'e2e.txt', mimeType: 'text/plain', buffer: Buffer.from('Synthetic E2E attachment') });
  await expect(page.locator('input[name$="[stored]"]')).toHaveCount(1);
  await page.getByRole('button', { name: 'Send', exact: true }).click();
  await expect(page).toHaveURL(new RegExp('/front/ticket.form.php\\?id=' + fixture.ticket));
  await expect.poll(() => sql('SELECT status FROM glpi_plugin_ticketmailer_logs WHERE tickets_id=' + fixture.ticket + ' ORDER BY id DESC LIMIT 1')).toBe('sent');
  const audit = sql('SELECT id,followups_id FROM glpi_plugin_ticketmailer_logs WHERE tickets_id=' + fixture.ticket).split('\t');
  expect(Number(audit[1])).toBeGreaterThan(0);
  expect(sql('SELECT content FROM glpi_itilfollowups WHERE id=' + Number(audit[1]))).toContain('Ticketmailer browser E2E content');
  const response = await request.get((process.env.TICKETMAILER_MAIL_URL || 'http://127.0.0.1:8025') + '/api/v1/search?query=' + encodeURIComponent('subject:' + subject));
  const messages = await response.json();
  expect(messages.messages).toHaveLength(1);
  const detail = await (await request.get((process.env.TICKETMAILER_MAIL_URL || 'http://127.0.0.1:8025') + '/api/v1/message/' + messages.messages[0].ID)).json();
  expect(detail.HTML).toContain('Ticketmailer browser E2E content');
  expect(detail.To[0].Address).toBe('recipient@example.test');
  expect(detail.Cc[0].Address).toBe('copy@example.test');
  expect(detail.Attachments).toHaveLength(1);
  expect(detail.Attachments[0].FileName).toBe('e2e.txt');
  // Mailpit synthesizes a Bcc header from the SMTP envelope for its UI.
  expect(detail.Bcc[0].Address).toBe('blind@example.test');
  const stored = JSON.parse(sql('SELECT attachments FROM glpi_plugin_ticketmailer_logs WHERE id=' + Number(audit[0])));
  const downloadPath = '/plugins/ticketmailer/front/download.php?log_id=' + audit[0] + '&attachment_id=' + encodeURIComponent(stored[0].id);
  const download = await page.request.get(downloadPath);
  expect(download.status()).toBe(200);
  expect(await download.text()).toBe('Synthetic E2E attachment');
  await page.goto('/front/ticket.form.php?id=' + fixture.ticket);
  await expect(page.locator('body')).toContainText('Ticketmailer browser E2E content');
  // Uploaded attachments become ordinary ticket documents and survive a new modal.
  const documentId = sql('SELECT d.id FROM glpi_documents d JOIN glpi_documents_items di ON di.documents_id=d.id WHERE di.itemtype="Ticket" AND di.items_id=' + fixture.ticket + ' AND d.filename="e2e.txt"');
  expect(Number(documentId)).toBeGreaterThan(0);
  expect(sql('SELECT entities_id FROM glpi_documents WHERE id=' + documentId)).toBe(sql('SELECT entities_id FROM glpi_tickets WHERE id=' + fixture.ticket));
  await page.locator('.ticketmailer-action-reply').click();
  const form = page.locator('form.ticketmailer-compose:visible');
  const selected = form.locator('[name="history_attachments[]"][value="' + documentId + '"]');
  await expect(selected).toBeVisible();
  await expect(selected).not.toBeChecked();
  const preview = await page.request.get('/plugins/ticketmailer/front/history_attachment.php?tickets_id=' + fixture.ticket + '&documents_id=' + documentId);
  expect(await preview.text()).toBe('Synthetic E2E attachment');
  await selected.check();
  await expect(form.locator('[name=include_history]')).not.toBeChecked();
  const secondSubject = subject + '-again';
  await form.getByRole('combobox', { name: 'To', exact: true }).fill('recipient@example.test');
  await form.locator('[name=subject]').fill(secondSubject);
  await form.frameLocator('iframe[id^=ticketmailer-body-html]').locator('body').fill('Reuse the existing ticket document');
  await form.getByRole('button', { name: 'Send', exact: true }).click();
  await expect(page).toHaveURL(new RegExp('/front/ticket.form.php\\?id=' + fixture.ticket));
  const secondMail = await captured(request, secondSubject);
  expect(secondMail.Attachments.map(a => a.FileName)).toEqual(['e2e.txt']);
  const secondBytes = await request.get((process.env.TICKETMAILER_MAIL_URL || 'http://127.0.0.1:8025') + '/api/v1/message/' + secondMail.ID + '/part/' + secondMail.Attachments[0].PartID);
  expect(await secondBytes.text()).toBe('Synthetic E2E attachment');
  expect(sql('SELECT COUNT(*) FROM glpi_documents d JOIN glpi_documents_items di ON di.documents_id=d.id WHERE di.itemtype="Ticket" AND di.items_id=' + fixture.ticket)).toBe('1');
  expect(sql('SELECT COUNT(*) FROM glpi_plugin_ticketmailer_logs WHERE tickets_id=' + fixture.ticket + ' AND status="sent" AND timeline_status="recorded"')).toBe('2');
  const anonymous = await browser.newContext({ baseURL });
  const denied = await anonymous.request.get('/plugins/ticketmailer/front/compose.php?tickets_id=' + fixture.ticket);
  expect(await denied.text()).not.toContain('data-ticketmailer-form');
  const deniedDownload = await anonymous.request.get(downloadPath);
  expect(await deniedDownload.text()).not.toBe('Synthetic E2E attachment');
  await anonymous.close();
});

test('SMTP outage records failure without a followup and recovers for subsequent runs', async ({ page, request }) => {
  const fixture = JSON.parse(compose('exec', '-T', 'glpi', 'php', '/usr/local/bin/e2e-fixture.php'));
  const subject = 'E2E-outage-' + Date.now();
  await login(page);
  await composeMail(page, fixture.ticket, subject);
  await page.locator('input[type=file].ticketmailer-file').setInputFiles({ name: 'unsent.txt', mimeType: 'text/plain', buffer: Buffer.from('Not delivered') });
  await expect(page.locator('input[name$="[stored]"]')).toHaveCount(1);
  compose('stop', 'mailpit');
  try {
    await page.getByRole('button', { name: 'Send', exact: true }).click();
    await expect(page).toHaveURL(/front\/log_entry.php\?id=/);
    expect(sql('SELECT status FROM glpi_plugin_ticketmailer_logs WHERE tickets_id=' + fixture.ticket)).toBe('failed');
    expect(sql('SELECT COUNT(*) FROM glpi_itilfollowups WHERE itemtype="Ticket" AND items_id=' + fixture.ticket)).toBe('0');
    expect(sql('SELECT COUNT(*) FROM glpi_documents_items WHERE itemtype="Ticket" AND items_id=' + fixture.ticket)).toBe('0');
  } finally {
    compose('start', 'mailpit');
  }
  await expect.poll(async () => {
    try { return (await request.get((process.env.TICKETMAILER_MAIL_URL || 'http://127.0.0.1:8025') + '/api/v1/info')).ok(); } catch { return false; }
  }).toBe(true);
  const found = await (await request.get((process.env.TICKETMAILER_MAIL_URL || 'http://127.0.0.1:8025') + '/api/v1/search?query=' + encodeURIComponent('subject:' + subject))).json();
  expect(found.messages).toHaveLength(0);
});

test('self-service account cannot compose on another user ticket', async ({ page }) => {
  const fixture = JSON.parse(compose('exec', '-T', 'glpi', 'php', '/usr/local/bin/e2e-fixture.php'));
  await page.goto('/');
  await page.locator('#login_name').fill('post-only');
  await page.locator('#login_password').fill('postonly');
  await page.locator('[name=submit]').click();
  await expect(page.locator('#login_name')).toHaveCount(0);
  const response = await page.goto('/plugins/ticketmailer/front/compose.php?tickets_id=' + fixture.ticket);
  expect(response.status()).toBe(403);
  await expect(page.locator('form.ticketmailer-compose')).toHaveCount(0);
});

function fixture(scenario) {
  return JSON.parse(compose('exec', '-T', 'glpi', 'php', '/usr/local/bin/e2e-fixture.php', scenario));
}
async function post(page, url, fields = {}, files = {}) {
  const token = await page.locator('meta[property="glpi:csrf_token"]').getAttribute('content');
  return page.request.post(url, {
    headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-Glpi-Csrf-Token': token },
    multipart: { ...fields, ...files },
  });
}
async function captured(request, subject) {
  let found;
  await expect.poll(async () => {
    found = await (await request.get((process.env.TICKETMAILER_MAIL_URL || 'http://127.0.0.1:8025') + '/api/v1/search?query=' + encodeURIComponent('subject:' + subject))).json();
    return found.messages.length;
  }).toBe(1);
  return (await request.get((process.env.TICKETMAILER_MAIL_URL || 'http://127.0.0.1:8025') + '/api/v1/message/' + found.messages[0].ID)).json();
}

test('public history and selected attachment forward without private or unselected data', async ({ page, request }) => {
  const f = fixture('history');
  const subject = 'E2E-history-' + Date.now();
  await login(page);
  await composeMail(page, f.ticket, subject);
  const preview = await post(page, '/plugins/ticketmailer/ajax/forward_preview.php', { tickets_id: String(f.ticket) });
  expect(preview.status()).toBe(200);
  const history = await preview.json();
  expect(history.body_html).toContain('E2E_PUBLIC_CONTEXT');
  expect(history.body_html).not.toContain('E2E_PRIVATE_CONTEXT');
  await page.locator('[name=new_conversation]').check();
  await page.locator('[name=include_history]').check();
  await page.locator('[name="history_attachments[]"][value="' + f.selected + '"]').check();
  expect(await page.locator('[name="history_attachments[]"]').evaluateAll(nodes => nodes.map(n => Number(n.value)))).not.toContain(f.private);
  await page.getByRole('button', { name: 'Send', exact: true }).click();
  await expect(page).toHaveURL(/front\/ticket.form.php\?id=/);
  const mail = await captured(request, subject);
  expect(mail.Subject).toBe(subject);
  expect(mail.HTML).toContain('E2E_PUBLIC_CONTEXT');
  expect(mail.HTML).not.toContain('E2E_PRIVATE_CONTEXT');
  expect(mail.HTML).not.toContain('E2E_PRIVATE_FILE');
  expect(mail.Attachments.map(a => a.FileName)).toEqual(['E2E_SELECTED_FILE.txt']);
  expect(sql('SELECT new_conversation FROM glpi_plugin_ticketmailer_logs WHERE tickets_id=' + f.ticket)).toBe('1');
  const bytes = await request.get((process.env.TICKETMAILER_MAIL_URL || 'http://127.0.0.1:8025') + '/api/v1/message/' + mail.ID + '/part/' + mail.Attachments[0].PartID);
  expect(await bytes.text()).toBe('E2E_SELECTED_FILE');
  const audit = sql('SELECT body_html FROM glpi_plugin_ticketmailer_logs WHERE tickets_id=' + f.ticket);
  expect(audit).toContain('E2E_PUBLIC_CONTEXT');
  expect(audit).not.toContain('E2E_PRIVATE_CONTEXT');
});

test('history endpoints and send reject private and foreign document IDs', async ({ page, request }) => {
  const f = fixture('history');
  await login(page);
  for (const id of [f.private, f.foreign]) {
    const subject = 'E2E-forged-history-' + id + '-' + Date.now();
    await composeMail(page, f.ticket, subject);
    const denied = await page.request.get('/plugins/ticketmailer/front/history_attachment.php?tickets_id=' + f.ticket + '&documents_id=' + id);
    expect(denied.status()).toBe(404);
    const preview = await post(page, '/plugins/ticketmailer/ajax/forward_preview.php', { tickets_id: String(f.ticket), 'history_attachments[]': String(id) });
    expect(preview.status()).toBe(400);
    await page.locator('form.ticketmailer-compose').evaluate((form, documentId) => {
      const input = document.createElement('input');
      input.type = 'hidden'; input.name = 'history_attachments[]'; input.value = documentId; form.appendChild(input);
    }, String(id));
    await page.getByRole('button', { name: 'Send', exact: true }).click();
    await expect(page).toHaveURL(/front\/send.php/);
    await expect(page.locator('form.ticketmailer-compose')).toBeVisible();
    expect(sql('SELECT COUNT(*) FROM glpi_plugin_ticketmailer_logs WHERE tickets_id=' + f.ticket)).toBe('0');
    const found = await (await request.get((process.env.TICKETMAILER_MAIL_URL || 'http://127.0.0.1:8025') + '/api/v1/search?query=' + encodeURIComponent('subject:' + subject))).json();
    expect(found.messages).toHaveLength(0);
  }
});

test('inline image upload produces a matching CID MIME part and rejects non-images', async ({ page, request }) => {
  const f = fixture('basic');
  const subject = 'E2E-image-' + Date.now();
  const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', 'base64');
  await login(page);
  await composeMail(page, f.ticket, subject);
  const invalid = await post(page, '/plugins/ticketmailer/ajax/upload_image.php', { tickets_id: String(f.ticket) }, { image: { name: 'fake.png', mimeType: 'image/png', buffer: Buffer.from('not an image') } });
  expect(invalid.status()).toBe(415);
  const upload = await post(page, '/plugins/ticketmailer/ajax/upload_image.php', { tickets_id: String(f.ticket) }, { image: { name: 'pixel.png', mimeType: 'image/png', buffer: png } });
  expect(upload.status()).toBe(200);
  const image = await upload.json();
  expect(image.cid).toMatch(/^img-.*@ticketmailer$/);
  await page.locator('form.ticketmailer-compose').evaluate((form, image) => {
    for (const [key, value] of Object.entries(image)) {
      const input = document.createElement('input'); input.type = 'hidden';
      input.name = 'inline_images[0][' + key + ']'; input.value = String(value); form.appendChild(input);
    }
  }, image);
  // Use the native editor's source dialog, rather than inserting into a hidden textarea.
  await page.getByRole('button', { name: 'Source code', exact: true }).click();
  await page.getByRole('dialog').locator('textarea').fill('<p>E2E inline image</p><img src="' + ('cid:' + image.cid) + '">');
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await page.getByRole('button', { name: 'Send', exact: true }).click();
  await expect(page).toHaveURL(/front\/ticket.form.php\?id=/);
  const mail = await captured(request, subject);
  expect(mail.HTML).toContain('cid:' + image.cid);
  expect(mail.HTML).not.toMatch(/\/var\/www|\/files\/_|127\.0\.0\.1/);
  const part = [...mail.Inline, ...mail.Attachments].find(p => p.ContentID === image.cid || p.ContentID === '<' + image.cid + '>');
  expect(part).toBeTruthy();
  expect(part.ContentType).toBe('image/png');
  const bytes = await request.get((process.env.TICKETMAILER_MAIL_URL || 'http://127.0.0.1:8025') + '/api/v1/message/' + mail.ID + '/part/' + part.PartID);
  expect(await bytes.body()).toEqual(png);
  const anonymous = await request.get('/plugins/ticketmailer/front/download.php?log_id=' + sql('SELECT id FROM glpi_plugin_ticketmailer_logs WHERE tickets_id=' + f.ticket) + '&attachment_id=' + image.id);
  expect(await anonymous.body()).not.toEqual(png);
});

test('mailbox warning is enforced at send and explicit override is audited', async ({ page, request }) => {
  const f = fixture('mailbox');
  const subject = 'E2E-mailbox-' + Date.now();
  try {
    await login(page);
    await composeMail(page, f.ticket, subject, f.mailbox.toUpperCase());
    await page.getByRole('combobox', { name: 'To', exact: true }).press('Tab');
    await expect(page.locator('.ticketmailer-mailbox')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Send', exact: true })).toBeDisabled();
    const form = await page.locator('form.ticketmailer-compose').evaluate(form => Object.fromEntries([...new FormData(form)].filter(([,value]) => typeof value === 'string')));
    const blocked = await post(page, '/plugins/ticketmailer/front/send.php', form);
    expect(await blocked.text()).toContain('mailbox_override');
    await expect(page.locator('[name=mailbox_override]')).toBeVisible();
    expect(sql('SELECT COUNT(*) FROM glpi_plugin_ticketmailer_logs WHERE tickets_id=' + f.ticket)).toBe('0');
    await page.locator('[name=mailbox_override]').check();
    await page.getByRole('button', { name: 'Send', exact: true }).click();
    await expect(page).toHaveURL(/front\/ticket.form.php\?id=/);
    expect(sql('SELECT mailbox_override FROM glpi_plugin_ticketmailer_logs WHERE tickets_id=' + f.ticket)).toBe('1');
    expect(sql('SELECT mailbox_matches FROM glpi_plugin_ticketmailer_logs WHERE tickets_id=' + f.ticket)).toContain(f.mailbox);
    const mail = await captured(request, subject);
    expect(mail.To[0].Address.toLowerCase()).toBe(f.mailbox);
  } finally {
    sql('DELETE FROM glpi_mailcollectors WHERE id=' + f.collector);
  }
});

test('entity boundaries, recursive grants and profile reply policy protect direct endpoints', async ({ page, request }) => {
  const f = fixture('acl');
  await login(page, f.users.writer.login, 'E2E-local-only-123!');
  for (const ticket of [f.ownTicket, f.childTicket]) {
    const r = await page.goto('/plugins/ticketmailer/front/compose.php?tickets_id=' + ticket);
    expect(r.status()).toBe(200);
    await expect(page.locator('form.ticketmailer-compose')).toBeVisible();
  }
  const foreign = await page.goto('/plugins/ticketmailer/front/compose.php?tickets_id=' + f.foreignTicket);
  expect(foreign.status()).toBe(403);
  const foreignDocument = await page.request.get('/plugins/ticketmailer/front/history_attachment.php?tickets_id=' + f.foreignTicket + '&documents_id=' + f.foreignDocument);
  expect(foreignDocument.status()).toBe(403);
  for (const endpoint of ['front/send.php', 'ajax/upload.php', 'ajax/upload_image.php', 'ajax/forward_preview.php', 'ajax/validate_recipients.php', 'ajax/autocomplete_users.php']) {
    const denied = await post(page, '/plugins/ticketmailer/' + endpoint, { tickets_id: String(f.foreignTicket), recipients_to: 'recipient@example.test' });
    expect(denied.status()).toBe(403);
  }
  // These modes affect presentation, not authorization. Test actual stored/effective precedence.
  sql('UPDATE glpi_plugin_ticketmailer_reply_policies SET mode="promoted" WHERE entities_id=' + f.entity + ' AND profiles_id IS NULL');
  const policy = profile => JSON.parse(compose('exec', '-T', 'glpi', 'php', '/usr/local/bin/e2e-fixture.php', 'policy', String(f.entity), String(profile)));
  expect(policy(f.users.writer.profile)).toEqual({ stored: 'promoted', effective: 'promoted' });
  sql('INSERT INTO glpi_plugin_ticketmailer_reply_policies (entities_id,profiles_id,mode) VALUES (' + f.entity + ',' + f.users.writer.profile + ',"available")');
  expect(policy(f.users.writer.profile)).toEqual({ stored: 'available', effective: 'available' });
  expect(policy(f.users.reader.profile)).toEqual({ stored: 'promoted', effective: 'promoted' });
  sql('UPDATE glpi_plugin_ticketmailer_reply_policies SET mode="hide_native" WHERE entities_id=' + f.entity + ' AND profiles_id=' + f.users.writer.profile);
  expect(policy(f.users.writer.profile)).toEqual({ stored: 'hide_native', effective: 'promoted' });
  sql('UPDATE glpi_profiles_users SET is_recursive=0 WHERE profiles_id=' + f.users.writer.profile);
  await page.context().clearCookies();
  await login(page, f.users.writer.login, 'E2E-local-only-123!');
  const childDenied = await page.goto('/plugins/ticketmailer/front/compose.php?tickets_id=' + f.childTicket);
  expect(childDenied.status()).toBe(403);
  const subject = 'E2E-authorized-writer-' + Date.now();
  await composeMail(page, f.ownTicket, subject);
  await page.getByRole('button', { name: 'Send', exact: true }).click();
  await expect(page).toHaveURL(/front\/ticket.form.php\?id=/);
  await captured(request, subject);
  await page.context().clearCookies();
  await login(page, f.users.reader.login, 'E2E-local-only-123!');
  const readable = await page.goto('/front/ticket.form.php?id=' + f.ownTicket);
  expect(readable.status()).toBe(200);
  await expect(page.locator('body')).toContainText('Ticketmailer E2E');
  const cannotCompose = await page.goto('/plugins/ticketmailer/front/compose.php?tickets_id=' + f.ownTicket);
  expect(cannotCompose.status()).toBe(403);
  const cannotSend = await post(page, '/plugins/ticketmailer/front/send.php', { tickets_id: String(f.ownTicket), recipients_to: 'recipient@example.test' });
  expect(cannotSend.status()).toBe(403);
  for (const endpoint of ['ajax/upload.php', 'ajax/upload_image.php', 'ajax/forward_preview.php']) {
    const denied = await post(page, '/plugins/ticketmailer/' + endpoint, { tickets_id: String(f.ownTicket) });
    expect(denied.status()).toBe(403);
  }
  expect(sql('SELECT COUNT(*) FROM glpi_plugin_ticketmailer_logs WHERE tickets_id=' + f.ownTicket)).toBe('1');
  expect(sql('SELECT COUNT(*) FROM glpi_plugin_ticketmailer_logs WHERE tickets_id IN (' + [f.childTicket, f.foreignTicket].join(',') + ')')).toBe('0');
});
