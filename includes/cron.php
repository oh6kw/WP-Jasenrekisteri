<?php
if (!defined('ABSPATH')) exit;

/*
 * Automaattiset maksumuistutukset (valinnainen, asetuksista).
 * WP-Cron tarkistaa kerran tunnissa, onko muistutettavia. Huom: WP-Cron käynnistyy sivuston
 * käyntien yhteydessä, joten hiljaisella sivustolla muistutus voi lähteä vähän myöhemmin.
 */

add_action('jr_auto_muistutus', 'jr_run_auto_reminders');
add_action('init', 'jr_schedule_cron');
register_deactivation_hook(JR_FILE, function () {
    wp_clear_scheduled_hook('jr_auto_muistutus');
});

function jr_schedule_cron() {
    $on = jr_settings()['auto_muistutus'] === '1';
    $next = wp_next_scheduled('jr_auto_muistutus');
    if ($on && !$next) {
        wp_schedule_event(time() + 300, 'hourly', 'jr_auto_muistutus');
    } elseif (!$on && $next) {
        wp_clear_scheduled_hook('jr_auto_muistutus');
    }
}

/** Päivä, jona laskun ensimmäinen automaattinen muistutus lähtee (tai null). */
function jr_auto_first_date($lasku) {
    $s = jr_settings();
    if ($s['auto_muistutus'] !== '1' || empty($lasku->auto_muistutus)) return null;
    return date('Y-m-d', strtotime($lasku->erapaiva . ' +' . (int) $s['auto_paivia'] . ' days'));
}

/** Hakee muistutettavat rivit. */
function jr_auto_candidates($limit = 50) {
    global $wpdb;
    $s     = jr_settings();
    $days  = max(1, (int) $s['auto_paivia']);
    $max   = max(1, (int) $s['auto_max']);
    $today = current_time('Y-m-d');
    $cut   = date('Y-m-d', strtotime($today . " -$days days")); // eräpäivä/edellinen muistutus viimeistään tänä päivänä
    $sql = jr_rivi_select_sql() . 'JOIN ' . jr_t('laskut') . ' l ON l.id = r.lasku_id '
        . "WHERE l.auto_muistutus = 1 AND l.erapaiva <= %s AND r.maksettu IS NULL AND r.lahetetty IS NOT NULL "
        . "AND COALESCE(NULLIF(m.email, ''), r.email) <> '' "
        . "AND (m.tila IS NULL OR m.tila = 'aktiivinen') "
        . 'AND r.muistutuksia < %d '
        . 'AND (r.muistutettu IS NULL OR r.muistutettu < %s) '
        . 'AND (r.viim_yritys IS NULL OR r.viim_yritys < %s) '
        . 'ORDER BY r.id LIMIT %d';
    return $wpdb->get_results($wpdb->prepare($sql, $cut, $max,
        date('Y-m-d', strtotime($cut . ' +1 day')) . ' 00:00:00', $today . ' 00:00:00', $limit));
}

function jr_run_auto_reminders() {
    $s = jr_settings();
    if ($s['auto_muistutus'] !== '1') return;
    $rows = jr_auto_candidates(50);
    if (!$rows) return;

    $laskut = array();
    $sent = array(); $failed = array();
    foreach ($rows as $r) {
        if (!isset($laskut[$r->lasku_id])) $laskut[$r->lasku_id] = jr_get_lasku($r->lasku_id);
        $lasku = $laskut[$r->lasku_id];
        if (!$lasku) continue;
        $line = jr_rivi_person($r)['nimi'] . ' – ' . $lasku->nimi . ' (' . jr_money($r->summa) . ', muistutus ' . ((int) $r->muistutuksia + 1) . ')';
        if (jr_send_rivi($lasku, $r, true)) $sent[] = $line;
        else $failed[] = $line . ': ' . ($GLOBALS['jr_mail_error'] ?: 'lähetys epäonnistui');
    }

    if ($s['auto_raportti'] === '1' && ($sent || $failed)) {
        $to = $s['vastaus_email'] ? $s['vastaus_email'] : ($s['lahettaja_email'] ? $s['lahettaja_email'] : get_option('admin_email'));
        $html = '<p>Jäsenrekisteri lähetti automaattisesti seuraavat maksumuistutukset:</p>';
        if ($sent) $html .= '<ul><li>' . implode('</li><li>', array_map('esc_html', $sent)) . '</li></ul>';
        if ($failed) $html .= '<p><strong>Epäonnistuneet:</strong></p><ul><li>' . implode('</li><li>', array_map('esc_html', $failed)) . '</li></ul>';
        $html .= '<p><a href="' . esc_url(admin_url('admin.php?page=jr-laskutus')) . '">Avaa laskutus</a></p>';
        jr_mail($to, 'Automaattiset maksumuistutukset: ' . count($sent) . ' lähetetty', jr_email_layout('Maksumuistutukset', $html));
    }
}
