const assert = require("node:assert/strict");

let domReady;
let ajaxComplete;
let classMutation;
const collapseEvents = {};
let clickCapture = null;
let forwardPanelOpen = false;
let composeForms = [];
let nativeForms = [];
const timelineActions = [{ hidden: false }, { hidden: false }];
const bodyClasses = new Set();
const nowMs = 1000;
const timers = [];
let frame = null;
let queryCount = 0;

global.Date.now = () => nowMs;

global.requestAnimationFrame = (fn) => {
	frame = fn;
	return 1;
};
global.setTimeout = (fn, delay) => {
	timers.push({ fn, delay });
	return timers.length;
};

const flushTimers = () => {
    while (frame) { const fn = frame; frame = null; fn(); }
	while (timers.length) {
		timers.shift().fn();
	}
};
const makeCollapse = (state, pluginOwned = false) => ({
	querySelector(selector) {
		return pluginOwned && selector === ".ticketmailer-compose" ? {} : null;
	},
	classList: {
		contains(name) {
			return name === state;
		},
	},
});
const makeForm = (open) => ({
	offsetHeight: open ? 100 : 0,
	closest(sel) {
		return sel === ".collapse" ? makeCollapse(open ? "show" : "") : null;
	},
});

global.document = {
	readyState: "loading",
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
		if (type === "DOMContentLoaded") {
			domReady = handler;
		} else if (type === "click") {
			clickCapture = handler;
		} else {
			collapseEvents[type] = handler;
		}
	},
	querySelectorAll(selector) {
        queryCount++;
		if (selector === ".ticketmailer-compose") {
			return composeForms;
		}
		if (selector === "#new-itilobject-form > .collapse") {
			return nativeForms;
		}
		if (selector === ".ticketmailer-timeline-action") {
			return timelineActions;
		}
		assert.fail("unexpected querySelectorAll: " + selector);
	},
};
global.getComputedStyle = () => {
	return {
		display: "block",
		visibility: "visible",
	};
};
global.MutationObserver = class {
	constructor(handler) {
		if (this.constructor.observers) {
			this.constructor.observers.push(handler);
		}
	}

	takeRecords() { return []; } observe() {}

	disconnect() {}
};
global.MutationObserver.observers = [];

const jqueryCollapseEvents = {};
global.$ = () => {
	const chainable = { toggle() {},
		on(events, handler) {
			jqueryCollapseEvents[events] = handler;
			return chainable;
		},
		ajaxComplete(handler) {
			ajaxComplete = handler;
		},
	};
	return chainable;
};

require("../public/js/ticket-timeline.js");

// Capture the class observer.
{
	const observers = global.MutationObserver.observers;
	classMutation = observers[observers.length - 1];
}

nativeForms = [makeCollapse("show")];
domReady();
flushTimers();
assert.equal(
	composeForms.length,
	0,
	"reply remains closed during initial page load",
);
assert.deepEqual(
	timelineActions.map((action) => action.hidden),
	[true, true],
	"every plugin action is hidden when a native answer is initially open",
);

nativeForms = [];
collapseEvents["hidden.bs.collapse"]({ target: makeCollapse("") });
flushTimers();
assert.deepEqual(
	timelineActions.map((action) => action.hidden),
	[false, false],
	"plugin actions return after the last native form closes",
);

nativeForms = [makeCollapse("collapsing")];
collapseEvents["show.bs.collapse"]({ target: nativeForms[0] });
flushTimers();
assert.equal(
	timelineActions[0].hidden,
	true,
	"opening native solution hides the plugin action",
);

nativeForms = [makeCollapse("show"), makeCollapse("show")];
collapseEvents["shown.bs.collapse"]({ target: nativeForms[1] });
flushTimers();
nativeForms = [makeCollapse("show")];
collapseEvents["hidden.bs.collapse"]({ target: makeCollapse("") });
flushTimers();
assert.equal(
	timelineActions[0].hidden,
	true,
	"closing one of multiple native forms keeps plugin actions hidden",
);

nativeForms = [makeCollapse("show", true)];
classMutation([{ type: "attributes", target: { nodeType: 1, id: "new-itilobject-form", classList: { contains: () => false } } }]);
flushTimers();
assert.equal(
	timelineActions[0].hidden,
	false,
	"plugin-owned collapse is not classified as native",
);

nativeForms = [makeCollapse("show")];
ajaxComplete();
flushTimers();
assert.equal(
	timelineActions[0].hidden,
	true,
	"AJAX replacement resynchronizes native form visibility",
);
nativeForms = [];
classMutation([{ type: "attributes", target: { nodeType: 1, id: "new-itilobject-form", classList: { contains: () => false } } }]);
flushTimers();
assert.equal(
	timelineActions[0].hidden,
	false,
	"class mutations resynchronize native form visibility",
);
assert.equal(
	composeForms.length,
	0,
	"reply remains closed until explicitly opened",
);

// Close the reply form via hidden event
composeForms = [makeForm(false)];
jqueryCollapseEvents["hidden.bs.collapse"]();
flushTimers();
assert.equal(
	document.body.classList.contains("ticketmailer-compose-active"),
	false,
	"timeline actions return when the email reply form is closed",
);

// Open forward form via shown event
forwardPanelOpen = true;
composeForms = [makeForm(true)];
jqueryCollapseEvents["shown.bs.collapse"]();
flushTimers();
assert.equal(
	document.body.classList.contains("ticketmailer-compose-active"),
	true,
	"timeline actions are hidden while the email forward form is open",
);

// Close forward to reset before optimistic click test
forwardPanelOpen = false;
composeForms = [makeForm(false)];
jqueryCollapseEvents["hidden.bs.collapse"]();
flushTimers();
assert.equal(
	document.body.classList.contains("ticketmailer-compose-active"),
	false,
	"body class cleared before optimistic click test",
);

// Close all forms via hidden event (exercises onCollapseHidden path)
forwardPanelOpen = false;
composeForms = [makeForm(false)];
jqueryCollapseEvents["hidden.bs.collapse"]();
flushTimers();
ajaxComplete();
flushTimers();
flushTimers();
assert.equal(
	document.body.classList.contains("ticketmailer-compose-active"),
	false,
	"timeline actions remain available after the compose form is closed",
);

const queriesBefore = queryCount;
for (let i = 0; i < 20; i++) ajaxComplete();
assert.equal(queryCount, queriesBefore, "events defer DOM reads until the next frame");
flushTimers();
assert.equal(queryCount - queriesBefore, 3, "event bursts share one synchronization");
classMutation([{type:"attributes",target:{nodeType:1,id:"noise",classList:{contains:()=>false},getElementsByClassName:()=>[]}}]);
assert.equal(frame, null, "unrelated classes schedule no work");

classMutation([{type:"attributes", attributeName:"class", oldValue:"collapse show", target:{nodeType:1,id:"native",parentElement:{id:"new-itilobject-form"},classList:{contains:()=>false},getElementsByClassName:()=>[]}}]);
assert.notEqual(frame, null, "removing the native collapse class schedules a synchronization");
flushTimers();
classMutation([{type:"attributes", attributeName:"class", oldValue:"ticketmailer-compose", target:{nodeType:1,id:"renamed",classList:{contains:()=>false},getElementsByClassName:()=>[]}}]);
assert.notEqual(frame, null, "removing compose ownership schedules a synchronization");
flushTimers();
