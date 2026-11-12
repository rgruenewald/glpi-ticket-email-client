(() => {
    let classObserver;
    let syncPending = false;
    const COMPOSE_SELECTOR = '.ticketmailer-compose';
    const NATIVE_FORM_SELECTOR = '#new-itilobject-form > .collapse';
    const isFormVisible = (el) => {
        if (!el) return false;
        const collapse = el.closest('.collapse');
        if (collapse) {
            return collapse.classList.contains('show')
                || collapse.classList.contains('in')
                || collapse.classList.contains('collapsing');
        }
        const style = getComputedStyle(el);
        return el.offsetHeight > 0 && style.display !== 'none' && style.visibility !== 'hidden';
    };
    const syncComposeActions = () => {
        const composeActive = Array.prototype.some.call(document.querySelectorAll(COMPOSE_SELECTOR), isFormVisible);
        const nativeActive = Array.prototype.some.call(
            document.querySelectorAll(NATIVE_FORM_SELECTOR),
            (collapse) => !collapse.querySelector(COMPOSE_SELECTOR)
                && (collapse.classList.contains('show')
                    || collapse.classList.contains('in')
                    || collapse.classList.contains('collapsing')),
        );
        document.body?.classList.toggle('ticketmailer-compose-active', composeActive);
        document.querySelectorAll('.ticketmailer-timeline-action').forEach((action) => {
            action.hidden = nativeActive;
        });
        // GLPI hides both action groups whenever any timeline form opens.
        if (typeof $ === 'function') {
            const mainActions = $('#itil-footer .main-actions, #right-actions');
            const answerActions = $('#itil-footer .answer-action, #itil-footer .dropdown-toggle-split');
            if (typeof answerActions.toggle === 'function') answerActions.toggle(!composeActive);
            if (composeActive) mainActions.toggle(true);
        }
        // Attribute writes must not feed back into the observer.
        if (classObserver) classObserver.takeRecords();
    };
    // Collapse, Ajax and observer notifications often describe the same update.
    const scheduleSync = () => {
        if (syncPending) return;
        syncPending = true;
        requestAnimationFrame(() => {
            syncPending = false;
            syncComposeActions();
        });
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleSync);
    } else {
        scheduleSync();
    }
    for (const event of ['show.bs.collapse', 'shown.bs.collapse', 'hide.bs.collapse', 'hidden.bs.collapse']) {
        document.addEventListener(event, scheduleSync);
    }
    if (typeof $ === 'function') {
        $(document)
            .on('show.bs.collapse', scheduleSync)
            .on('shown.bs.collapse', scheduleSync)
            .on('hide.bs.collapse', scheduleSync)
            .on('hidden.bs.collapse', scheduleSync)
            .ajaxComplete(scheduleSync);
    }
    if (typeof MutationObserver !== 'undefined' && document.body) {
        classObserver = new MutationObserver(scheduleSync);
        classObserver.observe(document.body, {
            subtree: true,
            attributes: true,
            attributeFilter: ['class'],
        });
    }
})();
