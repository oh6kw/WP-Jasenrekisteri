/* Jäsenrekisteri: kysytään käytöstä poistettaessa, säilytetäänkö tiedot lisäosan poiston jälkeen. */
(function ($) {
    'use strict';
    $(function () {
        var $link = $('tr[data-plugin="' + JRD.plugin + '"] .deactivate a');
        if (!$link.length) return;
        $link.on('click', function (e) {
            e.preventDefault();
            var href = this.href;
            var $m = $(
                '<div class="jrd-overlay" role="dialog" aria-modal="true" aria-labelledby="jrd-title"><div class="jrd-box">' +
                '<h2 id="jrd-title">Jäsenrekisterin tiedot</h2>' +
                '<p>Lisäosa poistetaan nyt käytöstä. Tiedot säilyvät käytöstä poistettaessa joka tapauksessa. Mitä tiedoille tehdään, jos lisäosa myöhemmin <strong>poistetaan</strong> kokonaan?</p>' +
                '<label><input type="radio" name="jrd" value="0"> <strong>Säilytä tiedot</strong> – jäsenet, laskut, maksut ja asetukset jäävät tietokantaan.</label>' +
                '<label><input type="radio" name="jrd" value="1"> <strong>Poista kaikki tiedot</strong> – kaikki lisäosan luomat taulut ja asetukset poistetaan pysyvästi.</label>' +
                '<p class="jrd-warn" hidden>⚠ Tietoja ei voi palauttaa. Ota varmuuskopio: <a href="' + JRD.export + '">vie jäsenet CSV-tiedostoon</a>.</p>' +
                '<p class="jrd-actions"><button type="button" class="button jrd-cancel">Peruuta</button> ' +
                '<button type="button" class="button button-primary jrd-ok">Poista käytöstä</button></p>' +
                '</div></div>');
            $m.find('input[value="' + JRD.current + '"]').prop('checked', true);
            $m.find('.jrd-warn').prop('hidden', JRD.current !== '1');
            $m.on('change', 'input', function () { $m.find('.jrd-warn').prop('hidden', this.value !== '1'); });
            $m.on('click', '.jrd-cancel', function () { $m.remove(); });
            $m.on('click', function (ev) { if (ev.target === this) $m.remove(); });
            $m.on('click', '.jrd-ok', function () {
                var val = $m.find('input:checked').val() || '0';
                $(this).prop('disabled', true).text('Tallennetaan…');
                $.post(ajaxurl, { action: 'jr_set_uninstall', nonce: JRD.nonce, poista: val })
                    .always(function () { window.location.href = href; });
            });
            $('body').append($m);
            $m.find('input:checked').trigger('focus');
        });
    });
})(jQuery);
