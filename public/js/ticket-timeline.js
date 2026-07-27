(() => {
    let autoOpened = false;
    let openObserver;
    let classObserver;

    const COMPOSE_SELECTOR = '.ticketmailer-compose';
    const NATIVE_FORM_SELECTOR = '#new-itilobject-form > .collapse';

    const isFormVisible = (el) => {
        if (!el) {
            return false;
        }
        const collapse = el.closest('.collapse');
        if (collapse) {
            return collapse.classList.contains('show')
                || collapse.classList.contains('in')
                || collapse.classList.contains('collapsing');
        }
        return el.offsetHeight > 0
            && getComputedStyle(el).display !== 'none'
            && getComputedStyle(el).visibility !== 'hidden';
    };


    const syncComposeActions = () => {
        const forms = document.querySelectorAll(COMPOSE_SELECTOR);
        const composeActive = Array.prototype.some.call(forms, isFormVisible);
        const nativeActive = Array.prototype.some.call(
            document.querySelectorAll(NATIVE_FORM_SELECTOR),
            (collapse) => !collapse.querySelector?.(COMPOSE_SELECTOR)
                && (collapse.classList.contains('show')
                    || collapse.classList.contains('in')
                    || collapse.classList.contains('collapsing')),
        );
        document.body?.classList.toggle('ticketmailer-compose-active', composeActive);
        document.querySelectorAll('.ticketmailer-timeline-action').forEach((action) => {
            action.hidden = nativeActive;
        });
        // GLPI hides both action groups whenever any timeline form opens.
        // For ticketmailer, restore them and suppress only the reply controls.
        if (typeof $ === 'function') {
            const mainActions = $('#itil-footer .main-actions, #right-actions');
            const answerActions = $('#itil-footer .answer-action, #itil-footer .dropdown-toggle-split');
            if (typeof answerActions.toggle === 'function') {
                answerActions.toggle(!composeActive);
            }
            if (composeActive && typeof mainActions.show === 'function') {
                mainActions.show();
            }
        }
    };

    const scheduleSyncBurst = () => {
        syncComposeActions();
        if (typeof requestAnimationFrame === 'function') {
            requestAnimationFrame(syncComposeActions);
        }
        [50, 150, 350, 600].forEach((delay) => {
            setTimeout(syncComposeActions, delay);
        });
    };

    const openReply = () => {
        if (autoOpened) {
            return;
        }

        const reply = document.querySelector('.ticketmailer-timeline-action[data-ticketmailer-auto-open="1"]');
        if (!reply || !reply.getAttribute('onclick')
            || Function('return ' + reply.dataset.ticketmailerModalReady)() !== 1) {
            return;
        }

        reply.click();
        autoOpened = true;
        openObserver?.disconnect();
    };

    if (typeof MutationObserver !== 'undefined' && document.documentElement) {
        openObserver = new MutationObserver(openReply);
        openObserver.observe(document.documentElement, {childList: true, subtree: true});
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            openReply();
            syncComposeActions();
        }, {once: true});
    } else {
        openReply();
        syncComposeActions();
    }
    const onCollapseHidden = () => {
        syncComposeActions();
    };
    document.addEventListener('show.bs.collapse', syncComposeActions);
    document.addEventListener('shown.bs.collapse', syncComposeActions);
    document.addEventListener('hide.bs.collapse', syncComposeActions);
    document.addEventListener('hidden.bs.collapse', onCollapseHidden);
    if (typeof $ === 'function') {
        $(document)
            .on('show.bs.collapse', syncComposeActions)
            .on('shown.bs.collapse', syncComposeActions)
            .on('hide.bs.collapse', syncComposeActions)
            .on('hidden.bs.collapse', onCollapseHidden)
            .ajaxComplete(() => {
                openReply();
                syncComposeActions();
            });
    }
    if (typeof MutationObserver !== 'undefined' && document.body) {
        classObserver = new MutationObserver(scheduleSyncBurst);
        classObserver.observe(document.body, {
            subtree: true,
            attributes: true,
            attributeFilter: ['class'],
        });
    }
})();
