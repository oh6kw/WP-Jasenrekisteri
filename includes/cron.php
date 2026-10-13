<?php
if (!defined('ABSPATH')) exit;

/*
 * Automaattiset maksumuistutukset (valinnainen, asetuksista).
 * WP-Cron tarkistaa kerran tunnissa, onko muistutettavia. Huom: WP-Cron käynnistyy sivuston
 * käyntien yhteydessä, joten hiljaisella sivustolla muistutus voi lähteä vähän myöhemmin.
 */

add_action('jasrek_auto_muistutus', 'jasrek_run_auto_reminders');
add_action('init', 'jasrek_schedule_cron');
register_deactivation_hook(JASREK_FILE, function () {
    wp_clear_scheduled_hook('jasrek_auto_muistutus');
});

function jasrek_schedule_cron() {
    $on = jasrek_settings()['auto_muistutus'] === '1';
    $next = wp_next_scheduled('jasrek_auto_muistutus');
    if ($on && !$next) {
        wp_schedule_event(time() + 300, 'hourly', 'jasrek_auto_muistutus');
    } elseif (!$on && $next) {
        wp_clear_scheduled_hook('jasrek_auto_muistutus');
    }
}

/** Päivä, jona laskun ensimmäinen automaattinen muistutus lähtee (tai null). */
function jasrek_auto_first_date($lasku) {
    $s = jasrek_settings();
    if ($s['auto_muistutus'] !== '1' || empty($lasku->auto_muistutus)) return null;
    return gmdate('Y-m-d', strtotime($lasku->erapaiva . ' +' . (int) $s['auto_paivia'] . ' days'));
}

/** Hakee muistutettavat rivit. */
function jasrek_auto_candidates($limit = 50) {
    global $wpdb;
    $s     = jasrek_settings();
    $days  = max(1, (int) $s['auto_paivia']);
    $max   = max(1, (int) $s['auto_max']);
    $today = current_time('Y-m-d');
    $cut   = gmdate('Y-m-d', strtotime($today . " -$days days")); // eräpäivä/edellinen muistutus viimeistään tänä päivänä
    $where = "JOIN {$wpdb->jasrek_laskut} l ON l.id = r.lasku_id WHERE l.auto_muistutus = 1 AND COALESCE(r.erapaiva, l.erapaiva) <= %s AND r.maksettu IS NULL AND r.lahetetty IS NOT NULL AND COALESCE(NULLIF(m.email, ''), r.email) <> '' AND COALESCE(m.paperilasku, 0) = 0 AND (m.tila IS NULL OR m.tila = 'aktiivinen') AND r.muistutuksia < %d AND (r.muistutettu IS NULL OR r.muistutettu < %s) AND (r.viim_yritys IS NULL OR r.viim_yritys < %s) ORDER BY r.id LIMIT %d";
    return jasrek_get_rivit($where, array($cut, $max, gmdate('Y-m-d', strtotime($cut . ' +1 day')) . ' 00:00:00', $today . ' 00:00:00', $limit));
}

function jasrek_run_auto_reminders() {
    $s = jasrek_settings();
    if ($s['auto_muistutus'] !== '1') return;
    $rows = jasrek_auto_candidates(50);
    if (!$rows) return;

    $laskut = array();
    $sent = array(); $failed = array(); $per_lasku = array();
    foreach ($rows as $r) {
        if (!isset($laskut[$r->lasku_id])) $laskut[$r->lasku_id] = jasrek_get_lasku($r->lasku_id);
        $lasku = $laskut[$r->lasku_id];
        if (!$lasku) continue;
        $line = jasrek_rivi_person($r)['nimi'] . ' – ' . $lasku->nimi . ' (' . jasrek_money($r->summa) . ', muistutus ' . ((int) $r->muistutuksia + 1) . ')';
        if (jasrek_send_rivi($lasku, $r, true)) {
            $sent[] = $line;
            $per_lasku[$lasku->id][] = jasrek_rivi_person($r)['nimi'];
        } else {
            $failed[] = $line . ': ' . ($GLOBALS['jasrek_mail_error'] ?: 'lähetys epäonnistui');
        }
    }
    foreach ($per_lasku as $lid => $nimet) {
        jasrek_log('lasku', $lid, 'auto_muistutukset', $laskut[$lid]->nimi . ': ' . count($nimet) . ' automaattista muistutusta', array('nimet' => $nimet));
    }

    if ($s['auto_raportti'] === '1' && ($sent || $failed)) {
        $to = $s['vastaus_email'] ? $s['vastaus_email'] : ($s['lahettaja_email'] ? $s['lahettaja_email'] : get_option('admin_email'));
        $html = '<p>Jäsenrekisteri lähetti automaattisesti seuraavat maksumuistutukset:</p>';
        if ($sent) $html .= '<ul><li>' . implode('</li><li>', array_map('esc_html', $sent)) . '</li></ul>';
        if ($failed) $html .= '<p><strong>Epäonnistuneet:</strong></p><ul><li>' . implode('</li><li>', array_map('esc_html', $failed)) . '</li></ul>';
        $html .= '<p><a href="' . esc_url(admin_url('admin.php?page=jasrek-laskutus')) . '">Avaa laskutus</a></p>';
        jasrek_mail($to, 'Automaattiset maksumuistutukset: ' . count($sent) . ' lähetetty', jasrek_email_layout('Maksumuistutukset', $html));
    }
}
