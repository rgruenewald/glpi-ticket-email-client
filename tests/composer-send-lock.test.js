'use strict';

const assert = require('node:assert/strict');

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
    insertAdjacentHTML() {},
};

const form = {
    dataset: {},
    submitHandler: null,
    addEventListener(type, handler) {
        if (type === 'submit') {
            this.submitHandler = handler;
        }
    },
    querySelector() {
        return null;
    },
    querySelectorAll(selector) {
        if (selector === 'button[type="submit"]') {
            return [sendButton];
        }
        return [];
    },
};

global.window = {};
global.document = {
    body,
    addEventListener(type, handler) {
        listeners[type] = handler;
    },
    createElement() {
        return {
            attributes: {},
            style: {},
            setAttribute(name, value) {
                this.attributes[name] = value;
            },
            insertAdjacentHTML() {},
        };
    },
    querySelectorAll(selector) {
        return selector === '.ticketmailer-compose' ? [form] : [];
    },
};
let templateChangeHandler;
global.$ = function () {
    return {
        ajaxComplete() {},
        on(type, selector, handler) {
            if (type === 'change') {
                templateChangeHandler = handler;
            }
        },
    };
};
const templateEditor = {
    content: '<p>Signature</p>',
    getContent() {
        return this.content;
    },
    setContent(content) {
        this.content = content;
    },
};
global.tinymce = {
    get() {
        return templateEditor;
    },
};
global.window.tinymce = global.tinymce;
let templateRequestHeaders = {};
global.XMLHttpRequest = class {
    open() {}
    setRequestHeader(name, value) {
        templateRequestHeaders[name] = value;
    }
    send() {
        this.status = 200;
        this.responseText = JSON.stringify({ content: '<p>Template</p>' });
        this.onload();
    }
};

require('../public/js/composer.js');
listeners.DOMContentLoaded();

const {recipientForSuggestion, validUserSuggestions} = require('../public/js/composer.js');
assert.deepEqual(validUserSuggestions([
    {label: 'Ada Lovelace', email: 'ada@example.test'},
    {label: '', email: 'empty-label@example.test'},
    {label: 'Bad Email', email: 'not-an-email'},
    {label: 'Malformed Response', email: 42},
    null,
]), [{label: 'Ada Lovelace', email: 'ada@example.test'}]);
assert.deepEqual(
    recipientForSuggestion({label: 'Ronny Grünewald', email: 'ronny@example.test'}, false),
    {label: 'Ronny Grünewald', email: 'ronny@example.test'},
);

let formSubmissionPrevented = false;
form.submitHandler({
    preventDefault() {
        formSubmissionPrevented = true;
    },
});

const overlay = body.children[1];
assert.equal(page.inert, true);
assert.equal(page.attributes['aria-hidden'], 'true');
assert.equal(body.attributes['aria-busy'], 'true');
assert.equal(formSubmissionPrevented, false);
assert.match(overlay.style.cssText, /position:fixed/);

for (const type of ['pointerdown', 'click', 'keydown', 'submit']) {
    let prevented = false;
    let stopped = false;
    listeners[type]({
        preventDefault() { prevented = true; },
        stopImmediatePropagation() { stopped = true; },
    });
    assert.equal(prevented, true, `${type} must be blocked`);
    assert.equal(stopped, true, `${type} must stop propagation`);
}

const templateForm = {
    dataset: {
        followupTemplateUrl: '/ajax/itilfollowup.php',
        editorId: 'body_html',
    },
    querySelector(selector) {
        return selector === 'input[name="tickets_id"]' ? { value: '42' } : null;
    },
};
const templateSelect = { form: templateForm, value: '7' };
templateChangeHandler.call(templateSelect);
assert.equal(templateRequestHeaders['X-Requested-With'], 'XMLHttpRequest');
assert.equal(templateEditor.content, '<p>Template</p><p>Signature</p>');
templateChangeHandler.call(templateSelect);
assert.equal(templateEditor.content, '<p>Template</p><p>Signature</p>');

global.window.tinymce = undefined;
const fallbackTextarea = { value: '<p>Fallback signature</p>' };
const fallbackForm = {
    dataset: {
        followupTemplateUrl: '/ajax/itilfollowup.php',
        editorId: 'body_html',
    },
    querySelector(selector) {
        if (selector === 'input[name="tickets_id"]') {
            return { value: '42' };
        }
        return selector === '#body_html' ? fallbackTextarea : null;
    },
};
templateChangeHandler.call({ form: fallbackForm, value: '7' });
assert.equal(fallbackTextarea.value, '<p>Template</p><p>Fallback signature</p>');
