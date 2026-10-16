/* Azure Bay Hotel — small progressive enhancements. */
(function () {
    'use strict';

    // Auto-dismiss flash alerts after a while.
    document.querySelectorAll('[data-auto-dismiss]').forEach(function (el) {
        window.setTimeout(function () {
            el.classList.remove('show');
            el.classList.add('fade');
            window.setTimeout(function () { el.remove(); }, 400);
        }, 6000);
    });

    // Enable Bootstrap tooltips.
    if (window.bootstrap && window.bootstrap.Tooltip) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            new window.bootstrap.Tooltip(el);
        });
    }

    // Confirm destructive actions.
    document.querySelectorAll('[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    // Keep the booking check-out date after the check-in date.
    document.querySelectorAll('[data-date-pair]').forEach(function (pair) {
        var checkIn = pair.querySelector('[name="check_in"]') || pair.querySelector('[name="check_in_date"]');
        var checkOut = pair.querySelector('[name="check_out"]') || pair.querySelector('[name="check_out_date"]');
        if (!checkIn || !checkOut) { return; }

        function sync() {
            if (checkIn.value) {
                checkOut.min = checkIn.value;
                if (checkOut.value && checkOut.value <= checkIn.value) {
                    checkOut.value = '';
                }
            }
        }
        checkIn.addEventListener('change', sync);
        sync();
    });

    // Print button.
    document.querySelectorAll('[data-print]').forEach(function (btn) {
        btn.addEventListener('click', function () { window.print(); });
    });
})();
