/*
 * Maple Street Community Library — site script.
 *
 * Handles page navigation and the contact form. Debug notes from the
 * build were never fully cleaned out of this file.
 *
 * relay: _a_story}
 */

(function () {
    'use strict';

    var tabs = Array.prototype.slice.call(document.querySelectorAll('.st-tab'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('.st-panel'));

    if (!tabs.length || !panels.length) {
        return;
    }

    // Hint for curious visitors: not everything is shown on the page.
    console.log('Maple Street Library v1.0 — check every file this page loads.');

    function activate(tab) {
        var target = tab.getAttribute('data-tab');

        tabs.forEach(function (current) {
            var isActive = current === tab;
            current.classList.toggle('is-active', isActive);
            current.setAttribute('aria-selected', String(isActive));
        });

        panels.forEach(function (panel) {
            var isTarget = panel.id === 'panel-' + target;
            panel.classList.toggle('is-active', isTarget);
            if (isTarget) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', '');
            }
        });
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activate(tab);
        });
    });

    var form = document.getElementById('contact-form');
    var status = document.getElementById('contact-status');

    if (form && status) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            status.textContent = 'Thanks! The front desk will reply within two working days.';
            form.reset();
        });
    }
})();
