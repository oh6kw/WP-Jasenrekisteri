<?php
if (!defined('ABSPATH')) exit;

/*
 * Muutosloki: kuka muutti mitä ja milloin.
 *
 * kohde:    jasen (jäsenen tiedot), maksu (jäsenen yksittäinen lasku: maksu, lähetys, postitus),
 *           lasku (koko lasku), asetukset, viesti (tiedotteet), vienti (tuonnit ja viennit)
 * kohde_id: jäsenen id (jasen, maksu), laskun id (lasku), muuten 0
 * muutokset: JSON-lista [kenttä, vanha, uusi] tai vapaamuotoiset lisätiedot
 */

function jr_log($kohde, $kohde_id, $toiminto, $kuvaus, $muutokset = null) {
    global $wpdb;
    $u = wp_get_current_user();
    $nimi = ($u && $u->ID) ? $u->display_name . ($u->user_login !== $u->display_name ? ' (' . $u->user_login . ')' : '') : 'Järjestelmä (automaattinen)';
    $wpdb->insert(jr_t('loki'), array(
        'aika'        => current_time('mysql'),
        'kayttaja_id' => $u ? (int) $u->ID : 0,
        'kayttaja'    => function_exists('mb_substr') ? mb_substr($nimi, 0, 100) : substr($nimi, 0, 100),
        'kohde'       => $kohde,
        'kohde_id'    => (int) $kohde_id,
        'toiminto'    => $toiminto,
        'kuvaus'      => (string) $kuvaus,
        'muutokset'   => ($muutokset === null || $muutokset === array()) ? null : wp_json_encode($muutokset),
    ));
    $id = (int) $wpdb->insert_id;
    jr_log_cleanup();
    return $id;
}

/** Poistaa säilytysajan ylittäneet merkinnät (kerran vuorokaudessa). */
function jr_log_cleanup($force = false) {
    if (!$force && get_transient('jr_log_cleanup')) return;
    set_transient('jr_log_cleanup', 1, DAY_IN_SECONDS);
    $kk = (int) jr_settings()['loki_kk'];
    if ($kk <= 0) return;
    global $wpdb;
    $raja = date('Y-m-d H:i:s', strtotime(current_time('mysql') . " -$kk months"));
    $wpdb->query($wpdb->prepare('DELETE FROM ' . jr_t('loki') . ' WHERE aika < %s', $raja));
}

/** Muutokset kahden tilannekuvan välillä: [[kenttä, vanha, uusi], ...]. */
function jr_log_diff($old, $new) {
    $out = array();
    foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $k) {
        $a = isset($old[$k]) ? (string) $old[$k] : '';
        $b = isset($new[$k]) ? (string) $new[$k] : '';
        if ($a !== $b) $out[] = array($k, $a, $b);
    }
    return $out;
}

/** Jäsenen tiedot luettavassa muodossa lokia varten (kenttä => arvo). */
function jr_member_snapshot($m) {
    if (!$m) return array();
    $m = (object) $m;
    $tilat = jr_tilat();
    $s = array(
        'Etunimi'     => (string) $m->etunimi,
        'Sukunimi'    => (string) $m->sukunimi,
    );
    if (jr_tunnus_on() || (string) $m->tunnus !== '') $s[jr_tunnus_nimi()] = (string) $m->tunnus;
    $s += array(
        'Sähköposti'  => (string) $m->email,
        'Puhelin'     => (string) $m->puhelin,
        'Osoite'      => (string) $m->osoite,
        'Liittynyt'   => jr_date($m->liittynyt),
        'Jäsenluokka' => jr_laji_nimi((string) $m->jasenlaji) !== '' ? jr_laji_nimi((string) $m->jasenlaji) : (string) $m->jasenlaji,
        'Jäsenyys'    => isset($tilat[$m->tila]) ? $tilat[$m->tila] : (string) $m->tila,
        'Eronnut'     => isset($m->eronnut_pvm) ? jr_date($m->eronnut_pvm) : '',
        'Paperilasku' => !empty($m->paperilasku) ? 'kyllä' : '',
        'Lisätiedot'  => (string) $m->lisatiedot,
    );
    $lisa = jr_member_lisa($m);
    foreach (jr_lisakentat() as $id => $k) {
        $v = isset($lisa[$id]) ? (string) $lisa[$id] : '';
        $s[$k['nimi']] = $k['tyyppi'] === 'valinta' ? ($v !== '' ? 'kyllä' : '') : $v;
    }
    return $s;
}

function jr_log_member_name($m) {
    $m = (object) $m;
    return trim($m->etunimi . ' ' . $m->sukunimi);
}

function jr_log_toiminnot() {
    return array(
        'lisatty'          => 'Jäsen lisätty',
        'muokattu'         => 'Tietoja muutettu',
        'poistettu'        => 'Jäsen poistettu',
        'tuotu'            => 'Lisätty CSV-tuonnilla',
        'tuonti_paivitys'  => 'Päivitetty CSV-tuonnilla',
        'laskulle'         => 'Lisätty laskulle',
        'maksettu'         => 'Merkitty maksetuksi',
        'maksu_peruttu'    => 'Maksumerkintä peruttu',
        'poistettu_laskulta' => 'Poistettu laskulta',
        'lahetetty'        => 'Lasku lähetetty',
        'muistutettu'      => 'Muistutus lähetetty',
        'postitettu'       => 'Paperilasku postitettu',
        'muistutus_postitettu' => 'Paperimuistutus postitettu',
        'lasku_luotu'      => 'Lasku luotu',
        'lasku_muokattu'   => 'Laskua muokattu',
        'lasku_poistettu'  => 'Lasku poistettu',
        'jasenia_lisatty'  => 'Jäseniä lisätty laskulle',
        'lahetys'          => 'Laskut lähetetty',
        'muistutukset'     => 'Muistutukset lähetetty',
        'auto_muistutukset'=> 'Automaattiset muistutukset',
        'asetukset'        => 'Asetuksia muutettu',
        'tiedote'          => 'Tiedote lähetetty',
        'vienti'           => 'Tiedot viety',
        'tuonti'           => 'Tiedot tuotu',
    );
}

function jr_log_kohteet() {
    return array(
        ''          => 'Kaikki',
        'jasen'     => 'Jäsenten tiedot',
        'maksu'     => 'Maksut ja lähetykset',
        'lasku'     => 'Laskut',
        'asetukset' => 'Asetukset',
        'viesti'    => 'Tiedotteet',
        'vienti'    => 'Tuonnit ja viennit',
    );
}

/**
 * Lähetysajo (useita AJAX-eriä) kirjataan yhdeksi merkinnäksi, jota päivitetään erä kerrallaan.
 */
function jr_log_run($lasku, $toiminto, $run_key, $sent_names, $failed) {
    global $wpdb;
    $t = jr_t('loki');
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $t WHERE kohde = 'lasku' AND kohde_id = %d AND toiminto = %s AND kayttaja_id = %d ORDER BY id DESC LIMIT 1",
        $lasku->id, $toiminto, get_current_user_id()
    ));
    $data = $row && $row->muutokset ? json_decode($row->muutokset, true) : null;
    if (!is_array($data) || !isset($data['ajo']) || $data['ajo'] !== $run_key) {
        if (!$sent_names && !$failed) return;
        $data = array('ajo' => $run_key, 'nimet' => array(), 'epaonnistui' => 0);
        $row = null;
    }
    $data['nimet'] = array_merge($data['nimet'], $sent_names);
    $data['epaonnistui'] += $failed;
    $n = count($data['nimet']);
    $kuvaus = $lasku->nimi . ': ' . $n . ($toiminto === 'muistutukset' ? ' muistutusta' : ' laskua') . ' sähköpostilla'
        . ($data['epaonnistui'] ? ', ' . $data['epaonnistui'] . ' epäonnistui' : '');
    if ($row) {
        $wpdb->update($t, array('kuvaus' => $kuvaus, 'muutokset' => wp_json_encode($data), 'aika' => current_time('mysql')), array('id' => $row->id));
    } else {
        jr_log('lasku', $lasku->id, $toiminto, $kuvaus, $data);
    }
}

/* ------------------------------------------------------------------
 * Näkymät
 * ------------------------------------------------------------------ */

/** Muutokset HTML-listana. */
function jr_log_changes_html($json) {
    if (!$json) return '';
    $d = json_decode($json, true);
    if (!is_array($d)) return '';
    if (isset($d['nimet'])) {
        $n = $d['nimet'];
        $txt = implode(', ', array_slice($n, 0, 30)) . (count($n) > 30 ? ' … (+' . (count($n) - 30) . ')' : '');
        return $n ? '<div class="jr-sub">' . esc_html($txt) . '</div>' : '';
    }
    $out = '<ul class="jr-log-changes">';
    foreach ($d as $c) {
        if (!is_array($c) || count($c) < 3) continue;
        $out .= '<li><span class="jr-log-field">' . esc_html($c[0]) . ':</span> ';
        if ((string) $c[1] !== '') $out .= '<del>' . esc_html(wp_html_excerpt((string) $c[1], 120, '…')) . '</del> → ';
        $out .= (string) $c[2] !== '' ? '<ins>' . esc_html(wp_html_excerpt((string) $c[2], 120, '…')) . '</ins>' : '<em class="jr-muted">tyhjä</em>';
        $out .= '</li>';
    }
    return $out . '</ul>';
}

function jr_log_target_link($e) {
    if (($e->kohde === 'jasen' || $e->kohde === 'maksu') && $e->kohde_id) {
        return admin_url('admin.php?page=jasenrekisteri&view=edit&id=' . (int) $e->kohde_id);
    }
    if ($e->kohde === 'lasku' && $e->kohde_id) {
        return admin_url('admin.php?page=jr-laskutus&view=lasku&id=' . (int) $e->kohde_id);
    }
    return '';
}

function jr_log_table($entries, $show_target = true) {
    $labels = jr_log_toiminnot();
    ?>
    <table class="widefat striped jr-table jr-log">
        <thead><tr><th style="width:130px">Aika</th><th style="width:170px">Käyttäjä</th><th>Tapahtuma</th></tr></thead>
        <tbody>
        <?php if (!$entries): ?><tr><td colspan="3" class="jr-empty">Ei merkintöjä.</td></tr><?php endif; ?>
        <?php foreach ($entries as $e):
            $link = $show_target ? jr_log_target_link($e) : ''; ?>
            <tr>
                <td><?php echo esc_html(date('j.n.Y H:i', strtotime($e->aika))); ?></td>
                <td><?php echo esc_html($e->kayttaja); ?></td>
                <td><span class="jr-badge jr-log-<?php echo esc_attr($e->kohde); ?>"><?php echo esc_html(isset($labels[$e->toiminto]) ? $labels[$e->toiminto] : $e->toiminto); ?></span>
                    <?php echo $link ? '<a href="' . esc_url($link) . '">' . esc_html($e->kuvaus) . '</a>' : esc_html($e->kuvaus); ?>
                    <?php echo jr_log_changes_html($e->muutokset); // phpcs:ignore -- escapattu funktiossa ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/** Jäsenen muutoshistoria jäsenen lomakkeen alle. */
function jr_member_log_panel($m) {
    global $wpdb;
    $entries = $wpdb->get_results($wpdb->prepare(
        'SELECT * FROM ' . jr_t('loki') . " WHERE kohde IN ('jasen','maksu') AND kohde_id = %d ORDER BY id DESC LIMIT 15", $m->id
    ));
    ?>
    <div class="jr-panel">
        <h2>Muutoshistoria</h2>
        <?php jr_log_table($entries, false); ?>
        <p><a href="<?php echo esc_url(admin_url('admin.php?page=jr-loki&jasen=' . (int) $m->id)); ?>">Näytä kaikki jäsenen merkinnät muutoslokissa →</a></p>
    </div>
    <?php
}

function jr_page_log() {
    global $wpdb;
    $t = jr_t('loki');
    $kohteet = jr_log_kohteet();
    $kohde = isset($_GET['kohde']) && isset($kohteet[$_GET['kohde']]) ? sanitize_key($_GET['kohde']) : '';
    $q     = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
    $jasen = isset($_GET['jasen']) ? intval($_GET['jasen']) : 0;
    $sivu  = max(1, isset($_GET['sivu']) ? intval($_GET['sivu']) : 1);
    $per   = 50;

    $where = array('1=1');
    if ($kohde !== '') $where[] = $wpdb->prepare('kohde = %s', $kohde);
    if ($jasen) $where[] = $wpdb->prepare("kohde IN ('jasen','maksu') AND kohde_id = %d", $jasen);
    if ($q !== '') {
        $like = '%' . $wpdb->esc_like($q) . '%';
        $where[] = $wpdb->prepare('(kuvaus LIKE %s OR kayttaja LIKE %s OR muutokset LIKE %s)', $like, $like, $like);
    }
    $w = implode(' AND ', $where);
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE $w");
    $entries = $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE $w ORDER BY id DESC LIMIT %d OFFSET %d", $per, ($sivu - 1) * $per));
    $pages = max(1, (int) ceil($total / $per));
    $kk = (int) jr_settings()['loki_kk'];
    $jm = $jasen ? $wpdb->get_row($wpdb->prepare('SELECT etunimi, sukunimi FROM ' . jr_t('jasenet') . ' WHERE id = %d', $jasen)) : null;
    ?>
    <div class="wrap jr-wrap">
        <h1>Muutosloki</h1>
        <p class="description">Kaikki jäsenrekisteriin, laskuihin ja asetuksiin tehdyt muutokset: kuka, mitä ja milloin.
            <?php echo $kk > 0 ? 'Merkinnät säilytetään ' . (int) $kk . ' kuukautta.' : 'Merkinnät säilytetään pysyvästi.'; ?>
            Säilytysajan voi muuttaa <a href="<?php echo esc_url(admin_url('admin.php?page=jr-asetukset#jr-loki')); ?>">asetuksissa</a>.</p>

        <form method="get" class="jr-toolbar">
            <input type="hidden" name="page" value="jr-loki">
            <?php if ($jasen): ?><input type="hidden" name="jasen" value="<?php echo (int) $jasen; ?>"><?php endif; ?>
            <div class="jr-toolbar-right">
                <select name="kohde">
                    <?php foreach ($kohteet as $k => $label): ?><option value="<?php echo esc_attr($k); ?>" <?php selected($kohde, $k); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                </select>
                <input type="search" name="q" value="<?php echo esc_attr($q); ?>" class="jr-search" placeholder="Hae nimellä, käyttäjällä tai muutoksella…">
                <button type="submit" class="button">Suodata</button>
                <?php if ($kohde !== '' || $q !== '' || $jasen): ?><a class="button-link" href="<?php echo esc_url(admin_url('admin.php?page=jr-loki')); ?>">Tyhjennä suodatus</a><?php endif; ?>
            </div>
            <span class="jr-muted"><?php echo $jm ? esc_html('Jäsen: ' . $jm->etunimi . ' ' . $jm->sukunimi . ' · ') : ($jasen ? 'Jäsen #' . (int) $jasen . ' (poistettu) · ' : ''); ?><?php echo (int) $total; ?> merkintää</span>
        </form>

        <?php jr_log_table($entries); ?>

        <?php if ($pages > 1): ?>
        <p class="jr-pager">
            <?php for ($i = 1; $i <= $pages; $i++):
                if ($pages > 12 && abs($i - $sivu) > 3 && $i !== 1 && $i !== $pages) { if (abs($i - $sivu) === 4) echo '<span>…</span> '; continue; } ?>
                <?php if ($i === $sivu): ?><span class="button disabled"><?php echo $i; ?></span>
                <?php else: ?><a class="button" href="<?php echo esc_url(add_query_arg(array('sivu' => $i))); ?>"><?php echo $i; ?></a><?php endif; ?>
            <?php endfor; ?>
        </p>
        <?php endif; ?>
    </div>
    <?php
}
