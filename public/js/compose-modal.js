(() => {
    const states = new Map();
    const selector = '[data-ticketmailer-compose-modal]';

    function prepare(modal) {
        let state = states.get(modal);
        if (state) return state;
        const owner = document.createComment('ticketmailer compose owner');
        modal.before(owner);
        document.body.append(modal);
        state = { pending: null, ready: false, owner };
        states.set(modal, state);
        state.load = () => {
            if (state.ready) return Promise.resolve();
            if (state.pending) return state.pending;
            state.pending = new Promise((resolve, reject) => {
                $.ajax({
                    url: modal.dataset.composeUrl,
                    data: { _in_modal: 1 },
                    dataType: 'html',
                }).done((html) => {
                    if (!modal.isConnected || !state.owner.isConnected) {
                        reject(new Error('Detached compose modal')); return;
                    }
                    try {
                        // Execute GLPI's native editor and dropdown setup scripts.
                        $(modal.querySelector('.modal-body')).html(html);
                        if (!modal.querySelector('.ticketmailer-compose')) {
                            throw new Error('Compose form unavailable');
                        }
                        state.ready = true;
                        resolve();
                    } catch (error) { reject(error); }
                }).fail(reject);
            }).catch((error) => {
                state.pending = null;
                throw error;
            });
            return state.pending;
        };
        return state;
    }

    document.addEventListener('click', (event) => {
        const button = event.target.closest?.('[data-ticketmailer-compose-target]');
        if (!button) return;
        const modal = document.getElementById(button.dataset.ticketmailerComposeTarget);
        if (!modal) return;
        event.preventDefault();
        bootstrap.Modal.getOrCreateInstance(modal).show(button);
        prepare(modal).load().catch(() => {
            // Retain the ACL-protected full-page fallback if loading fails.
            if (modal.isConnected) window.location.assign(modal.dataset.composeUrl);
        });
    });

    function schedule() {
        states.forEach((state, modal) => {
            if (state.owner.isConnected) return;
            modal.querySelectorAll('textarea').forEach((element) => {
                const editor = window.tinymce?.get(element.id);
                if (editor?.getElement() === element) editor.remove();
            });
            const instance = bootstrap.Modal.getInstance(modal);
            instance?.hide();
            instance?.dispose();
            modal.remove();
            states.delete(modal);
        });
        if (document.readyState !== 'complete') return;
        document.querySelectorAll(selector).forEach((modal) => {
            const state = prepare(modal);
            if (state.scheduled) return;
            state.scheduled = true;
            const load = () => {
                if (modal.isConnected) state.load().catch(() => {});
            };
            if (typeof window.requestIdleCallback === 'function') {
                window.requestIdleCallback(load, { timeout: 2000 });
            } else {
                window.setTimeout(load, 250);
            }
        });
    }
    if (document.readyState === 'complete') schedule();
    else window.addEventListener('load', schedule, { once: true });
    $(document).ajaxComplete(schedule);
})();
