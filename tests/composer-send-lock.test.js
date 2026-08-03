const assert = require("node:assert/strict");

const listeners = {};
const page = {
	attributes: {},
	inert: false,
	setAttribute(name, value) {
		this.attributes[name] = value;
	},
};
const body = {
	attributes: {},
	children: [page],
	appendChild(child) {
		child.parentNode = this;
		this.children.push(child);
	},
	setAttribute(name, value) {
		this.attributes[name] = value;
	},
};
const sendButton = {
	attributes: {},
	disabled: false,
	setAttribute(name, value) {
		this.attributes[name] = value;
	},
	prepend() {},
};

function statusToggle(name, checked) {
	return {
		name,
		checked,
		changeHandler: null,
		addEventListener(type, handler) {
			if (type === "change") {
				this.changeHandler = handler;
			}
		},
		dispatchEvent() {
			if (this.changeHandler) {
				this.changeHandler();
			}
		},
	};
}
function makeForm(waitingChecked = false, solvedChecked = false) {
	const waiting = statusToggle("set_waiting", waitingChecked);
	const solved = statusToggle("set_solved", solvedChecked);
	return {
		dataset: {},
		submitHandler: null,
		status: { waiting, solved },
		addEventListener(type, handler) {
			if (type === "submit") {
				this.submitHandler = handler;
			}
		},
		querySelector(selector) {
			if (selector === 'input[name="set_waiting"]') {
				return waiting;
			}
			if (selector === 'input[name="set_solved"]') {
				return solved;
			}
			return null;
		},
		querySelectorAll(selector) {
			return selector === 'button[type="submit"]' ? [sendButton] : [];
		},
	};
}
const form = makeForm();
const composeForms = [form];

global.window = {};
global.document = {
	body,
	addEventListener(type, handler) {
		listeners[type] = handler;
	},
	createEvent() {
		return { initEvent() {} };
	},
	createElement() {
		return {
			attributes: {},
			style: {},
			append(...children) {
				this.innerText = children.map((child) => child.innerText).join("");
			},
			setAttribute(name, value) {
				this.attributes[name] = value;
			},
			appendChild() {},
			remove() {
				this.parentNode.children = this.parentNode.children.filter(
					(child) => child !== this,
				);
			},
		};
	},
	querySelector() {
		return null;
	},
	querySelectorAll(selector) {
		if (selector === ".ticketmailer-compose") {
			return composeForms;
		}
		if (selector === "[data-ticketmailer-delivery]") {
			return deliveryModeRoots;
		}
		return [];
	},
};
global.DOMParser = class DOMParser {
	parseFromString(content) {
		return {
			body: {
				childNodes: [
					{
						innerText: content
							.replace(/<\/(p|div|li|h[1-6])>/gi, "\n")
							.replace(/<br\s*\/?>/gi, "\n")
							.replace(/<[^>]*>/g, "")
							.trim(),
					},
				],
			},
		};
	}
};
function deliveryModeInput(value, checked) {
	return {
		value,
		checked,
		changeHandler: null,
		addEventListener(type, handler) {
			if (type === "change") {
				this.changeHandler = handler;
			}
		},
	};
}
function makeDeliveryModeRoot() {
	const inputs = [
		deliveryModeInput("email", true),
		deliveryModeInput("internal_note", false),
	];
	const panels = {
		email: {
			hidden: false,
			attributes: {},
			setAttribute(name, value) {
				this.attributes[name] = value;
			},
		},
		internal_note: {
			hidden: true,
			attributes: {},
			setAttribute(name, value) {
				this.attributes[name] = value;
			},
		},
	};
	return {
		dataset: {},
		inputs,
		panels,
		querySelector(selector) {
			if (selector === "[data-delivery-mode]:checked") {
				return inputs.find((input) => input.checked) || null;
			}
			if (selector === '[data-ticketmailer-panel="email"]') {
				return panels.email;
			}
			if (selector === '[data-ticketmailer-panel="internal_note"]') {
				return panels.internal_note;
			}
			return null;
		},
		querySelectorAll(selector) {
			return selector === "[data-delivery-mode]" ? inputs : [];
		},
	};
}
const deliveryModeRoot = makeDeliveryModeRoot();
const secondDeliveryModeRoot = makeDeliveryModeRoot();
deliveryModeRoot.radioName = "delivery_mode_ticketmailer-email-reply-42-inline";
secondDeliveryModeRoot.radioName =
	"delivery_mode_ticketmailer-email-reply-42-modal";
assert.notEqual(
	deliveryModeRoot.radioName,
	secondDeliveryModeRoot.radioName,
	"compose roots use distinct radio groups",
);
const deliveryModeRoots = [deliveryModeRoot, secondDeliveryModeRoot];
const deliveryModeInputs = deliveryModeRoot.inputs;
const deliveryModePanels = deliveryModeRoot.panels;
let templateChangeHandler;
let ajaxComplete;
global.$ = () => ({
	ajaxComplete(handler) {
		ajaxComplete = handler;
	},
	on(type, selector, handler) {
		if (type === "change") {
			templateChangeHandler = handler;
		}
	},
});
const templateEditor = {
	content: "<p>Signature</p>",
	getContent() {
		return this.content;
	},
	setContent(content) {
		this.content = content;
	},
	getElement() {
		return { isConnected: true };
	},
};
global.tinymce = {
	get() {
		return templateEditor;
	},
	init() {},
};
global.window.tinymce = global.tinymce;
const templateRequestHeaders = {};
global.XMLHttpRequest = class {
	open() {}
	setRequestHeader(name, value) {
		templateRequestHeaders[name] = value;
	}
	send() {
		this.status = 200;
		this.responseText = JSON.stringify({ content: "<p>Template</p>" });
		this.onload();
	}
};

require("../public/js/composer.js");
listeners.DOMContentLoaded();
assert.equal(
	deliveryModePanels.email.hidden,
	false,
	"Email mode is visible by default",
);
assert.equal(
	deliveryModePanels.internal_note.hidden,
	true,
	"Internal note mode is hidden by default",
);
const mountedEmailPanel = deliveryModePanels.email;
const mountedNotePanel = deliveryModePanels.internal_note;
deliveryModeInputs[0].checked = false;
deliveryModeInputs[1].checked = true;
deliveryModeInputs[1].changeHandler();
assert.equal(
	deliveryModePanels.email.hidden,
	true,
	"Email mode hides after switching",
);
assert.equal(
	deliveryModePanels.internal_note.hidden,
	false,
	"Internal note mode shows after switching",
);
assert.equal(
	deliveryModePanels.email,
	mountedEmailPanel,
	"Email draft panel stays mounted",
);
assert.equal(
	deliveryModePanels.internal_note,
	mountedNotePanel,
	"Internal note draft panel stays mounted",
);
assert.equal(
	secondDeliveryModeRoot.inputs[0].checked,
	true,
	"second compose keeps its Email selection",
);
assert.equal(
	secondDeliveryModeRoot.panels.email.hidden,
	false,
	"second compose keeps its Email panel visible",
);
assert.equal(
	secondDeliveryModeRoot.panels.internal_note.hidden,
	true,
	"second compose keeps its note panel hidden",
);
form.status.waiting.checked = true;
const secondForm = makeForm(false, true);
composeForms.push(secondForm);
ajaxComplete();
secondForm.status.waiting.checked = true;
secondForm.status.waiting.changeHandler();
assert.equal(
	secondForm.status.solved.checked,
	false,
	"AJAX-loaded form initializes exclusive status toggles",
);
assert.equal(
	form.status.waiting.checked,
	true,
	"status coordination stays form-local",
);
let removedStaleEditor = false;
let reinitializedEditor = false;
global.window.tinymce_editor_configs = {
	body_html: { selector: "#body_html" },
};
global.window.tinymce = global.tinymce = {
	get() {
		return {
			getElement() {
				return { isConnected: false };
			},
			remove() {
				removedStaleEditor = true;
			},
		};
	},
	init(config) {
		reinitializedEditor =
			config === global.window.tinymce_editor_configs.body_html;
	},
};
const staleEditorForm = makeForm();
staleEditorForm.dataset.editorId = "body_html";
composeForms.push(staleEditorForm);
ajaxComplete();
assert.equal(
	removedStaleEditor,
	true,
	"stale modal editor is removed on reopen",
);
assert.equal(
	reinitializedEditor,
	true,
	"modal editor is reinitialized on reopen",
);
global.window.tinymce = global.tinymce = {
	get() {
		return templateEditor;
	},
	init() {},
};
const composerSource = require("node:fs").readFileSync(
	require.resolve("../public/js/composer.js"),
	"utf8",
);
const composerCss = require("node:fs").readFileSync(
	require.resolve("../public/css/ticketmailer.css"),
	"utf8",
);
const composeTemplate = require("node:fs").readFileSync(
	require.resolve("../templates/compose.html.twig"),
	"utf8",
);
const timelineActionSource = require("node:fs").readFileSync(
	require.resolve("../inc/timelineaction.class.php"),
	"utf8",
);
assert.match(
	timelineActionSource,
	/\$editor_id = 'ticketmailer-body-html-'[\s\S]*\$tickets_id[\s\S]*\$inline \? 'inline' : 'modal'/,
	"inline and modal email editors have distinct TinyMCE IDs",
);
assert.match(
	composerSource,
	/var pendingIcon = pendingLabel[\s\S]*pendingLabel\.querySelector\(["']:scope > i["']\)/,
	"pending icon lookup cannot hijack unrelated native pending-control icons",
);
assert.doesNotMatch(
	composerSource,
	/var pendingIcon = pendingControl\.querySelector\(["']i["']\)/,
	"pending icon lookup is not an unrestricted descendant query",
);
assert.match(
	composerSource,
	/pendingLabel\.prepend\(pendingIcon\)[\s\S]*pendingIcon\.className = ["']ti ti-player-pause["']/,
	"pending icon visibly renders inside the native Waiting toggle label",
);
assert.match(
	composerSource,
	/pendingLabel\.classList\.add\([\s\S]*["']ticketmailer-status-toggle["']/,
	"first native toggle labels share the status layout class",
);
assert.match(
	composerSource,
	/solvedInput\.name = ["']_ticketmailer_set_solved["'];[\s\S]*solvedInput\.value = ["']1["']/,
	"Internal note solved toggle requests the deferred plugin status update",
);
assert.doesNotMatch(
	composerSource,
	/solvedInput\.name = ["']add_close["']/,
	"Internal note solved toggle does not use GLPI's close-existing-solution action",
);
assert.match(
	composerCss,
	/\.ticketmailer-status-toggle\s*\{[^}]*align-items:\s*center[^}]*gap:\s*0\.5rem[^}]*padding:\s*0 0\.5rem/s,
	"all status groups use centered equal geometry",
);
assert.match(
	composerCss,
	/\.ticketmailer-note-actions\s*\{[^}]*gap:\s*0\.5rem/s,
	"native footer uses equal gaps between adjacent controls",
);
assert.match(
	composerCss,
	/\.ticketmailer-note-cancel\s*\{[^}]*margin-right:\s*auto/s,
	"Internal note status toggles and Add stay right-aligned",
);
assert.doesNotMatch(
	composeTemplate,
	/name="save_knowledge"/,
	"Email actions omit the knowledge-base toggle",
);
assert.match(
	composeTemplate,
	/data-ticketmailer-email-knowledge/,
	"Email compose exposes the knowledge-base article selector",
);
assert.doesNotMatch(
	composerSource,
	/button\.append\(label\)|label\.textContent = button\.title/,
	"Knowledge-base actions remain icon-only in both compose modes",
);
assert.doesNotMatch(
	composerCss,
	/#modal_search_knowbaseitem \.list-group-item \.use-knowbaseitem,[\s\S]*min-width:\s*8rem/,
	"Knowledge-base icon actions retain native compact sizing",
);
assert.match(
	composerSource,
	/saveKnowledgeField\.remove\(\)/,
	"Internal note actions omit the knowledge-base toggle",
);
assert.match(
	composerCss,
	/\[id\^="pending-reasons-control-"\]\s*\{[^}]*padding:\s*0;/s,
	"pending wrapper adds no asymmetric outer spacing",
);
assert.doesNotMatch(
	composerSource,
	/footer\.insertBefore\(privateField, pendingControl \|\| add\)/,
	"Internal note does not expose the implicit Private toggle",
);
assert.match(
	composerSource,
	/solved\.dispatchEvent\(new Event\(["']change["'], \{ bubbles: true \}\)\)/,
	"solution templates dispatch a bubbling modern change event",
);
assert.doesNotMatch(
	composerSource,
	/\.initEvent\(/,
	"composer does not use deprecated Event.initEvent()",
);
assert.match(
	composerCss,
	/\.ticketmailer-note-knowledge\s*\{[^}]*justify-self:\s*end[^}]*width:\s*auto\s*!important/s,
	"Knowledge base field occupies the right metadata column",
);
assert.match(
	composerCss,
	/\.ticketmailer-note-knowledge[\s\S]*> \.field-container\s*\{[^}]*width:\s*auto[^}]*padding:\s*0/s,
	"Knowledge base button keeps its compact field container",
);
assert.match(
	composerSource,
	/delivery\.dataset\.answerTemplatesLabel[\s\S]*emptyTemplate\.textContent = templateText/,
	"native Internal note empty template uses the localized Answer templates label",
);
assert.match(composerSource, /form\.dataset\.validateUrl/);
assert.match(
	composerSource,
	/\[["']recipients_to["'], ["']recipients_cc["'], ["']recipients_bcc["']\]/,
);
assert.match(
	composerSource,
	/var matchesElement = warning\s*\? warning\.querySelector\(["']\.ticketmailer-mailbox-matches["']\)\s*:\s*null;/,
);
assert.match(
	composerSource,
	/if \(matchesElement\) \{\s*matchesElement\.textContent/,
);
assert.match(composerSource, /button\.disabled =\s*normalized\.length > 0/);
assert.match(composerSource, /ticketmailer-recipient-chip--mailbox/);
assert.match(composerSource, /ti-alert-triangle/);
assert.match(composerSource, /form\.ticketmailerRecipientValidation/);
assert.match(
	composerSource,
	/currentRequest !== validation\.requestId\) \{\s*resolve\(\);\s*return;/,
);
assert.match(
	composerSource,
	/input\.addEventListener\(["']input["'],[\s\S]*\+\+requestId/,
);
const autocompleteSource = require("node:fs").readFileSync(
	require.resolve("../ajax/autocomplete_users.php"),
	"utf8",
);
const validationSource = require("node:fs").readFileSync(
	require.resolve("../ajax/validate_recipients.php"),
	"utf8",
);
assert.equal(
	(autocompleteSource.match(/Session::getNewCSRFToken\(true\)/g) || []).length,
	2,
);
assert.match(
	validationSource,
	/'csrf'\s*=>\s*Session::getNewCSRFToken\(true\)/,
);

const {
	bindEmailKnowledge,
	bindKnowledgeModal,
	copyKnowledgeArticleContent,
	preserveModalWhileOpeningKnowledge,
	bindKnowledgeActionIcons,
	recipientForSuggestion,
	selectKnowledgeArticle,
	validUserSuggestions,
} = require("../public/js/composer.js");
assert.deepEqual(
	validUserSuggestions([
		{ label: "Ada Lovelace", email: "ada@example.test" },
		{ label: "", email: "empty-label@example.test" },
		{ label: "Bad Email", email: "not-an-email" },
		{ label: "Mixed Email", email: "ada@example.test, invalid" },
		{ label: "Malformed Response", email: 42 },
		null,
	]),
	[{ label: "Ada Lovelace", email: "ada@example.test" }],
);
assert.deepEqual(
	recipientForSuggestion(
		{ label: "Ronny Grünewald", email: "ronny@example.test" },
		false,
	),
	{ label: "Ronny Grünewald", email: "ronny@example.test" },
);

let formSubmissionPrevented = false;
form.submitHandler({
	preventDefault() {
		formSubmissionPrevented = true;
	},
});

const overlay = body.children[1];
assert.equal(page.inert, true);
assert.equal(page.attributes["aria-hidden"], "true");
assert.equal(body.attributes["aria-busy"], "true");
assert.equal(formSubmissionPrevented, false);
assert.match(overlay.style.cssText, /position:fixed/);

for (const type of ["pointerdown", "click", "keydown", "submit"]) {
	let prevented = false;
	let stopped = false;
	listeners[type]({
		preventDefault() {
			prevented = true;
		},
		stopImmediatePropagation() {
			stopped = true;
		},
	});
	assert.equal(prevented, true, `${type} must be blocked`);
	assert.equal(stopped, true, `${type} must stop propagation`);
}

const templateForm = {
	dataset: {
		followupTemplateUrl: "/ajax/itilfollowup.php",
		editorId: "body_html",
	},
	querySelector(selector) {
		return selector === 'input[name="tickets_id"]' ? { value: "42" } : null;
	},
};
const templateSelect = {
	form: templateForm,
	value: "7",
	name: "itilfollowuptemplates_id",
};
templateChangeHandler.call(templateSelect);
assert.equal(templateRequestHeaders["X-Requested-With"], "XMLHttpRequest");
assert.equal(templateEditor.content, "<p>Template</p><p>Signature</p>");
templateChangeHandler.call(templateSelect);
assert.equal(templateEditor.content, "<p>Template</p><p>Signature</p>");

global.window.tinymce = undefined;
const fallbackTextarea = { value: "<p>Fallback signature</p>" };
const fallbackForm = {
	dataset: {
		followupTemplateUrl: "/ajax/itilfollowup.php",
		editorId: "body_html",
	},
	querySelector(selector) {
		if (selector === 'input[name="tickets_id"]') {
			return { value: "42" };
		}
		return selector === "#body_html" ? fallbackTextarea : null;
	},
};
templateChangeHandler.call({
	form: fallbackForm,
	value: "7",
	name: "itilfollowuptemplates_id",
});
assert.equal(
	fallbackTextarea.value,
	"<p>Template</p><p>Fallback signature</p>",
);

global.window.tinymce = global.tinymce;
const solutionForm = makeForm(true, false);
solutionForm.dataset.solutionTemplateUrl = "/ajax/solution.php";
solutionForm.dataset.editorId = "body_html";
solutionForm.querySelector = function (selector) {
	if (selector === 'input[name="tickets_id"]') {
		return { value: "42" };
	}
	if (selector === 'input[name="set_waiting"]') {
		return this.status.waiting;
	}
	if (selector === 'input[name="set_solved"]') {
		return this.status.solved;
	}
	return null;
};
solutionForm.status.waiting.addEventListener("change", function () {
	if (this.checked) {
		solutionForm.status.solved.checked = false;
	}
});
solutionForm.status.solved.addEventListener("change", function () {
	if (this.checked) {
		solutionForm.status.waiting.checked = false;
	}
});
templateChangeHandler.call({
	form: solutionForm,
	value: "9",
	name: "solutiontemplates_id",
});
assert.equal(
	solutionForm.status.solved.checked,
	true,
	"solution template checks solved",
);
assert.equal(
	solutionForm.status.waiting.checked,
	false,
	"solution template unchecks waiting through existing status logic",
);

function editorPanel(contentField) {
	return {
		querySelector(selector) {
			return selector.includes('textarea[name="content"]') ||
				selector.includes('textarea[name="body_html"]')
				? contentField
				: null;
		},
	};
}

function notePanel(contentField) {
	return editorPanel(contentField);
}

const knowledgeFields = {
	"knowledge-a": { id: "knowledge-a", value: "" },
	"knowledge-b": { id: "knowledge-b", value: "" },
};
const knowledgeEditors = {};
Object.values(knowledgeFields).forEach((field) => {
	knowledgeEditors[field.id] = {
		content: "",
		save() {},
		setContent(content) {
			this.content = content;
		},
	};
	field.dispatchEvent = () => {};
});
global.window.tinymce = {
	get(id) {
		return knowledgeEditors[id] || null;
	},
};
let hiddenKnowledgeModal = null;
global.bootstrap = {
	Modal: {
		getOrCreateInstance(modal) {
			return {
				hide() {
					hiddenKnowledgeModal = modal;
				},
			};
		},
	},
};
global.CFG_GLPI = { root_doc: "/glpi" };
let openedKnowledgeDialog = null;
let openedKnowledgeDialogCount = 0;
global.window.glpi_ajax_dialog = (options) => {
	openedKnowledgeDialog = options;
	openedKnowledgeDialogCount += 1;
};
const emailKnowledgeButton = {
	dataset: {},
	textContent: " Knowledge base ",
	addEventListener(type, handler) {
		this[type] = handler;
	},
};
const emailKnowledgeDelivery = {
	dataset: {
		knowledgeCopyFailed: "Copy failed",
		knowledgeCopySucceeded: "Article copied",
	},
};
const emailKnowledgeForm = {
	closest(selector) {
		return selector === "[data-ticketmailer-delivery]"
			? emailKnowledgeDelivery
			: null;
	},
	querySelector(selector) {
		if (selector === "[data-ticketmailer-email-knowledge]") {
			return emailKnowledgeButton;
		}
		if (selector === '[name="tickets_id"]') {
			return { value: "42" };
		}
		return null;
	},
};
const knowledgeUseIcon = {
	classList: {
		classes: new Set(["ti", "ti-check"]),
		replace(from, to) {
			if (this.classes.delete(from)) {
				this.classes.add(to);
			}
		},
	},
};
const openedKnowledgeModal = {
	dataset: {},
	handlers: {},
	addEventListener(type, handler) {
		(this.handlers[type] ||= []).push(handler);
	},
	querySelectorAll(selector) {
		return selector === ".use-knowbaseitem .ti-check"
			? [knowledgeUseIcon]
			: [];
	},
};
let knowledgeDialogAvailable = false;
global.document.getElementById = () =>
	knowledgeDialogAvailable ? openedKnowledgeModal : null;
let bindKnowledgeDialog = null;
global.window.setTimeout = (handler) => {
	bindKnowledgeDialog = handler;
};
bindEmailKnowledge(emailKnowledgeForm);
emailKnowledgeButton.click();
emailKnowledgeButton.click();
assert.equal(
	openedKnowledgeDialogCount,
	1,
	"Email knowledge dialog ignores repeated opens until binding completes",
);
assert.equal(
	openedKnowledgeDialog.url,
	"/glpi/Knowbase/KnowbaseItem/Search/Ticket/42",
);
knowledgeDialogAvailable = true;
bindKnowledgeDialog();
assert.equal(
	knowledgeUseIcon.classList.classes.has("ti-copy"),
	true,
	"Knowledge actions use a copy icon",
);
assert.equal(knowledgeUseIcon.classList.classes.has("ti-check"), false);
knowledgeUseIcon.classList.classes.delete("ti-copy");
knowledgeUseIcon.classList.classes.add("ti-check");
const iconListenerCount = openedKnowledgeModal.handlers.click.length;
bindKnowledgeActionIcons(openedKnowledgeModal);
bindKnowledgeActionIcons(openedKnowledgeModal);
assert.equal(
	openedKnowledgeModal.handlers.click.length,
	iconListenerCount,
	"Repeated copy-icon binding adds no listener",
);
assert.equal(
	knowledgeUseIcon.classList.classes.has("ti-copy"),
	true,
	"Internal-note and email bindings share copy icon setup",
);
let copiedKnowledgeItems = null;
global.ClipboardItem = class ClipboardItem {
	constructor(items) {
		this.items = items;
	}
};
Object.defineProperty(global, "navigator", {
	configurable: true,
	value: {
		clipboard: {
			write(items) {
				copiedKnowledgeItems = items;
				return Promise.resolve();
			},
		},
	},
});
knowledgeEditors["knowledge-a"].content = "<p>Existing note</p>";
knowledgeFields["knowledge-a"].value = "<p>Existing note</p>";
const directKnowledgeCopy = copyKnowledgeArticleContent(
	"<p>Knowledge article</p><p>Second paragraph</p>",
);
assert.equal(
	knowledgeEditors["knowledge-a"].content,
	"<p>Existing note</p>",
	"Copying a knowledge article does not replace the editor content",
);
assert.equal(knowledgeFields["knowledge-a"].value, "<p>Existing note</p>");
const dialogParent = {
	children: [],
	insertBefore(child) {
		child.parentNode = this;
		this.children.push(child);
	},
};
const preservedParentModal = {
	parentNode: dialogParent,
	nextSibling: null,
	remove() {
		this.parentNode = null;
	},
};
dialogParent.children.push(preservedParentModal);
let closeAllCalls = 0;
global.window.glpi_close_all_dialogs = () => {
	closeAllCalls += 1;
};
const restoreDialogClosing = preserveModalWhileOpeningKnowledge(
preservedParentModal,
);
global.window.glpi_close_all_dialogs();
assert.equal(
	closeAllCalls,
	0,
	"Opening knowledge never detaches the parent modal and its TinyMCE iframe",
);
assert.equal(preservedParentModal.parentNode, dialogParent);
restoreDialogClosing();
assert.notEqual(
	global.window.glpi_close_all_dialogs.name,
	"guardedCloseAllDialogs",
);

let knowledgeAlert = null;
global.window.alert = (message) => {
	knowledgeAlert = message;
};
const knowledgeModal = {
	dataset: {},
	handlers: {},
	addEventListener(type, handler) {
		(this.handlers[type] ||= []).push(handler);
	},
	querySelectorAll() {
		return [];
	},
};
const parentA = {};
const parentB = {};
let knowledgeToast = null;
global.glpi_toast_success = (message, caption, options) => {
	knowledgeToast = { message, caption, options };
};
bindKnowledgeModal(
	knowledgeModal,
	parentA,
	"Copy failed",
	"Text copied",
);
bindKnowledgeModal(
	knowledgeModal,
	parentB,
	"Copy failed",
	"Text copied",
);
assert.equal(knowledgeModal.handlers.click.length, 2);
const selectKnowledgeArticleHandler = knowledgeModal.handlers.click[1];
assert.equal(knowledgeModal.handlers["hidden.bs.modal"].length, 1);
assert.equal(knowledgeModal.ticketmailerParentModal, parentB);

let knowledgeRequest = null;
global.window.fetch = (url, options) => {
	knowledgeRequest = { url, options };
	return Promise.resolve({
		ok: true,
		text: () =>
			Promise.resolve("<p>Active article</p><p>Second paragraph</p>"),
	});
};
selectKnowledgeArticleHandler({
	target: {
		closest(selector) {
			return selector === ".use-knowbaseitem"
				? {
						dataset: { knowbaseitemId: "7" },
						closest() {
							return null;
						},
					}
				: null;
		},
	},
	preventDefault() {},
	stopImmediatePropagation() {},
});

setImmediate(() => {
	Promise.resolve()
		.then(async () => {
			await directKnowledgeCopy;
			assert.deepEqual(Object.keys(copiedKnowledgeItems[0].items).sort(), [
				"text/html",
				"text/plain",
			]);
			assert.equal(
				await copiedKnowledgeItems[0].items["text/html"].text(),
				"<p>Active article</p><p>Second paragraph</p>",
			);
			assert.equal(
				await copiedKnowledgeItems[0].items["text/plain"].text(),
				"Active article\nSecond paragraph",
			);
			assert.equal(
				knowledgeEditors["knowledge-b"].content,
				"",
				"Selecting a knowledge article leaves the editor unchanged",
			);
			assert.equal(
				knowledgeRequest.url,
				"/glpi/Knowbase/KnowbaseItem/7/Content",
			);
			assert.equal(
				knowledgeRequest.options.headers["X-Requested-With"],
				"XMLHttpRequest",
			);
			assert.equal(hiddenKnowledgeModal, knowledgeModal);
			assert.deepEqual(knowledgeToast, {
				message: "Text copied",
				caption: undefined,
				options: { delay: 3000 },
			});
			assert.equal(hiddenKnowledgeModal, knowledgeModal);
			assert.equal(
				knowledgeModal.ticketmailerParentModal,
				parentB,
				"Successful copy retains the editor modal",
			);
			knowledgeToast = null;
			bindKnowledgeModal(knowledgeModal, parentB, "Copy failed", undefined);
			selectKnowledgeArticleHandler({
				target: {
					closest(selector) {
						return selector === ".use-knowbaseitem"
							? {
								dataset: { knowbaseitemId: "9" },
								closest() {
									return null;
								},
							  }
							: null;
					},
				},
				preventDefault() {},
				stopImmediatePropagation() {},
			});
			await new Promise(setImmediate);
			assert.deepEqual(
				knowledgeToast,
				{
					message: "Text copied",
					caption: undefined,
					options: { delay: 3000 },
				},
				"A later binding without success text retains the localized toast",
			);
			assert.equal(
				knowledgeModal.ticketmailerCopySucceededMessage,
				"Text copied",
				"Missing markup still has a safe success-text fallback",
			);
			knowledgeToast = null;
			delete global.glpi_toast_success;
			selectKnowledgeArticleHandler({
				target: {
					closest(selector) {
						return selector === ".use-knowbaseitem"
							? {
								dataset: { knowbaseitemId: "8" },
								closest() {
									return null;
								},
							  }
							: null;
					},
				},
				preventDefault() {},
				stopImmediatePropagation() {},
			});
			await new Promise(setImmediate);
			assert.equal(
				knowledgeAlert,
				null,
				"Missing toast API does not fall back to an information modal",
			);
			global.glpi_toast_success = (message, caption, options) => {
				knowledgeToast = { message, caption, options };
			};
			const responses = [];
			global.window.fetch = () =>
				new Promise((resolve) => {
					responses.push(resolve);
				});
			const first = selectKnowledgeArticle(knowledgeModal, "1");
			const second = selectKnowledgeArticle(knowledgeModal, "2");
			responses[1]({
				ok: true,
				text: () => Promise.resolve("<p>Newest article</p>"),
			});
			await second;
			responses[0]({
				ok: true,
				text: () => Promise.resolve("<p>Stale article</p>"),
			});
			assert.equal(await first, false);
			assert.equal(
				await copiedKnowledgeItems[0].items["text/html"].text(),
				"<p>Newest article</p>",
			);
			assert.equal(
				knowledgeToast,
				null,
				"Direct selections do not show UI feedback outside the click handler",
			);
			assert.equal(knowledgeEditors["knowledge-b"].content, "");
			let finishCopy;
			navigator.clipboard.write = () =>
				new Promise((resolve) => {
					finishCopy = resolve;
				});
			global.window.fetch = () =>
				Promise.resolve({
					ok: true,
					text: () => Promise.resolve("<p>Slow copy</p>"),
				});
			const slowCopy = selectKnowledgeArticle(knowledgeModal, "3");
			await new Promise(setImmediate);
			knowledgeModal.ticketmailerKnowledgeRequestId += 1;
			finishCopy();
			assert.equal(await slowCopy, false);
			assert.equal(hiddenKnowledgeModal, knowledgeModal);
			assert.equal(knowledgeToast, null, "Stale copies do not show a toast");
			navigator.clipboard.write = () => Promise.reject(new Error("Denied"));
			selectKnowledgeArticleHandler({
				target: {
					closest(selector) {
						return selector === ".use-knowbaseitem"
							? {
									dataset: { knowbaseitemId: "4" },
									closest() {
										return null;
									},
								}
							: null;
					},
				},
				preventDefault() {},
				stopImmediatePropagation() {},
			});
			await new Promise(setImmediate);
			assert.equal(knowledgeAlert, "Copy failed");
			assert.equal(knowledgeToast, null, "Failed copies do not show a toast");
		})
		.catch((error) => {
			process.nextTick(() => {
				throw error;
			});
		});
});
