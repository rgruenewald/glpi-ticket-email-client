#!/usr/bin/env node
// NODE_PATH=/tmp/ticketmailer-ui-bench/node_modules node scripts/benchmark-ui.cjs ROOT OUTPUT
// Dependencies: npm install --prefix /tmp/ticketmailer-ui-bench playwright pngjs jquery@3.7.1
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(process.argv[2] || '.');
const output = path.resolve(process.argv[3] || '/tmp/ui-benchmark.json');
const repetitions = Number(process.env.REPETITIONS || 9);
assert.ok(Number.isInteger(repetitions) && repetitions >= 1, 'valid repetition count');
function fixture() {
 return '<!doctype html><html><head></head><body><div id="itil-footer"><div class="main-actions"><button class="answer-action">Reply</button><button class="dropdown-toggle-split">More</button></div></div><div id="right-actions"></div><ul class="legacy-timeline-actions"><li><button class="ticketmailer-timeline-action">Email</button></li></ul><div id="new-itilobject-form"><div class="collapse" id="native"></div><div class="collapse show" id="plugin"><form class="ticketmailer-compose"><div class="ticketmailer-mail-panel"><div class="ticketmailer-mail-header">Email</div><div class="ticketmailer-mail-row"><label class="ticketmailer-mail-label">To</label><div class="ticketmailer-mail-field"><div class="ticketmailer-recipient-control"><div class="ticketmailer-recipient-chip"><span>Test</span><button>×</button></div><input class="ticketmailer-recipient-input" value="example@example.test"></div></div></div><div class="ticketmailer-mail-row ticketmailer-mail-row--editor"><div class="ticketmailer-mail-field"><div class="ticketmailer-editor" contenteditable="true">Message</div></div></div><div class="ticketmailer-forward-toolbar"><button class="ticketmailer-mode-button">Forward</button></div><div class="ticketmailer-send-row"><button class="btn">Send</button></div></div></form></div></div><div data-ticketmailer-panel="internal_note"><form><div class="ticketmailer-note-options"><div class="row"><div class="ticketmailer-note-meta"><label>Source</label><br><select><option>Phone</option></select></div></div></div><div class="ticketmailer-note-actions"><button>Cancel</button><div class="ticketmailer-note-private"><label><input type="checkbox">Private</label></div><button>Save</button></div></form></div><div id="noise">' + '<div class="noise">Timeline item</div>'.repeat(1500) + '</div></body></html>';
}
async function main() {
 fs.mkdirSync(path.dirname(output), { recursive: true });
 const browser = await chromium.launch({headless:true, executablePath: process.env.CHROMIUM || undefined, args:['--no-sandbox']});
 try {
 const page = await browser.newPage({viewport:{width:1280,height:900}});
 const pageErrors=[]; page.on('pageerror',error=>pageErrors.push(error.message));
 const cdp = await page.context().newCDPSession(page);
 await cdp.send('Performance.enable');
 async function metrics() { return Object.fromEntries((await cdp.send('Performance.getMetrics')).metrics.map(m=>[m.name,m.value])); }
 async function setup() {
  await page.goto('about:blank'); await page.setContent(fixture());
  await page.addStyleTag({path:process.env.UI_STYLESHEET || path.join(root,'public/css/ticketmailer.css')});
  await page.addScriptTag({path:require.resolve('jquery/dist/jquery.js')});
  await page.evaluate(() => {
   window.uiCounts={queries:0,toggles:0};
   const query=document.querySelectorAll.bind(document);
   document.querySelectorAll=(s)=>{window.uiCounts.queries++; return query(s);};
   const toggle=$.fn.toggle;
   $.fn.toggle=function(...args){window.uiCounts.toggles++;return toggle.apply(this,args);};
   window.ajaxComplete=()=>$(document).trigger('ajaxComplete');
  });
  await page.addScriptTag({path:process.env.TIMELINE_SCRIPT || path.join(root,'public/js/ticket-timeline.js')});
  await page.waitForTimeout(400);
 }
 await setup(); // Discard a warm-up before collecting samples.
 const samples=[];
 for(let i=0;i<repetitions;i++) {
  await setup();
  await page.evaluate(()=>{window.uiCounts={queries:0,toggles:0};});
  const before=await metrics();
  await page.evaluate(async()=>{for(let i=0;i<30;i++){document.querySelector('#noise').classList.toggle('busy'); await new Promise(requestAnimationFrame);} });
  await page.waitForTimeout(400);
  const after=await metrics();
  samples.push({...(await page.evaluate(()=>window.uiCounts)), scriptMs:(after.ScriptDuration-before.ScriptDuration)*1000,layoutMs:(after.LayoutDuration-before.LayoutDuration)*1000,styleMs:(after.RecalcStyleDuration-before.RecalcStyleDuration)*1000,taskMs:(after.TaskDuration-before.TaskDuration)*1000});
 }
 await setup(); // Warm up the active scenario independently.
 const activeSamples=[];
 for(let i=0;i<repetitions;i++) {
  await setup();
  await page.evaluate(()=>{window.uiCounts={queries:0,toggles:0};});
  const before=await metrics();
  await page.evaluate(async()=>{for(let i=0;i<30;i++){
   const plugin=document.querySelector('#plugin');
   plugin.classList.toggle('show');
   document.dispatchEvent(new Event('shown.bs.collapse'));
   $(document).trigger('shown.bs.collapse');
   window.ajaxComplete();
   await new Promise(requestAnimationFrame);
  }});
  await page.waitForTimeout(400);
  const after=await metrics();
  activeSamples.push({...(await page.evaluate(()=>window.uiCounts)),scriptMs:(after.ScriptDuration-before.ScriptDuration)*1000,layoutMs:(after.LayoutDuration-before.LayoutDuration)*1000,styleMs:(after.RecalcStyleDuration-before.RecalcStyleDuration)*1000,taskMs:(after.TaskDuration-before.TaskDuration)*1000});
 }
 // Interaction and dynamically inserted native/plugin forms, including unwrapped forms.
 await setup();
 async function state() {if(process.env.OBSERVER_CHECKS !== '1') await page.evaluate(()=>window.ajaxComplete()); await page.waitForTimeout(400); return page.evaluate(()=>({compose:document.body.classList.contains('ticketmailer-compose-active'),hidden:document.querySelector('.ticketmailer-timeline-action').hidden}));}
 assert.deepEqual(await state(),{compose:true,hidden:false});
 await page.evaluate(()=>document.querySelector('#native').classList.add('show'));
 assert.deepEqual(await state(),{compose:true,hidden:true});
 await page.evaluate(()=>{document.querySelector('#native').classList.remove('show');document.querySelector('#plugin').classList.remove('show');});
 assert.deepEqual(await state(),{compose:false,hidden:false});
 await page.evaluate(()=>{const f=document.createElement('form');f.className='ticketmailer-compose';f.id='standalone';f.textContent='Open';document.body.append(f);});
 assert.deepEqual(await state(),{compose:true,hidden:false});
 await page.evaluate(()=>document.querySelector('#standalone').remove());
 assert.deepEqual(await state(),{compose:false,hidden:false});
 // A visibility wrapper outside .collapse must also be observed.
 await page.evaluate(()=>{const w=document.createElement('div');w.id='wrapper';w.innerHTML='<form class="ticketmailer-compose">Open</form>';document.body.append(w);});
 assert.equal((await state()).compose,true);
 await page.evaluate(()=>document.querySelector('#wrapper').style.display='none');
 assert.equal((await state()).compose,false);
 await page.evaluate(()=>document.querySelector('#wrapper').remove());
 await page.evaluate(()=>{const f=document.createElement('form');f.id='renamed';f.className='ticketmailer-compose';f.textContent='Visible';document.body.append(f);});
 assert.equal((await state()).compose,true);
 await page.evaluate(()=>document.querySelector('#renamed').classList.remove('ticketmailer-compose'));
 await page.waitForTimeout(400);
 if(process.env.BASELINE !== '1') assert.equal(await page.evaluate(()=>document.body.classList.contains('ticketmailer-compose-active')),false);
 if(process.env.OBSERVER_CHECKS === '1') {
  await page.evaluate(()=>document.querySelector('#native').classList.add('show'));
  await page.waitForTimeout(80);
  assert.equal(await page.evaluate(()=>document.querySelector('.ticketmailer-timeline-action').hidden),true);
  await page.evaluate(()=>document.querySelector('#native').classList.remove('collapse'));
  await page.waitForTimeout(80);
  assert.equal(await page.evaluate(()=>document.querySelector('.ticketmailer-timeline-action').hidden),false);
  await page.evaluate(()=>document.querySelector('#native').remove());
  await page.evaluate(()=>{const n=document.createElement('div');n.className='collapse show';document.querySelector('#new-itilobject-form').append(n);});
  await page.waitForTimeout(80);
  assert.equal(await page.evaluate(()=>document.querySelector('.ticketmailer-timeline-action').hidden),true);
  await page.evaluate(()=>document.querySelector('#new-itilobject-form > .collapse:last-child').remove());
  await page.waitForTimeout(80);
  assert.equal(await page.evaluate(()=>document.querySelector('.ticketmailer-timeline-action').hidden),false);

  await page.evaluate(()=>{const w=document.createElement('div');w.id='observed';w.innerHTML='<form class="ticketmailer-compose">Visible</form>';document.body.append(w);});
  await page.waitForTimeout(80);
  assert.equal(await page.evaluate(()=>document.body.classList.contains('ticketmailer-compose-active')),true);
  await page.evaluate(()=>document.querySelector('#observed').hidden=true);
  await page.waitForTimeout(80);
  assert.equal(await page.evaluate(()=>document.body.classList.contains('ticketmailer-compose-active')),false);
  await page.evaluate(()=>document.querySelector('#observed').hidden=false);
  await page.waitForTimeout(80);
  assert.equal(await page.evaluate(()=>document.body.classList.contains('ticketmailer-compose-active')),true);
  await page.evaluate(()=>document.querySelector('#observed form').classList.remove('ticketmailer-compose'));
  await page.waitForTimeout(80);
  assert.equal(await page.evaluate(()=>document.body.classList.contains('ticketmailer-compose-active')),false);
  await page.evaluate(()=>document.querySelector('#observed').remove());
 }
 await setup();
 for(const width of [1280,390]) {
  await page.setViewportSize({width,height:900});
  await page.screenshot({path:output.replace(/.json$/, '-'+width+'.png')});
 }
 const median=key=>samples.map(s=>s[key]).sort((a,b)=>a-b)[Math.floor(samples.length/2)];
 const activeMedian=Object.fromEntries(Object.keys(activeSamples[0]).map(k=>[k,activeSamples.map(s=>s[k]).sort((a,b)=>a-b)[Math.floor(activeSamples.length/2)]]));
 assert.deepEqual(pageErrors,[], 'no browser runtime errors');
 const result={root,activeSamples,activeMedian,chromium:browser.version(),jquery:require('jquery/package.json').version,scenario:'30 unrelated class mutations over 30 frames; 1500 timeline nodes; 400ms settle; no CPU throttle',repetitions,samples,median:Object.fromEntries(Object.keys(samples[0]).map(k=>[k,median(k)])),functionalChecks:'passed'};
 fs.writeFileSync(output,JSON.stringify(result,null,2));
 console.log(JSON.stringify({noise:result.median,active:result.activeMedian}));
 } finally { await browser.close(); }
}
main().catch(e=>{console.error(e);process.exit(1);});
