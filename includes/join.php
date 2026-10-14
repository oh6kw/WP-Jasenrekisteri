<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own custom tables (members, invoices, audit log). The data is admin-only and changes on every write, so object caching is not used.

/*
 * Liittymislomake sivustolle: [jasenrekisteri_liittyminen]
 * Hakemus tallennetaan jäsenrekisteriin tilalla "hakemus". Hakemukset eivät ole jäseniä
 * (eivät näy jäsenmäärissä, laskuilla, tiedotteissa eivätkä raporteissa), ennen kuin ne hyväksytään.
 */

add_shortcode('jasenrekisteri_liittyminen', 'jasrek_join_shortcode');

/** Aikaleima + allekirjoitus: estää lomakkeen automaattisen lähettämisen heti sivun latauksen jälkeen. */
function jasrek_join_token() {
    $t = (string) time();
    return $t . '.' . substr(hash_hmac('sha256', $t, wp_salt('nonce')), 0, 20);
}
function jasrek_join_token_ok($token) {
    $p = explode('.', (string) $token);
    if (count($p) !== 2 || !ctype_digit($p[0])) return false;
    if (!hash_equals(substr(hash_hmac('sha256', $p[0], wp_salt('nonce')), 0, 20), $p[1])) return false;
    $age = time() - (int) $p[0];
    return $age >= 3 && $age <= 3 * DAY_IN_SECONDS;
}

/** Liittymislomakkeen jäsenluokat: asetuksissa valitut, jotka ovat yhä olemassa. */
function jasrek_join_lajit() {
    $sallitut = (array) jasrek_settings()['liitto_lajit'];
    $out = array();
    foreach (jasrek_jasenlajit() as $id => $l) if (in_array($id, $sallitut, true)) $out[$id] = $l;
    return $out;
}

function jasrek_join_kentat() {
    return array_filter(jasrek_lisakentat(), function ($k) { return !empty($k['lomake']) && $k['lomake'] === '1'; });
}

function jasrek_join_shortcode() {
    $s = jasrek_settings();
    if ($s['liitto_kaytossa'] !== '1') {
        return current_user_can('manage_options')
            ? '<p><em>Jäsenrekisteri: liittymislomake ei ole käytössä. Ota se käyttöön kohdassa Jäsenrekisteri → Asetukset → Liittymislomake. (Tämä huomautus näkyy vain ylläpitäjille.)</em></p>'
            : '';
    }
    wp_register_style('jasrek-liity', false, array(), JASREK_VERSION);
    wp_enqueue_style('jasrek-liity');
    wp_add_inline_style('jasrek-liity', '.jasrek-liity{max-width:640px}.jasrek-liity p{margin:0 0 14px}.jasrek-liity label{display:block;font-weight:600;margin-bottom:4px}'
        . '.jasrek-liity label.jasrek-check{font-weight:normal;display:flex;gap:8px;align-items:flex-start}.jasrek-liity input[type=text],.jasrek-liity input[type=email],'
        . '.jasrek-liity input[type=tel],.jasrek-liity input[type=date],.jasrek-liity select,.jasrek-liity textarea{width:100%;box-sizing:border-box;padding:8px}'
        . '.jasrek-liity .jasrek-rivi{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}.jasrek-liity .jasrek-rivi3{display:grid;grid-template-columns:2fr 1fr 2fr;gap:0 14px}'
        . '@media(max-width:560px){.jasrek-liity .jasrek-rivi,.jasrek-liity .jasrek-rivi3{grid-template-columns:1fr}}'
        . '.jasrek-liity .jasrek-hp{position:absolute!important;left:-10000px!important;width:1px;height:1px;overflow:hidden}'
        . '.jasrek-liity-viesti{padding:12px 16px;border-radius:6px;margin:0 0 16px}.jasrek-liity-ok{background:#e6f4ea;color:#1a5a2b}.jasrek-liity-virhe{background:#fdecea;color:#8a1c13}');

    $tila = jasrek_get('jasrek_liittyminen');
    $out = '<div class="jasrek-liity" id="jasrek-liity">';
    if ($tila === 'ok') {
        return $out . '<div class="jasrek-liity-viesti jasrek-liity-ok" role="status">' . nl2br(esc_html($s['liitto_kiitos'])) . '</div></div>';
    }
    if ($tila === 'virhe') {
        $syyt = array(
            'pakolliset' => 'Täytä nimi ja kelvollinen sähköpostiosoite sekä hyväksy tietojen tallentaminen.',
            'aika'       => 'Lomakkeen lähetys epäonnistui. Lataa sivu uudelleen ja yritä hetken kuluttua.',
            'raja'       => 'Liian monta hakemusta lyhyessä ajassa. Yritä myöhemmin uudelleen.',
        );
        $syy = jasrek_get('syy');
        $out .= '<div class="jasrek-liity-viesti jasrek-liity-virhe" role="alert">' . esc_html(isset($syyt[$syy]) ? $syyt[$syy] : $syyt['aika']) . '</div>';
    }
    $lajit = jasrek_join_lajit();
    ob_start(); ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" novalidate>
        <input type="hidden" name="action" value="jasrek_liity">
        <input type="hidden" name="jasrek_t" value="<?php echo esc_attr(jasrek_join_token()); ?>">
        <div class="jasrek-hp" aria-hidden="true"><label for="jasrek-www">Jätä tyhjäksi</label><input type="text" id="jasrek-www" name="jasrek_www" value="" tabindex="-1" autocomplete="off"></div>
        <div class="jasrek-rivi">
            <p><label for="jasrek-l-etu">Etunimi *</label><input type="text" id="jasrek-l-etu" name="etunimi" required maxlength="50" autocomplete="given-name"></p>
            <p><label for="jasrek-l-suku">Sukunimi *</label><input type="text" id="jasrek-l-suku" name="sukunimi" required maxlength="50" autocomplete="family-name"></p>
        </div>
        <div class="jasrek-rivi">
            <p><label for="jasrek-l-email">Sähköposti *</label><input type="email" id="jasrek-l-email" name="email" required maxlength="100" autocomplete="email"></p>
            <p><label for="jasrek-l-puh">Puhelin</label><input type="tel" id="jasrek-l-puh" name="puhelin" maxlength="20" autocomplete="tel"></p>
        </div>
        <p><label for="jasrek-l-osoite">Katuosoite</label><input type="text" id="jasrek-l-osoite" name="osoite" maxlength="150" autocomplete="street-address"></p>
        <div class="jasrek-rivi3">
            <p><label for="jasrek-l-pnro">Postinumero</label><input type="text" id="jasrek-l-pnro" name="postinumero" maxlength="10" autocomplete="postal-code" inputmode="numeric"></p>
            <p><label for="jasrek-l-ptp">Postitoimipaikka</label><input type="text" id="jasrek-l-ptp" name="postitoimipaikka" maxlength="60" autocomplete="address-level2"></p>
            <?php if (jasrek_tunnus_on()): ?>
            <p><label for="jasrek-l-tunnus"><?php echo esc_html(jasrek_tunnus_nimi()); ?></label><input type="text" id="jasrek-l-tunnus" name="tunnus" maxlength="20"></p>
            <?php endif; ?>
        </div>
        <?php if ($lajit): ?>
        <p><label for="jasrek-l-laji">Jäsenluokka</label>
           <select id="jasrek-l-laji" name="jasenlaji">
               <?php foreach ($lajit as $id => $l): ?>
                   <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($l['nimi'] . ((float) $l['summa'] > 0 ? ' – jäsenmaksu ' . jasrek_money($l['summa']) : '')); ?></option>
               <?php endforeach; ?>
           </select></p>
        <?php endif; ?>
        <?php foreach (jasrek_join_kentat() as $kid => $k): ?>
            <?php if ($k['tyyppi'] === 'valinta'): ?>
                <p><label class="jasrek-check"><input type="checkbox" name="lisa[<?php echo esc_attr($kid); ?>]" value="1"> <?php echo esc_html($k['nimi']); ?></label></p>
            <?php else: ?>
                <p><label for="jasrek-l-<?php echo esc_attr($kid); ?>"><?php echo esc_html($k['nimi']); ?></label>
                   <input type="<?php echo jasrek_lisa_is_date($k) ? 'date' : 'text'; ?>" id="jasrek-l-<?php echo esc_attr($kid); ?>" name="lisa[<?php echo esc_attr($kid); ?>]" maxlength="200"></p>
            <?php endif; ?>
        <?php endforeach; ?>
        <p><label class="jasrek-check"><input type="checkbox" name="paperilasku" value="1"> Haluan jäsenmaksulaskun paperilla postitse</label></p>
        <p><label for="jasrek-l-viesti">Lisätietoja tai viesti</label><textarea id="jasrek-l-viesti" name="viesti" rows="3" maxlength="1000"></textarea></p>
        <p><label class="jasrek-check"><input type="checkbox" name="suostumus" value="1" required>
            <span>Hyväksyn, että tietoni tallennetaan yhdistyksen jäsenrekisteriin jäsenyyden hoitamista varten.<?php if ($s['liitto_tietosuoja'] !== ''): ?> <a href="<?php echo esc_url($s['liitto_tietosuoja']); ?>" target="_blank" rel="noopener">Tietosuojaseloste</a><?php endif; ?> *</span></label></p>
        <p><button type="submit" class="wp-element-button">Lähetä liittymishakemus</button></p>
    </form>
    <?php
    return $out . ob_get_clean() . '</div>';
}

add_action('admin_post_nopriv_jasrek_liity', 'jasrek_handle_join');
add_action('admin_post_jasrek_liity', 'jasrek_handle_join');
function jasrek_handle_join() {
    global $wpdb;
    $back = wp_get_referer();
    if (!$back || wp_parse_url($back, PHP_URL_HOST) !== wp_parse_url(home_url(), PHP_URL_HOST)) $back = home_url('/');
    $back = remove_query_arg(array('jasrek_liittyminen', 'syy'), $back);
    $fail = function ($syy) use ($back) {
        wp_safe_redirect(add_query_arg(array('jasrek_liittyminen' => 'virhe', 'syy' => $syy), $back) . '#jasrek-liity');
        exit;
    };
    $s = jasrek_settings();
    if ($s['liitto_kaytossa'] !== '1') $fail('aika');

    // Julkinen lomake: WordPressin nonce vanhenisi välimuistissa olevalla sivulla, joten suojana on
    // palvelimen allekirjoittama aikaleima (lomake ladattu sivustolta 3 s – 3 vrk sitten), piilokenttä
    // botteja varten ja hakemusmäärän raja. Lomake ei muuta olemassa olevia tietoja.
    if (!jasrek_join_token_ok(sanitize_text_field(jasrek_post('jasrek_t')))) $fail('aika');
    if (jasrek_post('jasrek_www') !== '') {
        // Botti täytti piilokentän: näytetään kiitos, mutta ei tallenneta mitään.
        wp_safe_redirect(add_query_arg('jasrek_liittyminen', 'ok', $back) . '#jasrek-liity');
        exit;
    }
    // Enintään 5 hakemusta tunnissa samasta osoitteesta
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $rk = 'jasrek_liity_' . md5($ip . wp_salt('nonce'));
    $count = (int) get_transient($rk);
    if ($count >= 5) $fail('raja');
    set_transient($rk, $count + 1, HOUR_IN_SECONDS);

    $etu  = sanitize_text_field(jasrek_post('etunimi'));
    $suku = sanitize_text_field(jasrek_post('sukunimi'));
    $email = sanitize_email(jasrek_post('email'));
    if ($etu === '' || $suku === '' || !is_email($email) || jasrek_post('suostumus') !== '1') $fail('pakolliset');

    $cut = function ($v, $n) { return function_exists('mb_substr') ? mb_substr($v, 0, $n) : substr($v, 0, $n); };
    $katu = sanitize_text_field(jasrek_post('osoite'));
    $pnro = sanitize_text_field(jasrek_post('postinumero'));
    $ptp  = sanitize_text_field(jasrek_post('postitoimipaikka'));
    $osoite = trim($katu . (trim($pnro . ' ' . $ptp) !== '' ? ', ' . trim($pnro . ' ' . $ptp) : ''), ', ');
    $lajit = jasrek_join_lajit();
    $laji = sanitize_key(jasrek_post('jasenlaji'));
    if (!isset($lajit[$laji])) $laji = $lajit ? (string) key($lajit) : '';
    $tunnus = jasrek_tunnus_on() ? sanitize_text_field(jasrek_post('tunnus')) : '';
    if ($tunnus !== '' && $s['tunnus_isot'] === '1') $tunnus = function_exists('mb_strtoupper') ? mb_strtoupper($tunnus) : strtoupper($tunnus);
    $lisa = array();
    $posted = jasrek_post_array('lisa');
    foreach (jasrek_join_kentat() as $kid => $k) {
        $val = jasrek_lisa_parse($k, isset($posted[$kid]) ? $posted[$kid] : '');
        if ($val !== '') $lisa[$kid] = $val;
    }
    $viesti = $cut(sanitize_textarea_field(jasrek_post('viesti')), 1000);
    $dup = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->jasrek_jasenet} WHERE email = %s OR (etunimi = %s AND sukunimi = %s)", $email, $etu, $suku));
    $tiedot = 'Liittymishakemus ' . jasrek_date(current_time('Y-m-d')) . ' verkkolomakkeelta.'
        . ($dup ? ' Huom: rekisterissä on jo henkilö samalla nimellä tai sähköpostilla.' : '')
        . ($viesti !== '' ? "\nViesti: " . $viesti : '');

    $data = array(
        'etunimi' => $cut($etu, 50), 'sukunimi' => $cut($suku, 50), 'tunnus' => $cut($tunnus, 20),
        'osoite' => $cut($osoite, 255), 'puhelin' => $cut(sanitize_text_field(jasrek_normalize_phone(jasrek_post('puhelin'))), 20),
        'email' => $email, 'liittynyt' => null, 'tila' => 'hakemus', 'jasenlaji' => $laji, 'lisatiedot' => $tiedot,
        'paperilasku' => jasrek_post('paperilasku') === '1' ? 1 : 0, 'lisakentat' => $lisa ? wp_json_encode($lisa) : null,
    );
    $wpdb->insert($wpdb->jasrek_jasenet, $data);
    $id = (int) $wpdb->insert_id;
    if (!$id) $fail('aika');
    jasrek_log('jasen', $id, 'hakemus', jasrek_log_member_name($data) . ' (verkkolomake)', jasrek_log_diff(array(), jasrek_member_snapshot($data)));

    // Ilmoitus yhdistykselle ja vahvistus hakijalle
    $to = $s['liitto_ilmoitus'] !== '' ? $s['liitto_ilmoitus'] : ($s['vastaus_email'] ? $s['vastaus_email'] : ($s['lahettaja_email'] ? $s['lahettaja_email'] : get_option('admin_email')));
    $html = '<p>Uusi liittymishakemus:</p><p><strong>' . esc_html($etu . ' ' . $suku) . '</strong><br>' . esc_html($email)
        . ($laji !== '' ? '<br>Jäsenluokka: ' . esc_html(jasrek_laji_nimi($laji)) : '') . ($viesti !== '' ? '<br>Viesti: ' . esc_html($viesti) : '') . '</p>'
        . '<p><a href="' . esc_url(admin_url('admin.php?page=jasenrekisteri&nayta=hakemus')) . '">Käsittele hakemus jäsenrekisterissä</a></p>';
    jasrek_mail($to, 'Uusi liittymishakemus: ' . $etu . ' ' . $suku, jasrek_email_layout('Uusi liittymishakemus', $html));
    jasrek_mail($email, 'Liittymishakemus vastaanotettu – ' . $s['yhdistys'],
        jasrek_email_layout('Liittymishakemus', '<p>Hei ' . esc_html($etu) . ',</p><p>' . nl2br(esc_html($s['liitto_kiitos'])) . '</p>'));

    wp_safe_redirect(add_query_arg('jasrek_liittyminen', 'ok', $back) . '#jasrek-liity');
    exit;
}

/* ------------------------------------------------------------------
 * Hakemusten käsittely hallinnassa
 * ------------------------------------------------------------------ */

function jasrek_hakemuksia() {
    global $wpdb;
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->jasrek_jasenet} WHERE tila = 'hakemus'");
}

/** Hyväksyy hakemuksen: jäseneksi tästä päivästä, tervetuloviesti (jos asetettu). */
function jasrek_approve_application($m) {
    global $wpdb;
    $upd = array('tila' => 'aktiivinen', 'liittynyt' => $m->liittynyt ? $m->liittynyt : current_time('Y-m-d'));
    $wpdb->update($wpdb->jasrek_jasenet, $upd, array('id' => $m->id));
    jasrek_log('jasen', $m->id, 'hyvaksytty', jasrek_log_member_name($m), jasrek_log_diff(jasrek_member_snapshot($m), jasrek_member_snapshot(array_merge((array) $m, $upd))));
    $s = jasrek_settings();
    if (trim($s['liitto_tervetuloa']) !== '' && is_email($m->email)) {
        $vars = array('etunimi' => $m->etunimi, 'sukunimi' => $m->sukunimi, 'nimi' => trim($m->etunimi . ' ' . $m->sukunimi), 'tunnus' => $m->tunnus,
            'yhdistys' => $s['yhdistys'], 'jasenluokka' => jasrek_laji_nimi((string) $m->jasenlaji) !== '' ? jasrek_laji_nimi((string) $m->jasenlaji) : 'Jäsen');
        jasrek_mail($m->email, 'Tervetuloa jäseneksi – ' . $s['yhdistys'], jasrek_email_layout('Tervetuloa', '<div>' . jasrek_fill($s['liitto_tervetuloa'], $vars) . '</div>'));
    }
}

add_action('admin_post_jasrek_hakemus_hyvaksy', 'jasrek_handle_application_approve');
function jasrek_handle_application_approve() {
    global $wpdb;
    $id = jasrek_request_int('id');
    jasrek_check('jasrek_hakemus_' . $id);
    $m = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->jasrek_jasenet} WHERE id = %d AND tila = 'hakemus'", $id));
    if (!$m) jasrek_redirect('jasenrekisteri');
    jasrek_approve_application($m);
    jasrek_redirect('jasenrekisteri', array('view' => 'edit', 'id' => $id, 'jasrek_msg' => 'hakemus_ok'));
}

add_action('admin_post_jasrek_hakemus_hylkaa', 'jasrek_handle_application_reject');
function jasrek_handle_application_reject() {
    global $wpdb;
    $id = jasrek_request_int('id');
    jasrek_check('jasrek_hakemus_' . $id);
    $m = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->jasrek_jasenet} WHERE id = %d AND tila = 'hakemus'", $id));
    if ($m) {
        $wpdb->delete($wpdb->jasrek_jasenet, array('id' => $id));
        jasrek_log('jasen', $id, 'hylatty', jasrek_log_member_name($m));
    }
    jasrek_redirect('jasenrekisteri', array('nayta' => jasrek_hakemuksia() ? 'hakemus' : 'jasenet', 'jasrek_msg' => 'hakemus_pois'));
}

/** Hakemusten määrä valikkoon (kuten WordPressin kommentit). */
function jasrek_menu_bubble() {
    $n = jasrek_hakemuksia();
    return $n ? ' <span class="awaiting-mod count-' . (int) $n . '"><span class="pending-count">' . (int) $n . '</span></span>' : '';
}
