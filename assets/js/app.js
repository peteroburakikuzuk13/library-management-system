/* assets/js/app.js - small helpers shared by every page */
(function () {
    'use strict';

    // 1. Delete confirmation. Any <form data-confirm="..."> opens the shared dialog first.
    var modalEl = document.getElementById('confirmModal');
    if (modalEl && window.bootstrap) {
        var modal = new bootstrap.Modal(modalEl);
        var pendingForm = null;

        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!form.matches('form[data-confirm]')) { return; }
            event.preventDefault();
            pendingForm = form;
            document.getElementById('confirmMessage').textContent = form.getAttribute('data-confirm');
            modal.show();
        });

        document.getElementById('confirmYes').addEventListener('click', function () {
            if (!pendingForm) { return; }
            // The server checks this field, so a delete without the prompt is refused.
            var flag = document.createElement('input');
            flag.type = 'hidden'; flag.name = 'confirmed'; flag.value = '1';
            pendingForm.appendChild(flag);
            modal.hide();
            pendingForm.submit();
        });
    }

    // 2. Stop double submits: disable the button after the first click.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (event.defaultPrevented || form.matches('form[data-confirm]')) { return; }
        var btn = form.querySelector('button[type="submit"]');
        if (btn) { setTimeout(function () { btn.disabled = true; }, 0); }
    });
})();

// Coming back with the browser's Back button must not leave a disabled button behind.
window.addEventListener('pageshow', function () {
    document.querySelectorAll('button[type="submit"]:disabled').forEach(function (b) { b.disabled = false; });
});

