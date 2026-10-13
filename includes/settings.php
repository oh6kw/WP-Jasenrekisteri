<?php
if (!defined('ABSPATH')) exit;

add_action('admin_post_jr_save_settings', 'jr_handle_save_settings');
function jr_handle_save_settings() {
    jr_check('jr_save_settings');
    $iban = jr_iban_clean(sanitize_text_field(jr_post('iban')));
    $maksukoodi = jr_post('maksukoodi');

    // Jäsenlajit: rinnakkaiset taulukot id[], nimi[], summa[]
    $lajit = array();
    $ids   = (array) jr_post('laji_id', array());
    $nimet = (array) jr_post('laji_nimi', array());
    $summat= (array) jr_post('laji_summa', array());
    foreach ($nimet as $i => $nimi) {
        $nimi = sanitize_text_field($nimi);
        if ($nimi === '') continue;
        $id = isset($ids[$i]) ? sanitize_key($ids[$i]) : '';
        if ($id === '' || isset($lajit[$id])) $id = 'l' . strtolower(wp_generate_password(6, false));
        $lajit[$id] = array('nimi' => $nimi, 'summa' => jr_parse_money(isset($summat[$i]) ? $summat[$i] : ''));
    }

    $s = array(
        'yhdistys'         => sanitize_text_field(jr_post('yhdistys')),
        'iban'             => $iban,
        'bic'              => strtoupper(sanitize_text_field(jr_post('bic'))),
        'ytunnus'          => sanitize_text_field(jr_post('ytunnus')),
        'maksukoodi'       => in_array($maksukoodi, array('qr', 'viivakoodi', 'molemmat', 'ei'), true) ? $maksukoodi : 'molemmat',
        'lahettaja_nimi'   => sanitize_text_field(jr_post('lahettaja_nimi')),
        'lahettaja_email'  => sanitize_email(jr_post('lahettaja_email')),
        'vastaus_email'    => sanitize_email(jr_post('vastaus_email')),
        'yhteystiedot'     => sanitize_textarea_field(jr_post('yhteystiedot')),
        'tunnus_kaytossa'  => jr_post('tunnus_kaytossa') === '1' ? '1' : '0',
        'tunnus_nimi'      => sanitize_text_field(jr_post('tunnus_nimi')),
        'tunnus_isot'      => jr_post('tunnus_isot') === '1' ? '1' : '0',
        'jasenlajit'       => $lajit,
        'auto_muistutus'   => jr_post('auto_muistutus') === '1' ? '1' : '0',
        'auto_paivia'      => (string) max(1, min(365, intval(jr_post('auto_paivia', 14)))),
        'auto_max'         => (string) max(1, min(10, intval(jr_post('auto_max', 1)))),
        'auto_raportti'    => jr_post('auto_raportti') === '1' ? '1' : '0',
        'poista_tiedot'    => jr_post('poista_tiedot') === '1' ? '1' : '0',
        'viesti_oletus'    => sanitize_textarea_field(jr_post('viesti_oletus')),
        'muistutus_teksti' => sanitize_textarea_field(jr_post('muistutus_teksti')),
    );
    update_option('jr_settings', $s);
    jr_schedule_cron();
    jr_redirect('jr-asetukset', array('jr_msg' => ($iban !== '' && !jr_iban_valid($iban)) ? 'iban_invalid' : 'settings_saved'));
}

function jr_page_settings() {
    global $wpdb;
    $s = jr_settings();
    $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
    $from_host = $s['lahettaja_email'] ? substr(strrchr($s['lahettaja_email'], '@'), 1) : '';
    $laji_counts = $wpdb->get_results('SELECT jasenlaji, COUNT(*) AS n FROM ' . jr_t('jasenet') . ' WHERE ' . jr_sql_jasenet() . ' GROUP BY jasenlaji', OBJECT_K);
    $raportti_to = $s['vastaus_email'] ? $s['vastaus_email'] : ($s['lahettaja_email'] ? $s['lahettaja_email'] : get_option('admin_email'));
    $next = wp_next_scheduled('jr_auto_muistutus');
    ?>
    <div class="wrap jr-wrap">
        <h1>Jäsenrekisterin asetukset</h1>
        <?php jr_notice(); ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="jr-form">
            <input type="hidden" name="action" value="jr_save_settings">
            <?php wp_nonce_field('jr_save_settings'); ?>

            <div class="jr-panel">
                <h2>Yhdistys ja tilitiedot</h2>
                <p class="description">Näkyvät laskuissa.</p>
                <div class="jr-grid">
                    <p class="jr-span-2"><label for="jr-yhdistys">Yhdistyksen nimi (laskun saaja)</label>
                       <input type="text" id="jr-yhdistys" name="yhdistys" value="<?php echo esc_attr($s['yhdistys']); ?>"></p>
                    <p><label for="jr-iban">Tilinumero (IBAN)</label>
                       <input type="text" id="jr-iban" name="iban" value="<?php echo esc_attr(jr_iban_format($s['iban'])); ?>" placeholder="FI00 1234 5600 0007 85" class="jr-upper">
                       <?php if ($s['iban'] !== ''): ?>
                           <?php if (jr_iban_valid($s['iban'])): ?><span class="jr-badge jr-badge-ok">✓ kelvollinen</span>
                           <?php else: ?><span class="jr-badge jr-badge-err">tarkista numero</span><?php endif; ?>
                       <?php endif; ?></p>
                    <p><label for="jr-bic">BIC (valinnainen)</label>
                       <input type="text" id="jr-bic" name="bic" value="<?php echo esc_attr($s['bic']); ?>" class="jr-upper"></p>
                    <p><label for="jr-ytunnus">Y-tunnus (valinnainen)</label>
                       <input type="text" id="jr-ytunnus" name="ytunnus" value="<?php echo esc_attr($s['ytunnus']); ?>"></p>
                </div>
                <p><label for="jr-maksukoodi">Maksukoodi laskussa</label>
                   <select id="jr-maksukoodi" name="maksukoodi">
                       <option value="molemmat" <?php selected($s['maksukoodi'], 'molemmat'); ?>>QR-koodi ja pankkiviivakoodi</option>
                       <option value="qr" <?php selected($s['maksukoodi'], 'qr'); ?>>Vain QR-koodi</option>
                       <option value="viivakoodi" <?php selected($s['maksukoodi'], 'viivakoodi'); ?>>Vain pankkiviivakoodi</option>
                       <option value="ei" <?php selected($s['maksukoodi'], 'ei'); ?>>Ei kuvaa (vain virtuaaliviivakoodi tekstinä)</option>
                   </select>
                   <span class="description">QR-koodi on EPC-standardin mukainen (viite RF-muodossa, jonka pankit kohdistavat samaan viitteeseen). Pankkiviivakoodi on perinteinen suomalainen laskun viivakoodi. Virtuaaliviivakoodi tekstinä on laskussa aina.</span></p>
                <p><label for="jr-yhteystiedot">Yhteystiedot viestien alalaitaan</label>
                   <textarea id="jr-yhteystiedot" name="yhteystiedot" rows="3" placeholder="Esim. Yhdistys ry · rahastonhoitaja@example.fi · www.example.fi"><?php echo esc_textarea($s['yhteystiedot']); ?></textarea></p>
            </div>

            <div class="jr-panel" id="jr-luokat">
                <h2>Jäsenluokat</h2>
                <p class="description">Esim. Jäsen, Nuorisojäsen, Perhejäsen, Kunniajäsen. Jokaiselle luokalle oma jäsenmaksu. <strong>Jäsenmaksu 0 € = luokan jäsenille ei tehdä laskua</strong> (esim. kunniajäsenet), mutta he saavat tiedotteet. Luokat näkyvät jäsenlistassa välilehtinä. Summia voi vielä muuttaa laskua tehdessä.</p>
                <table class="widefat jr-lajit" id="jr-lajit">
                    <thead><tr><th>Jäsenluokka</th><th style="width:140px">Jäsenmaksu (€)</th><th style="width:150px">Jäseniä</th><th style="width:60px"></th></tr></thead>
                    <tbody>
                    <?php foreach ($s['jasenlajit'] as $id => $l): ?>
                        <tr>
                            <td><input type="hidden" name="laji_id[]" value="<?php echo esc_attr($id); ?>">
                                <input type="text" name="laji_nimi[]" value="<?php echo esc_attr($l['nimi']); ?>"></td>
                            <td><input type="text" name="laji_summa[]" inputmode="decimal" value="<?php echo esc_attr(number_format((float) $l['summa'], 2, ',', '')); ?>"></td>
                            <td><?php echo isset($laji_counts[$id]) ? (int) $laji_counts[$id]->n : 0; ?><?php echo (float) $l['summa'] <= 0 ? ' <span class="jr-badge jr-badge-purple">ei maksua</span>' : ''; ?></td>
                            <td><button type="button" class="button-link jr-laji-del" aria-label="Poista">✕</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <template id="jr-laji-tpl">
                    <tr>
                        <td><input type="hidden" name="laji_id[]" value=""><input type="text" name="laji_nimi[]" placeholder="esim. Nuorisojäsen"></td>
                        <td><input type="text" name="laji_summa[]" inputmode="decimal" placeholder="0,00"></td>
                        <td>–</td>
                        <td><button type="button" class="button-link jr-laji-del" aria-label="Poista">✕</button></td>
                    </tr>
                </template>
                <p><button type="button" class="button" id="jr-laji-add">+ Lisää jäsenluokka</button></p>
                <p class="description">Jos poistat luokan, sen jäsenet siirtyvät välilehdelle ”Ilman jäsenluokkaa”, josta heille voi valita uuden luokan joukkona. Luokan voi nimetä uudelleen – jäsenet pysyvät siinä.</p>
            </div>

            <div class="jr-panel">
                <h2>Automaattinen maksumuistutus</h2>
                <p><label class="jr-radio"><input type="checkbox" name="auto_muistutus" value="1" <?php checked($s['auto_muistutus'], '1'); ?>> Lähetä maksamattomille muistutus automaattisesti</label></p>
                <div class="jr-grid">
                    <p><label for="jr-apaiv">Päivää eräpäivän jälkeen</label>
                       <input type="number" id="jr-apaiv" name="auto_paivia" min="1" max="365" value="<?php echo esc_attr($s['auto_paivia']); ?>"></p>
                    <p><label for="jr-amax">Muistutuksia enintään</label>
                       <input type="number" id="jr-amax" name="auto_max" min="1" max="10" value="<?php echo esc_attr($s['auto_max']); ?>">
                       <span class="description">Seuraava muistutus lähtee aina saman päivämäärän kuluttua edellisestä.</span></p>
                </div>
                <p><label class="jr-radio"><input type="checkbox" name="auto_raportti" value="1" <?php checked($s['auto_raportti'], '1'); ?>> Lähetä yhteenveto lähetetyistä muistutuksista osoitteeseen <?php echo esc_html($raportti_to); ?></label></p>
                <p class="description">Muistutuksen saavat vain jäsenet, joille lasku on jo lähetetty ja joilla on sähköpostiosoite. Automaattisen muistutuksen voi kytkeä pois myös yksittäiseltä laskulta. WordPress tarkistaa tilanteen kerran tunnissa, kun sivustolla on kävijöitä.
                <?php if ($s['auto_muistutus'] === '1' && $next): ?><br>Seuraava tarkistus: <?php echo esc_html(wp_date('j.n.Y H:i', $next)); ?><?php endif; ?></p>
            </div>

            <div class="jr-panel">
                <h2>Jäsenten lisätunniste</h2>
                <p class="description">Vapaaehtoinen lisäkenttä jäsenille, esim. jäsennumero, tunnus tai lisenssinumero. Nimen voi vaihtaa vapaasti.</p>
                <p><label class="jr-radio"><input type="checkbox" name="tunnus_kaytossa" value="1" <?php checked($s['tunnus_kaytossa'], '1'); ?>> Lisätunniste käytössä</label></p>
                <div class="jr-grid">
                    <p><label for="jr-tnimi">Kentän nimi</label>
                       <input type="text" id="jr-tnimi" name="tunnus_nimi" value="<?php echo esc_attr($s['tunnus_nimi']); ?>" placeholder="esim. Jäsennumero, Tunnus"></p>
                    <p><label class="jr-radio" style="margin-top:28px"><input type="checkbox" name="tunnus_isot" value="1" <?php checked($s['tunnus_isot'], '1'); ?>> Muunna isoiksi kirjaimiksi</label></p>
                </div>
            </div>

            <div class="jr-panel">
                <h2>Sähköpostin lähettäjä</h2>
                <div class="jr-grid">
                    <p><label for="jr-lnimi">Lähettäjän nimi</label>
                       <input type="text" id="jr-lnimi" name="lahettaja_nimi" value="<?php echo esc_attr($s['lahettaja_nimi']); ?>"></p>
                    <p><label for="jr-lemail">Lähettäjän osoite</label>
                       <input type="email" id="jr-lemail" name="lahettaja_email" value="<?php echo esc_attr($s['lahettaja_email']); ?>"></p>
                    <p><label for="jr-vemail">Vastausosoite (esim. rahastonhoitaja)</label>
                       <input type="email" id="jr-vemail" name="vastaus_email" value="<?php echo esc_attr($s['vastaus_email']); ?>"></p>
                </div>
                <?php if ($from_host && $site_host && stripos($site_host, $from_host) === false && stripos($from_host, $site_host) === false): ?>
                    <p class="jr-hint">⚠ Lähettäjän osoite (@<?php echo esc_html($from_host); ?>) ei ole sivuston verkkotunnuksessa. Laskut voivat päätyä roskapostiin, ellei sivustolla ole SMTP-lisäosaa, joka lähettää kyseisen osoitteen kautta.</p>
                <?php endif; ?>
                <p class="description">Suositus: asenna SMTP-lisäosa (esim. WP Mail SMTP tai FluentSMTP), jolloin viestit lähtevät yhdistyksen oikean sähköpostitilin kautta eivätkä päädy roskapostiin. Kokeile asetukset lähettämällä testilasku itsellesi.</p>
            </div>

            <div class="jr-panel">
                <h2>Oletustekstit</h2>
                <p><label for="jr-vo">Laskun saateteksti (oletus uusille laskuille)</label>
                   <textarea id="jr-vo" name="viesti_oletus" rows="7"><?php echo esc_textarea($s['viesti_oletus']); ?></textarea>
                   <?php echo jr_placeholders('jr-vo', 'lasku'); ?></p>
                <p><label for="jr-mt">Maksumuistutuksen teksti</label>
                   <textarea id="jr-mt" name="muistutus_teksti" rows="6"><?php echo esc_textarea($s['muistutus_teksti']); ?></textarea>
                   <?php echo jr_placeholders('jr-mt', 'lasku'); ?></p>
            </div>

            <div class="jr-panel" id="jr-poisto">
                <h2>Lisäosan poistaminen</h2>
                <p>Kun lisäosa poistetaan WordPressistä (Lisäosat → Poista käytöstä → Poista):</p>
                <label class="jr-radio"><input type="radio" name="poista_tiedot" value="0" <?php checked($s['poista_tiedot'], '0'); ?>> <strong>Säilytä tiedot</strong> – jäsenet, laskut, maksut ja asetukset jäävät tietokantaan, ja lisäosan voi asentaa myöhemmin uudelleen.</label>
                <label class="jr-radio"><input type="radio" name="poista_tiedot" value="1" <?php checked($s['poista_tiedot'], '1'); ?> data-confirm="Haluatko varmasti, että kaikki jäsenrekisterin tiedot poistetaan pysyvästi, kun lisäosa poistetaan? Tätä ei voi perua."> <strong>Poista kaikki tiedot</strong> – kaikki lisäosan luomat taulut ja asetukset poistetaan pysyvästi.</label>
                <p class="description">Ota ennen poistamista varmuuskopio: <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=jr_export_members'), 'jr_export_members')); ?>">vie jäsenet CSV-tiedostoon</a> ja laskujen CSV:t laskujen sivuilta. Valinta kysytään myös, kun lisäosa poistetaan käytöstä.</p>
            </div>

            <p><button type="submit" class="button button-primary button-large">Tallenna asetukset</button></p>
        </form>
    </div>
    <?php
}
