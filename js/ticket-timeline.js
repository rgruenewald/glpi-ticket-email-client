(() => {
    let autoOpened = false;
    let openObserver;
    let classObserver;
    let lastComposeActive = false;

    const COMPOSE_SELECTOR = '.ticketmailer-compose';

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

    let optimisticUntil = 0;

    const syncComposeActions = () => {
        const now = Date.now();
        const forms = document.querySelectorAll(COMPOSE_SELECTOR);
        const composeActive = Array.prototype.some.call(forms, isFormVisible);
        let next = composeActive;
        if (!composeActive && now < optimisticUntil) {
            next = true;
        }
        lastComposeActive = next;
        document.body?.classList.toggle('ticketmailer-compose-active', next);

        // GLPI hides both action groups whenever any timeline form opens.
        // For ticketmailer, restore them and suppress only the reply controls.
        if (typeof $ === 'function') {
            const mainActions = $('#itil-footer .main-actions, #right-actions');
            const answerActions = $('#itil-footer .answer-action, #itil-footer .dropdown-toggle-split');
            if (typeof answerActions.toggle === 'function') {
                answerActions.toggle(!next);
            }
            if (next && typeof mainActions.show === 'function') {
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
        if (!reply) {
            return;
        }

        reply.click();
        autoOpened = true;
        openObserver?.disconnect();
        scheduleSyncBurst();
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
    document.addEventListener('click', (event) => {
        if (event.target?.closest?.('.ticketmailer-timeline-action')) {
            optimisticUntil = Date.now() + 800;
            if (!lastComposeActive) {
                lastComposeActive = true;
                document.body?.classList.add('ticketmailer-compose-active');
            }
            scheduleSyncBurst();
        }
    }, true);
    const onCollapseHidden = () => {
        optimisticUntil = 0;
        syncComposeActions();
    };
    document.addEventListener('shown.bs.collapse', syncComposeActions);
    document.addEventListener('hidden.bs.collapse', onCollapseHidden);
    if (typeof $ === 'function') {
        $(document)
            .on('shown.bs.collapse', syncComposeActions)
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
