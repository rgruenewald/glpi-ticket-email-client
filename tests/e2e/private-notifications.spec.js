const {test,expect}=require('@playwright/test');
const {execFileSync}=require('node:child_process');
const {readFileSync}=require('node:fs');
const path=require('node:path');
const {mailURL,sql}=require('./status-helpers');
test('native private notifications deliver only to permitted technician',async({request})=>{
 const output=execFileSync('docker',['--context',process.env.TICKETMAILER_DOCKER_CONTEXT||'rootless','compose','exec','-T','--user','www-data','glpi','php'],{
  cwd:path.resolve(__dirname,'../..'), input:readFileSync(path.resolve(__dirname,'../fixtures/native-notifications.php')),encoding:'utf8'
 });
 const f=JSON.parse(output);
 for(const [scenario,result] of Object.entries(f.results)) {
  expect(result.is_private).toBe(scenario==='public'?0:1);
  for(const queued of result.queue) expect(queued.sent).toBe(true);
  const search=await request.get(mailURL+'/api/v1/search?query='+encodeURIComponent(result.marker));
  expect(search.ok()).toBe(true); const messages=(await search.json()).messages;
  const recipients=[];
  for(const message of messages) {
   const response=await request.get(mailURL+'/api/v1/message/'+message.ID);
   expect(response.ok()).toBe(true); const detail=await response.json();
   expect(detail.Text+detail.HTML).toContain(result.marker);
   recipients.push(...[...(detail.To||[]),...(detail.Cc||[]),...(detail.Bcc||[])].map(r=>r.Address));
  }
  if(scenario==='public') {
   for(const person of Object.values(f.people)) expect(recipients).toContain(person.email);
  } else if(scenario==='private') {
   expect(recipients).toEqual([f.people.technician.email]);
  } else {
   expect(messages).toEqual([]);
   expect(result.queue).toEqual([]);
  }
  expect(sql('SELECT COUNT(*) FROM glpi_plugin_ticketmailer_logs WHERE tickets_id='+result.ticket)).toBe('0');
 }
});
