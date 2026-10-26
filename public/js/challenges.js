(function () {
    const backdrop = document.getElementById('challenge-modal');
    if (!backdrop) {
        return;
    }

    const dialog = document.getElementById('challenge-modal-dialog');
    const listUrl = backdrop.dataset.listUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    let lastFocus = null;

    function focusables() {
        return Array.from(dialog.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), summary'))
            .filter((el) => el.offsetParent !== null);
    }

    function show() {
        backdrop.hidden = false;
        document.body.classList.add('modal-open');
        dialog.scrollTop = 0;
        const input = dialog.querySelector('.modal-flag-input');
        (input || dialog).focus();
    }

    function hide() {
        backdrop.hidden = true;
        dialog.innerHTML = '';
        document.body.classList.remove('modal-open');
        if (lastFocus && document.contains(lastFocus)) {
            lastFocus.focus();
        }
    }

    async function openChallenge(url, push) {
        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                window.location.href = url;
                return;
            }
            dialog.innerHTML = await response.text();
            show();
            if (push) {
                history.pushState({ challengeUrl: url }, '', url);
            }
        } catch (error) {
            window.location.href = url;
        }
    }

    function closeChallenge(push) {
        hide();
        if (push && window.location.pathname !== new URL(listUrl, window.location.origin).pathname) {
            history.pushState({}, '', listUrl);
        }
    }

    function markCardSolved(id) {
        const card = document.querySelector('.challenge-card[data-challenge-id="' + id + '"]');
        if (!card) {
            return;
        }
        card.classList.add('is-solved');
        const badge = card.querySelector('.challenge-open-badge');
        if (badge) {
            badge.className = 'challenge-solved-badge';
            badge.textContent = 'Solved';
        }
    }

    function showMessage(container, type, text) {
        container.innerHTML = '';
        const note = document.createElement('div');
        note.className = type === 'success' ? 'modal-success-message' : 'modal-error-message';
        note.textContent = text;
        container.appendChild(note);
    }

    function markSolved(modal, section) {
        const form = section.querySelector('[data-modal-flag-form]');
        if (form) {
            form.outerHTML = '<div class="modal-solved-state"><div class="modal-solved-icon">&check;</div>'
                + '<div class="modal-solved-text"><strong>Challenge solved</strong><p>Points are only awarded once.</p></div></div>';
        }
        const status = modal.querySelector('[data-modal-status]');
        if (status) {
            status.textContent = 'Solved';
        }
        markCardSolved(modal.dataset.challengeId);
    }

    document.querySelectorAll('.challenge-card').forEach((card) => {
        const link = card.querySelector('.challenge-title a');
        if (!link) {
            return;
        }
        card.tabIndex = 0;
        card.setAttribute('role', 'button');

        card.addEventListener('click', (event) => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.button === 1) {
                return;
            }
            event.preventDefault();
            lastFocus = card;
            openChallenge(link.href, true);
        });

        card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                lastFocus = card;
                openChallenge(link.href, true);
            }
        });
    });

    backdrop.addEventListener('click', (event) => {
        if (event.target === backdrop || event.target.closest('[data-modal-close]')) {
            closeChallenge(true);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (backdrop.hidden) {
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            closeChallenge(true);
            return;
        }
        if (event.key === 'Tab') {
            const items = focusables();
            if (items.length === 0) {
                event.preventDefault();
                return;
            }
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && (document.activeElement === first || document.activeElement === dialog)) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    dialog.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-modal-flag-form]');
        if (!form) {
            return;
        }
        event.preventDefault();

        const modal = dialog.querySelector('.challenge-detail-modal');
        const section = form.closest('[data-modal-submission]');
        const message = section.querySelector('[data-modal-message]');
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                credentials: 'same-origin',
                body: new FormData(form),
            });
            const data = await response.json().catch(() => ({}));

            if (response.status === 422 && data.errors && data.errors.flag) {
                showMessage(message, 'error', data.errors.flag[0]);
            } else if (!response.ok) {
                showMessage(message, 'error', data.message || 'Something went wrong. Please try again.');
            } else if (data.status === 'solved' || data.status === 'already_solved') {
                showMessage(message, 'success', data.message);
                markSolved(modal, section);
            } else {
                showMessage(message, 'error', data.message);
                form.querySelector('input[name="flag"]').select();
            }
        } catch (error) {
            showMessage(message, 'error', 'Network error. Please try again.');
        } finally {
            button.disabled = false;
        }
    });

    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.challengeUrl) {
            openChallenge(event.state.challengeUrl, false);
        } else if (!backdrop.hidden) {
            hide();
        }
    });

    if (!backdrop.hidden) {
        history.replaceState({ challengeUrl: window.location.href }, '', window.location.href);
        show();
    }
})();
