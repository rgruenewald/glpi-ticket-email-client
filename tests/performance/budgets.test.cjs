const {test}=require('node:test');
const assert=require('node:assert/strict');
const {assertBudgets}=require('./budgets.cjs');
const baseline=()=>({repetitions:9,chromium:'test',median:{queries:0,toggles:0,scriptMs:1,taskMs:5,layoutMs:0,styleMs:0},activeMedian:{queries:150,toggles:45,scriptMs:7,taskMs:65,layoutMs:2,styleMs:3}});
test('same performance and tolerable measurement noise pass',()=>{
 const a=baseline(),b=baseline();b.median.scriptMs=1.8;b.activeMedian.scriptMs=9.5;assertBudgets(a,b);
});
for(const [name,change,reason] of [
 ['unrelated DOM scans', b=>b.median.queries=3,/must not query/],
 ['unrelated action writes', b=>b.median.toggles=1,/must not toggle/],
 ['lost event coalescing', b=>b.activeMedian.queries=155,/150 document queries/],
 ['duplicate action toggles', b=>b.activeMedian.toggles=46,/45 jQuery toggles/],
 ['slow idle observer', b=>b.median.scriptMs=4,/script budget exceeded/],
 ['slow active interactions', b=>b.activeMedian.scriptMs=14,/script budget exceeded/],
 ['slow layout', b=>b.activeMedian.layoutMs=12,/rendering budget exceeded/],
 ['slow styles', b=>b.activeMedian.styleMs=12,/rendering budget exceeded/],
 ['invalid timings', b=>b.activeMedian.scriptMs=NaN,/valid candidate/],
 ['missing metrics', b=>delete b.median.styleMs,/valid candidate/],
 ['insufficient samples', b=>b.repetitions=1,/same sample count/],
 ['different browsers', b=>b.chromium='other',/same Chromium/],
]) test(name+' fails',()=>{const a=baseline(),b=baseline();change(b);assert.throws(()=>assertBudgets(a,b),reason);});
