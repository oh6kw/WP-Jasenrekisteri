/* OH6KW Jäsenrekisteri – tulostettavien sivujen (paperilaskut, äänioikeusluettelo, tietosuojaseloste) painikkeet */
(function () {
    'use strict';
    document.addEventListener('click', function (e) {
        var el = e.target.closest ? e.target.closest('[data-jasrek-print], [data-jasrek-confirm], [data-jasrek-copy]') : null;
        if (!el) return;
        if (el.hasAttribute('data-jasrek-copy')) {
            var src = document.querySelector(el.getAttribute('data-jasrek-copy'));
            var label = el.textContent;
            var ok = function () { el.textContent = '✓ Kopioitu'; setTimeout(function () { el.textContent = label; }, 2000); };
            if (src && navigator.clipboard) navigator.clipboard.writeText(src.innerText).then(ok);
            return;
        }
        if (el.hasAttribute('data-jasrek-print')) {
            window.print();
        } else if (!window.confirm(el.getAttribute('data-jasrek-confirm'))) {
            e.preventDefault();
        }
    });
    window.addEventListener('load', function () {
        // Tilisiirtomalli: jos saate ei mahdu lomakkeen yläpuolelle, tekstiä pienennetään (enintään 8,5 pt:iin)
        // ja jos se ei vieläkään mahdu, yläpalkissa näytetään varoitus.
        var yli = [];
        Array.prototype.forEach.call(document.querySelectorAll('.ts-keski'), function (k) {
            var saate = k.querySelector('.saate'), pt = 10.5;
            while (k.scrollHeight > k.clientHeight + 1 && saate && pt > 8.5) {
                pt -= 0.5;
                saate.style.fontSize = pt + 'pt';
                saate.style.lineHeight = '1.35';
            }
            if (k.scrollHeight > k.clientHeight + 1) {
                k.parentNode.classList.add('ts-ylivuoto');
                var n = k.querySelector('.vastaanottaja strong');
                yli.push(n ? n.textContent : '?');
            }
        });
        var w = document.getElementById('jasrek-ylivuoto');
        if (w && yli.length) { w.querySelector('span').textContent = yli.join(', '); w.hidden = false; }
        if (document.body.getAttribute('data-jasrek-autoprint') === '1') window.print();
    });
})();
