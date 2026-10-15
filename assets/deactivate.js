/* OH6KW Jäsenrekisteri: kysytään käytöstä poistettaessa, säilytetäänkö tiedot lisäosan poiston jälkeen. */
(function ($) {
    'use strict';
    $(function () {
        var $link = $('tr[data-plugin="' + JASREKD.plugin + '"] .deactivate a');
        if (!$link.length) return;
        $link.on('click', function (e) {
            e.preventDefault();
            var href = this.href;
            var $m = $(
                '<div class="jasrekd-overlay" role="dialog" aria-modal="true" aria-labelledby="jasrekd-title"><div class="jasrekd-box">' +
                '<h2 id="jasrekd-title">Jäsenrekisterin tiedot</h2>' +
                '<p>Lisäosa poistetaan nyt käytöstä. Tiedot säilyvät käytöstä poistettaessa joka tapauksessa. Mitä tiedoille tehdään, jos lisäosa myöhemmin <strong>poistetaan</strong> kokonaan?</p>' +
                '<label><input type="radio" name="jrd" value="0"> <strong>Säilytä tiedot</strong> – jäsenet, laskut, maksut ja asetukset jäävät tietokantaan.</label>' +
                '<label><input type="radio" name="jrd" value="1"> <strong>Poista kaikki tiedot</strong> – kaikki lisäosan luomat taulut ja asetukset poistetaan pysyvästi.</label>' +
                '<p class="jasrekd-warn" hidden>⚠ Tietoja ei voi palauttaa. <a href="' + JASREKD.backup + '"><strong>Lataa ensin varmuuskopio</strong></a> (kaikki jäsenet, laskut, maksut ja asetukset – palautettavissa myöhemmin).' +
                '<br><small>Jäsenluettelon Exceliin saat <a href="' + JASREKD.csv + '">CSV-tiedostona</a>.</small></p>' +
                '<p class="jasrekd-actions"><button type="button" class="button jasrekd-cancel">Peruuta</button> ' +
                '<button type="button" class="button button-primary jasrekd-ok">Poista käytöstä</button></p>' +
                '</div></div>');
            $m.find('input[value="' + JASREKD.current + '"]').prop('checked', true);
            $m.find('.jasrekd-warn').prop('hidden', JASREKD.current !== '1');
            $m.on('change', 'input', function () { $m.find('.jasrekd-warn').prop('hidden', this.value !== '1'); });
            $m.on('click', '.jasrekd-cancel', function () { $m.remove(); });
            $m.on('click', function (ev) { if (ev.target === this) $m.remove(); });
            $m.on('click', '.jasrekd-ok', function () {
                var val = $m.find('input:checked').val() || '0';
                $(this).prop('disabled', true).text('Tallennetaan…');
                $.post(ajaxurl, { action: 'jasrek_set_uninstall', nonce: JASREKD.nonce, poista: val })
                    .always(function () { window.location.href = href; });
            });
            $('body').append($m);
            $m.find('input:checked').trigger('focus');
        });
    });
})(jQuery);
