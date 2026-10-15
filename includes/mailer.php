<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own custom tables (members, invoices, audit log). The data is admin-only and changes on every write, so object caching is not used.

/*
 * Tiedotteet jäsenille. Viesti tallennetaan väliaikaisesti ja lähetetään
 * selaimesta käsin pienissä erissä, jotta suurikaan lista ei aiheuta aikakatkaisua.
 */

function jasrek_mail_recipients($kohde) {
    global $wpdb;
    $base = "SELECT id FROM {$wpdb->jasrek_jasenet} WHERE email IS NOT NULL AND email <> ''";
    if ($kohde === 'aktiiviset') {
        $ids = $wpdb->get_col("$base AND tila = 'aktiivinen' ORDER BY id"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $base is a literal query without user input.
    } elseif (strpos($kohde, 'luokka_') === 0) {
        $ids = $wpdb->get_col($wpdb->prepare("$base AND tila = 'aktiivinen' AND jasenlaji = %s ORDER BY id", substr($kohde, 7))); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $base is a literal query without user input.
    } elseif ($kohde === 'maksamattomat') {
        $latest = (int) $wpdb->get_var("SELECT MAX(id) FROM {$wpdb->jasrek_laskut}");
        if (!$latest) return array();
        $ids = $wpdb->get_col($wpdb->prepare("$base AND id IN (SELECT jasen_id FROM {$wpdb->jasrek_rivit} WHERE lasku_id = %d AND maksettu IS NULL) ORDER BY id", $latest)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $base is a literal query without user input.
    } else {
        $ids = $wpdb->get_col("$base ORDER BY id"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $base is a literal query without user input.
    }
    return array_map('intval', $ids);
}

function jasrek_mass_mail_html($subject, $body, $m) {
    $s = jasrek_settings();
    $vars = array(
        'etunimi' => $m->etunimi, 'sukunimi' => $m->sukunimi,
        'nimi' => trim($m->etunimi . ' ' . $m->sukunimi), 'tunnus' => $m->tunnus, 'yhdistys' => $s['yhdistys'],
        'jasenluokka' => (isset($m->jasenlaji) && jasrek_laji_nimi((string) $m->jasenlaji) !== '') ? jasrek_laji_nimi((string) $m->jasenlaji) : 'Jäsen',
    );
    return jasrek_email_layout($subject, '<div>' . jasrek_fill($body, $vars) . '</div>');
}

add_action('admin_post_jasrek_mass_mail', 'jasrek_handle_mass_mail');
function jasrek_handle_mass_mail() {
    jasrek_check('jasrek_mass_mail');
    $subject = sanitize_text_field(jasrek_post('subject'));
    $body    = sanitize_textarea_field(jasrek_post('body'));
    $kohde   = jasrek_post('kohde', 'aktiiviset');
    $sallitut = array('aktiiviset', 'kaikki', 'maksamattomat');
    foreach (array_keys(jasrek_jasenlajit()) as $lid) $sallitut[] = 'luokka_' . $lid;
    if (!in_array($kohde, $sallitut, true)) $kohde = 'aktiiviset';

    $uid = get_current_user_id();
    set_transient('jasrek_mail_draft_' . $uid, compact('subject', 'body', 'kohde'), DAY_IN_SECONDS);

    if ($subject === '' || $body === '') jasrek_redirect('jasrek-sahkoposti', array('jasrek_msg' => 'mail_invalid'));

    // Testiviesti annettuun osoitteeseen
    if (jasrek_post('test') !== '') {
        $to = sanitize_email(jasrek_post('test_to'));
        if (!$to) jasrek_redirect('jasrek-sahkoposti', array('jasrek_msg' => 'test_invalid'));
        $me = wp_get_current_user();
        $fake = (object) array('etunimi' => $me->first_name ?: $me->display_name, 'sukunimi' => $me->last_name, 'tunnus' => '');
        $ok = jasrek_mail($to, '[TESTI] ' . $subject, jasrek_mass_mail_html($subject, $body, $fake));
        jasrek_redirect('jasrek-sahkoposti', array('jasrek_msg' => $ok ? 'test_sent' : 'send_failed', 'jasrek_to' => $to));
    }

    $ids = jasrek_mail_recipients($kohde);
    if (!$ids) jasrek_redirect('jasrek-sahkoposti', array('jasrek_msg' => 'no_mail_recipients'));
    // Pienet kirjaimet: sanitize_key() muuttaa tunnuksen pieneksi luettaessa.
    $key = strtolower(wp_generate_password(12, false));
    set_transient('jasrek_mail_run_' . $key, array('subject' => $subject, 'body' => $body, 'ids' => $ids, 'user' => $uid), DAY_IN_SECONDS);
    $kohteet = array('aktiiviset' => 'kaikki jäsenet', 'kaikki' => 'kaikki, myös eronneet', 'maksamattomat' => 'viimeisimmän laskun maksamattomat');
    $kuvaus = strpos($kohde, 'luokka_') === 0 ? jasrek_laji_nimi(substr($kohde, 7)) : (isset($kohteet[$kohde]) ? $kohteet[$kohde] : $kohde);
    jasrek_log('viesti', 0, 'tiedote', '"' . $subject . '" – ' . count($ids) . ' vastaanottajaa (' . $kuvaus . ')', array(array('Viesti', '', $body)));
    jasrek_redirect('jasrek-sahkoposti', array('run' => $key));
}

add_action('wp_ajax_jasrek_mass_mail_chunk', 'jasrek_ajax_mass_mail_chunk');
function jasrek_ajax_mass_mail_chunk() {
    if (!current_user_can('manage_options')) wp_send_json_error('Ei oikeuksia', 403);
    $key = sanitize_key(jasrek_post('key'));
    check_ajax_referer('jasrek_mass_mail_' . $key, 'nonce');

    $run = get_transient('jasrek_mail_run_' . $key);
    if (!$run) wp_send_json_error('Lähetystä ei löytynyt tai se on vanhentunut.');

    global $wpdb;
    // Edistyminen pidetään palvelimella, jotta sivun päivitys ei lähetä samoja viestejä uudelleen.
    $offset = isset($run['offset']) ? (int) $run['offset'] : 0;
    $batch  = array_slice($run['ids'], $offset, 5);
    $run['offset'] = $offset + count($batch);
    set_transient('jasrek_mail_run_' . $key, $run, DAY_IN_SECONDS);
    $sent = 0; $failed = 0; $errors = array();
    foreach ($batch as $id) {
        $m = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->jasrek_jasenet} WHERE id = %d", $id));
        if ($m && is_email($m->email) && jasrek_mail($m->email, $run['subject'], jasrek_mass_mail_html($run['subject'], $run['body'], $m))) {
            $sent++;
        } else {
            $failed++;
            if ($m) $errors[] = $m->etunimi . ' ' . $m->sukunimi . ': ' . ($GLOBALS['jasrek_mail_error'] ?: 'lähetys epäonnistui');
        }
    }
    $next = $offset + count($batch);
    $remaining = max(0, count($run['ids']) - $next);
    if ($remaining === 0) {
        delete_transient('jasrek_mail_run_' . $key);
        delete_transient('jasrek_mail_draft_' . $run['user']);
    }
    wp_send_json_success(array('sent' => $sent, 'failed' => $failed, 'remaining' => $remaining, 'offset' => $next, 'errors' => $errors));
}

function jasrek_page_mail() {
    $key = sanitize_key(jasrek_get('run', ''));
    $run = $key ? get_transient('jasrek_mail_run_' . $key) : null;
    $draft = get_transient('jasrek_mail_draft_' . get_current_user_id());
    $draft = is_array($draft) ? $draft : array('subject' => '', 'body' => '', 'kohde' => 'aktiiviset');

    $c_akt = count(jasrek_mail_recipients('aktiiviset'));
    $c_all = count(jasrek_mail_recipients('kaikki'));
    $c_unp = count(jasrek_mail_recipients('maksamattomat'));
    ?>
    <div class="wrap jasrek-wrap">
        <h1>Sähköposti jäsenille</h1>
        <?php jasrek_notice(); ?>

        <?php if ($run): ?>
            <div class="jasrek-panel">
                <h2>Lähetetään: <?php echo esc_html($run['subject']); ?></h2>
                <p><?php echo count($run['ids']); ?> vastaanottajaa<?php echo !empty($run['offset']) ? ', joista ' . (int) $run['offset'] . ' jo käsitelty' : ''; ?>. Pidä tämä sivu auki, kunnes lähetys on valmis.</p>
                <button type="button" class="button button-primary jasrek-run" data-autostart="1"
                    data-action="jasrek_mass_mail_chunk"
                    data-params="<?php echo esc_attr(wp_json_encode(array('key' => $key, 'offset' => 0, 'nonce' => wp_create_nonce('jasrek_mass_mail_' . $key)))); ?>"
                    data-done-url="<?php echo esc_url(admin_url('admin.php?page=jasrek-sahkoposti')); ?>">Jatka lähetystä</button>
                <div class="jasrek-progress" hidden><div class="jasrek-progress-bar"><span></span></div><p class="jasrek-progress-text"></p></div>
            </div>
        <?php else: ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="jasrek-panel jasrek-form">
            <input type="hidden" name="action" value="jasrek_mass_mail">
            <?php wp_nonce_field('jasrek_mass_mail'); ?>

            <fieldset class="jasrek-fieldset">
                <legend>Vastaanottajat</legend>
                <label class="jasrek-radio"><input type="radio" name="kohde" value="aktiiviset" <?php checked($draft['kohde'], 'aktiiviset'); ?>> Kaikki jäsenet (<?php echo esc_html($c_akt); ?>)</label>
                <?php foreach (jasrek_jasenlajit() as $lid => $l): $k = 'luokka_' . $lid; ?>
                    <label class="jasrek-radio jasrek-indent"><input type="radio" name="kohde" value="<?php echo esc_attr($k); ?>" <?php checked($draft['kohde'], $k); ?>> Vain: <?php echo esc_html($l['nimi']); ?> (<?php echo count(jasrek_mail_recipients($k)); ?>)</label>
                <?php endforeach; ?>
                <label class="jasrek-radio"><input type="radio" name="kohde" value="kaikki" <?php checked($draft['kohde'], 'kaikki'); ?>> Kaikki, myös eronneet (<?php echo esc_html($c_all); ?>)</label>
                <label class="jasrek-radio"><input type="radio" name="kohde" value="maksamattomat" <?php checked($draft['kohde'], 'maksamattomat'); ?>> Viimeisimmän laskun maksamattomat (<?php echo esc_html($c_unp); ?>)</label>
                <p class="description">Vain jäsenet, joilla on sähköpostiosoite. Jokainen saa oman viestinsä – osoitteet eivät näy muille.</p>
            </fieldset>

            <p><label for="jasrek-subject">Otsikko</label>
               <input type="text" id="jasrek-subject" name="subject" value="<?php echo esc_attr($draft['subject']); ?>" required></p>
            <p><label for="jasrek-body">Viesti</label>
               <textarea id="jasrek-body" name="body" rows="12" required placeholder="Hei {etunimi},&#10;&#10;…"><?php echo esc_textarea($draft['body']); ?></textarea>
               <?php echo wp_kses_post(jasrek_placeholders('jasrek-body', 'tiedote', 'Rivinvaihdot säilyvät.')); ?></p>

            <p class="jasrek-actions">
                <button type="submit" class="button button-primary button-large" data-confirm="Lähetetäänkö viesti valituille jäsenille?">Lähetä</button>
            </p>
            <div class="jasrek-testbox">
                <label for="jasrek-test-to">Testiviesti osoitteeseen</label>
                <input type="email" id="jasrek-test-to" name="test_to" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>">
                <button type="submit" name="test" value="1" class="button">Lähetä testi</button>
                <span class="description">Oletuksena sinun WordPress-tunnuksesi osoite (<?php echo esc_html(wp_get_current_user()->display_name); ?>). Testi lähtee vain tähän osoitteeseen.</span>
            </div>
        </form>
        <?php endif; ?>
    </div>
    <?php
}
