<?php
if (!defined('ABSPATH')) exit;

/* ------------------------------------------------------------------
 * Tietokantaapurit
 * ------------------------------------------------------------------ */

function jr_get_lasku($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . jr_t('laskut') . ' WHERE id = %d', $id));
}

/** Laskurivit liitettynä jäsenen nykyisiin tietoihin. to_email = nykyinen osoite, tai laskulle tallennettu. */
function jr_rivi_select_sql() {
    // Jäsenluokka laskutushetken mukaan (vanhoilla riveillä nykyinen luokka)
    return "SELECT r.*, m.etunimi, m.sukunimi, m.tunnus, m.tila, m.osoite, COALESCE(m.paperilasku, 0) AS paperilasku, "
         . "COALESCE(NULLIF(r.jasenlaji, ''), m.jasenlaji, '') AS jasenlaji, "
         . "COALESCE(NULLIF(m.email, ''), r.email) AS to_email "
         . 'FROM ' . jr_t('rivit') . ' r LEFT JOIN ' . jr_t('jasenet') . ' m ON m.id = r.jasen_id ';
}

function jr_get_rivi($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(jr_rivi_select_sql() . 'WHERE r.id = %d', $id));
}

/** Hinnat laskulta: array(laji_id => summa). */
function jr_lasku_hinnat($lasku) {
    $h = !empty($lasku->hinnat) ? json_decode($lasku->hinnat, true) : array();
    return is_array($h) ? $h : array();
}

/**
 * Luo laskurivit. Summa määräytyy jäsenlajin mukaan ($hinnat), muuten $summa.
 * Jäsenet, joiden summa on 0, ohitetaan ($skipped).
 */
function jr_add_rows($lasku_id, $members, $summa, $hinnat = array(), &$skipped = 0, $erapaiva = null, $postimaksu = 0) {
    global $wpdb;
    $n = 0;
    $skipped = 0;
    foreach ($members as $m) {
        $price = jr_member_price($m, $hinnat, $summa);
        if ($price <= 0) { $skipped++; continue; }
        // Postimaksu vain paperilaskun saajille
        $pm = ($postimaksu > 0 && jr_is_paperi($m)) ? (float) $postimaksu : 0;
        $ok = $wpdb->insert(jr_t('rivit'), array(
            'lasku_id' => $lasku_id,
            'jasen_id' => $m->id,
            'nimi'     => trim($m->etunimi . ' ' . $m->sukunimi),
            'email'    => (string) $m->email,
            'viite'    => jr_unique_viite($lasku_id, $m->id),
            'summa'    => $price + $pm,
            'postimaksu' => $pm,
            'jasenlaji'=> (string) $m->jasenlaji,
            'erapaiva' => $erapaiva,
        ));
        if ($ok) $n++;
    }
    return $n;
}

/**
 * Lisää yksittäisen jäsenen jo tehdylle laskulle (esim. kauden jälkeen liittynyt).
 * Palauttaa uuden laskurivin id:n, tai 0 jos jäsen on jo laskulla.
 */
function jr_add_member_to_lasku($lasku, $m, $summa, $erapaiva = null) {
    global $wpdb;
    $t = jr_t('rivit');
    $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE lasku_id = %d AND jasen_id = %d", $lasku->id, $m->id));
    if ($exists) return 0;
    $pm = (!empty($lasku->postimaksu) && (float) $lasku->postimaksu > 0 && jr_is_paperi($m)) ? (float) $lasku->postimaksu : 0;
    $ok = $wpdb->insert($t, array(
        'lasku_id' => $lasku->id,
        'jasen_id' => $m->id,
        'nimi'     => trim($m->etunimi . ' ' . $m->sukunimi),
        'email'    => (string) $m->email,
        'viite'    => jr_unique_viite($lasku->id, $m->id),
        'summa'    => $summa + $pm,
        'postimaksu' => $pm,
        'jasenlaji'=> (string) $m->jasenlaji,
        'erapaiva' => ($erapaiva && $erapaiva !== $lasku->erapaiva) ? $erapaiva : null,
    ));
    return $ok ? (int) $wpdb->insert_id : 0;
}

/** Laskut, joille jäsenen voi vielä lisätä (uusimmat ensin). */
function jr_laskut_ilman_jasenta($jasen_id, $limit = 3) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        'SELECT * FROM ' . jr_t('laskut') . ' WHERE id NOT IN (SELECT lasku_id FROM ' . jr_t('rivit') . ' WHERE jasen_id = %d) ORDER BY id DESC LIMIT %d',
        (int) $jasen_id, (int) $limit
    ));
}

/**
 * Lisää tai poistaa postimaksun maksamattomilta laskuriveiltä. Lisättäessä käytetään laskun postimaksua,
 * tai asetusten postimaksua, jos laskulla ei ole omaa. Palauttaa muutettujen rivien määrän.
 */
function jr_set_postimaksu($lasku, $rows, $lisaa) {
    global $wpdb;
    $pm = (float) $lasku->postimaksu > 0 ? (float) $lasku->postimaksu : jr_postimaksu_oletus();
    $n = 0;
    foreach ($rows as $r) {
        if ($r->maksettu) continue;
        $old = jr_rivi_postimaksu($r);
        $new = $lisaa ? $pm : 0;
        if (abs($old - $new) < 0.005) continue;
        $wpdb->update(jr_t('rivit'), array('postimaksu' => $new, 'summa' => jr_rivi_jasenmaksu($r) + $new), array('id' => $r->id));
        jr_log_rivi($r, 'muokattu', ($new > 0 ? 'postimaksu lisätty ' . html_entity_decode(jr_money($new)) : 'postimaksu poistettu') . ', uusi summa ' . html_entity_decode(jr_money(jr_rivi_jasenmaksu($r) + $new)), $lasku);
        $n++;
    }
    return $n;
}

/** Kirjaa yksittäisen laskurivin tapahtuman jäsenen muutoshistoriaan. */
function jr_log_rivi($r, $toiminto, $lisa = '', $lasku = null) {
    if (!$lasku) $lasku = jr_get_lasku($r->lasku_id);
    $p = jr_rivi_person($r);
    jr_log('maksu', (int) $r->jasen_id, $toiminto, ($lasku ? $lasku->nimi : 'Lasku') . ': ' . $p['nimi'] . ', ' . html_entity_decode(jr_money($r->summa)) . ($lisa !== '' ? ' – ' . $lisa : ''));
}

/**
 * Lähettää yhden laskun. $test_to: lähetä testinä tähän osoitteeseen, ei kirjata mitään.
 */
function jr_send_rivi($lasku, $r, $reminder = false, $test_to = null) {
    global $wpdb;
    $s  = jr_settings();
    $to = $test_to ? $test_to : $r->to_email;
    $now = current_time('mysql');

    if (!$to || !is_email($to)) {
        if (!$test_to) {
            $wpdb->update(jr_t('rivit'), array('viim_yritys' => $now, 'virhe' => 'Ei kelvollista sähköpostiosoitetta'), array('id' => $r->id));
        }
        return false;
    }

    $subject = ($reminder ? 'Muistutus: ' : '') . $lasku->nimi . ($s['yhdistys'] !== '' ? ' – ' . $s['yhdistys'] : '');
    if ($test_to) $subject = '[TESTI] ' . $subject;

    $ok = jr_mail($to, $subject, jr_invoice_html($lasku, $r, $reminder), jr_invoice_images($lasku, $r));
    if ($test_to) return $ok;

    $upd = array('viim_yritys' => $now);
    if ($ok) {
        if ($reminder) {
            $upd['muistutettu']  = $now;
            $upd['muistutuksia'] = (int) $r->muistutuksia + 1;
        }
        if ($reminder === false || empty($r->lahetetty)) $upd['lahetetty'] = $now;
        $upd['virhe'] = null;
    } else {
        $err = $GLOBALS['jr_mail_error'] ? $GLOBALS['jr_mail_error'] : 'Lähetys epäonnistui';
        $upd['virhe'] = function_exists('mb_substr') ? mb_substr($err, 0, 250) : substr($err, 0, 250);
    }
    $wpdb->update(jr_t('rivit'), $upd, array('id' => $r->id));
    return $ok;
}

/* ------------------------------------------------------------------
 * Käsittelijät
 * ------------------------------------------------------------------ */

add_action('admin_post_jr_create_lasku', 'jr_handle_create_lasku');
function jr_handle_create_lasku() {
    jr_check('jr_create_lasku');
    global $wpdb;

    $nimi     = sanitize_text_field(jr_post('nimi'));
    $summa    = jr_parse_money(jr_post('summa'));
    $erapaiva = jr_date_or_null(jr_post('erapaiva'));
    $laskupvm = jr_date_or_null(jr_post('laskupvm'));
    if (!$laskupvm) $laskupvm = current_time('Y-m-d');
    $viesti   = sanitize_textarea_field(jr_post('viesti'));
    $postimaksu = isset($_POST['postimaksu']) ? min(999.99, max(0, jr_parse_money(jr_post('postimaksu')))) : jr_postimaksu_oletus();

    // Jäsenlajikohtaiset summat
    $hinnat = array();
    foreach (jr_jasenlajit() as $lid => $l) {
        $raw = isset($_POST['hinta'][$lid]) ? wp_unslash($_POST['hinta'][$lid]) : '';
        $hinnat[$lid] = min(999999.99, max(0, jr_parse_money($raw)));
    }
    $max = $hinnat ? max(array_merge(array($summa), $hinnat)) : $summa;
    if ($nimi === '' || $max <= 0 || $summa < 0 || $summa > 999999.99 || !$erapaiva) {
        jr_redirect('jr-laskutus', array('view' => 'new', 'jr_msg' => 'lasku_invalid'));
    }
    if ($erapaiva < $laskupvm) {
        jr_redirect('jr-laskutus', array('view' => 'new', 'jr_msg' => 'due_before_date'));
    }

    $t = jr_t('jasenet');
    if (jr_post('vastaanottajat') === 'valitut') {
        $ids = array_filter(array_map('intval', (array) (isset($_POST['jasenet']) ? $_POST['jasenet'] : array())));
        $members = $ids ? $wpdb->get_results("SELECT * FROM $t WHERE id IN (" . implode(',', $ids) . ')') : array();
    } else {
        $members = $wpdb->get_results("SELECT * FROM $t WHERE tila = 'aktiivinen'");
    }
    if (!$members) {
        jr_redirect('jr-laskutus', array('view' => 'new', 'jr_msg' => 'no_recipients'));
    }

    $wpdb->insert(jr_t('laskut'), array(
        'nimi' => $nimi, 'summa' => $summa, 'laskupvm' => $laskupvm,
        'erapaiva' => $erapaiva, 'viesti' => $viesti, 'luotu' => current_time('mysql'),
        'hinnat' => $hinnat ? wp_json_encode($hinnat) : null,
        'postimaksu' => $postimaksu,
        'auto_muistutus' => 1,
    ));
    $lasku_id = (int) $wpdb->insert_id;
    $skipped = 0;
    $n = jr_add_rows($lasku_id, $members, $summa, $hinnat, $skipped, null, $postimaksu);
    $failed = count($members) - $n - $skipped;
    if ($n === 0) {
        $wpdb->delete(jr_t('laskut'), array('id' => $lasku_id));
        jr_redirect('jr-laskutus', array('view' => 'new', 'jr_msg' => 'no_recipients'));
    }
    $hm = array();
    foreach ($hinnat as $lid => $h) $hm[] = array('Hinta: ' . jr_laji_nimi($lid), '', html_entity_decode(jr_money($h)));
    if (!$hinnat || $summa > 0) $hm[] = array('Summa' . ($hinnat ? ' (ilman jäsenluokkaa)' : ''), '', html_entity_decode(jr_money($summa)));
    if ($postimaksu > 0) $hm[] = array('Postimaksu paperilaskuihin', '', html_entity_decode(jr_money($postimaksu)));
    $hm[] = array('Eräpäivä', '', jr_date($erapaiva));
    jr_log('lasku', $lasku_id, 'lasku_luotu', $nimi . ': ' . $n . ' jäsentä' . ($skipped ? ', ' . $skipped . ' ohitettu (0 €)' : ''), $hm);
    if ($skipped > 0 && $failed <= 0) {
        jr_redirect('jr-laskutus', array('view' => 'lasku', 'id' => $lasku_id, 'jr_msg' => 'lasku_created_skip', 'jr_n' => $n, 'jr_s' => $skipped));
    }
    if ($failed > 0) {
        jr_redirect('jr-laskutus', array('view' => 'lasku', 'id' => $lasku_id, 'jr_msg' => 'rows_failed', 'jr_n' => $failed));
    }
    jr_redirect('jr-laskutus', array('view' => 'lasku', 'id' => $lasku_id, 'jr_msg' => 'lasku_created', 'jr_n' => $n));
}

add_action('admin_post_jr_update_lasku', 'jr_handle_update_lasku');
function jr_handle_update_lasku() {
    $id = intval(jr_post('id', 0));
    jr_check('jr_update_lasku_' . $id);
    global $wpdb;
    $nimi     = sanitize_text_field(jr_post('nimi'));
    $erapaiva = jr_date_or_null(jr_post('erapaiva'));
    if ($nimi === '' || !$erapaiva) {
        jr_redirect('jr-laskutus', array('view' => 'lasku', 'id' => $id, 'jr_msg' => 'lasku_invalid'));
    }
    $old = jr_get_lasku($id);
    if (!$old) jr_redirect('jr-laskutus');
    $upd = array(
        'nimi' => $nimi, 'erapaiva' => $erapaiva, 'viesti' => sanitize_textarea_field(jr_post('viesti')),
        'auto_muistutus' => jr_settings()['auto_muistutus'] === '1' ? (jr_post('auto_muistutus') === '1' ? 1 : 0) : (int) $old->auto_muistutus,
    );
    $wpdb->update(jr_t('laskut'), $upd, array('id' => $id));
    $snap = function ($l) {
        $l = (object) $l;
        return array('Nimi' => $l->nimi, 'Eräpäivä' => jr_date($l->erapaiva), 'Saateteksti' => (string) $l->viesti, 'Automaattiset muistutukset' => $l->auto_muistutus ? 'kyllä' : 'ei');
    };
    $diff = jr_log_diff($snap($old), $snap($upd));
    if ($diff) jr_log('lasku', $id, 'lasku_muokattu', $nimi, $diff);
    jr_redirect('jr-laskutus', array('view' => 'lasku', 'id' => $id, 'jr_msg' => 'lasku_updated'));
}

add_action('admin_post_jr_delete_lasku', 'jr_handle_delete_lasku');
function jr_handle_delete_lasku() {
    $id = intval(isset($_REQUEST['id']) ? $_REQUEST['id'] : 0);
    jr_check('jr_delete_lasku_' . $id);
    global $wpdb;
    $old = jr_get_lasku($id);
    if ($old) {
        $st = $wpdb->get_row($wpdb->prepare('SELECT COUNT(*) AS n, SUM(maksettu IS NOT NULL) AS mak, SUM(summa) AS yht FROM ' . jr_t('rivit') . ' WHERE lasku_id = %d', $id));
        jr_log('lasku', $id, 'lasku_poistettu', sprintf('%s (%d laskua, %d maksettu, yhteensä %s)', $old->nimi, $st->n, $st->mak, html_entity_decode(jr_money($st->yht))));
    }
    $wpdb->delete(jr_t('rivit'), array('lasku_id' => $id));
    $wpdb->delete(jr_t('laskut'), array('id' => $id));
    jr_redirect('jr-laskutus', array('jr_msg' => 'lasku_deleted'));
}

add_action('admin_post_jr_add_missing', 'jr_handle_add_missing');
function jr_handle_add_missing() {
    $id = intval(isset($_REQUEST['id']) ? $_REQUEST['id'] : 0);
    jr_check('jr_add_missing_' . $id);
    global $wpdb;
    $lasku = jr_get_lasku($id);
    if (!$lasku) jr_redirect('jr-laskutus');
    $members = $wpdb->get_results($wpdb->prepare(
        'SELECT * FROM ' . jr_t('jasenet') . " WHERE tila = 'aktiivinen' AND id NOT IN (SELECT jasen_id FROM " . jr_t('rivit') . ' WHERE lasku_id = %d)',
        $id
    ));
    $era = jr_late_era($lasku);
    $skipped = 0;
    $n = jr_add_rows($id, $members, $lasku->summa, jr_lasku_hinnat($lasku), $skipped, $era !== $lasku->erapaiva ? $era : null, (float) $lasku->postimaksu);
    if ($n) {
        $nimet = array();
        foreach ($members as $m) if (jr_member_price($m, jr_lasku_hinnat($lasku), $lasku->summa) > 0) $nimet[] = jr_log_member_name($m);
        jr_log('lasku', $id, 'jasenia_lisatty', $lasku->nimi . ': ' . $n . ' uutta jäsentä, eräpäivä ' . jr_date($era), array('nimet' => $nimet));
    }
    jr_redirect('jr-laskutus', array('view' => 'lasku', 'id' => $id, 'jr_msg' => 'rows_added', 'jr_n' => $n));
}

add_action('admin_post_jr_add_postage', 'jr_handle_add_postage');
function jr_handle_add_postage() {
    $id = intval(isset($_REQUEST['id']) ? $_REQUEST['id'] : 0);
    jr_check('jr_add_postage_' . $id);
    global $wpdb;
    $lasku = jr_get_lasku($id);
    if (!$lasku) jr_redirect('jr-laskutus');
    $rows = array_filter($wpdb->get_results($wpdb->prepare(jr_rivi_select_sql() . 'WHERE r.lasku_id = %d AND r.maksettu IS NULL AND r.lahetetty IS NULL', $id)), 'jr_is_paperi');
    $n = jr_set_postimaksu($lasku, $rows, true);
    jr_redirect('jr-laskutus', array('view' => 'lasku', 'id' => $id, 'jr_msg' => 'postage_updated', 'jr_n' => $n));
}

add_action('admin_post_jr_toggle_paid', 'jr_handle_toggle_paid');
function jr_handle_toggle_paid() {
    $rid = intval(isset($_REQUEST['rivi']) ? $_REQUEST['rivi'] : 0);
    jr_check('jr_paid_' . $rid);
    global $wpdb;
    $r = jr_get_rivi($rid);
    if (!$r) jr_redirect('jr-laskutus');
    $wpdb->update(jr_t('rivit'), $r->maksettu
        ? array('maksettu' => null, 'maksettu_summa' => null, 'maksaja' => null, 'arkistotunnus' => null)
        : array('maksettu' => current_time('Y-m-d')), array('id' => $rid));
    jr_log_rivi($r, $r->maksettu ? 'maksu_peruttu' : 'maksettu', $r->maksettu ? 'aiemmin merkitty ' . jr_date($r->maksettu) : 'käsin');
    jr_redirect('jr-laskutus', array('view' => 'lasku', 'id' => $r->lasku_id, 'jr_msg' => 'paid_updated', 'jr_n' => 1));
}

add_action('admin_post_jr_bulk_rows', 'jr_handle_bulk_rows');
function jr_handle_bulk_rows() {
    $lasku_id = intval(jr_post('lasku_id', 0));
    jr_check('jr_bulk_rows_' . $lasku_id);
    global $wpdb;
    $ids = array_filter(array_map('intval', (array) (isset($_POST['rivit']) ? $_POST['rivit'] : array())));
    if (!$ids) {
        jr_redirect('jr-laskutus', array('view' => 'lasku', 'id' => $lasku_id, 'jr_msg' => 'nothing_selected'));
    }
    $in = implode(',', $ids);
    $t  = jr_t('rivit');
    $op = jr_post('bulk_op');
    $kohteet = $wpdb->get_results($wpdb->prepare(jr_rivi_select_sql() . "WHERE r.lasku_id = %d AND r.id IN ($in)", $lasku_id));
    $bpvm = jr_date_or_null(jr_post('bulk_pvm'));
    foreach ($kohteet as $r) {
        if ($op === 'paid' && !$r->maksettu) jr_log_rivi($r, 'maksettu', 'joukkotoiminto, maksupäivä ' . jr_date($bpvm ? $bpvm : current_time('Y-m-d')));
        if ($op === 'unpaid' && $r->maksettu) jr_log_rivi($r, 'maksu_peruttu', 'joukkotoiminto');
        if ($op === 'remove') jr_log_rivi($r, 'poistettu_laskulta', 'viite ' . jr_viite_format($r->viite));
    }
    if ($op === 'posti_lisaa' || $op === 'posti_poista') {
        $lasku = jr_get_lasku($lasku_id);
        $n = jr_set_postimaksu($lasku, $kohteet, $op === 'posti_lisaa');
        jr_redirect('jr-laskutus', array('view' => 'lasku', 'id' => $lasku_id, 'jr_msg' => 'postage_updated', 'jr_n' => $n));
    }
    if ($op === 'paid') {
        $pvm = jr_date_or_null(jr_post('bulk_pvm'));
        if (!$pvm) $pvm = current_time('Y-m-d');
        $n = $wpdb->query($wpdb->prepare("UPDATE $t SET maksettu = %s WHERE lasku_id = %d AND id IN ($in)", $pvm, $lasku_id));
        jr_redirect('jr-laskutus', array('view' => 'lasku', 'id' => $lasku_id, 'jr_msg' => 'paid_updated', 'jr_n' => (int) $n));
    } elseif ($op === 'unpaid') {
        $n = $wpdb->query($wpdb->prepare("UPDATE $t SET maksettu = NULL, maksettu_summa = NULL, maksaja = NULL, arkistotunnus = NULL WHERE lasku_id = %d AND id IN ($in)", $lasku_id));
        jr_redirect('jr-laskutus', array('view' => 'lasku', 'id' => $lasku_id, 'jr_msg' => 'paid_updated', 'jr_n' => (int) $n));
    } elseif ($op === 'remove') {
        $wpdb->query($wpdb->prepare("DELETE FROM $t WHERE lasku_id = %d AND id IN ($in)", $lasku_id));
        jr_redirect('jr-laskutus', array('view' => 'lasku', 'id' => $lasku_id, 'jr_msg' => 'row_deleted'));
    }
    jr_redirect('jr-laskutus', array('view' => 'lasku', 'id' => $lasku_id, 'jr_msg' => 'nothing_selected'));
}

add_action('admin_post_jr_delete_rivi', 'jr_handle_delete_rivi');
function jr_handle_delete_rivi() {
    $rid = intval(isset($_REQUEST['rivi']) ? $_REQUEST['rivi'] : 0);
    jr_check('jr_delete_rivi_' . $rid);
    global $wpdb;
    $r = jr_get_rivi($rid);
    if (!$r) jr_redirect('jr-laskutus');
    $wpdb->delete(jr_t('rivit'), array('id' => $rid));
    jr_log_rivi($r, 'poistettu_laskulta', 'viite ' . jr_viite_format($r->viite));
    jr_redirect('jr-laskutus', array('view' => 'lasku', 'id' => $r->lasku_id, 'jr_msg' => 'row_deleted'));
}

add_action('admin_post_jr_send_one', 'jr_handle_send_one');
function jr_handle_send_one() {
    $rid = intval(isset($_REQUEST['rivi']) ? $_REQUEST['rivi'] : 0);
    jr_check('jr_send_one_' . $rid);
    $r = jr_get_rivi($rid);
    $lasku = $r ? jr_get_lasku($r->lasku_id) : null;
    if (!$lasku) jr_redirect('jr-laskutus');
    $reminder = !empty($_REQUEST['muistutus']);
    $ok = jr_send_rivi($lasku, $r, $reminder);
    if ($ok) jr_log_rivi($r, $reminder ? 'muistutettu' : 'lahetetty', $r->to_email, $lasku);
    jr_redirect('jr-laskutus', array('view' => 'lasku', 'id' => $lasku->id, 'jr_msg' => $ok ? 'sent_one' : 'send_failed'));
}

add_action('admin_post_jr_send_test', 'jr_handle_send_test');
function jr_handle_send_test() {
    $id = intval(isset($_REQUEST['id']) ? $_REQUEST['id'] : 0);
    jr_check('jr_send_test_' . $id);
    global $wpdb;
    $lasku = jr_get_lasku($id);
    $r = $lasku ? $wpdb->get_row($wpdb->prepare(jr_rivi_select_sql() . 'WHERE r.lasku_id = %d ORDER BY r.id LIMIT 1', $id)) : null;
    if (!$r) jr_redirect('jr-laskutus');
    $to = sanitize_email(jr_post('test_to'));
    if (!$to) jr_redirect('jr-laskutus', array('view' => 'lasku', 'id' => $id, 'jr_msg' => 'test_invalid'));
    $ok = jr_send_rivi($lasku, $r, jr_post('muistutus') === '1', $to);
    jr_redirect('jr-laskutus', array('view' => 'lasku', 'id' => $id, 'jr_msg' => $ok ? 'test_sent' : 'send_failed', 'jr_to' => $to));
}

add_action('admin_post_jr_preview', 'jr_handle_preview');
function jr_handle_preview() {
    $rid = intval(isset($_REQUEST['rivi']) ? $_REQUEST['rivi'] : 0);
    jr_check('jr_preview_' . $rid);
    $r = jr_get_rivi($rid);
    $lasku = $r ? jr_get_lasku($r->lasku_id) : null;
    if (!$lasku) wp_die('Laskua ei löytynyt.');
    nocache_headers();
    header('Content-Type: text/html; charset=UTF-8');
    echo jr_invoice_html($lasku, $r, !empty($_REQUEST['muistutus']), 'data'); // phpcs:ignore -- valmiiksi escapattu HTML
    exit;
}

add_action('admin_post_jr_export_lasku', 'jr_handle_export_lasku');
function jr_handle_export_lasku() {
    $id = intval(isset($_REQUEST['id']) ? $_REQUEST['id'] : 0);
    jr_check('jr_export_lasku_' . $id);
    global $wpdb;
    $lasku = jr_get_lasku($id);
    if (!$lasku) jr_redirect('jr-laskutus');
    $rows = $wpdb->get_results($wpdb->prepare(jr_rivi_select_sql() . 'WHERE r.lasku_id = %d ORDER BY r.nimi', $id));
    $out = array();
    foreach ($rows as $r) {
        $p = jr_rivi_person($r);
        $out[] = array($p['nimi'], $p['tunnus'], $r->to_email, jr_viite_format($r->viite), number_format((float) $r->summa, 2, ',', ''), jr_date(jr_rivi_era($lasku, $r)),
            $r->lahetetty ? jr_date($r->lahetetty) : '', $r->muistutettu ? (int) $r->muistutuksia . ' kertaa, viimeksi ' . jr_date($r->muistutettu) : '',
            $r->maksettu ? jr_date($r->maksettu) : '', $r->maksettu_summa !== null ? number_format((float) $r->maksettu_summa, 2, ',', '') : '',
            (string) $r->maksaja, (string) $r->arkistotunnus, $r->virhe);
    }
    jr_log('vienti', 0, 'vienti', 'Lasku ' . $lasku->nimi . ' CSV-tiedostoon');
    jr_csv_output(sanitize_file_name($lasku->nimi) . '.csv',
        array('Nimi', jr_tunnus_nimi(), 'Sähköposti', 'Viite', 'Summa', 'Eräpäivä', 'Lähetetty', 'Muistutettu', 'Maksettu', 'Maksettu summa', 'Maksaja', 'Arkistointitunnus', 'Virhe'), $out);
}

/* ------------------------------------------------------------------
 * AJAX: laskujen lähetys pienissä erissä (ei aikakatkaisuja, edistymispalkki)
 * ------------------------------------------------------------------ */

add_action('wp_ajax_jr_send_chunk', 'jr_ajax_send_chunk');
function jr_ajax_send_chunk() {
    if (!current_user_can('manage_options')) wp_send_json_error('Ei oikeuksia', 403);
    $lasku_id = intval(isset($_POST['lasku_id']) ? $_POST['lasku_id'] : 0);
    check_ajax_referer('jr_send_chunk_' . $lasku_id, 'nonce');

    global $wpdb;
    $lasku = jr_get_lasku($lasku_id);
    if (!$lasku) wp_send_json_error('Laskua ei löytynyt');

    $reminder  = (isset($_POST['mode']) ? $_POST['mode'] : '') === 'muistutus';
    $run_start = isset($_POST['run_start']) ? sanitize_text_field(wp_unslash($_POST['run_start'])) : '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $run_start)) wp_send_json_error('Virheellinen pyyntö');

    $cond = $wpdb->prepare(
        "WHERE r.lasku_id = %d AND (r.viim_yritys IS NULL OR r.viim_yritys < %s) AND COALESCE(NULLIF(m.email, ''), r.email) <> '' AND COALESCE(m.paperilasku, 0) = 0 ",
        $lasku_id, $run_start
    );
    if ($reminder) {
        // Samana päivänä jo muistutetuille ei lähetetä uudelleen (estää vahingossa tehdyn tuplalähetyksen).
        $cond .= $wpdb->prepare('AND r.maksettu IS NULL AND (r.muistutettu IS NULL OR r.muistutettu < %s) ', current_time('Y-m-d') . ' 00:00:00');
    } else {
        $cond .= 'AND r.lahetetty IS NULL ';
    }

    $rows = $wpdb->get_results(jr_rivi_select_sql() . $cond . 'ORDER BY r.id LIMIT 5');
    $sent = 0; $failed = 0; $errors = array(); $names = array();
    foreach ($rows as $r) {
        if (jr_send_rivi($lasku, $r, $reminder)) {
            $sent++;
            $names[] = jr_rivi_person($r)['nimi'];
        } else {
            $failed++;
            $errors[] = jr_rivi_person($r)['nimi'] . ': ' . ($GLOBALS['jr_mail_error'] ?: 'lähetys epäonnistui');
        }
    }
    $remaining = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . jr_t('rivit') . ' r LEFT JOIN ' . jr_t('jasenet') . ' m ON m.id = r.jasen_id ' . $cond);
    jr_log_run($lasku, $reminder ? 'muistutukset' : 'lahetys', $run_start, $names, $failed);

    wp_send_json_success(array('sent' => $sent, 'failed' => $failed, 'remaining' => $remaining, 'errors' => $errors));
}

/* ------------------------------------------------------------------
 * Sivut
 * ------------------------------------------------------------------ */

function jr_page_invoices() {
    $view = isset($_GET['view']) ? sanitize_key($_GET['view']) : '';
    echo '<div class="wrap jr-wrap">';
    if ($view === 'new') {
        jr_invoice_new_form();
    } elseif ($view === 'maksutuonti') {
        jr_payments_preview();
    } elseif ($view === 'lasku' && !empty($_GET['id'])) {
        jr_invoice_detail(intval($_GET['id']));
    } else {
        jr_invoice_list();
    }
    echo '</div>';
}

function jr_settings_warning() {
    $s = jr_settings();
    if ($s['iban'] === '' || !jr_iban_valid($s['iban'])) {
        echo '<div class="notice notice-warning"><p><strong>Tilinumero puuttuu tai on virheellinen.</strong> Lisää yhdistyksen IBAN '
            . '<a href="' . esc_url(admin_url('admin.php?page=jr-asetukset')) . '">asetuksiin</a> ennen laskujen lähettämistä.</p></div>';
        return true;
    }
    return false;
}

function jr_invoice_list() {
    global $wpdb;
    delete_transient('jr_payimp_' . get_current_user_id()); // keskeneräinen maksujen tuonti peruttu
    $laskut = $wpdb->get_results(
        $wpdb->prepare('SELECT l.*, COUNT(r.id) AS n, SUM(r.lahetetty IS NOT NULL) AS lah, SUM(r.maksettu IS NOT NULL) AS mak, MIN(r.summa - r.postimaksu) AS min_s, MAX(r.summa - r.postimaksu) AS max_s, '
        . 'SUM(r.maksettu IS NULL AND COALESCE(r.erapaiva, l.erapaiva) < %s) AS erant, ', current_time('Y-m-d'))
        . 'SUM(CASE WHEN r.maksettu IS NOT NULL THEN r.summa ELSE 0 END) AS mak_summa, SUM(r.summa) AS yht '
        . 'FROM ' . jr_t('laskut') . ' l LEFT JOIN ' . jr_t('rivit') . ' r ON r.lasku_id = l.id GROUP BY l.id ORDER BY l.id DESC'
    );
    $today = current_time('Y-m-d');
    ?>
    <h1 class="wp-heading-inline">Jäsenmaksulaskutus</h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=jr-laskutus&view=new')); ?>" class="page-title-action">Uusi lasku</a>
    <?php if ($laskut): ?>
    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=jr_export_xlsx&id=0'), 'jr_export_xlsx_0')); ?>" class="page-title-action" title="Yhteenveto, kaikki avoimet maksut ja jokainen lasku omalla välilehdellään">Vie kaikki laskut Exceliin</a>
    <?php endif; ?>
    <hr class="wp-header-end">
    <?php jr_notice(); jr_settings_warning(); ?>

    <?php if (!$laskut): ?>
        <div class="jr-panel jr-empty-state">
            <h2>Ei vielä laskuja</h2>
            <p>Luo esimerkiksi ”Jäsenmaksu 2026” -lasku. Jokainen aktiivinen jäsen saa oman laskun omalla viitenumerollaan sähköpostiin.</p>
            <p><a class="button button-primary button-hero" href="<?php echo esc_url(admin_url('admin.php?page=jr-laskutus&view=new')); ?>">Luo ensimmäinen lasku</a></p>
        </div>
    <?php else: ?>
    <table class="widefat striped jr-table">
        <thead><tr>
            <th>Lasku</th><th>Summa</th><th>Eräpäivä</th><th>Lähetetty</th><th>Maksettu</th><th>Kertynyt / yhteensä</th>
        </tr></thead>
        <tbody>
        <?php foreach ($laskut as $l):
            $n = (int) $l->n; $pct = $n ? round($l->mak / $n * 100) : 0;
            $url = admin_url('admin.php?page=jr-laskutus&view=lasku&id=' . $l->id); ?>
            <tr>
                <td><a class="jr-name" href="<?php echo esc_url($url); ?>"><?php echo esc_html($l->nimi); ?></a>
                    <div class="jr-sub">Luotu <?php echo esc_html(jr_date($l->luotu)); ?></div></td>
                <td><?php echo esc_html(jr_summa_range($l->min_s, $l->max_s, $l->summa)); ?></td>
                <td><?php echo esc_html(jr_date($l->erapaiva)); ?>
                    <?php if ((int) $l->erant > 0): ?><span class="jr-badge jr-badge-err"><?php echo (int) $l->erant; ?> erääntynyt</span><?php endif; ?></td>
                <td><?php echo (int) $l->lah . ' / ' . $n; ?></td>
                <td><div class="jr-meter" title="<?php echo esc_attr($pct . ' %'); ?>"><span style="width:<?php echo (int) $pct; ?>%"></span></div>
                    <?php echo (int) $l->mak . ' / ' . $n; ?></td>
                <td><?php echo esc_html(jr_money($l->mak_summa)) . ' / ' . esc_html(jr_money($l->yht)); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php jr_payments_upload_panel(); ?>
    <?php endif;
}

function jr_invoice_new_form() {
    global $wpdb;
    $s = jr_settings();
    $members = $wpdb->get_results('SELECT * FROM ' . jr_t('jasenet') . ' ORDER BY tila ASC, sukunimi, etunimi');
    $n_akt = 0; $n_noemail = 0; $n_mv = 0; $n_pap = 0;
    $lajit = jr_jasenlajit();
    $laji_n = array();
    foreach ($members as $m) {
        if ($m->tila === 'eronnut') continue;
        $k = isset($lajit[$m->jasenlaji]) ? $m->jasenlaji : '';
        $laji_n[$k] = isset($laji_n[$k]) ? $laji_n[$k] + 1 : 1;
        if (jr_is_maksuvapaa($m)) { $n_mv++; continue; }
        $n_akt++;
        if (!$m->email) $n_noemail++;
        elseif ($m->paperilasku) $n_pap++;
    }
    $year = (int) current_time('Y');
    $suggest_year = ((int) current_time('n') >= 10) ? $year + 1 : $year;
    ?>
    <h1>Uusi lasku</h1>
    <p><a href="<?php echo esc_url(admin_url('admin.php?page=jr-laskutus')); ?>">&larr; Takaisin laskuihin</a></p>
    <?php jr_notice(); jr_settings_warning(); ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="jr-panel jr-form">
        <input type="hidden" name="action" value="jr_create_lasku">
        <?php wp_nonce_field('jr_create_lasku'); ?>

        <div class="jr-grid">
            <p class="jr-span-2"><label for="jr-nimi">Laskun nimi *</label>
               <input type="text" id="jr-nimi" name="nimi" required value="<?php echo esc_attr('Jäsenmaksu ' . $suggest_year); ?>"></p>
            <?php if (!$lajit): ?>
            <p><label for="jr-summa">Summa (€) *</label>
               <input type="text" id="jr-summa" name="summa" required inputmode="decimal" placeholder="esim. 25,00"></p>
            <?php endif; ?>
            <p><label for="jr-laskupvm">Laskun päiväys</label>
               <input type="date" id="jr-laskupvm" name="laskupvm" value="<?php echo esc_attr(current_time('Y-m-d')); ?>"></p>
            <p><label for="jr-erapaiva">Eräpäivä *</label>
               <input type="date" id="jr-erapaiva" name="erapaiva" required value="<?php echo esc_attr(date('Y-m-d', strtotime(current_time('Y-m-d') . ' +21 days'))); ?>"></p>
            <p><label for="jr-postimaksu">Postimaksu paperilaskuihin (€)</label>
               <input type="text" id="jr-postimaksu" name="postimaksu" inputmode="decimal" value="<?php echo esc_attr(number_format(jr_postimaksu_oletus(), 2, ',', '')); ?>">
               <span class="description">Lisätään vain paperilaskun saajien laskuihin<?php echo ($n_noemail + $n_pap) ? ' (' . (int) ($n_noemail + $n_pap) . ' jäsentä)' : ''; ?>. 0 = ei postimaksua. Oletus <a href="<?php echo esc_url(admin_url('admin.php?page=jr-asetukset#jr-paperi-asetukset')); ?>">asetuksista</a>.</span></p>
        </div>

        <?php if ($lajit): ?>
        <fieldset class="jr-fieldset">
            <legend>Jäsenmaksut jäsenluokittain</legend>
            <table class="jr-hinnat">
                <?php foreach ($lajit as $lid => $l): ?>
                <tr><td><label for="jr-h-<?php echo esc_attr($lid); ?>"><?php echo esc_html($l['nimi']); ?></label></td>
                    <td><input type="text" id="jr-h-<?php echo esc_attr($lid); ?>" name="hinta[<?php echo esc_attr($lid); ?>]" inputmode="decimal" value="<?php echo esc_attr(number_format((float) $l['summa'], 2, ',', '')); ?>"> €</td>
                    <td class="jr-muted"><?php echo isset($laji_n[$lid]) ? (int) $laji_n[$lid] : 0; ?> jäsentä<?php echo (float) $l['summa'] <= 0 ? ' · ei laskua' : ''; ?></td></tr>
                <?php endforeach; ?>
                <?php if (!empty($laji_n[''])): ?>
                <tr><td><label for="jr-summa">Ilman jäsenluokkaa</label></td>
                    <td><input type="text" id="jr-summa" name="summa" inputmode="decimal" value="" placeholder="0,00"> €</td>
                    <td class="jr-muted"><?php echo (int) $laji_n['']; ?> jäsentä</td></tr>
                <?php else: ?>
                <input type="hidden" name="summa" value="0">
                <?php endif; ?>
            </table>
            <p class="description">Summat haetaan asetuksista, ja niitä voi muuttaa tälle laskulle. Jos summa on 0 €, sen luokan jäsenille ei tehdä laskua (esim. kunniajäsenet).</p>
        </fieldset>
        <?php endif; ?>

        <p><label for="jr-viesti">Saateteksti</label>
           <textarea id="jr-viesti" name="viesti" rows="8"><?php echo esc_textarea($s['viesti_oletus']); ?></textarea>
           <?php echo jr_placeholders('jr-viesti', 'lasku', 'Laskun tiedot (tilinumero, viite, summa, eräpäivä, QR- ja viivakoodi) lisätään viestiin automaattisesti.'); ?></p>

        <fieldset class="jr-fieldset">
            <legend>Kenelle lasku tehdään?</legend>
            <label class="jr-radio"><input type="radio" name="vastaanottajat" value="aktiiviset" checked>
                Kaikille jäsenmaksun maksaville jäsenille (<?php echo (int) $n_akt; ?>)<?php if ($n_mv): ?> <span class="jr-muted">– <?php echo (int) $n_mv; ?> jäsentä, joiden jäsenmaksu on 0 €, jätetään pois</span><?php endif; ?></label>
            <label class="jr-radio"><input type="radio" name="vastaanottajat" value="valitut" data-jr-toggle="#jr-member-pick">
                Valituille jäsenille</label>
            <?php if ($n_noemail || $n_pap): ?>
                <p class="jr-hint">📄 <?php echo (int) ($n_noemail + $n_pap); ?> laskutettavaa saa laskun paperilla (<?php echo $n_pap ? (int) $n_pap . ' haluaa paperilaskun' : ''; ?><?php echo $n_pap && $n_noemail ? ', ' : ''; ?><?php echo $n_noemail ? (int) $n_noemail . ' ilman sähköpostia' : ''; ?>). Heidän laskunsa tulostetaan laskun sivulta ikkunakuoreen sopivina.</p>
            <?php endif; ?>
            <div id="jr-member-pick" class="jr-member-pick" hidden>
                <p><input type="search" class="jr-search" data-jr-filter="#jr-pick-list" placeholder="Hae…">
                   <label><input type="checkbox" class="jr-check-all" data-target="#jr-pick-list"> Valitse kaikki näkyvät</label></p>
                <div id="jr-pick-list" class="jr-pick-list">
                <?php foreach ($members as $m):
                    $search = preg_replace('/\s+/', '', strtolower(remove_accents($m->etunimi . $m->sukunimi . $m->tunnus))); ?>
                    <label data-search="<?php echo esc_attr($search); ?>">
                        <input type="checkbox" name="jasenet[]" value="<?php echo esc_attr($m->id); ?>">
                        <?php echo esc_html($m->sukunimi . ' ' . $m->etunimi); ?>
                        <?php if ($m->tunnus && jr_tunnus_on()): ?><span class="jr-call"><?php echo esc_html($m->tunnus); ?></span><?php endif; ?>
                        <?php echo jr_laji_badge($m->jasenlaji); ?>
                        <?php echo jr_tila_badge($m->tila); ?>
                        <?php if (!$m->email): ?><span class="jr-badge jr-badge-warn">ei sähköpostia</span><?php elseif ($m->paperilasku): ?><span class="jr-badge jr-badge-info">paperilasku</span><?php endif; ?>
                    </label>
                <?php endforeach; ?>
                </div>
            </div>
        </fieldset>

        <p class="jr-actions">
            <button type="submit" class="button button-primary button-large">Luo laskut</button>
            <span class="description">Laskuja ei vielä lähetetä – voit tarkistaa ne ja lähettää testilaskun ensin.</span>
        </p>
    </form>
    <?php
}

/** Laskurivin maksutilanne merkkinä. */
function jr_pay_badge($lasku, $r, $today) {
    if ($r->maksettu) {
        $h = '<span class="jr-badge jr-badge-ok">Maksettu ' . esc_html(jr_date($r->maksettu)) . '</span>';
        if ($r->maksettu_summa !== null && abs((float) $r->maksettu_summa - (float) $r->summa) > 0.004) {
            $h .= '<div><span class="jr-badge jr-badge-orange">Maksettu summa ' . esc_html(jr_money($r->maksettu_summa)) . '</span></div>';
        }
        if ($r->maksaja) $h .= '<div class="jr-sub">Maksaja: ' . esc_html($r->maksaja) . '</div>';
        return $h;
    }
    if (jr_rivi_era($lasku, $r) < $today) return '<span class="jr-badge jr-badge-err">Erääntynyt</span>';
    return '<span class="jr-badge jr-badge-info">Avoin</span>';
}

function jr_invoice_detail($id) {
    global $wpdb;
    $lasku = jr_get_lasku($id);
    if (!$lasku) { echo '<p>Laskua ei löytynyt.</p>'; return; }

    $rows  = $wpdb->get_results($wpdb->prepare(jr_rivi_select_sql() . 'WHERE r.lasku_id = %d ORDER BY m.sukunimi, m.etunimi, r.nimi', $id));
    $today = current_time('Y-m-d');
    $no_iban = false;

    $st = array('n' => 0, 'lah' => 0, 'mak' => 0, 'mak_summa' => 0, 'avoin_summa' => 0, 'unsent' => 0, 'unpaid_mail' => 0, 'noemail' => 0, 'muist_tanaan' => 0, 'muist_aiemmin' => 0, 'min' => null, 'max' => null, 'erant' => 0,
                'paperi' => 0, 'p_unsent' => 0, 'p_remind' => 0, 'p_noaddr' => 0, 'p_nopost' => 0);
    $paper_rows = array();
    foreach ($rows as $r) {
        $st['n']++;
        $jm = jr_rivi_jasenmaksu($r); // korttiin jäsenmaksut ilman postimaksua
        $st['min'] = $st['min'] === null ? $jm : min($st['min'], $jm);
        $st['max'] = $st['max'] === null ? $jm : max($st['max'], $jm);
        if ($r->lahetetty) $st['lah']++;
        if ($r->maksettu) { $st['mak']++; $st['mak_summa'] += $r->summa; } else { $st['avoin_summa'] += $r->summa; if (jr_rivi_era($lasku, $r) < $today) $st['erant'] += $r->summa; }
        if (jr_is_paperi($r)) {
            $st['paperi']++;
            if (!$r->to_email) $st['noemail']++;
            if (!$r->lahetetty) $st['p_unsent']++;
            elseif (!$r->maksettu && jr_rivi_era($lasku, $r) < $today) $st['p_remind']++;
            if (trim((string) $r->osoite) === '') $st['p_noaddr']++;
            if (!$r->maksettu && !$r->lahetetty && jr_rivi_postimaksu($r) <= 0) $st['p_nopost']++;
            $paper_rows[] = $r;
            continue;
        }
        if (!$r->lahetetty) $st['unsent']++;
        if (!$r->maksettu) {
            if ($r->muistutettu && substr($r->muistutettu, 0, 10) === $today) { $st['muist_tanaan']++; }
            else { $st['unpaid_mail']++; if ($r->muistutettu) $st['muist_aiemmin']++; }
        }
    }
    $n_missing = 0;
    $hinnat = jr_lasku_hinnat($lasku);
    foreach ($wpdb->get_results($wpdb->prepare('SELECT * FROM ' . jr_t('jasenet') . " WHERE tila = 'aktiivinen' AND id NOT IN (SELECT jasen_id FROM " . jr_t('rivit') . ' WHERE lasku_id = %d)', $id)) as $mm) {
        if (jr_member_price($mm, $hinnat, $lasku->summa) > 0) $n_missing++;
    }

    $post_url   = admin_url('admin-post.php');
    $run_start  = current_time('mysql');
    $send_nonce = wp_create_nonce('jr_send_chunk_' . $id);
    $is_overdue = $lasku->erapaiva < $today; // laskun yleinen eräpäivä
    ?>
    <h1 class="wp-heading-inline"><?php echo esc_html($lasku->nimi); ?></h1>
    <hr class="wp-header-end">
    <p><a href="<?php echo esc_url(admin_url('admin.php?page=jr-laskutus')); ?>">&larr; Kaikki laskut</a></p>
    <?php jr_notice(); $no_iban = jr_settings_warning(); ?>

    <div class="jr-cards">
        <div class="jr-card"><span class="jr-card-num"><?php echo esc_html(jr_summa_range($st['min'], $st['max'], $lasku->summa)); ?></span><span class="jr-card-label">Eräpäivä <?php echo esc_html(jr_date($lasku->erapaiva)); ?></span></div>
        <div class="jr-card"><span class="jr-card-num"><?php echo $st['lah'] . ' / ' . $st['n']; ?></span><span class="jr-card-label">Lähetetty</span></div>
        <div class="jr-card jr-card-ok"><span class="jr-card-num"><?php echo $st['mak'] . ' / ' . $st['n']; ?></span><span class="jr-card-label">Maksettu (<?php echo esc_html(jr_money($st['mak_summa'])); ?>)</span></div>
        <div class="jr-card <?php echo $st['erant'] > 0 ? 'jr-card-err' : ''; ?>"><span class="jr-card-num"><?php echo esc_html(jr_money($st['avoin_summa'])); ?></span><span class="jr-card-label">Avoinna<?php
            if ($st['erant'] > 0) echo abs($st['erant'] - $st['avoin_summa']) < 0.005 ? ' – erääntynyt' : esc_html(', josta erääntynyt ' . jr_money($st['erant'])); ?></span></div>
    </div>

    <div class="jr-panel jr-send-panel">
        <h2>Lähetys</h2>
        <div class="jr-send-actions">
            <?php if ($st['unsent'] > 0): ?>
                <button type="button" class="button button-primary button-large jr-run"
                    <?php disabled($no_iban); ?>
                    data-action="jr_send_chunk"
                    data-params="<?php echo esc_attr(wp_json_encode(array('lasku_id' => $id, 'mode' => 'lasku', 'run_start' => $run_start, 'nonce' => $send_nonce))); ?>"
                    data-confirm="<?php echo esc_attr('Lähetetäänkö lasku ' . $st['unsent'] . ' jäsenelle sähköpostilla?'); ?>">
                    Lähetä laskut (<?php echo (int) $st['unsent']; ?> lähettämätöntä)</button>
            <?php else: ?>
                <span class="jr-badge jr-badge-ok jr-badge-lg">✓ Kaikki sähköpostilaskut lähetetty</span>
            <?php endif; ?>

            <?php if ($st['lah'] > 0 && $st['unpaid_mail'] > 0): ?>
                <button type="button" class="button button-large jr-run"
                    data-action="jr_send_chunk"
                    data-params="<?php echo esc_attr(wp_json_encode(array('lasku_id' => $id, 'mode' => 'muistutus', 'run_start' => $run_start, 'nonce' => $send_nonce))); ?>"
                    data-confirm="<?php echo esc_attr('Lähetetäänkö maksumuistutus ' . $st['unpaid_mail'] . ' maksamattomalle jäsenelle?' . ($st['muist_aiemmin'] ? ' Heistä ' . $st['muist_aiemmin'] . ' on jo muistutettu aiemmin.' : '')); ?>">
                    Lähetä muistutus maksamattomille (<?php echo (int) $st['unpaid_mail']; ?>)</button>
            <?php elseif ($st['muist_tanaan'] > 0): ?>
                <span class="jr-badge jr-badge-orange jr-badge-lg">Maksamattomia muistutettu tänään (<?php echo (int) $st['muist_tanaan']; ?>)</span>
            <?php endif; ?>

            <?php if ($rows): ?>
                <a class="button" target="_blank" href="<?php echo esc_url(wp_nonce_url($post_url . '?action=jr_preview&rivi=' . $rows[0]->id, 'jr_preview_' . $rows[0]->id)); ?>">Esikatsele laskua</a>
            <?php endif; ?>
        </div>
        <?php if ($rows): $sample = jr_rivi_person($rows[0]); ?>
        <form method="post" action="<?php echo esc_url($post_url); ?>" class="jr-testbox">
            <input type="hidden" name="action" value="jr_send_test">
            <input type="hidden" name="id" value="<?php echo esc_attr($id); ?>">
            <?php wp_nonce_field('jr_send_test_' . $id); ?>
            <label for="jr-test-to">Testilasku osoitteeseen</label>
            <input type="email" id="jr-test-to" name="test_to" required value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>">
            <select name="muistutus"><option value="0">lasku</option><option value="1">muistutus</option></select>
            <button type="submit" class="button">Lähetä testi</button>
            <span class="description">Oletuksena sinun WordPress-tunnuksesi osoite (<?php echo esc_html(wp_get_current_user()->display_name); ?>). Testissä käytetään jäsenen <?php echo esc_html($sample['nimi']); ?> laskun tietoja, mutta jäsenelle ei lähetetä mitään.</span>
        </form>
        <?php endif; ?>
        <div class="jr-progress" hidden><div class="jr-progress-bar"><span></span></div><p class="jr-progress-text"></p></div>
        <?php $auto = jr_auto_first_date($lasku); if (jr_settings()['auto_muistutus'] === '1'): ?>
            <p class="description jr-auto-info">🔔 Automaattinen muistutus:
            <?php if ($auto): ?>
                päällä – <?php echo esc_html(jr_settings()['auto_paivia']); ?> päivää eräpäivän jälkeen<?php echo $auto >= $today ? ' (ensimmäinen ' . esc_html(jr_date($auto)) . ')' : ''; ?>, enintään <?php echo (int) jr_settings()['auto_max']; ?> kpl.
            <?php else: ?>
                pois päältä tältä laskulta.
            <?php endif; ?></p>
        <?php endif; ?>
        <?php if ($st['paperi']): ?>
            <p class="description">📄 <?php echo (int) $st['paperi']; ?> jäsentä saa laskun paperilla, eikä heille lähetetä sähköpostia – ks. <a href="#jr-paperi">Paperilaskut</a>.</p>
        <?php endif; ?>
    </div>

    <?php if ($paper_rows): ?>
    <div class="jr-panel" id="jr-paperi">
        <h2>Paperilaskut (<?php echo (int) $st['paperi']; ?>)</h2>
        <p class="description"><?php
            $pref = $st['paperi'] - $st['noemail'];
            echo esc_html(implode(', ', array_filter(array($pref ? $pref . ' haluaa laskun paperilla' : '', $st['noemail'] ? $st['noemail'] . ' ilman sähköpostia' : '')))) . '.'; ?>
            Tulosta laskut, taita ja laita ikkunakuoreen (C5 tai DL) – nimi ja osoite osuvat ikkunaan. Merkitse sitten postitetuksi, niin näet mitkä on hoidettu.</p>
        <div class="jr-send-actions">
            <?php if ($st['p_unsent']): ?>
                <a class="button button-primary button-large" target="_blank" href="<?php echo esc_url(jr_print_url($id, array('joukko' => 'postittamattomat'))); ?>" <?php echo $no_iban ? 'aria-disabled="true"' : ''; ?>>🖨 Tulosta postittamattomat (<?php echo (int) $st['p_unsent']; ?>)</a>
            <?php else: ?>
                <span class="jr-badge jr-badge-ok jr-badge-lg">✓ Kaikki paperilaskut postitettu</span>
            <?php endif; ?>
            <?php if ($st['p_remind']): ?>
                <a class="button button-large" target="_blank" href="<?php echo esc_url(jr_print_url($id, array('joukko' => 'muistutukset'))); ?>">🖨 Tulosta muistutukset erääntyneille (<?php echo (int) $st['p_remind']; ?>)</a>
            <?php endif; ?>
            <a class="button" target="_blank" href="<?php echo esc_url(jr_print_url($id, array('joukko' => 'kaikki'))); ?>">Näytä kaikki paperilaskut</a>
        </div>
        <?php $pm_lasku = (float) $lasku->postimaksu > 0 ? (float) $lasku->postimaksu : jr_postimaksu_oletus();
        if ($st['p_nopost'] && $pm_lasku > 0): ?>
            <p class="jr-hint">📮 <?php echo (int) $st['p_nopost']; ?> postittamattomalta paperilaskulta puuttuu postimaksu (esim. jäsen valitsi paperilaskun laskun teon jälkeen).
                <a class="button button-small" href="<?php echo esc_url(wp_nonce_url($post_url . '?action=jr_add_postage&id=' . $id, 'jr_add_postage_' . $id)); ?>"
                   data-confirm="<?php echo esc_attr('Lisätäänkö postimaksu ' . html_entity_decode(jr_money($pm_lasku)) . ' ' . $st['p_nopost'] . ' paperilaskuun?'); ?>">Lisää postimaksu <?php echo esc_html(jr_money($pm_lasku)); ?></a></p>
        <?php endif; ?>
        <?php if ($st['p_noaddr']): ?>
            <p class="jr-hint">⚠ <?php echo (int) $st['p_noaddr']; ?> paperilaskun saajalta puuttuu osoite. Lisää osoite jäsenen tietoihin.</p>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url($post_url); ?>">
            <input type="hidden" name="action" value="jr_paper_mark">
            <input type="hidden" name="lasku_id" value="<?php echo esc_attr($id); ?>">
            <?php wp_nonce_field('jr_paper_mark_' . $id); ?>
            <table class="widefat striped jr-table" id="jr-paper-rows">
                <thead><tr>
                    <td class="check-column"><input type="checkbox" class="jr-check-all" data-target="#jr-paper-rows"></td>
                    <th>Jäsen</th><th>Postiosoite</th><th>Viite</th><th>Summa</th><th>Postitus</th><th>Maksu</th><th class="jr-col-actions">Toiminnot</th>
                </tr></thead>
                <tbody>
                <?php foreach ($paper_rows as $r):
                    $p = jr_rivi_person($r); ?>
                    <tr>
                        <th class="check-column"><input type="checkbox" name="rivit[]" value="<?php echo esc_attr($r->id); ?>"></th>
                        <td><strong><?php echo esc_html($p['nimi']); ?></strong>
                            <div class="jr-sub"><?php echo $r->paperilasku ? 'haluaa paperilaskun' : 'ei sähköpostia'; ?></div></td>
                        <td><?php echo trim((string) $r->osoite) !== '' ? esc_html($r->osoite) : '<span class="jr-badge jr-badge-warn">osoite puuttuu</span>'; ?></td>
                        <td><code class="jr-ref"><?php echo esc_html(jr_viite_format($r->viite)); ?></code></td>
                        <td><?php echo esc_html(jr_money($r->summa)); ?>
                            <?php if (jr_rivi_postimaksu($r) > 0): ?><div class="jr-sub">sis. postimaksun <?php echo esc_html(jr_money(jr_rivi_postimaksu($r))); ?></div><?php endif; ?></td>
                        <td><?php if ($r->lahetetty): ?><span class="jr-badge jr-badge-ok">Postitettu <?php echo esc_html(jr_date($r->lahetetty)); ?></span><?php else: ?><span class="jr-badge jr-badge-muted">Ei postitettu</span><?php endif; ?>
                            <?php if ($r->muistutettu): ?><div><span class="jr-badge jr-badge-orange">Muistutettu <?php echo (int) $r->muistutuksia > 1 ? (int) $r->muistutuksia . '× · ' : ''; ?><?php echo esc_html(jr_date($r->muistutettu)); ?></span></div><?php endif; ?></td>
                        <td><?php echo jr_pay_badge($lasku, $r, $today); // phpcs:ignore ?></td>
                        <td class="jr-col-actions">
                            <a class="button button-small" target="_blank" href="<?php echo esc_url(jr_print_url($id, array('rivi' => $r->id))); ?>">Näytä lasku</a>
                            <a class="button button-small" target="_blank" href="<?php echo esc_url(jr_print_url($id, array('rivi' => $r->id, 'tulosta' => 1))); ?>">🖨 Tulosta</a>
                            <?php if (!$r->maksettu && $r->lahetetty && jr_rivi_era($lasku, $r) < $today): ?>
                                <a class="button button-small" target="_blank" href="<?php echo esc_url(jr_print_url($id, array('rivi' => $r->id, 'muistutus' => 1, 'tulosta' => 1))); ?>">🖨 Muistutus</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="jr-bulk">
                <label for="jr-paper-op"><strong>Valituille:</strong></label>
                <select name="tapa" id="jr-paper-op">
                    <option value="lasku">Merkitse lasku postitetuksi tänään</option>
                    <option value="muistutus">Merkitse muistutus postitetuksi tänään</option>
                </select>
                <button type="submit" class="button" data-confirm="Merkitäänkö valitut postitetuiksi?">Suorita</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url($post_url); ?>" id="jr-bulk-form">
        <input type="hidden" name="action" value="jr_bulk_rows">
        <input type="hidden" name="lasku_id" value="<?php echo esc_attr($id); ?>">
        <?php wp_nonce_field('jr_bulk_rows_' . $id); ?>

        <div class="jr-toolbar">
            <ul class="subsubsub jr-tabs" data-jr-status-for="#jr-rows">
                <li><a href="#" data-status="" class="current">Kaikki (<?php echo $st['n']; ?>)</a></li>
                <li><a href="#" data-status="avoin">Avoimet (<?php echo $st['n'] - $st['mak']; ?>)</a></li>
                <li><a href="#" data-status="maksettu">Maksetut (<?php echo $st['mak']; ?>)</a></li>
                <li><a href="#" data-status="lahettamaton">Lähettämättömät (<?php echo $st['n'] - $st['lah']; ?>)</a></li>
                <?php if ($st['paperi']): ?><li><a href="#" data-status="paperi">Paperilaskut (<?php echo $st['paperi']; ?>)</a></li><?php endif; ?>
            </ul>
            <div class="jr-toolbar-right">
                <input type="search" class="jr-search" data-jr-filter="#jr-rows" placeholder="Hae viitteellä, nimellä tai sähköpostilla…">
            </div>
        </div>

        <table class="widefat striped jr-table" id="jr-rows">
            <thead><tr>
                <td class="check-column"><input type="checkbox" class="jr-check-all" data-target="#jr-rows"></td>
                <th>Jäsen</th><th>Viite</th><th>Summa</th><th>Sähköposti</th><th>Maksu</th><th class="jr-col-actions">Toiminnot</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="7" class="jr-empty">Laskulla ei ole rivejä.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r):
                $p = jr_rivi_person($r);
                $status = array($r->maksettu ? 'maksettu' : 'avoin');
                if (!$r->lahetetty) $status[] = 'lahettamaton';
                $paperi = jr_is_paperi($r);
                if ($paperi) $status[] = 'paperi';
                $search = preg_replace('/\s+/', '', strtolower(remove_accents($p['nimi'] . $p['tunnus'] . $r->to_email)) . $r->viite);
                $paid_url = wp_nonce_url($post_url . '?action=jr_toggle_paid&rivi=' . $r->id, 'jr_paid_' . $r->id);
                $send_url = wp_nonce_url($post_url . '?action=jr_send_one&rivi=' . $r->id, 'jr_send_one_' . $r->id);
                $prev_url = wp_nonce_url($post_url . '?action=jr_preview&rivi=' . $r->id, 'jr_preview_' . $r->id);
                ?>
                <tr data-search="<?php echo esc_attr($search); ?>" data-status="<?php echo esc_attr(implode(' ', $status)); ?>">
                    <th class="check-column"><input type="checkbox" name="rivit[]" value="<?php echo esc_attr($r->id); ?>"></th>
                    <td><strong><?php echo esc_html($p['nimi']); ?></strong>
                        <?php if ($p['tunnus'] && jr_tunnus_on()): ?><span class="jr-call"><?php echo esc_html($p['tunnus']); ?></span><?php endif; ?>
                        <?php if ($r->etunimi === null): ?><div class="jr-sub">poistettu rekisteristä</div><?php endif; ?></td>
                    <td><code class="jr-ref"><?php echo esc_html(jr_viite_format($r->viite)); ?></code></td>
                    <td><?php echo esc_html(jr_money($r->summa)); ?>
                        <?php if (jr_rivi_postimaksu($r) > 0): ?><div class="jr-sub">sis. postimaksun <?php echo esc_html(jr_money(jr_rivi_postimaksu($r))); ?></div><?php endif; ?>
                        <?php if (!empty($r->erapaiva) && $r->erapaiva !== $lasku->erapaiva): ?><div class="jr-sub" title="Jäsenkohtainen eräpäivä (lisätty laskulle myöhemmin)">eräp. <?php echo esc_html(jr_date($r->erapaiva)); ?></div><?php endif; ?></td>
                    <td>
                        <?php if ($paperi): ?>
                            <span class="jr-badge jr-badge-info">📄 paperilasku</span>
                            <div><?php echo $r->lahetetty ? '<span class="jr-badge jr-badge-ok">Postitettu ' . esc_html(jr_date($r->lahetetty)) . '</span>' : '<span class="jr-badge jr-badge-muted">Ei postitettu</span>'; ?></div>
                            <?php if ($r->muistutettu): ?><div><span class="jr-badge jr-badge-orange">Muistutettu <?php echo esc_html(jr_date($r->muistutettu)); ?></span></div><?php endif; ?>
                        <?php elseif ($r->lahetetty): ?>
                            <span class="jr-badge jr-badge-ok">Lähetetty <?php echo esc_html(jr_date($r->lahetetty)); ?></span>
                            <?php if ($r->muistutettu): ?><div><span class="jr-badge jr-badge-orange" title="<?php echo esc_attr('Muistutuksia lähetetty ' . (int) $r->muistutuksia . ' kpl'); ?>">Muistutettu <?php echo (int) $r->muistutuksia > 1 ? (int) $r->muistutuksia . '× · viimeksi ' : ''; ?><?php echo esc_html(jr_date($r->muistutettu)); ?></span></div><?php endif; ?>
                        <?php else: ?>
                            <span class="jr-badge jr-badge-muted">Ei lähetetty</span>
                        <?php endif; ?>
                        <?php if ($r->virhe): ?><div class="jr-sub jr-err" title="<?php echo esc_attr($r->virhe); ?>">⚠ <?php echo esc_html(wp_trim_words($r->virhe, 8)); ?></div><?php endif; ?>
                        <?php if ($r->to_email): ?><div class="jr-sub"><?php echo esc_html($r->to_email); ?></div><?php endif; ?>
                    </td>
                    <td><?php echo jr_pay_badge($lasku, $r, $today); // phpcs:ignore ?></td>
                    <td class="jr-col-actions">
                        <a class="button button-small <?php echo $r->maksettu ? '' : 'button-primary'; ?>" href="<?php echo esc_url($paid_url); ?>"><?php echo $r->maksettu ? 'Peru maksu' : 'Merkitse maksetuksi'; ?></a>
                        <?php if ($paperi): ?>
                            <a class="button button-small" target="_blank" href="<?php echo esc_url(jr_print_url($id, array('rivi' => $r->id))); ?>">Näytä</a>
                            <a class="button button-small" target="_blank" href="<?php echo esc_url(jr_print_url($id, array('rivi' => $r->id, 'tulosta' => 1))); ?>">Tulosta</a>
                        <?php else: ?>
                            <a class="button button-small" target="_blank" href="<?php echo esc_url($prev_url); ?>">Näytä</a>
                        <?php endif; ?>
                        <?php if ($r->to_email && !$paperi): ?>
                            <a class="button button-small" href="<?php echo esc_url($send_url); ?>"
                               data-confirm="<?php echo esc_attr(($r->lahetetty ? 'Lähetetäänkö lasku uudelleen' : 'Lähetetäänkö lasku') . ' osoitteeseen ' . $r->to_email . '?'); ?>"><?php echo $r->lahetetty ? 'Lähetä uudelleen' : 'Lähetä'; ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="jr-bulk">
            <label for="jr-bulk-op"><strong>Valituille:</strong></label>
            <select name="bulk_op" id="jr-bulk-op">
                <option value="paid">Merkitse maksetuksi päivämäärällä</option>
                <option value="unpaid">Peru maksumerkintä</option>
                <option value="remove">Poista laskulta</option>
                <option value="posti_lisaa">Lisää postimaksu (<?php echo esc_html(jr_money((float) $lasku->postimaksu > 0 ? $lasku->postimaksu : jr_postimaksu_oletus())); ?>, maksamattomat)</option>
                <option value="posti_poista">Poista postimaksu (maksamattomat)</option>
            </select>
            <input type="date" name="bulk_pvm" value="<?php echo esc_attr($today); ?>">
            <button type="submit" class="button" data-confirm="Suoritetaanko toiminto valituille riveille?">Suorita</button>
        </div>
    </form>

    <div class="jr-columns">
        <details class="jr-panel">
            <summary><strong>Muokkaa laskun tietoja</strong></summary>
            <form method="post" action="<?php echo esc_url($post_url); ?>" class="jr-form">
                <input type="hidden" name="action" value="jr_update_lasku">
                <input type="hidden" name="id" value="<?php echo esc_attr($id); ?>">
                <?php wp_nonce_field('jr_update_lasku_' . $id); ?>
                <p><label for="jr-e-nimi">Laskun nimi</label><input type="text" id="jr-e-nimi" name="nimi" value="<?php echo esc_attr($lasku->nimi); ?>" required></p>
                <p><label for="jr-e-era">Eräpäivä</label><input type="date" id="jr-e-era" name="erapaiva" value="<?php echo esc_attr($lasku->erapaiva); ?>" required></p>
                <?php if (jr_settings()['auto_muistutus'] === '1'): ?>
                <p><label class="jr-radio"><input type="checkbox" name="auto_muistutus" value="1" <?php checked((int) $lasku->auto_muistutus, 1); ?>> Automaattiset maksumuistutukset tälle laskulle</label></p>
                <?php endif; ?>
                <p><label for="jr-e-viesti">Saateteksti</label><textarea id="jr-e-viesti" name="viesti" rows="6"><?php echo esc_textarea($lasku->viesti); ?></textarea>
                   <?php echo jr_placeholders('jr-e-viesti', 'lasku'); ?></p>
                <p class="description">Summaa ei voi muuttaa jälkikäteen, koska laskut on jo muodostettu. Muutokset näkyvät seuraavissa lähetyksissä.</p>
                <p><button type="submit" class="button button-primary">Tallenna</button></p>
            </form>
        </details>

        <div class="jr-panel">
            <p><strong>Muut toiminnot</strong></p>
            <p>
                <?php if ($n_missing > 0): ?>
                    <a class="button" href="<?php echo esc_url(wp_nonce_url($post_url . '?action=jr_add_missing&id=' . $id, 'jr_add_missing_' . $id)); ?>">Lisää laskulle uudet aktiiviset jäsenet (<?php echo $n_missing; ?>)</a>
                <?php endif; ?>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=jr-laskutus#jr-maksutuonti')); ?>">Tuo maksut pankista</a>
                <a class="button" href="<?php echo esc_url(wp_nonce_url($post_url . '?action=jr_export_xlsx&id=' . $id, 'jr_export_xlsx_' . $id)); ?>">Vie Exceliin</a>
                <a class="button" href="<?php echo esc_url(wp_nonce_url($post_url . '?action=jr_export_lasku&id=' . $id, 'jr_export_lasku_' . $id)); ?>">Vie CSV</a>
                <a class="button jr-btn-danger" href="<?php echo esc_url(wp_nonce_url($post_url . '?action=jr_delete_lasku&id=' . $id, 'jr_delete_lasku_' . $id)); ?>"
                   data-confirm="Poistetaanko koko lasku ja kaikki sen maksutiedot pysyvästi?">Poista lasku</a>
            </p>
            <p class="description">Vinkki: hae viitenumerolla tiliotteelta ylläolevasta hakukentästä – voit kirjoittaa viitteen välilyönneillä tai ilman.</p>
        </div>
    </div>
    <?php
}
