const assert = require('node:assert/strict');
const fs = require('node:fs');
const read = (path) => fs.readFileSync(require('node:path').join(__dirname, '..', path), 'utf8');
const action = read('inc/timelineaction.class.php');
const config = read('inc/config.class.php');
for (const key of ['show_action_reply', 'show_action_task', 'show_action_solution', 'show_action_document', 'show_action_internal_note', 'show_answer_templates', 'show_solution_templates', 'show_followup_source']) {
    assert.ok(config.includes(key), key + ' must be configurable');
}
assert.ok(action.includes('initial_mode'));
assert.ok(action.includes('nativeActions'));
const autocomplete = read('ajax/autocomplete_users.php');
assert.ok(autocomplete.includes('getEntitiesRestrictCriteria'));
assert.ok(autocomplete.includes('glpiactiveentities'));
assert.ok(!autocomplete.includes('getSonsOf'));
assert.ok(!autocomplete.includes('$DB->escape($query)'));
console.log('Configurable action contracts passed');
