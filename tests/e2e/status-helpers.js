const { execFileSync } = require('node:child_process');
const path = require('node:path');
const compose = (...args) => execFileSync('docker', ['--context', process.env.TICKETMAILER_DOCKER_CONTEXT || 'rootless', 'compose', ...args], {cwd:path.resolve(__dirname,'../..'),encoding:'utf8'});
const sql = query => compose('exec','-T','db','mariadb','-uglpi','-pglpi','--batch','--skip-column-names','glpi','-e',query).trim();
const mailURL = process.env.TICKETMAILER_MAIL_URL || 'http://127.0.0.1:8025';
async function mailIDs(request) {
 const response=await request.get(mailURL+'/api/v1/messages?limit=10000');
 if (!response.ok()) throw new Error('Mailpit unavailable');
 return (await response.json()).messages.map(m=>m.ID).sort();
}
module.exports={compose,sql,mailURL,mailIDs};
