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
    return 'SELECT r.*, m.etunimi, m.sukunimi, m.tunnus, m.tila, m.jasenlaji, '
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
function jr_add_rows($lasku_id, $members, $summa, $hinnat = array(), &$skipped = 0) {
    global $wpdb;
    $n = 0;
    $skipped = 0;
    foreach ($members as $m) {
        $price = jr_member_price($m, $hinnat, $summa);
        if ($price <= 0) { $skipped++; continue; }
        $ok = $wpdb->insert(jr_t('rivit'), array(
            'lasku_id' => $lasku_id,
            'jasen_id' => $m->id,
            'nimi'     => trim($m->etunimi . ' ' . $m->sukunimi),
            'email'    => (string) $m->email,
            'viite'    => jr_viite($lasku_id, $m->id),
            'summa'    => $price,
        ));
        if ($ok) $n++;
    }
    return $n;
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
        'auto_muistutus' => 1,
    ));
    $lasku_id = (int) $wpdb->insert_id;
    $skipped = 0;
    $n = jr_add_rows($lasku_id, $members, $summa, $hinnat, $skipped);
    $failed = count($members) - $n - $skipped;
    if ($n === 0) {
        $wpdb->delete(jr_t('laskut'), array('id' => $lasku_id));
        jr_redirect('jr-laskutus', array('view' => 'new', 'jr_msg' => 'no_recipients'));
    }
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
    $wpdb->update(jr_t('laskut'), array(
        'nimi' => $nimi, 'erapaiva' => $erapaiva, 'viesti' => sanitize_textarea_field(jr_post('viesti')),
        'auto_muistutus' => jr_post('auto_muistutus') === '1' ? 1 : 0,
    ), array('id' => $id));
    jr_redirect('jr-laskutus', array('view' => 'lasku', 'id' => $id, 'jr_msg' => 'lasku_updated'));
}

add_action('admin_post_jr_delete_lasku', 'jr_handle_delete_lasku');
function jr_handle_delete_lasku() {
    $id = intval(isset($_REQUEST['id']) ? $_REQUEST['id'] : 0);
    jr_check('jr_delete_lasku_' . $id);
    global $wpdb;
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
    $n = jr_add_rows($id, $members, $lasku->summa, jr_lasku_hinnat($lasku));
    jr_redirect('jr-laskutus', array('view' => 'lasku', 'id' => $id, 'jr_msg' => 'rows_added', 'jr_n' => $n));
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
        $out[] = array($p['nimi'], $p['tunnus'], $r->to_email, jr_viite_format($r->viite), number_format((float) $r->summa, 2, ',', ''),
            $r->lahetetty ? jr_date($r->lahetetty) : '', $r->muistutettu ? (int) $r->muistutuksia . ' kertaa, viimeksi ' . jr_date($r->muistutettu) : '',
            $r->maksettu ? jr_date($r->maksettu) : '', $r->maksettu_summa !== null ? number_format((float) $r->maksettu_summa, 2, ',', '') : '',
            (string) $r->maksaja, (string) $r->arkistotunnus, $r->virhe);
    }
    jr_csv_output(sanitize_file_name($lasku->nimi) . '.csv',
        array('Nimi', jr_tunnus_nimi(), 'Sähköposti', 'Viite', 'Summa', 'Lähetetty', 'Muistutettu', 'Maksettu', 'Maksettu summa', 'Maksaja', 'Arkistointitunnus', 'Virhe'), $out);
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
        "WHERE r.lasku_id = %d AND (r.viim_yritys IS NULL OR r.viim_yritys < %s) AND COALESCE(NULLIF(m.email, ''), r.email) <> '' ",
        $lasku_id, $run_start
    );
    if ($reminder) {
        // Samana päivänä jo muistutetuille ei lähetetä uudelleen (estää vahingossa tehdyn tuplalähetyksen).
        $cond .= $wpdb->prepare('AND r.maksettu IS NULL AND (r.muistutettu IS NULL OR r.muistutettu < %s) ', current_time('Y-m-d') . ' 00:00:00');
    } else {
        $cond .= 'AND r.lahetetty IS NULL ';
    }

    $rows = $wpdb->get_results(jr_rivi_select_sql() . $cond . 'ORDER BY r.id LIMIT 5');
    $sent = 0; $failed = 0; $errors = array();
    foreach ($rows as $r) {
        if (jr_send_rivi($lasku, $r, $reminder)) {
            $sent++;
        } else {
            $failed++;
            $errors[] = jr_rivi_person($r)['nimi'] . ': ' . ($GLOBALS['jr_mail_error'] ?: 'lähetys epäonnistui');
        }
    }
    $remaining = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . jr_t('rivit') . ' r LEFT JOIN ' . jr_t('jasenet') . ' m ON m.id = r.jasen_id ' . $cond);

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
    $laskut = $wpdb->get_results(
        'SELECT l.*, COUNT(r.id) AS n, SUM(r.lahetetty IS NOT NULL) AS lah, SUM(r.maksettu IS NOT NULL) AS mak, MIN(r.summa) AS min_s, MAX(r.summa) AS max_s, '
        . 'SUM(CASE WHEN r.maksettu IS NOT NULL THEN r.summa ELSE 0 END) AS mak_summa, SUM(r.summa) AS yht '
        . 'FROM ' . jr_t('laskut') . ' l LEFT JOIN ' . jr_t('rivit') . ' r ON r.lasku_id = l.id GROUP BY l.id ORDER BY l.id DESC'
    );
    $today = current_time('Y-m-d');
    ?>
    <h1 class="wp-heading-inline">Jäsenmaksulaskutus</h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=jr-laskutus&view=new')); ?>" class="page-title-action">Uusi lasku</a>
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
                    <?php if ($l->erapaiva < $today && $l->mak < $n): ?><span class="jr-badge jr-badge-err">erääntynyt</span><?php endif; ?></td>
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
    $n_akt = 0; $n_noemail = 0; $n_mv = 0;
    $lajit = jr_jasenlajit();
    $laji_n = array();
    foreach ($members as $m) {
        if ($m->tila === 'eronnut') continue;
        $k = isset($lajit[$m->jasenlaji]) ? $m->jasenlaji : '';
        $laji_n[$k] = isset($laji_n[$k]) ? $laji_n[$k] + 1 : 1;
        if (jr_is_maksuvapaa($m)) { $n_mv++; continue; }
        $n_akt++;
        if (!$m->email) $n_noemail++;
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
           <span class="description">Paikkamerkit: <code>{etunimi}</code> <code>{sukunimi}</code><?php if (jr_tunnus_on()): ?> <code>{tunnus}</code><?php endif; ?> <code>{jasenluokka}</code> <code>{laskun_nimi}</code> <code>{summa}</code> <code>{viite}</code> <code>{erapaiva}</code> <code>{yhdistys}</code>. Laskun tiedot (tilinumero, viite, summa, eräpäivä ja virtuaaliviivakoodi) lisätään viestiin automaattisesti.</span></p>

        <fieldset class="jr-fieldset">
            <legend>Kenelle lasku tehdään?</legend>
            <label class="jr-radio"><input type="radio" name="vastaanottajat" value="aktiiviset" checked>
                Kaikille jäsenmaksun maksaville jäsenille (<?php echo (int) $n_akt; ?>)<?php if ($n_mv): ?> <span class="jr-muted">– <?php echo (int) $n_mv; ?> jäsentä, joiden jäsenmaksu on 0 €, jätetään pois</span><?php endif; ?></label>
            <label class="jr-radio"><input type="radio" name="vastaanottajat" value="valitut" data-jr-toggle="#jr-member-pick">
                Valituille jäsenille</label>
            <?php if ($n_noemail): ?>
                <p class="jr-hint">⚠ <?php echo (int) $n_noemail; ?> laskutettavalla jäsenellä ei ole sähköpostia. Heille lasku luodaan viitteineen, mutta se pitää toimittaa muuta kautta.</p>
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
                        <?php if (!$m->email): ?><span class="jr-badge jr-badge-warn">ei sähköpostia</span><?php endif; ?>
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

function jr_invoice_detail($id) {
    global $wpdb;
    $lasku = jr_get_lasku($id);
    if (!$lasku) { echo '<p>Laskua ei löytynyt.</p>'; return; }

    $rows  = $wpdb->get_results($wpdb->prepare(jr_rivi_select_sql() . 'WHERE r.lasku_id = %d ORDER BY m.sukunimi, m.etunimi, r.nimi', $id));
    $today = current_time('Y-m-d');
    $no_iban = false;

    $st = array('n' => 0, 'lah' => 0, 'mak' => 0, 'mak_summa' => 0, 'avoin_summa' => 0, 'unsent' => 0, 'unpaid_mail' => 0, 'noemail' => 0, 'muist_tanaan' => 0, 'muist_aiemmin' => 0, 'min' => null, 'max' => null);
    foreach ($rows as $r) {
        $st['n']++;
        $st['min'] = $st['min'] === null ? $r->summa : min($st['min'], $r->summa);
        $st['max'] = $st['max'] === null ? $r->summa : max($st['max'], $r->summa);
        if ($r->lahetetty) $st['lah']++;
        if ($r->maksettu) { $st['mak']++; $st['mak_summa'] += $r->summa; } else { $st['avoin_summa'] += $r->summa; }
        if (!$r->to_email) { $st['noemail']++; continue; }
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
    $is_overdue = $lasku->erapaiva < $today;
    ?>
    <h1 class="wp-heading-inline"><?php echo esc_html($lasku->nimi); ?></h1>
    <hr class="wp-header-end">
    <p><a href="<?php echo esc_url(admin_url('admin.php?page=jr-laskutus')); ?>">&larr; Kaikki laskut</a></p>
    <?php jr_notice(); $no_iban = jr_settings_warning(); ?>

    <div class="jr-cards">
        <div class="jr-card"><span class="jr-card-num"><?php echo esc_html(jr_summa_range($st['min'], $st['max'], $lasku->summa)); ?></span><span class="jr-card-label">Eräpäivä <?php echo esc_html(jr_date($lasku->erapaiva)); ?></span></div>
        <div class="jr-card"><span class="jr-card-num"><?php echo $st['lah'] . ' / ' . $st['n']; ?></span><span class="jr-card-label">Lähetetty</span></div>
        <div class="jr-card jr-card-ok"><span class="jr-card-num"><?php echo $st['mak'] . ' / ' . $st['n']; ?></span><span class="jr-card-label">Maksettu (<?php echo esc_html(jr_money($st['mak_summa'])); ?>)</span></div>
        <div class="jr-card <?php echo $is_overdue && $st['avoin_summa'] > 0 ? 'jr-card-err' : ''; ?>"><span class="jr-card-num"><?php echo esc_html(jr_money($st['avoin_summa'])); ?></span><span class="jr-card-label">Avoinna<?php echo $is_overdue ? ' – erääntynyt' : ''; ?></span></div>
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
                <span class="jr-badge jr-badge-ok jr-badge-lg">✓ Kaikki sähköpostilliset laskut lähetetty</span>
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
        <?php if ($st['noemail']): ?>
            <p class="jr-hint">⚠ <?php echo (int) $st['noemail']; ?> jäsenellä ei ole sähköpostiosoitetta. Toimita heille lasku muuten – viite ja summa näkyvät alla olevassa taulukossa.</p>
        <?php endif; ?>
    </div>

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
                <?php if ($st['noemail']): ?><li><a href="#" data-status="eiemail">Ei sähköpostia (<?php echo $st['noemail']; ?>)</a></li><?php endif; ?>
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
                if (!$r->to_email) $status[] = 'eiemail';
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
                    <td><?php echo esc_html(jr_money($r->summa)); ?></td>
                    <td>
                        <?php if (!$r->to_email): ?>
                            <span class="jr-badge jr-badge-warn">ei sähköpostia</span>
                        <?php elseif ($r->lahetetty): ?>
                            <span class="jr-badge jr-badge-ok">Lähetetty <?php echo esc_html(jr_date($r->lahetetty)); ?></span>
                            <?php if ($r->muistutettu): ?><div><span class="jr-badge jr-badge-orange" title="<?php echo esc_attr('Muistutuksia lähetetty ' . (int) $r->muistutuksia . ' kpl'); ?>">Muistutettu <?php echo (int) $r->muistutuksia > 1 ? (int) $r->muistutuksia . '× · viimeksi ' : ''; ?><?php echo esc_html(jr_date($r->muistutettu)); ?></span></div><?php endif; ?>
                        <?php else: ?>
                            <span class="jr-badge jr-badge-muted">Ei lähetetty</span>
                        <?php endif; ?>
                        <?php if ($r->virhe): ?><div class="jr-sub jr-err" title="<?php echo esc_attr($r->virhe); ?>">⚠ <?php echo esc_html(wp_trim_words($r->virhe, 8)); ?></div><?php endif; ?>
                        <?php if ($r->to_email): ?><div class="jr-sub"><?php echo esc_html($r->to_email); ?></div><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r->maksettu): ?>
                            <span class="jr-badge jr-badge-ok">Maksettu <?php echo esc_html(jr_date($r->maksettu)); ?></span>
                            <?php if ($r->maksettu_summa !== null && abs((float) $r->maksettu_summa - (float) $r->summa) > 0.004): ?>
                                <div><span class="jr-badge jr-badge-orange">Maksettu summa <?php echo esc_html(jr_money($r->maksettu_summa)); ?></span></div>
                            <?php endif; ?>
                            <?php if ($r->maksaja): ?><div class="jr-sub">Maksaja: <?php echo esc_html($r->maksaja); ?></div><?php endif; ?>
                        <?php elseif ($is_overdue): ?>
                            <span class="jr-badge jr-badge-err">Erääntynyt</span>
                        <?php else: ?>
                            <span class="jr-badge jr-badge-info">Avoin</span>
                        <?php endif; ?>
                    </td>
                    <td class="jr-col-actions">
                        <a class="button button-small <?php echo $r->maksettu ? '' : 'button-primary'; ?>" href="<?php echo esc_url($paid_url); ?>"><?php echo $r->maksettu ? 'Peru maksu' : 'Merkitse maksetuksi'; ?></a>
                        <a class="button button-small" target="_blank" href="<?php echo esc_url($prev_url); ?>">Näytä</a>
                        <?php if ($r->to_email): ?>
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
                <p><label for="jr-e-viesti">Saateteksti</label><textarea id="jr-e-viesti" name="viesti" rows="6"><?php echo esc_textarea($lasku->viesti); ?></textarea></p>
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
                <a class="button" href="<?php echo esc_url(wp_nonce_url($post_url . '?action=jr_export_lasku&id=' . $id, 'jr_export_lasku_' . $id)); ?>">Vie CSV</a>
                <a class="button jr-btn-danger" href="<?php echo esc_url(wp_nonce_url($post_url . '?action=jr_delete_lasku&id=' . $id, 'jr_delete_lasku_' . $id)); ?>"
                   data-confirm="Poistetaanko koko lasku ja kaikki sen maksutiedot pysyvästi?">Poista lasku</a>
            </p>
            <p class="description">Vinkki: hae viitenumerolla tiliotteelta ylläolevasta hakukentästä – voit kirjoittaa viitteen välilyönneillä tai ilman.</p>
        </div>
    </div>
    <?php
}
