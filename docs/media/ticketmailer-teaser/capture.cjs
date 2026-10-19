const {chromium}=require(process.cwd()+'/node_modules/playwright');
const {execFileSync}=require('child_process');
(async()=>{
const fixture=JSON.parse(execFileSync('docker',['--context','rootless','compose','exec','-T','--user','www-data','glpi','php','/usr/local/bin/e2e-fixture.php'],{encoding:'utf8'}));
const browser=await chromium.launch({headless:true});
const page=await browser.newPage({viewport:{width:1440,height:1080},deviceScaleFactor:1});
await page.goto('http://127.0.0.1:8080/');
await page.locator('#login_name').fill('glpi');await page.locator('#login_password').fill('glpi');await page.locator('[name=submit]').click();
await page.goto('http://127.0.0.1:8080/plugins/ticketmailer/front/compose.php?tickets_id='+fixture.ticket);
await page.getByRole('combobox',{name:'To',exact:true}).fill('customer@example.test');
await page.getByRole('combobox',{name:'CC',exact:true}).fill('support@example.test');
await page.locator('[name=subject]').fill('Your replacement device is ready');
await page.frameLocator('iframe[id^=ticketmailer-body-html]').locator('body').fill(`Hello Alex,

Your replacement device is ready for collection. We have completed the setup and checked the connection.

Please reply to this email if you need help arranging the handover.

Kind regards,
The support team`);
await page.screenshot({path:'/tmp/ticketmailer-current-full.png',fullPage:true});
await page.screenshot({path:process.cwd()+'/assets/readme/email-compose-current.png',clip:{x:250,y:70,width:1180,height:930}});
await browser.close();
})();