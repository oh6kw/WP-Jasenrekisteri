/* OH6KW Jäsenrekisteri – tulostettavien sivujen (paperilaskut, äänioikeusluettelo) painikkeet */
(function () {
    'use strict';
    document.addEventListener('click', function (e) {
        var el = e.target.closest ? e.target.closest('[data-jasrek-print], [data-jasrek-confirm]') : null;
        if (!el) return;
        if (el.hasAttribute('data-jasrek-print')) {
            window.print();
        } else if (!window.confirm(el.getAttribute('data-jasrek-confirm'))) {
            e.preventDefault();
        }
    });
    window.addEventListener('load', function () {
        if (document.body.getAttribute('data-jasrek-autoprint') === '1') window.print();
    });
})();
