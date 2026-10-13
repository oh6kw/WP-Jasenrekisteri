<?php
if (!defined('ABSPATH')) exit;

/* ------------------------------------------------------------------
 * Taulut, asetukset, oikeudet
 * ------------------------------------------------------------------ */

function jr_t($name) {
    global $wpdb;
    $map = array(
        'jasenet' => 'jasenrekisteri',            // sama taulu kuin aiemmissa versioissa
        'laskut'  => 'jasenrekisteri_laskut',
        'rivit'   => 'jasenrekisteri_laskurivit',
    );
    return $wpdb->prefix . $map[$name];
}

function jr_settings() {
    $defaults = array(
        'yhdistys'        => get_bloginfo('name'),
        'iban'            => '',
        'bic'             => '',
        'ytunnus'         => '',
        'lahettaja_nimi'  => get_bloginfo('name'),
        'lahettaja_email' => get_option('admin_email'),
        'vastaus_email'   => '',
        'yhteystiedot'    => '',
        'tunnus_kaytossa' => '1',
        'tunnus_nimi'     => 'Jäsennumero',
        'tunnus_isot'     => '0',
        'maksukoodi'      => 'molemmat',
        'jasenlajit'      => array(),
        'auto_muistutus'  => '0',
        'auto_paivia'     => '14',
        'auto_max'        => '1',
        'auto_raportti'   => '1',
        'poista_tiedot'   => '0',
        'viesti_oletus'   => "Hei {etunimi},\n\nohessa {laskun_nimi}. Muistathan käyttää maksaessasi viitenumeroa, jotta maksusi kohdistuu oikein.\n\nKiitos, että olet mukana toiminnassa!\n\nYstävällisin terveisin\n{yhdistys}",
        'muistutus_teksti'=> "Hei {etunimi},\n\nemme ole vielä havainneet maksuasi alla olevaan laskuun. Jos olet jo maksanut, tämä viesti on aiheeton – kiitos!\n\nYstävällisin terveisin\n{yhdistys}",
    );
    $saved = get_option('jr_settings', array());
    $s = wp_parse_args(is_array($saved) ? $saved : array(), $defaults);
    // Tyhjäksi jätetyn tekstin tilalla käytetään oletusta, jottei lasku lähde ilman saatetta.
    foreach (array('viesti_oletus', 'muistutus_teksti', 'maksukoodi', 'yhdistys', 'lahettaja_nimi') as $k) {
        if (trim((string) $s[$k]) === '') $s[$k] = $defaults[$k];
    }
    return $s;
}

/** Onko lisätunniste (esim. kutsutunnus, jäsennumero) käytössä, ja millä nimellä. */
function jr_tunnus_on() {
    return jr_settings()['tunnus_kaytossa'] === '1';
}
function jr_tunnus_nimi() {
    $n = trim(jr_settings()['tunnus_nimi']);
    return $n !== '' ? $n : 'Tunnus';
}

/** Jäsenluokat: array(id => array('nimi' => ..., 'summa' => ...)). Summa 0 = ei laskuteta. */
function jr_jasenlajit() {
    $l = jr_settings()['jasenlajit'];
    return is_array($l) ? $l : array();
}
function jr_laji_nimi($id) {
    $l = jr_jasenlajit();
    return ($id !== '' && isset($l[$id])) ? $l[$id]['nimi'] : '';
}
/** Etsii jäsenluokan nimellä (kirjainkoolla ei väliä). */
function jr_laji_by_name($nimi) {
    $nimi = function_exists('mb_strtolower') ? mb_strtolower(trim($nimi)) : strtolower(trim($nimi));
    if ($nimi === '') return '';
    foreach (jr_jasenlajit() as $id => $l) {
        $n = function_exists('mb_strtolower') ? mb_strtolower($l['nimi']) : strtolower($l['nimi']);
        if ($n === $nimi) return $id;
    }
    return null;
}
/** Jäsenen laskutettava summa: laskun hinta luokalle, muuten luokan nykyinen hinta, muuten oletussumma. */
function jr_member_price($m, $hinnat, $oletus) {
    $laji = isset($m->jasenlaji) ? $m->jasenlaji : '';
    if ($laji !== '' && is_array($hinnat) && isset($hinnat[$laji])) return (float) $hinnat[$laji];
    $lajit = jr_jasenlajit();
    if ($laji !== '' && isset($lajit[$laji])) return (float) $lajit[$laji]['summa'];
    return (float) $oletus;
}
/** Onko jäsenen luokan jäsenmaksu 0 € (esim. kunniajäsen)? */
function jr_is_maksuvapaa($m) {
    $lajit = jr_jasenlajit();
    $laji  = isset($m->jasenlaji) ? $m->jasenlaji : '';
    return $laji !== '' && isset($lajit[$laji]) && (float) $lajit[$laji]['summa'] <= 0;
}
function jr_laji_badge($laji_id) {
    $lajit = jr_jasenlajit();
    if ($laji_id === '' || !isset($lajit[$laji_id])) return '';
    $free = (float) $lajit[$laji_id]['summa'] <= 0;
    return '<span class="jr-badge ' . ($free ? 'jr-badge-purple' : 'jr-badge-laji') . '"' . ($free ? ' title="Ei jäsenmaksua"' : '') . '>'
        . esc_html($lajit[$laji_id]['nimi']) . '</span>';
}

/** Jäsenyyden tila. Maksuvelvollisuus määräytyy jäsenluokasta (0 € = ei laskuteta). */
function jr_tilat() {
    return array(
        'aktiivinen' => 'Voimassa',
        'eronnut'    => 'Eronnut',
    );
}
function jr_tila_badge($tila) {
    if ($tila === 'eronnut') return '<span class="jr-badge jr-badge-muted">eronnut</span>';
    return '';
}
/** SQL-ehto: kaikki voimassa olevat jäsenet. */
function jr_sql_jasenet() {
    return "tila <> 'eronnut'";
}

/** Tarkistaa oikeudet ja nonce-tunnisteen (CSRF-suojaus). */
function jr_check($nonce_action) {
    if (!current_user_can('manage_options')) {
        wp_die('Sinulla ei ole oikeuksia tähän toimintoon.', 403);
    }
    check_admin_referer($nonce_action);
}

function jr_redirect($page, $args = array()) {
    $args = array_filter($args, function ($v) { return $v !== null && $v !== ''; });
    wp_safe_redirect(add_query_arg($args, admin_url('admin.php?page=' . $page)));
    exit;
}

function jr_post($key, $default = '') {
    return isset($_POST[$key]) ? wp_unslash($_POST[$key]) : $default;
}

/* ------------------------------------------------------------------
 * Ilmoitukset (Post/Redirect/Get -mallilla, ei uudelleenlähetystä F5:llä)
 * ------------------------------------------------------------------ */

function jr_notice() {
    if (empty($_GET['jr_msg'])) return;
    $n = isset($_GET['jr_n']) ? intval($_GET['jr_n']) : 0;
    $msgs = array(
        'member_added'    => array('success', 'Jäsen lisätty.'),
        'member_updated'  => array('success', 'Jäsenen tiedot päivitetty.'),
        'member_deleted'  => array('success', 'Jäsen poistettu.'),
        'name_required'   => array('error',   'Etunimi ja sukunimi ovat pakollisia.'),
        'email_invalid'   => array('warning', 'Tiedot tallennettiin, mutta sähköpostiosoite oli virheellinen eikä sitä tallennettu.'),
        'lasku_invalid'   => array('error',   'Tarkista laskun nimi, summa ja eräpäivä.'),
        'no_recipients'   => array('error',   'Laskulle ei valittu yhtään jäsentä.'),
        'lasku_created_skip' => array('success', sprintf('Lasku luotu %d jäsenelle. %d jäsentä ohitettiin, koska heidän jäsenmaksunsa on 0 € (esim. kunniajäsenet).', $n, isset($_GET['jr_s']) ? intval($_GET['jr_s']) : 0)),
        'lasku_created'   => array('success', sprintf('Lasku luotu %d jäsenelle. Tarkista tiedot, lähetä halutessasi testilasku itsellesi ja lähetä sitten laskut.', $n)),
        'lasku_updated'   => array('success', 'Laskun tiedot päivitetty.'),
        'lasku_deleted'   => array('success', 'Lasku poistettu.'),
        'rows_added'      => array('success', sprintf('Laskulle lisättiin %d jäsentä.', $n)),
        'paid_updated'    => array('success', sprintf('Maksutiedot päivitetty (%d kpl).', $n)),
        'row_deleted'     => array('success', 'Jäsen poistettu tältä laskulta.'),
        'sent_one'        => array('success', 'Lasku lähetetty.'),
        'send_failed'     => array('error',   'Lähetys epäonnistui. Tarkista sähköpostiosoite ja sivuston sähköpostiasetukset.'),
        'test_sent'       => array('success', 'Testiviesti lähetetty osoitteeseen ' . (isset($_GET['jr_to']) ? sanitize_email(wp_unslash($_GET['jr_to'])) : '') . '.'),
        'test_invalid'    => array('error',   'Anna kelvollinen sähköpostiosoite testiviestille.'),
        'settings_saved'  => array('success', 'Asetukset tallennettu.'),
        'iban_invalid'    => array('warning', 'Asetukset tallennettu, mutta IBAN-tilinumero ei näytä oikealta. Tarkista se.'),
        'mail_invalid'    => array('error',   'Otsikko ja viesti ovat pakollisia.'),
        'nothing_selected'=> array('warning', 'Et valinnut yhtään riviä.'),
        'members_bulk'    => array('success', sprintf('Muutettu %d jäsenen tiedot.', $n)),
        'csv_empty'       => array('error',   'Tiedostosta ei löytynyt rivejä. Tarkista, että ensimmäisellä rivillä on otsikot ja sen alla jäsenten tiedot.'),
        'members_imported'=> array('success', sprintf('Tuonti valmis: %d lisätty, %d päivitetty, %d ohitettu (jo rekisterissä), %d ohitettu puuttuvan nimen vuoksi.%s',
            $n, isset($_GET['jr_u']) ? intval($_GET['jr_u']) : 0, isset($_GET['jr_s']) ? intval($_GET['jr_s']) : 0, isset($_GET['jr_e']) ? intval($_GET['jr_e']) : 0,
            !empty($_GET['jr_l']) ? ' ' . intval($_GET['jr_l']) . ' riviltä jäsenluokkaa ei tunnistettu – lisää luokka asetuksiin ja valitse se jäsenelle.' : '')),
        'upload_failed'   => array('error',   'Tiedoston lataus epäonnistui. Tarkista, että valitsit tiedoston (enintään 5 Mt).'),
        'no_payments'     => array('error',   'Tiedostosta ei löytynyt maksuja. Tarkista, että se on viitesiirtoaineisto (KTL), camt.054/053-XML tai tiliote CSV-muodossa, jossa on viite- ja summasarake.'),
        'payments_imported' => array('success', sprintf('%d laskua merkitty maksetuksi pankin tiedostosta.', $n)),
        'no_mail_recipients' => array('warning', 'Valitulla vastaanottajajoukolla ei ole yhtään sähköpostiosoitetta.'),
        'due_before_date' => array('error',   'Eräpäivä ei voi olla ennen laskun päiväystä.'),
        'member_duplicate'=> array('warning', 'Jäsen tallennettiin, mutta rekisterissä on jo toinen jäsen samalla nimellä tai sähköpostiosoitteella. Tarkista, ettei kyseessä ole tuplakirjaus.'),
        'rows_failed'     => array('warning', sprintf('Lasku luotiin, mutta %d jäsenelle laskua ei voitu muodostaa (viitenumero oli jo käytössä). Ota yhteyttä ylläpitoon.', $n)),
    );
    if (!isset($msgs[$_GET['jr_msg']])) return;
    list($type, $text) = $msgs[$_GET['jr_msg']];
    printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($type), esc_html($text));
}

/* ------------------------------------------------------------------
 * Muotoilut
 * ------------------------------------------------------------------ */

function jr_money($amount) {
    return number_format((float) $amount, 2, ',', "\xC2\xA0") . "\xC2\xA0€";
}

/** Summa tai summaväli (jos jäsenlajeilla on eri hinnat). */
function jr_summa_range($min, $max, $fallback) {
    if ($min === null || $max === null) return jr_money($fallback);
    if (abs((float) $min - (float) $max) < 0.005) return jr_money($min);
    return number_format((float) $min, 2, ',', "\xC2\xA0") . '–' . jr_money($max);
}

/**
 * Korjaa taulukkolaskennassa rikkoutuneen puhelinnumeron: 401234567 → 0401234567,
 * 358401234567 → +358401234567. Muihin muotoihin ei kosketa.
 */
function jr_normalize_phone($p) {
    $p = trim((string) $p);
    if ($p === '' || !preg_match('/^[\d\s\-]+$/', $p)) return $p;
    $digits = preg_replace('/\D/', '', $p);
    if (strpos($digits, '358') === 0 && strlen($digits) >= 11) return '+' . $digits;
    if ($digits[0] !== '0' && strlen($digits) >= 6 && strlen($digits) <= 10) return '0' . $p;
    return $p;
}

function jr_parse_money($s) {
    $s = str_replace(array(' ', "\xC2\xA0", '€'), '', (string) $s);
    $s = str_replace(',', '.', $s);
    return is_numeric($s) ? round((float) $s, 2) : 0.0;
}

function jr_date($ymd) {
    if (!$ymd || $ymd === '0000-00-00') return '';
    $ts = strtotime($ymd);
    return $ts ? date('j.n.Y', $ts) : '';
}

function jr_date_or_null($s) {
    $s = trim(sanitize_text_field(wp_unslash((string) $s)));
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return $s;
    }
    return null;
}

/* ------------------------------------------------------------------
 * Pankkitiedot: IBAN, kotimainen viitenumero, virtuaaliviivakoodi
 * ------------------------------------------------------------------ */

function jr_iban_clean($iban) {
    return strtoupper(preg_replace('/\s+/', '', (string) $iban));
}

function jr_iban_valid($iban) {
    $iban = jr_iban_clean($iban);
    if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $iban)) return false;
    $r = substr($iban, 4) . substr($iban, 0, 4);
    $mod = 0;
    foreach (str_split($r) as $c) {
        $part = ctype_alpha($c) ? (string) (ord($c) - 55) : $c;
        foreach (str_split($part) as $d) {
            $mod = ($mod * 10 + (int) $d) % 97;
        }
    }
    return $mod === 1;
}

function jr_iban_format($iban) {
    return trim(chunk_split(jr_iban_clean($iban), 4, ' '));
}

/** Suomalaisen viitenumeron tarkiste (painot 7-3-1 oikealta vasemmalle). */
function jr_viite_tarkiste($base) {
    $weights = array(7, 3, 1);
    $sum = 0;
    $rev = strrev($base);
    $len = strlen($rev);
    for ($i = 0; $i < $len; $i++) {
        $sum += (int) $rev[$i] * $weights[$i % 3];
    }
    return (10 - $sum % 10) % 10;
}

/**
 * Yksilöllinen viite: laskun numero + jäsenen ID (4 numeroa) + tarkiste.
 * Esim. lasku 3, jäsen 17 -> 3 0017 + tarkiste. Viitteestä näkee suoraan kenen maksu on kyseessä.
 */
function jr_viite($lasku_id, $jasen_id) {
    $base = sprintf('%d%04d', (int) $lasku_id, (int) $jasen_id);
    return $base . jr_viite_tarkiste($base);
}

/** Viite viiden numeron ryhmiin oikealta, kuten pankit sen esittävät. */
function jr_viite_format($viite) {
    $v = preg_replace('/\D/', '', (string) $viite);
    return trim(strrev(chunk_split(strrev($v), 5, ' ')));
}

/**
 * Pankkiviivakoodin versio 4 (kotimainen viite) virtuaaliviivakoodina.
 * Jäsen voi liittää tämän pankkisovellukseen, jolloin tilinumero, summa, viite ja eräpäivä täyttyvät itsestään.
 * Toimii vain suomalaisella IBANilla.
 */
function jr_virtuaaliviivakoodi($iban, $summa, $viite, $erapaiva) {
    $iban = jr_iban_clean($iban);
    if (!preg_match('/^FI(\d{16})$/', $iban, $m)) return '';
    $cents = (int) round((float) $summa * 100);
    if ($cents <= 0 || $cents > 99999999) return '';
    $ref = preg_replace('/\D/', '', (string) $viite);
    if ($ref === '' || strlen($ref) > 20) return '';
    $ts  = $erapaiva ? strtotime($erapaiva) : false;
    $due = $ts ? date('ymd', $ts) : '000000';
    return '4' . $m[1]
        . sprintf('%06d', intdiv($cents, 100))
        . sprintf('%02d', $cents % 100)
        . '000'
        . str_pad($ref, 20, '0', STR_PAD_LEFT)
        . $due;
}

/* ------------------------------------------------------------------
 * Sähköposti
 * ------------------------------------------------------------------ */

$GLOBALS['jr_mail_error'] = '';
add_action('wp_mail_failed', function ($error) {
    $GLOBALS['jr_mail_error'] = $error->get_error_message();
});

function jr_mail($to, $subject, $html, $images = array()) {
    $s = jr_settings();
    $headers = array('Content-Type: text/html; charset=UTF-8');
    if ($s['lahettaja_email'] && is_email($s['lahettaja_email'])) {
        $name = str_replace(array("\r", "\n", '"', '<', '>'), '', $s['lahettaja_nimi']);
        $headers[] = 'From: ' . ($name !== '' ? $name . ' ' : '') . '<' . $s['lahettaja_email'] . '>';
    }
    if ($s['vastaus_email'] && is_email($s['vastaus_email'])) {
        $headers[] = 'Reply-To: ' . $s['vastaus_email'];
    }
    $GLOBALS['jr_mail_error'] = '';
    // Lisätään viestiin myös pelkkä tekstiversio (multipart/alternative). Moni roskapostisuodatin
    // suhtautuu epäluuloisesti pelkkään HTML-viestiin.
    $alt = function ($phpmailer) use ($html, $images) {
        $text = preg_replace('#<(br|/p|/tr|/div|/h\d)[^>]*>#i', "\n", $html);
        $text = preg_replace('#</td>\s*<td[^>]*>#i', ': ', $text);
        $text = html_entity_decode(wp_strip_all_tags(preg_replace('#<head.*?</head>#is', '', $text)), ENT_QUOTES, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text);
        $phpmailer->AltBody = trim(preg_replace("/\s*\n\s*(\n\s*)+/", "\n\n", $text));
        // Kuvat upotetaan viestiin (cid:), jolloin ne näkyvät ilman ulkoisia latauksia.
        foreach ($images as $cid => $png) {
            $phpmailer->addStringEmbeddedImage($png, $cid, $cid . '.png', 'base64', 'image/png');
        }
    };
    add_action('phpmailer_init', $alt);
    $ok = (bool) wp_mail($to, $subject, $html, $headers);
    remove_action('phpmailer_init', $alt);
    return $ok;
}

/** Korvaa {paikkamerkit} ja muuttaa tekstin turvalliseksi HTML:ksi. */
function jr_fill($text, $vars) {
    $out = esc_html($text);
    foreach ($vars as $k => $v) {
        $out = str_replace('{' . $k . '}', esc_html((string) $v), $out);
    }
    return nl2br($out);
}

/* ------------------------------------------------------------------
 * CSV-vienti (Excel-yhteensopiva: UTF-8 BOM ja puolipiste)
 * ------------------------------------------------------------------ */

function jr_csv_output($filename, $header, $rows) {
    nocache_headers();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $header, ';');
    foreach ($rows as $row) {
        $row = array_map(function ($v) {
            $v = (string) $v;
            // Puhelinnumerot ja muut etunollalliset numerot tekstinä, jottei Excel pudota nollaa
            // (0401234567 → 401234567) tai muuta +358-numeroa luvuksi. Tuonti tunnistaa muodon.
            if (preg_match('/^(0[\d ]*\d|\+\d[\d ]{6,})$/', $v)) return '="' . $v . '"';
            // Estetään kaavojen suoritus taulukkolaskennassa
            return ($v !== '' && in_array($v[0], array('=', '@', '+', '-', "\t", "\r"), true)) ? "'" . $v : $v;
        }, $row);
        fputcsv($out, $row, ';');
    }
    fclose($out);
    exit;
}
