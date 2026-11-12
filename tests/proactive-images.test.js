const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");
const source = fs.readFileSync(__dirname + "/../public/js/composer.js", "utf8");
const start = source.indexOf("    function initProactiveImages(form)");
const end = source.indexOf("function initForm(form)", start);
let handler;
let submit;
let releaseFirst;
let releaseSecond;
let requests = 0;
const button = { disabled: false };
const editor = {
    options: { set(name, value) { handler = value; } },
    getBody() { return { querySelector() { return null; } }; },
};
const form = {
    dataset: { proactive: "true", editorId: "body" },
    querySelectorAll() { return [button]; },
    querySelector() { return { value: "context" }; },
    addEventListener(name, callback) { if (name === "submit") submit = callback; },
    appendChild() {},
};
const sandbox = {
    window: { tinymce: { get() { return editor; } } },
    document: { createElement() { return { setAttribute() {} }; } },
    FormData: class { append() {} },
    getAjaxCsrf() { return "csrf"; },
    setAjaxCsrf() {},
    queueAjax(form, send) {
        const next = (form.pending || Promise.resolve()).then(() => new Promise(send));
        form.pending = next;
        return next;
    },
    fetch() {
        requests++;
        return new Promise(resolve => {
            const release = () => resolve({ ok: true, json: async () => ({ cid: "image", id: "id", stored: "file", mime: "image/png" }) });
            if (requests === 1) releaseFirst = release;
            else releaseSecond = release;
        });
    },
};
vm.runInNewContext(source.slice(start, end) + ";initProactiveImages(form);", { ...sandbox, form });
const tick = () => new Promise(resolve => setImmediate(resolve));
(async () => {
    const blob = { blob() { return {}; }, filename() { return "image.png"; } };
    const first = handler(blob);
    const second = handler(blob);
    await tick();
    assert.equal(button.disabled, true);
    releaseFirst();
    await first;
    await tick();
    assert.equal(button.disabled, true, "first upload must not release the second upload barrier");
    let prevented = false;
    let stopped = false;
    submit({ preventDefault() { prevented = true; }, stopImmediatePropagation() { stopped = true; } });
    assert.equal(prevented && stopped, true, "programmatic submit must also be blocked");
    releaseSecond();
    await second;
    await tick();
    assert.equal(button.disabled, false);
    prevented = false;
    submit({ preventDefault() { prevented = true; }, stopImmediatePropagation() {} });
    assert.equal(prevented, false);
    console.log("PASS concurrent proactive image upload barrier");
})().catch(error => { console.error(error); process.exitCode = 1; });
