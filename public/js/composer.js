/*
 * public/js/composer.js — attachment picker for compose form (v2).
 * TinyMCE comes from GLPI's Html::initEditorSystem.
 *
 * AJAX uploads use a standalone CSRF token (data-ajax-csrf).
 */
(function () {
    'use strict';

    function getAjaxCsrf(form) {
        return form.dataset.ajaxCsrf || '';
    }

    function setAjaxCsrf(form, token) {
        if (token) {
            form.dataset.ajaxCsrf = token;
        }
    }

    function queueAjax(form, send) {
        var pending = form.ticketmailerAjaxPending || Promise.resolve();
        var next = pending.catch(function () {}).then(function () {
            return new Promise(send);
        });
        form.ticketmailerAjaxPending = next;
        return next;
    }

    function splitRecipientTokens(raw) {
        var valid = [];
        var invalid = [];
        raw.split(/[,;\r\n]+/).forEach(function (token) {
            token = token.trim();
            if (!token) {
                return;
            }
            if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(token)) {
                valid.push(token);
            } else {
                invalid.push(token);
            }
        });
        return {valid: valid, invalid: invalid};
    }

    function validUserSuggestions(results) {
        if (!Array.isArray(results)) {
            return [];
        }
        return results.filter(function (result) {
            return result
                && typeof result.label === 'string'
                && result.label.trim() !== ''
                && typeof result.email === 'string'
                && splitRecipientTokens(result.email).valid.length === 1
                && splitRecipientTokens(result.email).invalid.length === 0;
        });
    }

    function recipientForSuggestion(suggestion, showEmail) {
        return {
            email: suggestion.email,
            label: showEmail ? suggestion.email : suggestion.label,
        };
    }

    if (typeof module !== 'undefined') {
        module.exports = {
            splitRecipientTokens: splitRecipientTokens,
            validUserSuggestions: validUserSuggestions,
            recipientForSuggestion: recipientForSuggestion,
        };
    }

    function initRecipientControl(control) {
        if (control.dataset.ticketmailerInitialized) {
            return;
        }
        control.dataset.ticketmailerInitialized = 'true';
        var chips = control.querySelector('.ticketmailer-recipient-chips');
        var input = control.querySelector('.ticketmailer-recipient-input');
        var value = control.querySelector('input[type="hidden"]');
        var clear = control.querySelector('.ticketmailer-recipient-clear');
        var form = control.closest('form');
        if (!chips || !input || !value || !form) {
            return;
        }
        var recipients = [];
        var removeRecipientLabel = form.dataset.i18nRemoveRecipient || 'Remove %s';
        var suggestions = [];
        var activeSuggestion = -1;
        var requestId = 0;
        var requestTimer = null;
        form.ticketmailerRecipientValidation = form.ticketmailerRecipientValidation || {
            timer: null,
            requestId: 0,
            lastMailboxMatches: '',
        };
        var validation = form.ticketmailerRecipientValidation;
        var suggestionList = document.createElement('ul');
        suggestionList.className = 'ticketmailer-recipient-suggestions';
        suggestionList.setAttribute('role', 'listbox');
        suggestionList.hidden = true;
        control.appendChild(suggestionList);

        function isEmail(candidate) {
            return splitRecipientTokens(candidate).valid.length === 1;
        }

        function add(email, label) {
            var key = email.toLowerCase();
            if (recipients.some(function (recipient) {
                return recipient.email.toLowerCase() === key;
            })) {
                return;
            }
            recipients.push({
                email: email,
                label: label || email,
            });
        }

        function validateRecipients() {
            var url = form.dataset.validateUrl || '';
            if (!url) {
                return;
            }
            window.clearTimeout(validation.timer);
            var currentRequest = ++validation.requestId;
            validation.timer = window.setTimeout(function () {
                queueAjax(form, function (resolve) {
                var data = new FormData();
                var token = getAjaxCsrf(form);
                data.append('tickets_id', form.querySelector('input[name="tickets_id"]').value);
                ['recipients_to', 'recipients_cc', 'recipients_bcc'].forEach(function (name) {
                    var field = form.querySelector('input[name="' + name + '"]');
                    data.append(name, field ? field.value : '');
                });
                data.append('_glpi_csrf_token', token);
                var xhr = new XMLHttpRequest();
                xhr.open('POST', url);
                if (token) {
                    xhr.setRequestHeader('X-Glpi-Csrf-Token', token);
                }
                xhr.onload = function () {
                    if (currentRequest !== validation.requestId) {
                        return;
                    }
                    try {
                        var response = JSON.parse(xhr.responseText);
                        setAjaxCsrf(form, response.csrf || '');
                        if (xhr.status < 200 || xhr.status >= 300) {
                            return;
                        }
                        var warning = form.querySelector('.ticketmailer-mailbox');
                        var matches = Array.isArray(response.mailbox_matches) ? response.mailbox_matches.join(', ') : '';
                        if (warning) {
                            warning.hidden = !matches;
                            warning.querySelector('.ticketmailer-mailbox-matches').textContent = matches;
                            if (matches !== validation.lastMailboxMatches) {
                                warning.querySelector('input[name="mailbox_override"]').checked = false;
                            }
                        }
                        validation.lastMailboxMatches = matches;
                    } catch (err) {}
                    resolve();
                };
                xhr.onerror = resolve;
                xhr.send(data);
                });
            }, 200);
        }

        function render() {
            value.value = recipients.map(function (recipient) { return recipient.email; }).join(', ');
            validateRecipients();
            chips.replaceChildren();
            recipients.forEach(function (recipient) {
                var chip = document.createElement('span');
                chip.className = 'ticketmailer-recipient-chip';
                chip.innerHTML = '<i class="ti ti-mail" aria-hidden="true"></i>';
                chip.append(document.createTextNode(recipient.label));
                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'ticketmailer-recipient-remove';
                remove.setAttribute('aria-label', removeRecipientLabel.replace('%s', recipient.label));
                remove.innerHTML = '<i class="ti ti-x" aria-hidden="true"></i>';
                remove.addEventListener('click', function () {
                    recipients = recipients.filter(function (item) {
                        return item.email !== recipient.email;
                    });
                    render();
                    input.focus();
                });
                chip.appendChild(remove);
                chips.appendChild(chip);
            });
        }

        function hideSuggestions() {
            suggestions = [];
            activeSuggestion = -1;
            suggestionList.replaceChildren();
            suggestionList.hidden = true;
            input.setAttribute('aria-expanded', 'false');
        }

        function selectSuggestion(suggestion) {
            var recipient = recipientForSuggestion(
                suggestion,
                form.dataset.userAutocompleteShowEmail === '1',
            );
            add(recipient.email, recipient.label);
            input.value = '';
            hideSuggestions();
            render();
            input.focus();
        }

        function showSuggestions(nextSuggestions) {
            suggestions = nextSuggestions;
            activeSuggestion = -1;
            suggestionList.replaceChildren();
            suggestions.forEach(function (suggestion, index) {
                var item = document.createElement('li');
                var button = document.createElement('button');
                var label = document.createElement('span');
                var email = document.createElement('span');
                button.type = 'button';
                button.className = 'ticketmailer-recipient-suggestion';
                button.setAttribute('role', 'option');
                button.setAttribute('aria-selected', 'false');
                label.textContent = suggestion.label;
                email.className = 'ticketmailer-recipient-suggestion-email';
                email.textContent = suggestion.email;
                button.appendChild(label);
                if (form.dataset.userAutocompleteShowEmail === '1') {
                    button.appendChild(email);
                }
                button.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                });
                button.addEventListener('click', function () {
                    selectSuggestion(suggestions[index]);
                });
                item.appendChild(button);
                suggestionList.appendChild(item);
            });
            suggestionList.hidden = suggestions.length === 0;
            input.setAttribute('aria-expanded', suggestions.length > 0 ? 'true' : 'false');
        }

        function setActiveSuggestion(index) {
            activeSuggestion = index;
            Array.prototype.forEach.call(suggestionList.querySelectorAll('button'), function (button, buttonIndex) {
                var active = buttonIndex === activeSuggestion;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
            });
        }

        function requestSuggestions() {
            var url = form.dataset.userAutocompleteUrl || '';
            var query = input.value.trim();
            if (!url || query.length < 2) {
                ++requestId;
                hideSuggestions();
                return;
            }
            var currentRequest = ++requestId;
            queueAjax(form, function (resolve) {
            var data = new FormData();
            var token = getAjaxCsrf(form);
            var ticket = form.querySelector('input[name="tickets_id"]');
            data.append('query', query);
            data.append('tickets_id', ticket ? ticket.value : '');
            data.append('_glpi_csrf_token', token);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', url);
            if (token) {
                xhr.setRequestHeader('X-Glpi-Csrf-Token', token);
            }
            xhr.onload = function () {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.csrf) {
                        setAjaxCsrf(form, response.csrf);
                    }
                    if (currentRequest !== requestId) {
                        resolve();
                        return;
                    }
                    if (xhr.status >= 200 && xhr.status < 300 && Array.isArray(response.results)) {
                        showSuggestions(validUserSuggestions(response.results));
                    } else {
                        hideSuggestions();
                    }
                } catch (err) {
                    hideSuggestions();
                }
                resolve();
            };
            xhr.onerror = resolve;
            xhr.send(data);
            });
        }

        function commit() {
            var tokens = splitRecipientTokens(input.value);
            tokens.valid.forEach(add);
            input.value = tokens.invalid.join(', ');
            hideSuggestions();
            render();
        }

        splitRecipientTokens(value.value).valid.forEach(add);
        var initialInvalid = splitRecipientTokens(value.value).invalid;
        input.value = initialInvalid.join(', ');
        render();
        if (clear) {
            clear.addEventListener('click', function (event) {
                event.stopPropagation();
                recipients = [];
                input.value = '';
                value.value = '';
                hideSuggestions();
                render();
                input.focus();
            });
        }
        control.addEventListener('click', function () {
            input.focus();
        });
        input.addEventListener('input', function () {
            ++requestId;
            if (requestTimer) {
                window.clearTimeout(requestTimer);
            }
            requestTimer = window.setTimeout(requestSuggestions, 150);
        });
        input.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown' && suggestions.length) {
                event.preventDefault();
                setActiveSuggestion((activeSuggestion + 1) % suggestions.length);
            } else if (event.key === 'ArrowUp' && suggestions.length) {
                event.preventDefault();
                setActiveSuggestion((activeSuggestion + suggestions.length - 1) % suggestions.length);
            } else if (event.key === 'Escape') {
                hideSuggestions();
            } else if (event.key === 'Enter' && activeSuggestion >= 0) {
                event.preventDefault();
                selectSuggestion(suggestions[activeSuggestion]);
            } else if (event.key === 'Enter' || event.key === ',' || event.key === ';') {
                event.preventDefault();
                commit();
            }
        });
        input.addEventListener('blur', function () {
            window.setTimeout(commit, 150);
        });
        input.addEventListener('paste', function () {
            window.setTimeout(commit, 0);
        });
        form.addEventListener('submit', function () {
            commit();
            value.value = recipients.map(function (recipient) {
                return recipient.email;
            }).concat(input.value.trim() ? [input.value.trim()] : []).join(', ');
        });
    }

    function initAttachments(form) {
        var input = form.querySelector('.ticketmailer-file');
        var list = form.querySelector('.ticketmailer-attachments');
        var drop = form.querySelector('[data-attachment-drop]');
        var choose = form.querySelector('.ticketmailer-choose-files');
        if (!input || !list) {
            return;
        }
        var uploadUrl = form.dataset.uploadUrl || '';
        if (!uploadUrl) {
            return;
        }

        var queue = [];
        var busy = false;
        var bytesLabel = form.dataset.i18nBytes || 'bytes';
        var uploadFailedLabel = form.dataset.i18nUploadFailed || 'Upload failed.';

        function showError(message) {
            var li = document.createElement('li');
            li.className = 'ticketmailer-upload-error';
            li.textContent = message;
            list.appendChild(li);
        }

        function enqueue(files) {
            for (var i = 0; i < files.length; i++) {
                queue.push(files[i]);
            }
            pump();
        }

        function pump() {
            if (busy || queue.length === 0) {
                return;
            }
            busy = true;
            var file = queue.shift();
            var ticketId = form.querySelector('input[name="tickets_id"]').value;
            var fd = new FormData();
            var token = getAjaxCsrf(form);
            fd.append('tickets_id', ticketId);
            fd.append('_glpi_csrf_token', token);
            fd.append('file', file);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', uploadUrl);
            if (token) {
                xhr.setRequestHeader('X-Glpi-Csrf-Token', token);
            }
            xhr.onload = function () {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.csrf) {
                        setAjaxCsrf(form, data.csrf);
                    }
                    if (xhr.status >= 200 && xhr.status < 300 && (data.path || data.stored)) {
                        var idx = list.querySelectorAll('input[name^="attachments"]').length / 5;
                        var li = document.createElement('li');
                        li.textContent = data.filename + ' (' + data.size + ' ' + bytesLabel + ')';
                        function hidden(name, hiddenValue) {
                            var el = document.createElement('input');
                            el.type = 'hidden';
                            el.name = 'attachments[' + idx + '][' + name + ']';
                            el.value = hiddenValue;
                            li.appendChild(el);
                        }
                        hidden('id', data.id || '');
                        hidden('stored', data.stored || data.path || '');
                        hidden('path', data.stored || data.path || '');
                        hidden('filename', data.filename || '');
                        hidden('mime', data.mime || '');
                        list.appendChild(li);
                    } else {
                        showError(data.error || uploadFailedLabel);
                    }
                } catch (err) {
                    showError(uploadFailedLabel);
                }
                busy = false;
                pump();
            };
            xhr.onerror = function () {
                showError(uploadFailedLabel);
                busy = false;
                pump();
            };
            xhr.send(fd);
        }

        input.addEventListener('change', function () {
            enqueue(input.files);
            input.value = '';
        });
        if (choose) {
            choose.addEventListener('click', function () {
                input.click();
            });
        }
        if (drop) {
            ['dragenter', 'dragover'].forEach(function (type) {
                drop.addEventListener(type, function (event) {
                    event.preventDefault();
                    drop.classList.add('is-dragover');
                });
            });
            ['dragleave', 'drop'].forEach(function (type) {
                drop.addEventListener(type, function (event) {
                    event.preventDefault();
                    drop.classList.remove('is-dragover');
                });
            });
            drop.addEventListener('drop', function (event) {
                enqueue(event.dataTransfer.files);
            });
        }
    }

    function lockPageWhileSending(overlay) {
        Array.prototype.forEach.call(document.body.children, function (child) {
            if (child !== overlay) {
                child.inert = true;
                child.setAttribute('aria-hidden', 'true');
            }
        });
        document.body.setAttribute('aria-busy', 'true');
        ['pointerdown', 'click', 'keydown', 'submit'].forEach(function (type) {
            document.addEventListener(type, function (event) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }, true);
        });
    }

    function showSendingOverlay() {
        var overlay = document.createElement('div');
        overlay.id = 'ticketmailer-sending-overlay';
        overlay.setAttribute('role', 'status');
        overlay.setAttribute('aria-live', 'polite');
        overlay.style.cssText =
            'position:fixed;inset:0;z-index:2147483600;' +
            'display:flex;align-items:center;justify-content:center;' +
            'background:rgba(0,0,0,0.35);cursor:progress;' +
            'pointer-events:auto;';
        overlay.insertAdjacentHTML(
            'beforeend',
            '<span class="spinner-border" style="width:3rem;height:3rem;border-width:0.35em" aria-hidden="true"></span>',
        );
        document.body.appendChild(overlay);
        lockPageWhileSending(overlay);
    }


    function initTinyMceSave(form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.ticketmailerSending) {
                event.preventDefault();
                return;
            }
            if (window.tinymce && typeof tinymce.triggerSave === 'function') {
                tinymce.triggerSave();
            }
            form.dataset.ticketmailerSending = 'true';
            showSendingOverlay();
            form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.insertAdjacentHTML(
                    'afterbegin',
                    '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>',
                );
            });
            form.querySelectorAll(
                '.ticketmailer-actions button:not([type="submit"]), .ticketmailer-actions a',
            ).forEach(function (cancel) {
                if (cancel.tagName === 'BUTTON') {
                    cancel.disabled = true;
                } else {
                    cancel.classList.add('disabled');
                    cancel.setAttribute('aria-disabled', 'true');
                    cancel.addEventListener('click', function (cancelEvent) {
                        cancelEvent.preventDefault();
                    });
                }
            });
        });
    }

    function applyFollowupTemplate(form, templateId) {
        if (!form) {
            return;
        }
        var url = form.dataset.followupTemplateUrl || '';
        var editorId = form.dataset.editorId || '';
        var ticket = form.querySelector('input[name="tickets_id"]');
        if (!url || !editorId || !ticket) {
            return;
        }

        var xhr = new XMLHttpRequest();
        var data = new FormData();
        data.append('itilfollowuptemplates_id', templateId);
        data.append('items_id', ticket.value);
        data.append('itemtype', 'Ticket');
        xhr.open('POST', url);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        var token = getAjaxCsrf(form);
        if (token) {
            xhr.setRequestHeader('X-Glpi-Csrf-Token', token);
        }
        xhr.onload = function () {
            if (xhr.status < 200 || xhr.status >= 300) {
                return;
            }
            try {
                var result = JSON.parse(xhr.responseText);
                var editor = window.tinymce && tinymce.get(editorId);
                var textarea = editor ? null : form.querySelector('#' + editorId);
                var signature = form.ticketmailerSignature;
                if (typeof signature === 'undefined') {
                    signature = editor ? editor.getContent() : textarea ? textarea.value : '';
                    form.ticketmailerSignature = signature;
                }
                if (editor) {
                    editor.setContent((result.content || '') + signature);
                } else if (textarea) {
                    textarea.value = (result.content || '') + signature;
                }
            } catch (e) {
                // The current form remains unchanged on an invalid response.
            }
        };
        xhr.send(data);
    }

    function initForm(form) {
        if (form.dataset.ticketmailerInitialized) {
            return;
        }
        form.dataset.ticketmailerInitialized = 'true';
        form.querySelectorAll('[data-recipient-control]').forEach(initRecipientControl);
        initAttachments(form);
        initTinyMceSave(form);
    }

    function initForms() {
        document.querySelectorAll('.ticketmailer-compose').forEach(initForm);
    }

    $(document).on(
        'change',
        '.ticketmailer-compose [name="itilfollowuptemplates_id"]',
        function () {
            applyFollowupTemplate(this.form, this.value);
        },
    );
    document.addEventListener('DOMContentLoaded', initForms);
    $(document).ajaxComplete(initForms);
}());
