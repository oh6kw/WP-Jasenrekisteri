<?php
if (!defined('ABSPATH')) exit;

/*
 * Tiedotteet jäsenille. Viesti tallennetaan väliaikaisesti ja lähetetään
 * selaimesta käsin pienissä erissä, jotta suurikaan lista ei aiheuta aikakatkaisua.
 */

function jr_mail_recipients($kohde) {
    global $wpdb;
    $t = jr_t('jasenet');
    $where = "email IS NOT NULL AND email <> ''";
    if ($kohde === 'aktiiviset') $where .= ' AND ' . jr_sql_jasenet();
    if (strpos($kohde, 'luokka_') === 0) $where .= ' AND ' . jr_sql_jasenet() . $wpdb->prepare(' AND jasenlaji = %s', substr($kohde, 7));
    if ($kohde === 'maksamattomat') {
        $latest = (int) $wpdb->get_var('SELECT MAX(id) FROM ' . jr_t('laskut'));
        if (!$latest) return array();
        $where .= $wpdb->prepare(' AND id IN (SELECT jasen_id FROM ' . jr_t('rivit') . ' WHERE lasku_id = %d AND maksettu IS NULL)', $latest);
    }
    return array_map('intval', $wpdb->get_col("SELECT id FROM $t WHERE $where ORDER BY id"));
}

function jr_mass_mail_html($subject, $body, $m) {
    $s = jr_settings();
    $vars = array(
        'etunimi' => $m->etunimi, 'sukunimi' => $m->sukunimi,
        'nimi' => trim($m->etunimi . ' ' . $m->sukunimi), 'tunnus' => $m->tunnus, 'yhdistys' => $s['yhdistys'],
        'jasenluokka' => (isset($m->jasenlaji) && jr_laji_nimi((string) $m->jasenlaji) !== '') ? jr_laji_nimi((string) $m->jasenlaji) : 'Jäsen',
    );
    return jr_email_layout($subject, '<div>' . jr_fill($body, $vars) . '</div>');
}

add_action('admin_post_jr_mass_mail', 'jr_handle_mass_mail');
function jr_handle_mass_mail() {
    jr_check('jr_mass_mail');
    $subject = sanitize_text_field(jr_post('subject'));
    $body    = sanitize_textarea_field(jr_post('body'));
    $kohde   = jr_post('kohde', 'aktiiviset');
    $sallitut = array('aktiiviset', 'kaikki', 'maksamattomat');
    foreach (array_keys(jr_jasenlajit()) as $lid) $sallitut[] = 'luokka_' . $lid;
    if (!in_array($kohde, $sallitut, true)) $kohde = 'aktiiviset';

    $uid = get_current_user_id();
    set_transient('jr_mail_draft_' . $uid, compact('subject', 'body', 'kohde'), DAY_IN_SECONDS);

    if ($subject === '' || $body === '') jr_redirect('jr-sahkoposti', array('jr_msg' => 'mail_invalid'));

    // Testiviesti annettuun osoitteeseen
    if (jr_post('test') !== '') {
        $to = sanitize_email(jr_post('test_to'));
        if (!$to) jr_redirect('jr-sahkoposti', array('jr_msg' => 'test_invalid'));
        $me = wp_get_current_user();
        $fake = (object) array('etunimi' => $me->first_name ?: $me->display_name, 'sukunimi' => $me->last_name, 'tunnus' => '');
        $ok = jr_mail($to, '[TESTI] ' . $subject, jr_mass_mail_html($subject, $body, $fake));
        jr_redirect('jr-sahkoposti', array('jr_msg' => $ok ? 'test_sent' : 'send_failed', 'jr_to' => $to));
    }

    $ids = jr_mail_recipients($kohde);
    if (!$ids) jr_redirect('jr-sahkoposti', array('jr_msg' => 'no_mail_recipients'));
    // Pienet kirjaimet: sanitize_key() muuttaa tunnuksen pieneksi luettaessa.
    $key = strtolower(wp_generate_password(12, false));
    set_transient('jr_mail_run_' . $key, array('subject' => $subject, 'body' => $body, 'ids' => $ids, 'user' => $uid), DAY_IN_SECONDS);
    jr_redirect('jr-sahkoposti', array('run' => $key));
}

add_action('wp_ajax_jr_mass_mail_chunk', 'jr_ajax_mass_mail_chunk');
function jr_ajax_mass_mail_chunk() {
    if (!current_user_can('manage_options')) wp_send_json_error('Ei oikeuksia', 403);
    $key = isset($_POST['key']) ? sanitize_key($_POST['key']) : '';
    check_ajax_referer('jr_mass_mail_' . $key, 'nonce');

    $run = get_transient('jr_mail_run_' . $key);
    if (!$run) wp_send_json_error('Lähetystä ei löytynyt tai se on vanhentunut.');

    global $wpdb;
    // Edistyminen pidetään palvelimella, jotta sivun päivitys ei lähetä samoja viestejä uudelleen.
    $offset = isset($run['offset']) ? (int) $run['offset'] : 0;
    $batch  = array_slice($run['ids'], $offset, 5);
    $run['offset'] = $offset + count($batch);
    set_transient('jr_mail_run_' . $key, $run, DAY_IN_SECONDS);
    $sent = 0; $failed = 0; $errors = array();
    foreach ($batch as $id) {
        $m = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . jr_t('jasenet') . ' WHERE id = %d', $id));
        if ($m && is_email($m->email) && jr_mail($m->email, $run['subject'], jr_mass_mail_html($run['subject'], $run['body'], $m))) {
            $sent++;
        } else {
            $failed++;
            if ($m) $errors[] = $m->etunimi . ' ' . $m->sukunimi . ': ' . ($GLOBALS['jr_mail_error'] ?: 'lähetys epäonnistui');
        }
    }
    $next = $offset + count($batch);
    $remaining = max(0, count($run['ids']) - $next);
    if ($remaining === 0) {
        delete_transient('jr_mail_run_' . $key);
        delete_transient('jr_mail_draft_' . $run['user']);
    }
    wp_send_json_success(array('sent' => $sent, 'failed' => $failed, 'remaining' => $remaining, 'offset' => $next, 'errors' => $errors));
}

function jr_page_mail() {
    $key = isset($_GET['run']) ? sanitize_key($_GET['run']) : '';
    $run = $key ? get_transient('jr_mail_run_' . $key) : null;
    $draft = get_transient('jr_mail_draft_' . get_current_user_id());
    $draft = is_array($draft) ? $draft : array('subject' => '', 'body' => '', 'kohde' => 'aktiiviset');

    $c_akt = count(jr_mail_recipients('aktiiviset'));
    $c_all = count(jr_mail_recipients('kaikki'));
    $c_unp = count(jr_mail_recipients('maksamattomat'));
    ?>
    <div class="wrap jr-wrap">
        <h1>Sähköposti jäsenille</h1>
        <?php jr_notice(); ?>

        <?php if ($run): ?>
            <div class="jr-panel">
                <h2>Lähetetään: <?php echo esc_html($run['subject']); ?></h2>
                <p><?php echo count($run['ids']); ?> vastaanottajaa<?php echo !empty($run['offset']) ? ', joista ' . (int) $run['offset'] . ' jo käsitelty' : ''; ?>. Pidä tämä sivu auki, kunnes lähetys on valmis.</p>
                <button type="button" class="button button-primary jr-run" data-autostart="1"
                    data-action="jr_mass_mail_chunk"
                    data-params="<?php echo esc_attr(wp_json_encode(array('key' => $key, 'offset' => 0, 'nonce' => wp_create_nonce('jr_mass_mail_' . $key)))); ?>"
                    data-done-url="<?php echo esc_url(admin_url('admin.php?page=jr-sahkoposti')); ?>">Jatka lähetystä</button>
                <div class="jr-progress" hidden><div class="jr-progress-bar"><span></span></div><p class="jr-progress-text"></p></div>
            </div>
        <?php else: ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="jr-panel jr-form">
            <input type="hidden" name="action" value="jr_mass_mail">
            <?php wp_nonce_field('jr_mass_mail'); ?>

            <fieldset class="jr-fieldset">
                <legend>Vastaanottajat</legend>
                <label class="jr-radio"><input type="radio" name="kohde" value="aktiiviset" <?php checked($draft['kohde'], 'aktiiviset'); ?>> Kaikki jäsenet (<?php echo $c_akt; ?>)</label>
                <?php foreach (jr_jasenlajit() as $lid => $l): $k = 'luokka_' . $lid; ?>
                    <label class="jr-radio jr-indent"><input type="radio" name="kohde" value="<?php echo esc_attr($k); ?>" <?php checked($draft['kohde'], $k); ?>> Vain: <?php echo esc_html($l['nimi']); ?> (<?php echo count(jr_mail_recipients($k)); ?>)</label>
                <?php endforeach; ?>
                <label class="jr-radio"><input type="radio" name="kohde" value="kaikki" <?php checked($draft['kohde'], 'kaikki'); ?>> Kaikki, myös eronneet (<?php echo $c_all; ?>)</label>
                <label class="jr-radio"><input type="radio" name="kohde" value="maksamattomat" <?php checked($draft['kohde'], 'maksamattomat'); ?>> Viimeisimmän laskun maksamattomat (<?php echo $c_unp; ?>)</label>
                <p class="description">Vain jäsenet, joilla on sähköpostiosoite. Jokainen saa oman viestinsä – osoitteet eivät näy muille.</p>
            </fieldset>

            <p><label for="jr-subject">Otsikko</label>
               <input type="text" id="jr-subject" name="subject" value="<?php echo esc_attr($draft['subject']); ?>" required></p>
            <p><label for="jr-body">Viesti</label>
               <textarea id="jr-body" name="body" rows="12" required placeholder="Hei {etunimi},&#10;&#10;…"><?php echo esc_textarea($draft['body']); ?></textarea>
               <?php echo jr_placeholders('jr-body', 'tiedote', 'Rivinvaihdot säilyvät.'); ?></p>

            <p class="jr-actions">
                <button type="submit" class="button button-primary button-large" data-confirm="Lähetetäänkö viesti valituille jäsenille?">Lähetä</button>
            </p>
            <div class="jr-testbox">
                <label for="jr-test-to">Testiviesti osoitteeseen</label>
                <input type="email" id="jr-test-to" name="test_to" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>">
                <button type="submit" name="test" value="1" class="button">Lähetä testi</button>
                <span class="description">Oletuksena sinun WordPress-tunnuksesi osoite (<?php echo esc_html(wp_get_current_user()->display_name); ?>). Testi lähtee vain tähän osoitteeseen.</span>
            </div>
        </form>
        <?php endif; ?>
    </div>
    <?php
}
