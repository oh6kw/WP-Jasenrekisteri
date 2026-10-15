<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own custom tables (members, invoices, audit log). The data is admin-only and changes on every write, so object caching is not used.

/*
 * Varmuuskopio ja palautus (Asetukset → Varmuuskopio ja siirto).
 *
 * Tiedosto on JSON: kaikki jäsenet, laskut, laskurivit (maksut), asetukset ja halutessa muutosloki.
 * Rivit tallennetaan tunnisteineen (id), jotta laskurivit pysyvät oikeiden jäsenten ja laskujen
 * kohdalla ja viitenumerot toimivat palautuksen jälkeen.
 *
 * Palautus korvaa kaikki nykyiset tiedot. Tiedosto tarkistetaan kokonaan ennen kuin mitään muutetaan,
 * ja nykyinen tilanne tallennetaan ensin, jotta palautuksen voi perua.
 */

define('JASREK_BACKUP_FORMAT', 'oh6kw-jasenrekisteri-backup');
define('JASREK_BACKUP_VERSION', 1);

/** Varmuuskopioon kuuluvat taulut (avain → taulun nimi $wpdb-oliossa). Muutosloki on valinnainen. */
function jasrek_backup_tables() {
    return array('jasenet', 'laskut', 'rivit', 'loki');
}

/** Taulun sarakkeet tietokannasta. Palautus käyttää vain näitä sarakkeita. */
function jasrek_backup_columns($key) {
    static $cache = array();
    global $wpdb;
    if (!isset($cache[$key])) {
        $table = $wpdb->{'jasrek_' . $key};
        $cache[$key] = array_map('strval', (array) $wpdb->get_col($wpdb->prepare('SHOW COLUMNS FROM %i', $table)));
    }
    return $cache[$key];
}

/** Rivimäärät taulukoittain nykyisessä tietokannassa. */
function jasrek_backup_current_counts() {
    global $wpdb;
    $out = array();
    foreach (jasrek_backup_tables() as $key) {
        $table = $wpdb->{'jasrek_' . $key};
        $out[$key] = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $table));
    }
    return $out;
}

/** Kokoaa varmuuskopion taulukkona. */
function jasrek_backup_build($with_log = true) {
    global $wpdb;
    $data = array(
        'format'         => JASREK_BACKUP_FORMAT,
        'format_version' => JASREK_BACKUP_VERSION,
        'plugin_version' => JASREK_VERSION,
        'db_version'     => (int) JASREK_DB_VERSION,
        'created'        => current_time('c'),
        'site'           => home_url(),
        'association'    => jasrek_settings()['yhdistys'],
        'settings'       => get_option('jasrek_settings', array()),
        'tables'         => array(),
    );
    foreach (jasrek_backup_tables() as $key) {
        if ($key === 'loki' && !$with_log) continue;
        $table = $wpdb->{'jasrek_' . $key};
        $data['tables'][$key] = (array) $wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY id', $table), ARRAY_A);
    }
    return $data;
}

function jasrek_backup_json($data) {
    return wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** Pakkaus (gzip), jos palvelimen PHP:ssä on zlib. Pakattu tiedosto on noin kymmenesosa JSONin koosta. */
function jasrek_backup_can_gzip() {
    return function_exists('gzencode') && function_exists('gzdecode');
}
/** Ladatun tiedoston sisältö JSON-tekstinä (purkaa gzip-pakkauksen). Palauttaa null, jos purku ei onnistu. */
function jasrek_backup_read_upload($raw) {
    $raw = (string) $raw;
    if (substr($raw, 0, 2) === "\x1f\x8b") {
        if (!function_exists('gzdecode')) return null;
        $json = gzdecode($raw, 200 * MB_IN_BYTES);
        return $json === false ? null : $json;
    }
    return preg_replace('/^\xEF\xBB\xBF/', '', $raw);
}
/** Väliaikainen säilytys tietokannassa (transient ja peruttava tilanne): pakattuna, jos mahdollista. */
function jasrek_backup_pack($json) {
    return function_exists('gzcompress') ? 'gz:' . base64_encode(gzcompress($json, 6)) : $json; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- compressed backup stored in the options table, not obfuscation.
}
function jasrek_backup_unpack($stored) {
    $stored = (string) $stored;
    if (strpos($stored, 'gz:') === 0) {
        $json = function_exists('gzuncompress') ? gzuncompress(base64_decode(substr($stored, 3))) : false; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- see jasrek_backup_pack().
        return $json === false ? '' : $json;
    }
    return $stored;
}

/**
 * Tarkistaa varmuuskopion ennen palautusta.
 * Palauttaa array('ok' => true, 'data' => ...) tai array('ok' => false, 'virhe' => '...').
 */
function jasrek_backup_validate($json) {
    $fail = function ($msg) { return array('ok' => false, 'virhe' => $msg); };
    $data = json_decode((string) $json, true);
    if (!is_array($data)) return $fail('Tiedosto ei ole kelvollinen JSON-tiedosto.');
    if (!isset($data['format']) || $data['format'] !== JASREK_BACKUP_FORMAT) {
        return $fail('Tiedosto ei ole OH6KW Jäsenrekisterin varmuuskopio.');
    }
    $fv = isset($data['format_version']) ? (int) $data['format_version'] : 0;
    if ($fv < 1 || $fv > JASREK_BACKUP_VERSION) {
        return $fail('Varmuuskopio on tehty uudemmalla lisäosan versiolla. Päivitä lisäosa ennen palautusta.');
    }
    $dbv = isset($data['db_version']) ? (int) $data['db_version'] : 0;
    if ($dbv < 1 || $dbv > (int) JASREK_DB_VERSION) {
        return $fail('Varmuuskopio on tehty uudemmalla lisäosan versiolla (' . (isset($data['plugin_version']) ? sanitize_text_field((string) $data['plugin_version']) : '?') . '). Päivitä lisäosa ennen palautusta.');
    }
    if (!isset($data['settings']) || !is_array($data['settings'])) return $fail('Varmuuskopiosta puuttuvat asetukset.');
    if (!isset($data['tables']) || !is_array($data['tables'])) return $fail('Varmuuskopiosta puuttuvat taulut.');
    $nimet = array('jasenet' => 'jäsenet', 'laskut' => 'laskut', 'rivit' => 'laskurivit', 'loki' => 'muutosloki');
    foreach (jasrek_backup_tables() as $key) {
        if (!array_key_exists($key, $data['tables'])) {
            if ($key === 'loki') continue; // muutosloki on valinnainen
            return $fail('Varmuuskopiosta puuttuvat ' . $nimet[$key] . '.');
        }
        if (!is_array($data['tables'][$key])) return $fail('Varmuuskopion ' . $nimet[$key] . ' ovat virheellisessä muodossa.');
        $ids = array();
        foreach ($data['tables'][$key] as $i => $row) {
            if (!is_array($row) || !isset($row['id']) || (int) $row['id'] < 1) {
                return $fail('Varmuuskopion ' . $nimet[$key] . ': rivi ' . ((int) $i + 1) . ' on virheellinen (tunniste puuttuu).');
            }
            foreach ($row as $col => $v) {
                if (!is_string($col) || ($v !== null && !is_scalar($v))) {
                    return $fail('Varmuuskopion ' . $nimet[$key] . ': rivillä ' . ((int) $i + 1) . ' on virheellinen arvo.');
                }
            }
            $id = (int) $row['id'];
            if (isset($ids[$id])) return $fail('Varmuuskopion ' . $nimet[$key] . ': tunniste ' . $id . ' on kahdesti.');
            $ids[$id] = true;
        }
        if ($key === 'jasenet') {
            foreach ($data['tables'][$key] as $i => $row) {
                if (!isset($row['etunimi'], $row['sukunimi'])) return $fail('Varmuuskopion jäsenet: rivillä ' . ((int) $i + 1) . ' ei ole nimeä.');
            }
        }
    }
    return array('ok' => true, 'data' => $data);
}

/** Yhteenveto varmuuskopiosta näytettäväksi. */
function jasrek_backup_summary($data) {
    $t = $data['tables'];
    return array(
        'created'     => isset($data['created']) ? (string) $data['created'] : '',
        'site'        => isset($data['site']) ? (string) $data['site'] : '',
        'association' => isset($data['association']) ? (string) $data['association'] : '',
        'version'     => isset($data['plugin_version']) ? (string) $data['plugin_version'] : '',
        'jasenet'     => count($t['jasenet']),
        'aktiiviset'  => count(array_filter($t['jasenet'], function ($r) { return isset($r['tila']) && $r['tila'] === 'aktiivinen'; })),
        'laskut'      => count($t['laskut']),
        'rivit'       => count($t['rivit']),
        'maksetut'    => count(array_filter($t['rivit'], function ($r) { return !empty($r['maksettu']); })),
        'loki'        => isset($t['loki']) ? count($t['loki']) : null,
    );
}

/**
 * Korvaa nykyiset tiedot varmuuskopiolla. Kutsujan pitää tarkistaa tiedosto ensin (jasrek_backup_validate).
 * Palauttaa true tai virheilmoituksen.
 */
function jasrek_backup_restore($data) {
    global $wpdb;
    $tables = array('rivit', 'laskut', 'jasenet');
    if (isset($data['tables']['loki'])) $tables[] = 'loki';

    $wpdb->query('START TRANSACTION');
    foreach ($tables as $key) {
        $table = $wpdb->{'jasrek_' . $key};
        $wpdb->query($wpdb->prepare('DELETE FROM %i', $table)); // Palautus korvaa kaikki rivit.
        $cols = array_flip(jasrek_backup_columns($key));
        foreach ($data['tables'][$key] as $row) {
            // Vain taulussa olevat sarakkeet; tuntemattomat (esim. uudemman version) ohitetaan.
            $row = array_intersect_key($row, $cols);
            $row['id'] = (int) $row['id'];
            if ($wpdb->insert($table, $row) === false) {
                $virhe = $wpdb->last_error;
                $wpdb->query('ROLLBACK');
                return 'Palautus keskeytyi (' . $key . ', tunniste ' . (int) $row['id'] . '): ' . $virhe;
            }
        }
    }

    // Asetukset: vain tunnetut avaimet. Tämän sivuston valinta "poista tiedot lisäosan poistossa" säilyy.
    $nykyiset = jasrek_settings();
    $uudet = array_intersect_key((array) $data['settings'], $nykyiset);
    $uudet['poista_tiedot'] = $nykyiset['poista_tiedot'];
    update_option('jasrek_settings', $uudet);
    $wpdb->query('COMMIT');

    // Vanhemman version varmuuskopio päivitetään nykyiseen muotoon.
    $dbv = (int) $data['db_version'];
    if ($dbv < (int) JASREK_DB_VERSION) jasrek_migrate($dbv);
    jasrek_schedule_cron();
    return true;
}

/** Palautusta edeltänyt tilanne (perumista varten), tallennetaan erilliseen asetukseen. */
function jasrek_backup_undo_get() {
    $u = get_option('jasrek_backup_edellinen', null);
    if (!is_array($u) || !isset($u['json'], $u['aika'])) return null;
    $u['json'] = jasrek_backup_unpack($u['json']);
    return $u;
}
function jasrek_backup_undo_save() {
    update_option('jasrek_backup_edellinen', array(
        'aika' => current_time('mysql'),
        'json' => jasrek_backup_pack(jasrek_backup_json(jasrek_backup_build(true))),
    ), false);
}

function jasrek_backup_filename($prefix = 'varmuuskopio') {
    $nimi = sanitize_title(jasrek_settings()['yhdistys']);
    return 'jasenrekisteri-' . $prefix . ($nimi !== '' ? '-' . $nimi : '') . '-' . current_time('Y-m-d') . '.json';
}

function jasrek_backup_download($json, $filename) {
    $body = $json;
    $type = 'application/json; charset=UTF-8';
    if (jasrek_backup_can_gzip()) {
        $body = gzencode($json, 9);
        $filename .= '.gz';
        $type = 'application/gzip';
    }
    nocache_headers();
    header('Content-Type: ' . $type);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($body));
    echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- file download (JSON or gzip), not HTML.
    exit;
}

/* ------------------------------------------------------------------
 * Käsittelijät
 * ------------------------------------------------------------------ */

add_action('admin_post_jasrek_backup_export', 'jasrek_handle_backup_export');
function jasrek_handle_backup_export() {
    jasrek_check('jasrek_backup_export');
    $loki = jasrek_post('loki') === '1';
    $c = jasrek_backup_current_counts();
    jasrek_log('vienti', 0, 'vienti', sprintf('Varmuuskopio ladattu (%d jäsentä, %d laskua%s)', $c['jasenet'], $c['laskut'], $loki ? ', muutosloki mukana' : ''));
    jasrek_backup_download(jasrek_backup_json(jasrek_backup_build($loki)), jasrek_backup_filename());
}

add_action('admin_post_jasrek_backup_upload', 'jasrek_handle_backup_upload');
function jasrek_handle_backup_upload() {
    jasrek_check('jasrek_backup_upload');
    $uid = get_current_user_id();
    $f = jasrek_uploaded_file('tiedosto');
    if (!$f) jasrek_redirect('jasrek-asetukset', array('jasrek_msg' => 'backup_upload_failed'));
    $json = jasrek_backup_read_upload(file_get_contents($f['tmp_name']));
    $v = $json === null ? array('ok' => false, 'virhe' => 'Pakattua tiedostoa ei voitu purkaa (tiedosto on vioittunut tai palvelimelta puuttuu zlib).') : jasrek_backup_validate($json);
    if (!$v['ok']) {
        set_transient('jasrek_backup_err_' . $uid, $v['virhe'], 10 * MINUTE_IN_SECONDS);
        jasrek_redirect('jasrek-asetukset', array('jasrek_msg' => 'backup_invalid'));
    }
    set_transient('jasrek_backup_' . $uid, array('json' => jasrek_backup_pack($json), 'tiedosto' => $f['name']), HOUR_IN_SECONDS);
    jasrek_redirect('jasrek-asetukset', array('view' => 'palautus'));
}

add_action('admin_post_jasrek_backup_restore', 'jasrek_handle_backup_restore');
function jasrek_handle_backup_restore() {
    jasrek_check('jasrek_backup_restore');
    $uid = get_current_user_id();
    $t = get_transient('jasrek_backup_' . $uid);
    if (!is_array($t) || !isset($t['json'])) jasrek_redirect('jasrek-asetukset', array('jasrek_msg' => 'backup_expired'));
    if (jasrek_post('vahvistus') !== '1') jasrek_redirect('jasrek-asetukset', array('view' => 'palautus', 'jasrek_msg' => 'backup_confirm'));
    $v = jasrek_backup_validate(jasrek_backup_unpack($t['json']));
    if (!$v['ok']) {
        set_transient('jasrek_backup_err_' . $uid, $v['virhe'], 10 * MINUTE_IN_SECONDS);
        jasrek_redirect('jasrek-asetukset', array('jasrek_msg' => 'backup_invalid'));
    }
    jasrek_backup_undo_save();
    $r = jasrek_backup_restore($v['data']);
    if ($r !== true) {
        set_transient('jasrek_backup_err_' . $uid, $r, 10 * MINUTE_IN_SECONDS);
        jasrek_redirect('jasrek-asetukset', array('jasrek_msg' => 'backup_invalid'));
    }
    delete_transient('jasrek_backup_' . $uid);
    $sum = jasrek_backup_summary($v['data']);
    jasrek_log('vienti', 0, 'palautus', sprintf('Varmuuskopio palautettu: %s (tehty %s%s), %d jäsentä, %d laskua',
        $t['tiedosto'], $sum['created'] ? jasrek_date(substr($sum['created'], 0, 10)) : '?', $sum['site'] ? ', ' . $sum['site'] : '', $sum['jasenet'], $sum['laskut']));
    jasrek_redirect('jasrek-asetukset', array('jasrek_msg' => 'backup_restored', 'jasrek_n' => $sum['jasenet']));
}

add_action('admin_post_jasrek_backup_undo', 'jasrek_handle_backup_undo');
function jasrek_handle_backup_undo() {
    jasrek_check('jasrek_backup_undo');
    $u = jasrek_backup_undo_get();
    if (!$u) jasrek_redirect('jasrek-asetukset', array('jasrek_msg' => 'backup_expired'));
    $op = jasrek_post('op');
    if ($op === 'lataa') {
        jasrek_log('vienti', 0, 'vienti', 'Palautusta edeltänyt tilanne ladattu (' . jasrek_date(substr($u['aika'], 0, 10)) . ')');
        jasrek_backup_download($u['json'], jasrek_backup_filename('ennen-palautusta'));
    }
    if ($op === 'poista') {
        delete_option('jasrek_backup_edellinen');
        jasrek_redirect('jasrek-asetukset', array('jasrek_msg' => 'backup_undo_deleted'));
    }
    if ($op === 'palauta') {
        $v = jasrek_backup_validate($u['json']);
        if (!$v['ok']) {
            set_transient('jasrek_backup_err_' . get_current_user_id(), $v['virhe'], 10 * MINUTE_IN_SECONDS);
            jasrek_redirect('jasrek-asetukset', array('jasrek_msg' => 'backup_invalid'));
        }
        jasrek_backup_undo_save(); // perumisen voi vielä perua
        $r = jasrek_backup_restore($v['data']);
        if ($r !== true) {
            set_transient('jasrek_backup_err_' . get_current_user_id(), $r, 10 * MINUTE_IN_SECONDS);
            jasrek_redirect('jasrek-asetukset', array('jasrek_msg' => 'backup_invalid'));
        }
        jasrek_log('vienti', 0, 'palautus', 'Palautus peruttu: palautettiin ' . jasrek_date(substr($u['aika'], 0, 10)) . ' tallennettu tilanne');
        jasrek_redirect('jasrek-asetukset', array('jasrek_msg' => 'backup_undone'));
    }
    jasrek_redirect('jasrek-asetukset');
}

/* ------------------------------------------------------------------
 * Näkymät
 * ------------------------------------------------------------------ */

/** Asetussivun paneeli (asetuslomakkeen ulkopuolella). */
function jasrek_backup_panel() {
    $c = jasrek_backup_current_counts();
    $u = jasrek_backup_undo_get();
    $post = esc_url(admin_url('admin-post.php'));
    ?>
    <div class="jasrek-panel" id="jasrek-varmuuskopio">
        <h2>Varmuuskopio ja siirto</h2>
        <p class="description">Varmuuskopio on yksi JSON-tiedosto, jossa ovat kaikki jäsenet, laskut, maksut, asetukset ja halutessa muutosloki.
            Sillä voi siirtää jäsenrekisterin toiselle sivustolle tai ottaa turvakopion esimerkiksi ennen laskutusta.
            Koko sivuston siirrossa (esim. siirtolisäosalla) rekisteri siirtyy jo muun tietokannan mukana.</p>
        <p class="jasrek-hint">⚠ Tiedosto sisältää jäsenten henkilötiedot. Säilytä se turvallisesti, äläkä lähetä sitä salaamattomana sähköpostilla.</p>

        <div class="jasrek-columns">
            <form method="post" action="<?php echo $post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above with esc_url(). ?>">
                <input type="hidden" name="action" value="jasrek_backup_export">
                <?php wp_nonce_field('jasrek_backup_export'); ?>
                <h3>Lataa varmuuskopio</h3>
                <p><?php echo esc_html(sprintf('Nyt rekisterissä: %d jäsentä, %d laskua, %d laskuriviä, %d lokimerkintää.', $c['jasenet'], $c['laskut'], $c['rivit'], $c['loki'])); ?></p>
                <p><label class="jasrek-radio"><input type="checkbox" name="loki" value="1" checked> Sisällytä muutosloki</label></p>
                <p><button type="submit" class="button button-primary">⬇ Lataa varmuuskopio</button></p>
                <?php if (jasrek_backup_can_gzip()): ?><p class="description">Tiedosto ladataan pakattuna (.json.gz), jotta se mahtuu sivustojen latausrajaan. Sen voi purkaa tavalliseksi JSON-tiedostoksi esim. 7-Zipillä.</p><?php endif; ?>
            </form>

            <form method="post" action="<?php echo $post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above with esc_url(). ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="jasrek_backup_upload">
                <?php wp_nonce_field('jasrek_backup_upload'); ?>
                <h3>Palauta varmuuskopiosta</h3>
                <p><input type="file" name="tiedosto" accept=".json,.gz,application/json,application/gzip" required></p>
                <p class="description">Ennen palautusta näytetään yhteenveto, eikä mitään muuteta ennen vahvistusta. Palautus <strong>korvaa kaikki nykyiset tiedot</strong>.
                    Kelpaa .json tai pakattu .json.gz. Suurin tiedostokoko tällä sivustolla: <?php echo esc_html(size_format(wp_max_upload_size())); ?>.</p>
                <p><button type="submit" class="button">Lue varmuuskopio…</button></p>
            </form>
        </div>

        <?php if ($u): ?>
        <form method="post" action="<?php echo $post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above with esc_url(). ?>" class="jasrek-backup-undo">
            <input type="hidden" name="action" value="jasrek_backup_undo">
            <?php wp_nonce_field('jasrek_backup_undo'); ?>
            <p><strong>Palautusta edeltänyt tilanne on tallessa</strong> (<?php echo esc_html(jasrek_date(substr($u['aika'], 0, 10)) . ' ' . substr($u['aika'], 11, 5)); ?>).
                Jos palautus meni väärin, voit palata siihen.</p>
            <p>
                <button type="submit" name="op" value="palauta" class="button" data-confirm="Palautetaanko palautusta edeltänyt tilanne? Nykyiset tiedot korvataan.">↶ Peru palautus</button>
                <button type="submit" name="op" value="lataa" class="button">⬇ Lataa tiedostona</button>
                <button type="submit" name="op" value="poista" class="button jasrek-btn-danger" data-confirm="Poistetaanko tallessa oleva tilanne? Sen jälkeen palautusta ei voi perua.">Poista</button>
            </p>
            <p class="description">Tallennettu tilanne sisältää henkilötietoja. Poista se, kun palautus on todettu onnistuneeksi.</p>
        </form>
        <?php endif; ?>
    </div>
    <?php
}

/** Palautuksen vahvistussivu (Asetukset → view=palautus). */
function jasrek_backup_confirm_page() {
    $t = get_transient('jasrek_backup_' . get_current_user_id());
    $back = admin_url('admin.php?page=jasrek-asetukset#jasrek-varmuuskopio');
    echo '<div class="wrap jasrek-wrap"><h1>Palauta varmuuskopio</h1>';
    jasrek_notice();
    $v = is_array($t) && isset($t['json']) ? jasrek_backup_validate(jasrek_backup_unpack($t['json'])) : array('ok' => false);
    if (!$v['ok']) {
        echo '<div class="notice notice-warning"><p>' . esc_html('Varmuuskopiota ei löytynyt tai se on vanhentunut. Lue tiedosto uudelleen.') . '</p></div>';
        echo '<p><a class="button" href="' . esc_url($back) . '">← Takaisin asetuksiin</a></p></div>';
        return;
    }
    $s = jasrek_backup_summary($v['data']);
    $c = jasrek_backup_current_counts();
    $luotu = $s['created'] ? jasrek_date(substr($s['created'], 0, 10)) . ' ' . substr($s['created'], 11, 5) : '–';
    ?>
        <div class="jasrek-panel">
            <h2>Varmuuskopio: <?php echo esc_html($t['tiedosto']); ?></h2>
            <table class="widefat striped jasrek-table jasrek-backup-compare">
                <thead><tr><th></th><th>Varmuuskopiossa</th><th>Nyt rekisterissä</th></tr></thead>
                <tbody>
                    <tr><th>Yhdistys</th><td><?php echo esc_html($s['association']); ?></td><td><?php echo esc_html(jasrek_settings()['yhdistys']); ?></td></tr>
                    <tr><th>Sivusto</th><td><?php echo esc_html($s['site']); ?></td><td><?php echo esc_html(home_url()); ?></td></tr>
                    <tr><th>Tehty</th><td><?php echo esc_html($luotu . ($s['version'] ? ' (versio ' . $s['version'] . ')' : '')); ?></td><td>–</td></tr>
                    <tr><th>Jäseniä</th><td><?php echo esc_html($s['jasenet'] . ' (voimassa ' . $s['aktiiviset'] . ')'); ?></td><td><?php echo (int) $c['jasenet']; ?></td></tr>
                    <tr><th>Laskuja</th><td><?php echo (int) $s['laskut']; ?></td><td><?php echo (int) $c['laskut']; ?></td></tr>
                    <tr><th>Laskurivejä</th><td><?php echo esc_html($s['rivit'] . ' (maksettu ' . $s['maksetut'] . ')'); ?></td><td><?php echo (int) $c['rivit']; ?></td></tr>
                    <tr><th>Muutosloki</th><td><?php echo $s['loki'] === null ? esc_html('ei mukana – nykyinen loki säilyy') : (int) $s['loki'] . ' merkintää'; ?></td><td><?php echo (int) $c['loki']; ?> merkintää</td></tr>
                </tbody>
            </table>
            <div class="notice notice-warning inline jasrek-keep"><p><strong>Palautus korvaa kaikki nykyiset jäsenet, laskut, maksut ja asetukset<?php echo $s['loki'] === null ? '' : ' sekä muutoslokin'; ?>.</strong>
                Nykyinen tilanne tallennetaan ensin, joten palautuksen voi perua asetussivulla.</p></div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="jasrek_backup_restore">
                <?php wp_nonce_field('jasrek_backup_restore'); ?>
                <p><label class="jasrek-radio"><input type="checkbox" name="vahvistus" value="1" required> Ymmärrän, että nykyiset tiedot korvataan varmuuskopion tiedoilla.</label></p>
                <p><button type="submit" class="button button-primary">Palauta varmuuskopio</button>
                   <a class="button" href="<?php echo esc_url($back); ?>">Peruuta</a></p>
            </form>
        </div>
    </div>
    <?php
}
