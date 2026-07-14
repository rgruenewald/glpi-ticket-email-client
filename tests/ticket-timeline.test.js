'use strict';

const assert = require('node:assert/strict');

let domReady;
let ajaxComplete;
let mutation;
let classMutation;
const collapseEvents = {};
let clickCapture = null;
let replyButton = null;
let clicks = 0;
let replyPanelOpen = false;
let forwardPanelOpen = false;
let composeForms = [];
const bodyClasses = new Set();
let nowMs = 1000;
const timers = [];

global.Date.now = () => nowMs;

global.requestAnimationFrame = (fn) => {
    fn();
    return 1;
};
global.setTimeout = (fn, delay) => {
    timers.push({fn, delay});
    return timers.length;
};

const flushTimers = () => {
    while (timers.length) {
        timers.shift().fn();
    }
};
const makeForm = (open) => ({
    offsetHeight: open ? 100 : 0,
    closest(sel) {
        if (sel === '.collapse') {
            return {
                classList: {
                    contains(name) {
                        return open && (name === 'show' || name === 'in');
                    },
                },
            };
        }
        return null;
    },
});

global.document = {
    readyState: 'loading',
    documentElement: {},
    body: {
        classList: {
            toggle(name, enabled) {
                if (enabled) {
                    bodyClasses.add(name);
                } else {
                    bodyClasses.delete(name);
                }
            },
            add(name) {
                bodyClasses.add(name);
            },
            contains(name) {
                return bodyClasses.has(name);
            },
        },
    },
    addEventListener(type, handler, capture) {
        if (type === 'DOMContentLoaded') {
            domReady = handler;
        } else if (type === 'click') {
            clickCapture = handler;
        } else {
            collapseEvents[type] = handler;
        }
    },
    querySelector(selector) {
        if (selector === '.ticketemailclient-timeline-action[data-ticketemailclient-auto-open="1"]') {
            return replyButton;
        }
        assert.fail('unexpected querySelector: ' + selector);
    },
    querySelectorAll(selector) {
        if (selector === '.ticketemailclient-compose') {
            return composeForms;
        }
        assert.fail('unexpected querySelectorAll: ' + selector);
    },
};
global.getComputedStyle = () => {
    return {
        display: 'block',
        visibility: 'visible',
    };
};
global.MutationObserver = class {
    constructor(handler) {
        if (this.constructor.observers) {
            this.constructor.observers.push(handler);
        }
    }

    observe() {}

    disconnect() {}
};
global.MutationObserver.observers = [];

let jqueryShown = null;
let jqueryHidden = null;
global.$ = function () {
    const chainable = {
        on(events, handler) {
            if (events === 'shown.bs.collapse') {
                jqueryShown = handler;
            } else if (events === 'hidden.bs.collapse') {
                jqueryHidden = handler;
            } else {
                assert.fail('unexpected jQuery on() events: ' + events);
            }
            return chainable;
        },
        ajaxComplete(handler) {
            ajaxComplete = handler;
        },
    };
    return chainable;
};

require('../js/ticket-timeline.js');

// Capture the MutationObserver handlers (first=openReply, second=classObserver)
{
    const observers = global.MutationObserver.observers;
    mutation = observers[0];
    classMutation = observers[observers.length - 1];
}

domReady();
assert.equal(clicks, 0, 'reply button is not present during initial page load');

// GLPI inserts the auto-open reply button
replyButton = {
    click() {
        clicks += 1;
        replyPanelOpen = true;
        composeForms = [makeForm(true)];
    },
};
mutation();
flushTimers();
assert.equal(clicks, 1, 'reply opens when GLPI inserts its action button later');
assert.equal(
    document.body.classList.contains('ticketemailclient-compose-active'),
    true,
    'timeline actions are hidden while the email reply form is open',
);

// Close the reply form via hidden event
replyPanelOpen = false;
composeForms = [makeForm(false)];
jqueryHidden();
assert.equal(
    document.body.classList.contains('ticketemailclient-compose-active'),
    false,
    'timeline actions return when the email reply form is closed',
);

// Open forward form via shown event
forwardPanelOpen = true;
composeForms = [makeForm(true)];
jqueryShown();
assert.equal(
    document.body.classList.contains('ticketemailclient-compose-active'),
    true,
    'timeline actions are hidden while the email forward form is open',
);

// Close forward to reset before optimistic click test
forwardPanelOpen = false;
composeForms = [makeForm(false)];
jqueryHidden();
assert.equal(
    document.body.classList.contains('ticketemailclient-compose-active'),
    false,
    'body class cleared before optimistic click test',
);


// Simulate user clicking a Reply timeline action button
replyPanelOpen = true;
composeForms = [makeForm(false)]; // form not visible yet (BS still transitioning)
clickCapture({target: {closest: (sel) => sel === '.ticketemailclient-timeline-action' ? {} : null}});
assert.equal(
    document.body.classList.contains('ticketemailclient-compose-active'),
    true,
    'optimistic hide fires immediately on plugin action click before BS transition completes',
);
flushTimers();
assert.equal(
    document.body.classList.contains('ticketemailclient-compose-active'),
    true,
    'optimistic window keeps body class alive until form becomes visible',
);
flushTimers();
assert.equal(
    document.body.classList.contains('ticketemailclient-compose-active'),
    true,
    'body class stays active after collapse transition completes',
);

// Advance past the optimistic window so close syncs properly
nowMs += 1000;

// Close all forms via hidden event (exercises onCollapseHidden path)
replyPanelOpen = false;
forwardPanelOpen = false;
composeForms = [makeForm(false)];
jqueryHidden();
ajaxComplete();
mutation();
flushTimers();
assert.equal(clicks, 1, 'later page updates do not reopen a manually closed reply');
assert.equal(
    document.body.classList.contains('ticketemailclient-compose-active'),
    false,
    'timeline actions remain available after the compose form is closed',
);
