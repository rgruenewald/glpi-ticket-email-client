const fs=require('node:fs');
const path=require('node:path');
const {spawnSync}=require('node:child_process');
const {assertBudgets}=require('./budgets.cjs');
const root=path.resolve(__dirname,'../..');
const output=path.join(root,'test-results/performance');
fs.mkdirSync(output,{recursive:true});
function measure(name,script,observerChecks,stylesheet) {
 const destination=path.join(output,name+'.json');
 const result=spawnSync(process.execPath,[path.join(root,'scripts/benchmark-ui.cjs'),root,destination],{
  cwd:root,stdio:'inherit',env:{...process.env,REPETITIONS:'9',TIMELINE_SCRIPT:script,UI_STYLESHEET:stylesheet,OBSERVER_CHECKS:observerChecks?'1':'0'}
 });
 if(result.error)throw result.error;
 if(result.status!==0)throw new Error(name+' browser measurement failed (exit '+result.status+')');
 return JSON.parse(fs.readFileSync(destination,'utf8'));
}
// Immutable performance reference, not a historical slow implementation.
// Update only after a deliberate baseline review, never automatically on failure.
const reference=measure('reference',path.join(__dirname,'reference-timeline.js'),true,path.join(__dirname,'reference.css'));
const candidate=measure('candidate',process.env.PERFORMANCE_CANDIDATE_SCRIPT || path.join(root,'public/js/ticket-timeline.js'),true,path.join(root,'public/css/ticketmailer.css'));
assertBudgets(reference,candidate);
console.log('PASS: UI performance budgets (9 samples per scenario), results: '+output);
