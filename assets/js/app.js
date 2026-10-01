/* ==========================================================================
   Computer Laboratory Booking System - front-end behaviour
   Plain ES5-compatible JavaScript (no build step, works in any modern browser).
   ========================================================================== */

(function () {
    'use strict';

    /**
     * Close the mobile sidebar automatically after a link is tapped, so the
     * content the user asked for is actually visible.
     */
    function initSidebar() {
        var sidebar = document.getElementById('appSidebar');
        if (!sidebar) { return; }

        var links = sidebar.querySelectorAll('a.sidebar-link');
        Array.prototype.forEach.call(links, function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth < 992) {
                    var instance = window.bootstrap ? window.bootstrap.Offcanvas.getInstance(sidebar) : null;
                    if (instance) { instance.hide(); }
                }
            });
        });
    }

    /**
     * Show the password a visitor typed, for the "show password" toggle.
     */
    function initPasswordToggles() {
        var toggles = document.querySelectorAll('[data-toggle-password]');
        Array.prototype.forEach.call(toggles, function (toggle) {
            toggle.addEventListener('click', function () {
                var target = document.getElementById(toggle.getAttribute('data-toggle-password'));
                if (!target) { return; }
                var isHidden = target.type === 'password';
                target.type = isHidden ? 'text' : 'password';
                toggle.innerHTML = isHidden
                    ? '<i class="bi bi-eye-slash"></i>'
                    : '<i class="bi bi-eye"></i>';
            });
        });
    }

    /**
     * Mark the current page in any tab-style nav.
     */
    function initActiveTabs() {
        var tabs = document.querySelectorAll('[data-auto-tab]');
        Array.prototype.forEach.call(tabs, function (tab) {
            var target = document.querySelector(tab.getAttribute('href'));
            if (!target) { return; }
            tab.classList.toggle('active', target.classList.contains('active'));
        });
    }

    /**
     * Ask for confirmation before a destructive action. Any element with
     * data-confirm gets the browser confirm() dialog when clicked.
     */
    function initConfirmations() {
        var items = document.querySelectorAll('[data-confirm]');
        Array.prototype.forEach.call(items, function (item) {
            item.addEventListener('click', function (event) {
                if (!window.confirm(item.getAttribute('data-confirm'))) {
                    event.preventDefault();
                }
            });
        });
    }

    /**
     * Live date validation for the booking form: warn when the chosen date is
     * in the past or beyond the booking window defined in the schema.
     */
    function initDateGuard() {
        var input = document.querySelector('[data-date-guard]');
        if (!input) { return; }

        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var min = today.toISOString().slice(0, 10);
        input.setAttribute('min', min);

        var maxDate = new Date(today);
        maxDate.setDate(maxDate.getDate() + 30);
        input.setAttribute('max', maxDate.toISOString().slice(0, 10));

        input.addEventListener('change', function () {
            var chosen = new Date(input.value + 'T00:00:00');
            if (isNaN(chosen.getTime())) { return; }

            var hint = document.getElementById('dateHint');
            if (!hint) { return; }

            if (chosen < today) {
                hint.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>That date is in the past.';
                hint.className = 'form-text text-danger';
            } else if (chosen > maxDate) {
                hint.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Bookings open 30 days ahead.';
                hint.className = 'form-text text-danger';
            } else {
                hint.innerHTML = '<i class="bi bi-check-circle me-1"></i>Date is bookable.';
                hint.className = 'form-text text-success';
            }
        });
    }

    /**
     * Set today's date as the default for any input carrying data-today.
     */
    function initTodayDefaults() {
        var inputs = document.querySelectorAll('[data-today]');
        Array.prototype.forEach.call(inputs, function (input) {
            if (input.value === '') {
                input.value = new Date().toISOString().slice(0, 10);
            }
        });
    }

    /**
     * Ask for a reason before a decision that needs one is submitted.
     *
     * A button carrying data-require-reason asks for a reason when the note
     * field beside it is empty. The answer is written into that field and the
     * click is then left to proceed normally, which is deliberate: only a real
     * submit carries the pressed button's own name and value, and that value is
     * the decision the server acts on. Calling form.submit() instead would drop
     * it, and the request would arrive with no decision at all.
     *
     * The server checks the reason as well, so this is a convenience rather than
     * the guarantee. Cancelling the prompt stops the submission.
     */
    function initReasonPrompts() {
        var buttons = document.querySelectorAll('[data-require-reason]');

        Array.prototype.forEach.call(buttons, function (button) {
            button.addEventListener('click', function (event) {
                var form = button.form;

                if (!form) { return; }

                var note = form.querySelector('[name="note"]');

                /* A reason is already typed: nothing to ask, submit as usual. */
                if (note && note.value.trim() !== '') { return; }

                var question = button.getAttribute('data-require-reason') || 'Please give a reason.';
                var answer = window.prompt(question, '');

                if (answer === null) {
                    event.preventDefault();
                    return;
                }

                answer = answer.trim();

                if (answer === '') {
                    event.preventDefault();
                    window.alert('A reason is required, and it is shown to the student.');
                    return;
                }

                if (note) { note.value = answer; }

                /* No preventDefault here on purpose: the click continues, so the
                   decision on this button is submitted with the new reason. */
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSidebar();
        initPasswordToggles();
        initActiveTabs();
        initConfirmations();
        initDateGuard();
        initTodayDefaults();
        initReasonPrompts();
    });
}());
