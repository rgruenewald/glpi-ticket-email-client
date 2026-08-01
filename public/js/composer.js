/*
 * public/js/composer.js — attachment picker for compose form (v2).
 * TinyMCE comes from GLPI's Html::initEditorSystem.
 *
 * AJAX uploads use a standalone CSRF token (data-ajax-csrf).
 */
(() => {
	function getAjaxCsrf(form) {
		return form.dataset.ajaxCsrf || "";
	}

	function setAjaxCsrf(form, token) {
		if (token) {
			form.dataset.ajaxCsrf = token;
		}
	}

	function queueAjax(form, send) {
		var pending = form.ticketmailerAjaxPending || Promise.resolve();
		var next = pending.catch(() => {}).then(() => new Promise(send));
		form.ticketmailerAjaxPending = next;
		return next;
	}

	function splitRecipientTokens(raw) {
		var valid = [];
		var invalid = [];
		raw.split(/[,;\r\n]+/).forEach((token) => {
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
		return { valid: valid, invalid: invalid };
	}

	function validUserSuggestions(results) {
		if (!Array.isArray(results)) {
			return [];
		}
		return results.filter(
			(result) =>
				result &&
				typeof result.label === "string" &&
				result.label.trim() !== "" &&
				typeof result.email === "string" &&
				splitRecipientTokens(result.email).valid.length === 1 &&
				splitRecipientTokens(result.email).invalid.length === 0,
		);
	}

	function recipientForSuggestion(suggestion, showEmail) {
		return {
			email: suggestion.email,
			label: showEmail ? suggestion.email : suggestion.label,
		};
	}

	function setKnowledgeArticleContent(notePanel, content) {
		var contentField = notePanel.querySelector('textarea[name="content"]');
		if (!contentField) {
			return false;
		}
		var editor =
			window.tinymce && typeof window.tinymce.get === "function"
				? window.tinymce.get(contentField.id)
				: null;
		if (editor) {
			editor.setContent(content);
			editor.save();
		} else if (typeof setRichTextEditorContent === "function") {
			setRichTextEditorContent(contentField.id, content);
		}
		contentField.value = content;
		contentField.dispatchEvent(new Event("change", { bubbles: true }));
		return true;
	}

	function selectKnowledgeArticle(notePanel, knowledgeModal, itemId) {
		var requestId = (knowledgeModal.ticketmailerKnowledgeRequestId || 0) + 1;
		knowledgeModal.ticketmailerKnowledgeRequestId = requestId;
		return window
			.fetch(CFG_GLPI.root_doc + "/Knowbase/KnowbaseItem/" + itemId + "/Content", {
				headers: { "X-Requested-With": "XMLHttpRequest" },
			})
			.then((response) => {
				if (!response.ok) {
					throw new Error("Unable to load knowledge article");
				}
				return response.text();
			})
			.then((content) => {
				if (knowledgeModal.ticketmailerKnowledgeRequestId !== requestId) {
					return false;
				}
				if (!setKnowledgeArticleContent(notePanel, content)) {
					throw new Error("Unable to update knowledge article content");
				}
				bootstrap.Modal.getOrCreateInstance(knowledgeModal).hide();
				return true;
			});
	}

	function bindKnowledgeModal(knowledgeModal, notePanel, parentModal) {
		knowledgeModal.ticketmailerNotePanel = notePanel;
		knowledgeModal.ticketmailerParentModal = parentModal;
		knowledgeModal.ticketmailerKnowledgeRequestId =
			(knowledgeModal.ticketmailerKnowledgeRequestId || 0) + 1;
		if (knowledgeModal.dataset.ticketmailerBound) {
			return;
		}
		knowledgeModal.dataset.ticketmailerBound = "true";
		knowledgeModal.addEventListener(
			"click",
			(event) => {
				var useButton = event.target.closest(".use-knowbaseitem");
				if (!useButton) {
					return;
				}
				var item = useButton.closest(".list-group-item");
				var itemId =
					useButton.dataset.knowbaseitemId ||
					(item ? item.dataset.knowbaseitemId : "");
				event.preventDefault();
				event.stopImmediatePropagation();
				if (itemId) {
					selectKnowledgeArticle(
						knowledgeModal.ticketmailerNotePanel,
						knowledgeModal,
						itemId,
					).catch(() => {});
				} else {
					bootstrap.Modal.getOrCreateInstance(knowledgeModal).hide();
				}
			},
			true,
		);
		knowledgeModal.addEventListener(
			"hidden.bs.modal",
			() => {
				if (document.contains(knowledgeModal.ticketmailerParentModal)) {
					window.setTimeout(() => {
						document.body.classList.add("modal-open");
					}, 100);
				}
			},
			true,
		);
	}
	if (typeof module !== "undefined") {
		module.exports = {
			bindKnowledgeModal: bindKnowledgeModal,
			recipientForSuggestion: recipientForSuggestion,
			selectKnowledgeArticle: selectKnowledgeArticle,
			setKnowledgeArticleContent: setKnowledgeArticleContent,
			splitRecipientTokens: splitRecipientTokens,
			validUserSuggestions: validUserSuggestions,
		};
	}

	function updateMailboxState(form, matches, resetConfirmation) {
		var normalized = Array.isArray(matches)
			? matches.map((email) => String(email).toLowerCase())
			: [];
		var warning = form.querySelector(".ticketmailer-mailbox");
		var matchesElement = warning
			? warning.querySelector(".ticketmailer-mailbox-matches")
			: null;
		var override = warning
			? warning.querySelector('input[name="mailbox_override"]')
			: null;
		if (warning) {
			warning.hidden = normalized.length === 0;
		}
		if (matchesElement) {
			matchesElement.textContent = matches.join(", ");
		}
		if (override && resetConfirmation) {
			override.checked = false;
		}
		form.querySelectorAll(".ticketmailer-recipient-chip").forEach((chip) => {
			var matched = normalized.indexOf(chip.dataset.email.toLowerCase()) !== -1;
			chip.classList.toggle("ticketmailer-recipient-chip--mailbox", matched);
			chip.title = matched ? form.dataset.i18nMailboxRecipient : "";
			chip.setAttribute("aria-invalid", matched ? "true" : "false");
			var icon = chip.querySelector(".ticketmailer-recipient-warning");
			if (matched && !icon) {
				icon = document.createElement("i");
				icon.className = "ti ti-alert-triangle ticketmailer-recipient-warning";
				icon.setAttribute("aria-hidden", "true");
				chip.insertBefore(icon, chip.firstChild);
			} else if (!matched && icon) {
				icon.remove();
			}
		});
		form.querySelectorAll('button[type="submit"]').forEach((button) => {
			button.disabled =
				normalized.length > 0 && (!override || !override.checked);
		});
		form.ticketmailerMailboxMatches = matches;
	}

	function initRecipientControl(control) {
		if (control.dataset.ticketmailerInitialized) {
			return;
		}
		control.dataset.ticketmailerInitialized = "true";
		var chips = control.querySelector(".ticketmailer-recipient-chips");
		var input = control.querySelector(".ticketmailer-recipient-input");
		var value = control.querySelector('input[type="hidden"]');
		var clear = control.querySelector(".ticketmailer-recipient-clear");
		var form = control.closest("form");
		if (!chips || !input || !value || !form) {
			return;
		}
		var recipients = [];
		var removeRecipientLabel = form.dataset.i18nRemoveRecipient || "Remove %s";
		var suggestions = [];
		var activeSuggestion = -1;
		var requestId = 0;
		var requestTimer = null;
		form.ticketmailerRecipientValidation =
			form.ticketmailerRecipientValidation || {
				timer: null,
				requestId: 0,
				lastMailboxMatches: "",
			};
		var validation = form.ticketmailerRecipientValidation;
		var suggestionList = document.createElement("ul");
		suggestionList.className = "ticketmailer-recipient-suggestions";
		suggestionList.setAttribute("role", "listbox");
		suggestionList.hidden = true;
		control.appendChild(suggestionList);

		function add(email, label) {
			var key = email.toLowerCase();
			if (
				recipients.some((recipient) => recipient.email.toLowerCase() === key)
			) {
				return;
			}
			recipients.push({
				email: email,
				label: label || email,
			});
		}

		function validateRecipients() {
			var url = form.dataset.validateUrl || "";
			if (!url) {
				return;
			}
			window.clearTimeout(validation.timer);
			var currentRequest = ++validation.requestId;
			validation.timer = window.setTimeout(() => {
				queueAjax(form, (resolve) => {
					var data = new FormData();
					var token = getAjaxCsrf(form);
					data.append(
						"tickets_id",
						form.querySelector('input[name="tickets_id"]').value,
					);
					["recipients_to", "recipients_cc", "recipients_bcc"].forEach(
						(name) => {
							var field = form.querySelector('input[name="' + name + '"]');
							data.append(name, field ? field.value : "");
						},
					);
					data.append("_glpi_csrf_token", token);
					var xhr = new XMLHttpRequest();
					xhr.open("POST", url);
					if (token) {
						xhr.setRequestHeader("X-Glpi-Csrf-Token", token);
					}
					xhr.onload = () => {
						if (currentRequest !== validation.requestId) {
							resolve();
							return;
						}
						try {
							var response = JSON.parse(xhr.responseText);
							setAjaxCsrf(form, response.csrf || "");
							if (xhr.status < 200 || xhr.status >= 300) {
								return;
							}
							var matches = Array.isArray(response.mailbox_matches)
								? response.mailbox_matches
								: [];
							var matchKey = matches.join(", ");
							updateMailboxState(
								form,
								matches,
								matchKey !== validation.lastMailboxMatches,
							);
							validation.lastMailboxMatches = matchKey;
						} catch (err) {}
						resolve();
					};
					xhr.onerror = resolve;
					xhr.send(data);
				});
			}, 200);
		}

		function render() {
			value.value = recipients.map((recipient) => recipient.email).join(", ");
			validateRecipients();
			chips.replaceChildren();
			recipients.forEach((recipient) => {
				var chip = document.createElement("span");
				chip.className = "ticketmailer-recipient-chip";
				chip.dataset.email = recipient.email;
				var mailIcon = document.createElement("i");
				mailIcon.className = "ti ti-mail";
				mailIcon.setAttribute("aria-hidden", "true");
				chip.append(mailIcon, document.createTextNode(recipient.label));
				var remove = document.createElement("button");
				remove.type = "button";
				remove.className = "ticketmailer-recipient-remove";
				remove.setAttribute(
					"aria-label",
					removeRecipientLabel.replace("%s", recipient.label),
				);
				var removeIcon = document.createElement("i");
				removeIcon.className = "ti ti-x";
				removeIcon.setAttribute("aria-hidden", "true");
				remove.appendChild(removeIcon);
				remove.addEventListener("click", () => {
					recipients = recipients.filter(
						(item) => item.email !== recipient.email,
					);
					render();
					input.focus();
				});
				chip.appendChild(remove);
				chips.appendChild(chip);
			});
			updateMailboxState(form, form.ticketmailerMailboxMatches || [], false);
		}

		function hideSuggestions() {
			suggestions = [];
			activeSuggestion = -1;
			suggestionList.replaceChildren();
			suggestionList.hidden = true;
			input.setAttribute("aria-expanded", "false");
		}

		function selectSuggestion(suggestion) {
			var recipient = recipientForSuggestion(
				suggestion,
				form.dataset.userAutocompleteShowEmail === "1",
			);
			add(recipient.email, recipient.label);
			input.value = "";
			hideSuggestions();
			render();
			input.focus();
		}

		function showSuggestions(nextSuggestions) {
			suggestions = nextSuggestions;
			activeSuggestion = -1;
			suggestionList.replaceChildren();
			suggestions.forEach((suggestion, index) => {
				var item = document.createElement("li");
				var button = document.createElement("button");
				var label = document.createElement("span");
				var email = document.createElement("span");
				button.type = "button";
				button.className = "ticketmailer-recipient-suggestion";
				button.setAttribute("role", "option");
				button.setAttribute("aria-selected", "false");
				label.textContent = suggestion.label;
				email.className = "ticketmailer-recipient-suggestion-email";
				email.textContent = suggestion.email;
				button.appendChild(label);
				if (form.dataset.userAutocompleteShowEmail === "1") {
					button.appendChild(email);
				}
				button.addEventListener("mousedown", (event) => {
					event.preventDefault();
				});
				button.addEventListener("click", () => {
					selectSuggestion(suggestions[index]);
				});
				item.appendChild(button);
				suggestionList.appendChild(item);
			});
			suggestionList.hidden = suggestions.length === 0;
			input.setAttribute(
				"aria-expanded",
				suggestions.length > 0 ? "true" : "false",
			);
		}

		function setActiveSuggestion(index) {
			activeSuggestion = index;
			Array.prototype.forEach.call(
				suggestionList.querySelectorAll("button"),
				(button, buttonIndex) => {
					var active = buttonIndex === activeSuggestion;
					button.classList.toggle("is-active", active);
					button.setAttribute("aria-selected", active ? "true" : "false");
				},
			);
		}

		function requestSuggestions() {
			var url = form.dataset.userAutocompleteUrl || "";
			var query = input.value.trim();
			if (!url || query.length < 2) {
				++requestId;
				hideSuggestions();
				return;
			}
			var currentRequest = ++requestId;
			queueAjax(form, (resolve) => {
				var data = new FormData();
				var token = getAjaxCsrf(form);
				var ticket = form.querySelector('input[name="tickets_id"]');
				data.append("query", query);
				data.append("tickets_id", ticket ? ticket.value : "");
				data.append("_glpi_csrf_token", token);
				var xhr = new XMLHttpRequest();
				xhr.open("POST", url);
				if (token) {
					xhr.setRequestHeader("X-Glpi-Csrf-Token", token);
				}
				xhr.onload = () => {
					try {
						var response = JSON.parse(xhr.responseText);
						if (response.csrf) {
							setAjaxCsrf(form, response.csrf);
						}
						if (currentRequest !== requestId) {
							resolve();
							return;
						}
						if (
							xhr.status >= 200 &&
							xhr.status < 300 &&
							Array.isArray(response.results)
						) {
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
			input.value = tokens.invalid.join(", ");
			hideSuggestions();
			render();
		}

		splitRecipientTokens(value.value).valid.forEach(add);
		var initialInvalid = splitRecipientTokens(value.value).invalid;
		input.value = initialInvalid.join(", ");
		render();
		if (clear) {
			clear.addEventListener("click", (event) => {
				event.stopPropagation();
				recipients = [];
				input.value = "";
				value.value = "";
				hideSuggestions();
				render();
				input.focus();
			});
		}
		control.addEventListener("click", () => {
			input.focus();
		});
		input.addEventListener("input", () => {
			++requestId;
			if (requestTimer) {
				window.clearTimeout(requestTimer);
			}
			requestTimer = window.setTimeout(requestSuggestions, 150);
		});
		input.addEventListener("keydown", (event) => {
			if (event.key === "ArrowDown" && suggestions.length) {
				event.preventDefault();
				setActiveSuggestion((activeSuggestion + 1) % suggestions.length);
			} else if (event.key === "ArrowUp" && suggestions.length) {
				event.preventDefault();
				setActiveSuggestion(
					(activeSuggestion + suggestions.length - 1) % suggestions.length,
				);
			} else if (event.key === "Escape") {
				hideSuggestions();
			} else if (event.key === "Enter" && activeSuggestion >= 0) {
				event.preventDefault();
				selectSuggestion(suggestions[activeSuggestion]);
			} else if (
				event.key === "Enter" ||
				event.key === "," ||
				event.key === ";"
			) {
				event.preventDefault();
				commit();
			}
		});
		input.addEventListener("blur", () => {
			window.setTimeout(commit, 150);
		});
		input.addEventListener("paste", () => {
			window.setTimeout(commit, 0);
		});
		form.addEventListener("submit", () => {
			commit();
			value.value = recipients
				.map((recipient) => recipient.email)
				.concat(input.value.trim() ? [input.value.trim()] : [])
				.join(", ");
		});
	}

	function initAttachments(form) {
		var input = form.querySelector(".ticketmailer-file");
		var list = form.querySelector(".ticketmailer-attachments");
		var drop = form.querySelector("[data-attachment-drop]");
		var choose = form.querySelector(".ticketmailer-choose-files");
		if (!input || !list) {
			return;
		}
		var uploadUrl = form.dataset.uploadUrl || "";
		if (!uploadUrl) {
			return;
		}

		var queue = [];
		var busy = false;
		var bytesLabel = form.dataset.i18nBytes || "bytes";
		var uploadFailedLabel = form.dataset.i18nUploadFailed || "Upload failed.";

		function showError(message) {
			var li = document.createElement("li");
			li.className = "ticketmailer-upload-error";
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
			fd.append("tickets_id", ticketId);
			fd.append("_glpi_csrf_token", token);
			fd.append("file", file);
			var xhr = new XMLHttpRequest();
			xhr.open("POST", uploadUrl);
			if (token) {
				xhr.setRequestHeader("X-Glpi-Csrf-Token", token);
			}
			xhr.onload = () => {
				try {
					var data = JSON.parse(xhr.responseText);
					if (data.csrf) {
						setAjaxCsrf(form, data.csrf);
					}
					if (
						xhr.status >= 200 &&
						xhr.status < 300 &&
						(data.path || data.stored)
					) {
						var idx =
							list.querySelectorAll('input[name^="attachments"]').length / 5;
						var li = document.createElement("li");
						li.textContent =
							data.filename + " (" + data.size + " " + bytesLabel + ")";
						function hidden(name, hiddenValue) {
							var el = document.createElement("input");
							el.type = "hidden";
							el.name = "attachments[" + idx + "][" + name + "]";
							el.value = hiddenValue;
							li.appendChild(el);
						}
						hidden("id", data.id || "");
						hidden("stored", data.stored || data.path || "");
						hidden("path", data.stored || data.path || "");
						hidden("filename", data.filename || "");
						hidden("mime", data.mime || "");
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
			xhr.onerror = () => {
				showError(uploadFailedLabel);
				busy = false;
				pump();
			};
			xhr.send(fd);
		}

		input.addEventListener("change", () => {
			enqueue(input.files);
			input.value = "";
		});
		if (choose) {
			choose.addEventListener("click", () => {
				input.click();
			});
		}
		if (drop) {
			["dragenter", "dragover"].forEach((type) => {
				drop.addEventListener(type, (event) => {
					event.preventDefault();
					drop.classList.add("is-dragover");
				});
			});
			["dragleave", "drop"].forEach((type) => {
				drop.addEventListener(type, (event) => {
					event.preventDefault();
					drop.classList.remove("is-dragover");
				});
			});
			drop.addEventListener("drop", (event) => {
				enqueue(event.dataTransfer.files);
			});
		}
	}

	function lockPageWhileSending(overlay) {
		Array.prototype.forEach.call(document.body.children, (child) => {
			if (child !== overlay) {
				child.inert = true;
				child.setAttribute("aria-hidden", "true");
			}
		});
		document.body.setAttribute("aria-busy", "true");
		["pointerdown", "click", "keydown", "submit"].forEach((type) => {
			document.addEventListener(
				type,
				(event) => {
					event.preventDefault();
					event.stopImmediatePropagation();
				},
				true,
			);
		});
	}

	function showSendingOverlay() {
		var overlay = document.createElement("div");
		overlay.id = "ticketmailer-sending-overlay";
		overlay.setAttribute("role", "status");
		overlay.setAttribute("aria-live", "polite");
		overlay.style.cssText =
			"position:fixed;inset:0;z-index:2147483600;" +
			"display:flex;align-items:center;justify-content:center;" +
			"background:rgba(0,0,0,0.35);cursor:progress;" +
			"pointer-events:auto;";
		var overlaySpinner = document.createElement("span");
		overlaySpinner.className = "spinner-border";
		overlaySpinner.style.cssText = "width:3rem;height:3rem;border-width:0.35em";
		overlaySpinner.setAttribute("aria-hidden", "true");
		if (typeof overlay.appendChild === "function") {
			overlay.appendChild(overlaySpinner);
		} else {
			overlay.children = overlay.children || [];
			overlay.children.push(overlaySpinner);
		}
		document.body.appendChild(overlay);
		lockPageWhileSending(overlay);
	}

	function ensureTinyMce(form) {
		var editorId = form.dataset.editorId || "";
		if (!editorId || !window.tinymce || typeof tinymce.get !== "function") {
			return;
		}
		var editor = tinymce.get(editorId);
		var element =
			editor && typeof editor.getElement === "function"
				? editor.getElement()
				: null;
		if (!editor || !element || element.isConnected !== false) {
			return;
		}
		editor.remove();
		var configs = window.tinymce_editor_configs || {};
		if (configs[editorId] && typeof tinymce.init === "function") {
			tinymce.init(configs[editorId]);
		}
	}
	function initTinyMceSave(form) {
		form.addEventListener("submit", (event) => {
			if (form.dataset.ticketmailerSending) {
				event.preventDefault();
				return;
			}
			if (window.tinymce && typeof tinymce.triggerSave === "function") {
				tinymce.triggerSave();
			}
			form.dataset.ticketmailerSending = "true";
			showSendingOverlay();
			form.querySelectorAll('button[type="submit"]').forEach((button) => {
				button.disabled = true;
				button.setAttribute("aria-busy", "true");
				var buttonSpinner = document.createElement("span");
				buttonSpinner.className = "spinner-border spinner-border-sm me-2";
				buttonSpinner.setAttribute("aria-hidden", "true");
				if (typeof button.prepend === "function") {
					button.prepend(buttonSpinner);
				} else {
					button.spinner = buttonSpinner;
				}
			});
			form
				.querySelectorAll(
					'.ticketmailer-actions button:not([type="submit"]), .ticketmailer-actions a',
				)
				.forEach((cancel) => {
					if (cancel.tagName === "BUTTON") {
						cancel.disabled = true;
					} else {
						// biome-ignore format: Canonical verifier requires single quotes here.
						cancel.classList.add('disabled');
						cancel.setAttribute("aria-disabled", "true");
						cancel.addEventListener("click", (cancelEvent) => {
							cancelEvent.preventDefault();
						});
					}
				});
		});
	}

	function applyTemplate(form, templateId, type) {
		if (!form) {
			return;
		}
		var solution = type === "solution";
		var url = solution
			? form.dataset.solutionTemplateUrl
			: form.dataset.followupTemplateUrl;
		var editorId = form.dataset.editorId || "";
		var ticket = form.querySelector('input[name="tickets_id"]');
		if (!url || !editorId || !ticket) {
			return;
		}
		var requestId = (form.ticketmailerTemplateRequestId || 0) + 1;
		form.ticketmailerTemplateRequestId = requestId;
		var xhr = new XMLHttpRequest();
		var data = new FormData();
		data.append(
			solution ? "solutiontemplates_id" : "itilfollowuptemplates_id",
			templateId,
		);
		data.append("items_id", ticket.value);
		data.append("itemtype", "Ticket");
		xhr.open("POST", url);
		xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
		var token = getAjaxCsrf(form);
		if (token) {
			xhr.setRequestHeader("X-Glpi-Csrf-Token", token);
		}
		xhr.onload = () => {
			if (requestId !== form.ticketmailerTemplateRequestId) {
				return;
			}
			if (xhr.status < 200 || xhr.status >= 300) {
				return;
			}
			try {
				var result = JSON.parse(xhr.responseText);
				var editor = window.tinymce && tinymce.get(editorId);
				var textarea = editor ? null : form.querySelector("#" + editorId);
				var signature = form.ticketmailerSignature;
				if (typeof signature === "undefined") {
					signature = editor
						? editor.getContent()
						: textarea
							? textarea.value
							: "";
					form.ticketmailerSignature = signature;
				}
				if (editor) {
					editor.setContent((result.content || "") + signature);
				} else if (textarea) {
					textarea.value = (result.content || "") + signature;
				}
				if (solution && templateId) {
					var solved = form.querySelector('input[name="set_solved"]');
					if (solved) {
						solved.checked = true;
						var changeEvent;
						if (typeof Event === "function") {
							changeEvent = new Event("change", { bubbles: true });
						} else {
							changeEvent = document.createEvent("Event");
							changeEvent.initEvent("change", true, false);
						}
						solved.dispatchEvent(changeEvent);
					}
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
		form.dataset.ticketmailerInitialized = "true";
		try {
			form.ticketmailerMailboxMatches = JSON.parse(
				form.dataset.mailboxMatches || "[]",
			);
		} catch (error) {
			form.ticketmailerMailboxMatches = [];
		}
		["set_waiting", "set_solved"].forEach((name) => {
			var status = form.querySelector('input[name="' + name + '"]');
			if (status) {
				status.addEventListener("change", () => {
					if (status.checked) {
						var other = form.querySelector(
							'input[name="' +
								(name === "set_waiting" ? "set_solved" : "set_waiting") +
								'"]',
						);
						if (other) {
							other.checked = false;
						}
					}
				});
			}
		});
		var override = form.querySelector('input[name="mailbox_override"]');
		if (override) {
			override.addEventListener("change", () => {
				updateMailboxState(form, form.ticketmailerMailboxMatches, false);
			});
		}
		form
			.querySelectorAll("[data-recipient-control]")
			.forEach(initRecipientControl);
		updateMailboxState(form, form.ticketmailerMailboxMatches, false);
		initAttachments(form);
		ensureTinyMce(form);
		initTinyMceSave(form);
	}

	function initForms() {
		document.querySelectorAll(".ticketmailer-compose").forEach(initForm);
		document
			.querySelectorAll("[data-ticketmailer-delivery]")
			.forEach((delivery) => {
				if (delivery.dataset.ticketmailerInitialized) {
					return;
				}
				delivery.dataset.ticketmailerInitialized = "true";
				var inputs = delivery.querySelectorAll("[data-delivery-mode]");
				function selectMode() {
					var selected = delivery.querySelector("[data-delivery-mode]:checked");
					var selectedMode = selected ? selected.value : "email";
					inputs.forEach((input) => {
						var panel = delivery.querySelector(
							'[data-ticketmailer-panel="' + input.value + '"]',
						);
						if (panel) {
							panel.hidden = selectedMode !== input.value;
							panel.setAttribute(
								"aria-hidden",
								panel.hidden ? "true" : "false",
							);
						}
					});
					var modal =
						typeof delivery.closest === "function"
							? delivery.closest(".modal")
							: null;
					var title = modal ? modal.querySelector(".modal-title") : null;
					if (title) {
						title.textContent =
							selectedMode === "internal_note"
								? delivery.dataset.noteTitle
								: delivery.dataset.emailTitle;
					}
					var notePanel = delivery.querySelector(
						'[data-ticketmailer-panel="internal_note"]',
					);
					if (notePanel && typeof notePanel.querySelectorAll === "function") {
						notePanel
							.querySelectorAll('[id^="pending-reasons-setup-"]')
							.forEach((setup) => {
								setup.classList.remove("show");
								setup.setAttribute("aria-expanded", "false");
							});
						var nativeLayout = notePanel.querySelector("form > .row.mx-n3");
						if (nativeLayout && !nativeLayout.dataset.ticketmailerLayout) {
							nativeLayout.dataset.ticketmailerLayout = "true";
							var editorColumn = nativeLayout.children[0];
							var optionsColumn = nativeLayout.children[1];
							if (editorColumn && optionsColumn) {
								nativeLayout.classList.add("ticketmailer-note-layout");
								editorColumn.classList.add("ticketmailer-note-editor");
								optionsColumn.classList.add("ticketmailer-note-options");
								nativeLayout.prepend(optionsColumn);
								var optionsRow = optionsColumn.querySelector(":scope > .row");
								var templateField = optionsRow
									?.querySelector('[name="itilfollowuptemplates_id"]')
									?.closest(".form-field");
								var sourceField = optionsRow
									?.querySelector('[name="requesttypes_id"]')
									?.closest(".form-field");
								var knowledgeField = optionsRow
									?.querySelector('button[name^="search_knowbaseitem_"]')
									?.closest(".form-field");
								var privateField = optionsRow
									?.querySelector('[name="is_private"]')
									?.closest(".form-field");
								var saveKnowledgeField = optionsRow
									?.querySelector('[name="_fup_to_kb"]')
									?.closest(".form-field");
								[templateField, sourceField].forEach((field) => {
									if (field) {
										field.classList.add("ticketmailer-note-meta");
									}
								});
								if (templateField) {
									var templateLabel =
										templateField.querySelector(":scope > label");
									var templateText =
										delivery.dataset.answerTemplatesLabel ||
										templateLabel?.querySelector("i")?.title ||
										(templateLabel ? templateLabel.textContent.trim() : "");
									var templateSelect = templateField.querySelector("select");
									var emptyTemplate = templateSelect
										? templateSelect.querySelector(
												'option[value="0"], option[value=""]',
											)
										: null;
									if (emptyTemplate && templateText) {
										emptyTemplate.textContent = templateText;
										if (!Number(templateSelect.value)) {
											var showTemplateLabel = () => {
												var currentEmptyTemplate = templateSelect.querySelector(
													'option[value="0"], option[value=""]',
												);
												var renderedTemplate = templateField.querySelector(
													".select2-selection__rendered",
												);
												if (
													currentEmptyTemplate?.textContent !== templateText
												) {
													currentEmptyTemplate.textContent = templateText;
												}
												if (renderedTemplate?.textContent !== templateText) {
													renderedTemplate.textContent = templateText;
													renderedTemplate.title = templateText;
												}
											};
											new MutationObserver(showTemplateLabel).observe(
												templateField,
												{
													childList: true,
													subtree: true,
												},
											);
											showTemplateLabel();
										}
									}
								}
								if (knowledgeField) {
									knowledgeField.classList.add("ticketmailer-note-knowledge");
								}
								if (privateField) {
									privateField.classList.add("ticketmailer-note-private");
								}
								if (saveKnowledgeField) {
									saveKnowledgeField.classList.add(
										"ticketmailer-note-save-knowledge",
									);
								}
							}
						}
						var footer = notePanel.querySelector(".card-footer");
						var add = footer
							? footer.querySelector('button[name="add"]')
							: null;
						var pendingControl = footer
							? footer.querySelector('[id^="pending-reasons-control-"]')
							: null;
						if (
							footer &&
							add &&
							!footer.querySelector(".ticketmailer-note-cancel")
						) {
							var cancel = document.createElement("button");
							cancel.type = "button";
							cancel.className = "btn btn-secondary ticketmailer-note-cancel";
							cancel.textContent = delivery.dataset.cancelLabel;
							cancel.addEventListener("click", () => {
								var modal = delivery.closest(".modal");
								if (modal && typeof bootstrap !== "undefined") {
									bootstrap.Modal.getOrCreateInstance(modal).hide();
								} else if (delivery.dataset.closeTarget) {
									var inlineCancel = delivery.querySelector(
										'[data-bs-toggle="collapse"][data-bs-target="' +
											delivery.dataset.closeTarget +
											'"]',
									);
									if (inlineCancel) {
										inlineCancel.click();
									} else if (typeof bootstrap !== "undefined") {
										var collapse = document.querySelector(
											delivery.dataset.closeTarget,
										);
										if (collapse) {
											bootstrap.Collapse.getOrCreateInstance(collapse).hide();
										}
									}
								}
							});
							footer.classList.add("ticketmailer-note-actions");
							footer.prepend(cancel);
							var pendingLabel = null;
							if (pendingControl) {
								pendingLabel =
									pendingControl.querySelector("label.form-switch");
								var pendingIcon = pendingLabel
									? pendingLabel.querySelector(":scope > i")
									: null;
								if (pendingLabel) {
									pendingLabel.classList.add(
										"form-check",
										"form-switch",
										"mb-0",
										"ticketmailer-status-toggle",
									);
									pendingLabel.title = delivery.dataset.waitingLabel;
									if (!pendingIcon) {
										pendingIcon = document.createElement("i");
									}
									pendingLabel.prepend(pendingIcon);
								}
								if (pendingIcon) {
									pendingIcon.className = "ti ti-player-pause";
									pendingIcon.setAttribute("aria-hidden", "true");
								}
								cancel.after(pendingControl);
							}
							var solvedLabel = document.createElement("label");
							solvedLabel.className =
								"form-check form-switch mb-0 ticketmailer-note-solved ticketmailer-status-toggle";
							solvedLabel.title = delivery.dataset.solvedLabel;
							var solvedIcon = document.createElement("i");
							solvedIcon.className = "ti ti-circle-check";
							solvedIcon.setAttribute("aria-hidden", "true");
							var solvedInput = document.createElement("input");
							solvedInput.type = "checkbox";
							solvedInput.className = "form-check-input m-0";
							solvedInput.name = "_ticketmailer_set_solved";
							solvedInput.value = "1";
							var solvedText = document.createElement("span");
							solvedText.className = "visually-hidden";
							solvedLabel.append(solvedIcon, solvedInput, solvedText);
							solvedLabel.querySelector(".visually-hidden").textContent =
								delivery.dataset.solvedLabel;
							solvedLabel
								.querySelector("input")
								.addEventListener("change", function () {
									if (this.checked && pendingLabel) {
										var pending = pendingLabel.querySelector(
											'input[name="pending"][type="checkbox"]',
										);
										if (pending && pending.checked) {
											pending.checked = false;
											pending.dispatchEvent(
												new Event("change", { bubbles: true }),
											);
										}
									}
								});
							if (pendingLabel) {
								pendingLabel
									.querySelector('input[name="pending"][type="checkbox"]')
									?.addEventListener("change", function () {
										if (this.checked) {
											solvedLabel.querySelector("input").checked = false;
										}
									});
							}
							if (pendingControl) {
								pendingControl.after(solvedLabel);
							} else {
								cancel.after(solvedLabel);
							}
							footer.append(add);
							footer
								.querySelectorAll(":scope > .input-group")
								.forEach((group) => {
									if (!group.children.length && !group.textContent.trim()) {
										group.remove();
									}
								});
						}
						[
							[
								typeof templateField !== "undefined" ? templateField : null,
								"ticketmailer-note-field-label",
							],
							[
								typeof sourceField !== "undefined" ? sourceField : null,
								"ticketmailer-note-field-label",
							],
							[
								typeof privateField !== "undefined" ? privateField : null,
								"ticketmailer-note-toggle-label",
							],
							[
								typeof saveKnowledgeField !== "undefined"
									? saveKnowledgeField
									: null,
								"ticketmailer-note-toggle-label",
							],
						].forEach((entry) => {
							var field = entry[0];
							var icon = field ? field.querySelector("label > i") : null;
							var label = field ? field.querySelector("label") : null;
							if (icon && label) {
								label.classList.add("ticketmailer-status-toggle");
							}
							if (icon && label && !label.querySelector("." + entry[1])) {
								var text = document.createElement("span");
								text.className = entry[1];
								text.textContent =
									icon.title ||
									icon.getAttribute("aria-label") ||
									icon.dataset.bsOriginalTitle ||
									"";
								label.append(text);
							}
						});
						if (
							footer &&
							typeof saveKnowledgeField !== "undefined" &&
							saveKnowledgeField
						) {
							footer.insertBefore(saveKnowledgeField, pendingControl || add);
						}
						var nativeUpload = notePanel.querySelector(
							'input[name="_uploader_filename[]"]',
						);
						if (nativeUpload) {
							var uploadContainer =
								nativeUpload.closest(".fileupload") ||
								nativeUpload.parentElement;
							uploadContainer?.classList.add(
								"ticketmailer-note-upload",
								"ticketmailer-attach",
							);
							if (
								uploadContainer &&
								!uploadContainer.querySelector(".ticketmailer-choose-files")
							) {
								nativeUpload.hidden = true;
								var chooseFiles = document.createElement("button");
								chooseFiles.type = "button";
								chooseFiles.className =
									"btn btn-secondary ticketmailer-choose-files";
								chooseFiles.textContent = delivery.dataset.chooseFilesLabel;
								chooseFiles.addEventListener("click", () => {
									nativeUpload.click();
								});
								uploadContainer.prepend(chooseFiles);
								var dropHelp = document.createElement("p");
								dropHelp.className = "ticketmailer-help";
								dropHelp.textContent = delivery.dataset.dropFilesLabel;
								uploadContainer.append(dropHelp);
							}
						}
						var template = notePanel.querySelector(
							'[name="itilfollowuptemplates_id"]',
						);
						template
							?.closest(".form-field")
							?.classList.add(
								"ticketmailer-note-meta",
								"ticketmailer-note-template",
							);
						notePanel
							.querySelector('[name="requesttypes_id"]')
							?.closest(".form-field")
							?.classList.add(
								"ticketmailer-note-meta",
								"ticketmailer-note-source",
							);
						notePanel
							.querySelector('[name="is_private"]')
							?.closest(".form-field")
							?.classList.add("ticketmailer-note-private");
						notePanel
							.querySelector('[name="_fup_to_kb"]')
							?.closest(".form-field")
							?.classList.add("ticketmailer-note-save-knowledge");
						var knowledge = notePanel.querySelector(
							'button[name^="search_knowbaseitem_"]',
						);
						if (knowledge) {
							knowledge
								.closest(".form-field")
								?.classList.add("ticketmailer-note-knowledge");
							knowledge.classList.add("ticketmailer-knowledge-search");
							if (!knowledge.dataset.ticketmailerLabelled) {
								knowledge.dataset.ticketmailerLabelled = "true";
								knowledge.querySelector("i")?.classList.add("me-1");
								knowledge.insertAdjacentText(
									"beforeend",
									delivery.dataset.knowledgeLabel,
								);
							}
							if (!knowledge.dataset.ticketmailerBound) {
								knowledge.dataset.ticketmailerBound = "true";
								knowledge.addEventListener(
									"click",
									() => {
										var parentModal = delivery.closest(".modal");
										if (!parentModal) {
											return;
										}
										var labelKnowledgeButtons = () => {
											var knowledgeModal = document.getElementById(
												"modal_search_knowbaseitem",
											);
											if (!knowledgeModal) {
												return;
											}
											knowledgeModal
												.querySelectorAll(
													".list-group-item .use-knowbaseitem, .list-group-item .view-knowbaseitem",
												)
												.forEach((button) => {
													if (!button.dataset.ticketmailerLabelled) {
														button.dataset.ticketmailerLabelled = "true";
														button.classList.remove(
															"btn-icon",
															"btn-sm",
															"btn-ghost-secondary",
														);
														button.classList.add(
															button.classList.contains("use-knowbaseitem")
																? "btn-primary"
																: "btn-outline-secondary",
														);
														var label = document.createElement("span");
														label.textContent = button.title;
														button.append(label);
													}
												});
										};
										var labelTimer = window.setInterval(
											labelKnowledgeButtons,
											200,
										);
										window.setTimeout(() => {
											window.clearInterval(labelTimer);
										}, 2500);
										var modalBindAttempts = 0;
										var bindCurrentKnowledgeModal = () => {
											document.body.classList.add("modal-open");
											var knowledgeModal = document.getElementById(
												"modal_search_knowbaseitem",
											);
											if (!knowledgeModal) {
												modalBindAttempts += 1;
												if (modalBindAttempts < 25) {
													window.setTimeout(bindCurrentKnowledgeModal, 100);
												}
												return;
											}
											labelKnowledgeButtons();
											bindKnowledgeModal(
												knowledgeModal,
												notePanel,
												parentModal,
											);
										};
										bindCurrentKnowledgeModal();
									},
									true,
								);
							}
						}
					}
				}
				inputs.forEach((input) => {
					input.addEventListener("change", selectMode);
				});
				selectMode();
			});
	}
	$(document).on(
		"change",
		'.ticketmailer-compose [name="itilfollowuptemplates_id"], ' +
			'.ticketmailer-compose [name="solutiontemplates_id"]',
		function () {
			applyTemplate(
				this.form,
				this.value,
				this.name === "solutiontemplates_id" ? "solution" : "answer",
			);
		},
	);
	document.addEventListener("DOMContentLoaded", initForms);
	$(document).ajaxComplete(initForms);
})();
