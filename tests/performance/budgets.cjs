const assert = require('node:assert/strict');

// Counts are deterministic; durations use a same-machine reference and tolerate
// both timer noise and small relative fluctuations. Do not compare CI ms to a laptop.
function assertBudgets(reference, candidate) {
 assert.equal(candidate.repetitions, reference.repetitions, 'same sample count');
 assert.ok(candidate.repetitions >= 7, 'at least seven samples');
 assert.equal(candidate.chromium, reference.chromium, 'same Chromium version');
 for (const scenario of ['median', 'activeMedian']) {
  for (const key of ['queries', 'toggles', 'scriptMs', 'taskMs', 'layoutMs', 'styleMs']) {
   assert.ok(Number.isFinite(reference[scenario]?.[key]) && reference[scenario][key] >= 0, 'valid reference '+scenario+'.'+key);
   assert.ok(Number.isFinite(candidate[scenario]?.[key]) && candidate[scenario][key] >= 0, 'valid candidate '+scenario+'.'+key);
  }
 }
 // No full UI sync for unrelated mutations. Active events must remain coalesced.
 assert.equal(candidate.median.queries, 0, 'unrelated mutations must not query the document');
 assert.equal(candidate.median.toggles, 0, 'unrelated mutations must not toggle actions');
 assert.ok(candidate.activeMedian.queries <= 150, 'at most 150 document queries for 30 active frames');
 assert.ok(candidate.activeMedian.toggles <= 45, 'at most 45 jQuery toggles for 30 active frames');
 const limits = {median:{relative:1.6,absoluteMs:1}, activeMedian:{relative:1.4,absoluteMs:3}};
 for (const key of ['layoutMs', 'styleMs']) {
  const limit=Math.max(reference.activeMedian[key]*1.6,reference.activeMedian[key]+2);
  assert.ok(candidate.activeMedian[key]<=limit, 'active '+key+' rendering budget exceeded');
 }
 for (const [scenario, budget] of Object.entries(limits)) {
  const limit = Math.max(reference[scenario].scriptMs * budget.relative, reference[scenario].scriptMs + budget.absoluteMs);
  assert.ok(candidate[scenario].scriptMs <= limit,
   scenario+' script budget exceeded: '+candidate[scenario].scriptMs.toFixed(3)+' ms > '+limit.toFixed(3)+' ms (reference '+reference[scenario].scriptMs.toFixed(3)+' ms)');
 }
}
module.exports = { assertBudgets };
