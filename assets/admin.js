/* Jäsenrekisteri – hallinnan skriptit */
(function ($) {
    'use strict';

    // Varmistuskysymys linkeille, painikkeille ja lomakkeille
    $(document).on('click', '[data-confirm]', function (e) {
        if ($(this).is('.jr-run')) return; // ajoon oma käsittely
        if (!window.confirm($(this).data('confirm'))) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }
    });

    // Haku + tilasuodatin taulukoille ja listoille
    var norm = function (s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ''); };
    var state = {};
    function applyFilter(target) {
        var st = state[target] || {};
        var q = norm(st.q), status = st.status || '';
        // Tiliotteella viite voi olla etunollilla täytetty (esim. 00000000000000100010)
        if (/^\d+$/.test(q)) q = q.replace(/^0+/, '');
        $(target).find('[data-search]').each(function () {
            var $el = $(this);
            var okQ = !q || String($el.data('search')).indexOf(q) !== -1;
            var okS = !status || (' ' + ($el.data('status') || '') + ' ').indexOf(' ' + status + ' ') !== -1;
            $el.toggle(okQ && okS);
        });
    }
    $(document).on('input', '[data-jr-filter]', function () {
        var target = $(this).data('jr-filter');
        state[target] = $.extend(state[target] || {}, { q: $(this).val() });
        applyFilter(target);
    });
    $(document).on('click', '[data-jr-status-for] a', function (e) {
        e.preventDefault();
        var $ul = $(this).closest('[data-jr-status-for]'), target = $ul.data('jr-status-for');
        $ul.find('a').removeClass('current');
        $(this).addClass('current');
        state[target] = $.extend(state[target] || {}, { status: $(this).data('status') });
        applyFilter(target);
    });

    // Valitse kaikki (vain näkyvät rivit)
    $(document).on('change', '.jr-check-all', function () {
        var checked = this.checked;
        $($(this).data('target')).find('input[type=checkbox]').not(this).each(function () {
            var $row = $(this).closest('tr, label');
            if ($row.is(':visible')) this.checked = checked;
        });
    });

    // Vastaanottajavalinta uudessa laskussa
    $(document).on('change', 'input[name=vastaanottajat]', function () {
        $('#jr-member-pick').prop('hidden', $('input[name=vastaanottajat]:checked').val() !== 'valitut');
    });

    // Jäsenlajien lisäys ja poisto asetuksissa
    $(document).on('click', '#jr-laji-add', function () {
        var tpl = document.getElementById('jr-laji-tpl');
        var $row = $(tpl.innerHTML);
        $('#jr-lajit tbody').append($row);
        $row.find('input[type=text]').first().trigger('focus');
    });
    $(document).on('click', '.jr-laji-del', function () {
        var $tr = $(this).closest('tr');
        var nimi = $tr.find('input[name="laji_nimi[]"]').val();
        if (!nimi || window.confirm('Poistetaanko jäsenluokka "' + nimi + '"? Muutos tallentuu, kun tallennat asetukset.')) $tr.remove();
    });

    // Jäsenlomake: laskulle lisättävän summan päivitys jäsenluokan mukaan (ellei summaa ole muutettu käsin)
    $(document).on('input', '.jr-laskulle-rivi input[type=text]', function () { $(this).data('touched', true); });
    $(document).on('change', '#jr-laji', function () {
        var laji = $(this).val();
        $('.jr-laskulle-rivi').each(function () {
            var map = $(this).data('hinnat') || {};
            var $in = $(this).find('input[type=text]');
            if ($in.data('touched')) return;
            var v = Object.prototype.hasOwnProperty.call(map, laji) ? map[laji] : map[''];
            $in.val(Number(v || 0).toFixed(2).replace('.', ','));
        });
    });

    // Tunnukset isoilla kirjaimilla
    $(document).on('input', '.jr-upper', function () {
        var p = this.selectionStart;
        this.value = this.value.toUpperCase();
        try { this.setSelectionRange(p, p); } catch (err) {}
    });

    // Erälähetys AJAXilla edistymispalkin kanssa
    function run($btn) {
        if ($btn.data('running')) return;
        $btn.data('running', true);
        var $panel = $btn.closest('.jr-panel');
        var $prog = $panel.find('.jr-progress').prop('hidden', false);
        var $bar = $prog.find('.jr-progress-bar span'), $txt = $prog.find('.jr-progress-text');
        var params = $.extend({}, $btn.data('params'));
        var tot = { sent: 0, failed: 0, errors: [] };
        $panel.find('.jr-run, .button').prop('disabled', true).addClass('disabled');
        $(window).on('beforeunload.jr', function () { return 'Lähetys on kesken.'; });

        function finish(msg, isError) {
            $(window).off('beforeunload.jr');
            $txt.html('');
            $txt.append($('<strong>').text(msg));
            if (tot.errors.length) {
                var $ul = $('<ul class="jr-errors">');
                tot.errors.forEach(function (er) { $ul.append($('<li>').text(er)); });
                $txt.append($ul);
            }
            $prog.toggleClass('is-error', !!isError || tot.failed > 0);
            var $again = $('<a class="button button-primary">').text('Päivitä näkymä').attr('href', $btn.data('done-url') || window.location.href.replace(/[?&]jr_msg=[^&]*/, ''));
            $txt.append($('<p>').append($again));
        }

        function step() {
            $.post(JR.ajax, $.extend({ action: $btn.data('action') }, params)).done(function (res) {
                if (!res || !res.success) { finish('Virhe: ' + (res && res.data ? res.data : 'tuntematon virhe'), true); return; }
                var d = res.data;
                tot.sent += d.sent; tot.failed += d.failed;
                tot.errors = tot.errors.concat(d.errors || []);
                if (typeof d.offset !== 'undefined') params.offset = d.offset;
                var done = tot.sent + tot.failed, all = done + d.remaining;
                var pct = all ? Math.round(done / all * 100) : 100;
                $bar.css('width', pct + '%');
                $txt.text('Lähetetty ' + tot.sent + ' / ' + all + (tot.failed ? ', epäonnistui ' + tot.failed : '') + '…');
                if (d.remaining > 0 && (d.sent + d.failed) > 0) {
                    step();
                } else {
                    $bar.css('width', '100%');
                    finish('Valmis! Lähetetty ' + tot.sent + ' viestiä' + (tot.failed ? ', epäonnistui ' + tot.failed + '.' : '.'));
                }
            }).fail(function (xhr) {
                finish('Yhteysvirhe (' + xhr.status + '). Voit jatkaa lähetystä päivittämällä sivun – jo lähetettyjä ei lähetetä uudelleen.', true);
            });
        }
        $txt.text('Aloitetaan…');
        step();
    }

    $(document).on('click', '.jr-run', function (e) {
        e.preventDefault();
        var $btn = $(this);
        if ($btn.data('confirm') && !window.confirm($btn.data('confirm'))) return;
        run($btn);
    });
    $(function () {
        $('.jr-run[data-autostart]').each(function () { run($(this)); });
    });
})(jQuery);
