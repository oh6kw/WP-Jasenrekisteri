<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own custom tables (members, invoices, audit log). The data is admin-only and changes on every write, so object caching is not used.

/*
 * Jäsenten tuonti CSV-tiedostosta (Excel: Tallenna nimellä → CSV UTF-8).
 * Vaihe 1: tiedoston lataus. Vaihe 2: sarakkeiden valinta ja tuplien käsittely. Vaihe 3: tuonti.
 */

function jasrek_import_fields() {
    $f = array(
        ''                => '— ei tuoda —',
        'etunimi'         => 'Etunimi',
        'sukunimi'        => 'Sukunimi',
        'nimi_es'         => 'Koko nimi (Etunimi Sukunimi)',
        'nimi_se'         => 'Koko nimi (Sukunimi Etunimi)',
        'tunnus'          => jasrek_tunnus_nimi(),
        'osoite'          => 'Osoite',
        'postinumero'     => 'Postinumero',
        'postitoimipaikka'=> 'Postitoimipaikka',
        'puhelin'         => 'Puhelin',
        'email'           => 'Sähköposti',
        'liittynyt'       => 'Liittynyt',
        'syntynyt'        => 'Syntymävuosi tai -päivä',
        'jasenlaji'       => 'Jäsenluokka',
        'tila'            => 'Jäsenyys (voimassa / eronnut)',
        'eronnut_pvm'     => 'Eroamispäivä',
        'paperilasku'     => 'Paperilasku (kyllä / tyhjä)',
        'lisatiedot'      => 'Lisätiedot',
    );
    foreach (jasrek_lisakentat() as $kid => $k) $f['lisa_' . $kid] = 'Lisäkenttä: ' . $k['nimi'];
    return $f;
}

/** Arvaa kentän sarakeotsikosta. */
function jasrek_guess_field($header) {
    $h = strtolower(remove_accents(trim($header)));
    $h = preg_replace('/[^a-z0-9]+/', '', $h);
    $tn = preg_replace('/[^a-z0-9]+/', '', strtolower(remove_accents(jasrek_tunnus_nimi())));
    $map = array(
        'etunimi' => 'etunimi', 'etunimet' => 'etunimi', 'firstname' => 'etunimi',
        'sukunimi' => 'sukunimi', 'lastname' => 'sukunimi',
        'nimi' => 'nimi_es', 'kokonimi' => 'nimi_es', 'name' => 'nimi_es',
        'tunnus' => 'tunnus', 'jasennumero' => 'tunnus', 'jasennro' => 'tunnus', 'jasentunnus' => 'tunnus',
        'osoite' => 'osoite', 'katuosoite' => 'osoite', 'lahiosoite' => 'osoite', 'address' => 'osoite',
        'postinumero' => 'postinumero', 'postinro' => 'postinumero', 'postnr' => 'postinumero',
        'postitoimipaikka' => 'postitoimipaikka', 'paikkakunta' => 'postitoimipaikka', 'kaupunki' => 'postitoimipaikka', 'kunta' => 'postitoimipaikka',
        'puhelin' => 'puhelin', 'puh' => 'puhelin', 'puhelinnumero' => 'puhelin', 'matkapuhelin' => 'puhelin', 'gsm' => 'puhelin', 'phone' => 'puhelin',
        'sahkoposti' => 'email', 'email' => 'email', 'sposti' => 'email', 'emailosoite' => 'email', 'sahkopostiosoite' => 'email',
        'liittynyt' => 'liittynyt', 'liittymispaiva' => 'liittynyt', 'liittymisvuosi' => 'liittynyt', 'jasenalkaen' => 'liittynyt',
        'syntynyt' => 'syntynyt', 'syntymaaika' => 'syntynyt', 'syntymapaiva' => 'syntynyt', 'syntymavuosi' => 'syntynyt', 'syntymavuositaipaiva' => 'syntynyt', 'syntynytvuonna' => 'syntynyt', 'birthdate' => 'syntynyt',
        'jasenlaji' => 'jasenlaji', 'laji' => 'jasenlaji', 'jasentyyppi' => 'jasenlaji', 'jasenluokka' => 'jasenlaji', 'luokka' => 'jasenlaji',
        'jasenyys' => 'tila', 'tila' => 'tila', 'status' => 'tila',
        'lisatiedot' => 'lisatiedot', 'huom' => 'lisatiedot', 'huomautukset' => 'lisatiedot', 'muistiinpanot' => 'lisatiedot',
        'eronnut' => 'eronnut_pvm', 'eroamispaiva' => 'eronnut_pvm', 'eronnutpvm' => 'eronnut_pvm', 'eropaiva' => 'eronnut_pvm',
        'paperilasku' => 'paperilasku', 'laskupaperilla' => 'paperilasku',
    );
    if ($tn !== '' && $h === $tn) return 'tunnus';
    foreach (jasrek_lisakentat() as $kid => $k) {
        if ($h === preg_replace('/[^a-z0-9]+/', '', strtolower(remove_accents($k['nimi'])))) return 'lisa_' . $kid;
    }
    return isset($map[$h]) ? $map[$h] : '';
}

function jasrek_parse_csv_table($content) {
    $content = jasrek_to_utf8((string) $content);
    $lines = preg_split('/\r\n|\r|\n/', $content);
    $lines = array_values(array_filter($lines, function ($l) { return trim($l, " ;,\t") !== ''; }));
    if (!$lines) return null;
    $delim = ';'; $best = -1;
    foreach (array(';', ',', "\t") as $d) {
        $c = substr_count($lines[0], $d);
        if ($c > $best) { $best = $c; $delim = $d; }
    }
    // Rivit voivat sisältää lainausmerkeissä rivinvaihtoja: jäsennetään koko teksti.
    $rows = array();
    // php://temp on muistissa oleva virta, jotta fgetcsv voi jäsentää lainausmerkeissä olevat rivinvaihdot.
    $fh = fopen('php://temp', 'r+'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- in-memory stream, not a file.
    fwrite($fh, implode("\n", $lines)); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- in-memory stream.
    rewind($fh);
    while (($r = fgetcsv($fh, 0, $delim)) !== false) {
        if ($r === array(null)) continue;
        $rows[] = array_map(function ($c) {
            $c = trim((string) $c);
            if (preg_match('/^="(.*)"$/s', $c, $m)) return $m[1];          // ="0401234567" (oma vienti)
            if (preg_match("/^'[=@+\-]/", $c)) return substr($c, 1);       // kaavasuojauksen heittomerkki
            return $c;
        }, $r);
    }
    fclose($fh); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- in-memory stream.
    if (count($rows) < 2) return null;
    return array('otsikot' => array_shift($rows), 'rivit' => $rows);
}

function jasrek_import_tila($s, $oletus) {
    $s = strtolower(remove_accents(trim($s)));
    if ($s === '') return $oletus;
    if (strpos($s, 'eronn') !== false || $s === 'ero' || strpos($s, 'erosi') !== false) return 'eronnut';
    return 'aktiivinen';
}

/* ------------------------------------------------------------------
 * Käsittelijät
 * ------------------------------------------------------------------ */

add_action('admin_post_jasrek_members_template', function () {
    jasrek_check('jasrek_members_template');
    $kentat = array_values(array_map(function ($k) { return $k['nimi']; }, jasrek_lisakentat()));
    $head = array_merge(array('Etunimi', 'Sukunimi', jasrek_tunnus_nimi(), 'Osoite', 'Puhelin', 'Sähköposti', 'Liittynyt', 'Syntynyt', 'Jäsenluokka', 'Jäsenyys', 'Eronnut', 'Paperilasku', 'Lisätiedot'), $kentat);
    $tyhjat = array_fill(0, count($kentat), '');
    $nimet = array_values(array_map(function ($l) { return $l['nimi']; }, jasrek_jasenlajit()));
    jasrek_csv_output('jasenrekisteri-malli.csv', $head, array(
        array_merge(array('Matti', 'Meikäläinen', '', 'Esimerkkitie 1, 00100 Helsinki', '040 123 4567', 'matti@example.com', '1.1.2020', '12.3.1976', isset($nimet[0]) ? $nimet[0] : 'Jäsen', 'Voimassa', '', '', ''), $tyhjat),
        array_merge(array('Maija', 'Mallikas', '', 'Mallitie 2, 00200 Helsinki', '', '', '2015', '1950', isset($nimet[1]) ? $nimet[1] : 'Kunniajäsen', 'Voimassa', '', 'Kyllä', ''), $tyhjat),
        array_merge(array('Erkki', 'Esimerkki', '', '', '', '', '2001', '', '', 'Eronnut', '31.12.2025', '', ''), $tyhjat),
    ));
});

add_action('admin_post_jasrek_members_upload', 'jasrek_handle_members_upload');
function jasrek_handle_members_upload() {
    jasrek_check('jasrek_members_upload');
    $f = jasrek_uploaded_file('tiedosto');
    if (!$f || $f['size'] > 5 * MB_IN_BYTES) {
        jasrek_redirect('jasenrekisteri', array('view' => 'tuonti', 'jasrek_msg' => 'upload_failed'));
    }
    $t = jasrek_parse_csv_table(file_get_contents($f['tmp_name']));
    if (!$t) jasrek_redirect('jasenrekisteri', array('view' => 'tuonti', 'jasrek_msg' => 'csv_empty'));
    $t['tiedosto'] = $f['name'];
    $t['gen'] = jasrek_data_gen();
    set_transient('jasrek_memimp_' . get_current_user_id(), $t, HOUR_IN_SECONDS);
    jasrek_redirect('jasenrekisteri', array('view' => 'tuonti', 'vaihe' => 2));
}

add_action('admin_post_jasrek_members_import', 'jasrek_handle_members_import');
function jasrek_handle_members_import() {
    jasrek_check('jasrek_members_import');
    global $wpdb;
    $key = 'jasrek_memimp_' . get_current_user_id();
    $t = get_transient($key);
    if (!$t) jasrek_redirect('jasenrekisteri', array('view' => 'tuonti', 'jasrek_msg' => 'csv_empty'));
    if (!isset($t['gen']) || $t['gen'] !== jasrek_data_gen()) {
        delete_transient($key);
        jasrek_redirect('jasenrekisteri', array('view' => 'tuonti', 'jasrek_msg' => 'data_restored'));
    }

    $fields  = jasrek_import_fields();
    $map     = array();
    foreach ((array) jasrek_post('kentta', array()) as $i => $f) {
        if ($f !== '' && isset($fields[$f])) $map[(int) $i] = $f;
    }
    $tuplat  = jasrek_post('tuplat') === 'paivita' ? 'paivita' : 'ohita';
    $oletus  = array_key_exists(jasrek_post('oletustila'), jasrek_tilat()) ? jasrek_post('oletustila') : 'aktiivinen';
    $isot    = jasrek_settings()['tunnus_isot'] === '1';
    $table   = jasrek_t('jasenet');
    $c = array('lisatty' => 0, 'paivitetty' => 0, 'ohitettu' => 0, 'virheelliset' => 0, 'laji' => 0);

    foreach ($t['rivit'] as $row) {
        $v = array();
        foreach ($map as $i => $f) $v[$f] = isset($row[$i]) ? trim($row[$i]) : '';
        $g = function ($k) use ($v) { return isset($v[$k]) ? $v[$k] : ''; };

        $etu = $g('etunimi'); $suku = $g('sukunimi');
        foreach (array('nimi_es', 'nimi_se') as $nk) {
            if (($etu === '' || $suku === '') && $g($nk) !== '') {
                $parts = preg_split('/\s+/', $g($nk));
                if (count($parts) >= 2) {
                    if ($nk === 'nimi_es') { $suku = array_pop($parts); $etu = implode(' ', $parts); }
                    else { $suku = array_shift($parts); $etu = implode(' ', $parts); }
                }
            }
        }
        $etu = sanitize_text_field($etu); $suku = sanitize_text_field($suku);
        if ($etu === '' || $suku === '') { $c['virheelliset']++; continue; }

        $osoite = trim($g('osoite') . ($g('postinumero') . $g('postitoimipaikka') !== '' ? ', ' . trim($g('postinumero') . ' ' . $g('postitoimipaikka')) : ''), ', ');
        $liitt = $g('liittynyt');
        $liitt = preg_match('/^\d{4}$/', $liitt) ? $liitt . '-01-01' : jasrek_parse_date_any($liitt);
        $laji = '';
        if ($g('jasenlaji') !== '') {
            $laji = jasrek_laji_by_name($g('jasenlaji'));
            if ($laji === null) { $laji = ''; $c['laji']++; }
        } elseif (isset($v['tila']) && $v['tila'] !== '') {
            // Jäsenyys-sarakkeessa voi olla luokan nimi, esim. "Kunniajäsen"
            $byname = jasrek_laji_by_name($v['tila']);
            if ($byname) $laji = $byname;
        }
        $tunnus = sanitize_text_field($g('tunnus'));
        if ($isot) $tunnus = jasrek_upper($tunnus);
        $tila = jasrek_import_tila(isset($v['tila']) ? $v['tila'] : '', $oletus);
        $ero  = $tila === 'eronnut' ? jasrek_parse_date_any($g('eronnut_pvm')) : null;
        $lisa = array();
        foreach (jasrek_lisakentat() as $kid => $k) {
            $val = jasrek_lisa_parse($k, $g('lisa_' . $kid));
            if ($val !== '') $lisa[$kid] = $val;
        }

        $data = array(
            'etunimi'    => $etu,
            'sukunimi'   => $suku,
            'tunnus'     => $tunnus,
            'osoite'     => sanitize_text_field($osoite),
            'puhelin'    => sanitize_text_field(jasrek_normalize_phone($g('puhelin'))),
            'email'      => sanitize_email($g('email')),
            'liittynyt'  => $liitt,
            'syntynyt'   => jasrek_syntynyt_parse($g('syntynyt')) !== '' ? jasrek_syntynyt_parse($g('syntynyt')) : null,
            'tila'       => $tila,
            'jasenlaji'  => $laji,
            'lisatiedot' => sanitize_textarea_field($g('lisatiedot')),
            'eronnut_pvm'=> $ero,
            'paperilasku'=> jasrek_lisa_parse(array('tyyppi' => 'valinta'), $g('paperilasku')) === '1' ? 1 : 0,
            'lisakentat' => $lisa ? wp_json_encode($lisa) : null,
        );

        // Onko jäsen jo rekisterissä? 1) nimi + sähköposti, 2) nimi (yksiselitteinen),
        // 3) sähköposti + sama etunimi (yksiselitteinen, esim. sukunimi vaihtunut). Pelkkä
        //    sähköposti ei riitä: perheenjäsenillä voi olla yhteinen osoite.
        // Kohdissa 2 ja 3 eri tunnus (esim. kutsumerkki tai jäsennumero) tarkoittaa eri henkilöä.
        $sama_tunnus = function ($ids) use ($wpdb, $tunnus) {
            if ($tunnus === '' || !$ids) return $ids;
            $out = array();
            foreach ($ids as $id) {
                $old_t = (string) $wpdb->get_var($wpdb->prepare("SELECT tunnus FROM {$wpdb->jasrek_jasenet} WHERE id = %d", $id));
                if ($old_t === '' || jasrek_lower($old_t) === jasrek_lower($tunnus)) $out[] = $id;
            }
            return $out;
        };
        $existing = 0;
        if ($data['email'] !== '') {
            $existing = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->jasrek_jasenet} WHERE etunimi = %s AND sukunimi = %s AND email = %s ORDER BY id LIMIT 1", $etu, $suku, $data['email']));
        }
        if (!$existing) {
            $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->jasrek_jasenet} WHERE etunimi = %s AND sukunimi = %s", $etu, $suku));
            $ids = $sama_tunnus($ids);
            if (count($ids) === 1) $existing = (int) $ids[0];
        }
        if (!$existing && $data['email'] !== '') {
            $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->jasrek_jasenet} WHERE email = %s AND etunimi = %s", $data['email'], $etu));
            $ids = $sama_tunnus($ids);
            if (count($ids) === 1) $existing = (int) $ids[0];
        }

        if ($existing) {
            if ($tuplat === 'ohita') { $c['ohitettu']++; continue; }
            // Päivitetään vain tiedostossa annetut (ei-tyhjät) kentät
            $upd = array();
            $old = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->jasrek_jasenet} WHERE id = %d", $existing));
            foreach ($data as $k => $val) {
                if ($k === 'lisakentat' || $k === 'paperilasku' || $k === 'eronnut_pvm') continue;
                $src = array('osoite' => array('osoite', 'postinumero', 'postitoimipaikka'), 'etunimi' => array('etunimi', 'nimi_es', 'nimi_se'), 'sukunimi' => array('sukunimi', 'nimi_es', 'nimi_se'));
                $keys = isset($src[$k]) ? $src[$k] : array($k);
                $given = false;
                foreach ($keys as $kk) if ($g($kk) !== '') $given = true;
                if ($given && $val !== null && $val !== '') $upd[$k] = $val;
            }
            if (isset($upd['tila'])) {
                if ($upd['tila'] === 'eronnut') $upd['eronnut_pvm'] = $ero ? $ero : ($old->eronnut_pvm ? $old->eronnut_pvm : current_time('Y-m-d'));
                else $upd['eronnut_pvm'] = null;
            }
            if ($g('paperilasku') !== '') $upd['paperilasku'] = $data['paperilasku'];
            if ($lisa) $upd['lisakentat'] = wp_json_encode(array_merge(jasrek_member_lisa($old), $lisa));
            if ($upd) {
                $wpdb->update($table, $upd, array('id' => $existing));
                $diff = jasrek_log_diff(jasrek_member_snapshot($old), jasrek_member_snapshot(array_merge((array) $old, $upd)));
                if ($diff) jasrek_log('jasen', $existing, 'tuonti_paivitys', jasrek_log_member_name($old) . ' (' . $t['tiedosto'] . ')', $diff);
            }
            $c['paivitetty']++;
        } else {
            $wpdb->insert($table, $data);
            jasrek_log('jasen', (int) $wpdb->insert_id, 'tuotu', jasrek_log_member_name($data) . ' (' . $t['tiedosto'] . ')', jasrek_log_diff(array(), jasrek_member_snapshot($data)));
            $c['lisatty']++;
        }
    }
    delete_transient($key);
    jasrek_log('vienti', 0, 'tuonti', sprintf('Jäseniä tuotu tiedostosta %s: %d lisätty, %d päivitetty, %d ohitettu', $t['tiedosto'], $c['lisatty'], $c['paivitetty'], $c['ohitettu'] + $c['virheelliset']));
    jasrek_redirect('jasenrekisteri', array('jasrek_msg' => 'members_imported', 'jasrek_n' => $c['lisatty'], 'jasrek_u' => $c['paivitetty'],
        'jasrek_s' => $c['ohitettu'], 'jasrek_e' => $c['virheelliset'], 'jasrek_l' => $c['laji']));
}

/* ------------------------------------------------------------------
 * Näkymä
 * ------------------------------------------------------------------ */

function jasrek_members_import_page() {
    $t = get_transient('jasrek_memimp_' . get_current_user_id());
    $post = admin_url('admin-post.php');
    ?>
    <h1>Tuo jäseniä CSV-tiedostosta</h1>
    <p><a href="<?php echo esc_url(admin_url('admin.php?page=jasenrekisteri')); ?>">&larr; Takaisin jäsenlistaan</a></p>
    <?php jasrek_notice(); ?>

    <?php if (empty(jasrek_get('vaihe')) || !$t):
        if ($t) delete_transient('jasrek_memimp_' . get_current_user_id()); // peruttu tai uusi tuonti ?>
    <div class="jasrek-panel">
        <h2>1. Valitse tiedosto</h2>
        <form method="post" action="<?php echo esc_url($post); ?>" enctype="multipart/form-data" class="jasrek-upload">
            <input type="hidden" name="action" value="jasrek_members_upload">
            <?php wp_nonce_field('jasrek_members_upload'); ?>
            <input type="file" name="tiedosto" accept=".csv,.txt" required>
            <button type="submit" class="button button-primary">Lue tiedosto</button>
        </form>
        <p class="description">Ensimmäisellä rivillä pitää olla sarakkeiden otsikot. Excelissä: <em>Tiedosto → Tallenna nimellä → CSV UTF-8</em>. Sarakkeet tunnistetaan otsikoista, ja voit tarkistaa ne seuraavassa vaiheessa. Jäsenrekisterin oma CSV-vienti kelpaa sellaisenaan.</p>
        <p><a class="button" href="<?php echo esc_url(wp_nonce_url($post . '?action=jasrek_members_template', 'jasrek_members_template')); ?>">Lataa malli-CSV</a></p>
    </div>
    <?php else:
        $fields = jasrek_import_fields();
        $n = count($t['rivit']); ?>
    <form method="post" action="<?php echo esc_url($post); ?>" class="jasrek-form">
        <input type="hidden" name="action" value="jasrek_members_import">
        <?php wp_nonce_field('jasrek_members_import'); ?>
        <div class="jasrek-panel">
            <h2>2. Tarkista sarakkeet</h2>
            <p><?php echo esc_html($t['tiedosto'] . ' · ' . $n . ' riviä'); ?>. Valitse jokaiselle sarakkeelle, mihin kenttään se tuodaan. Etunimi ja sukunimi (tai koko nimi) ovat pakollisia.</p>
            <table class="widefat striped jasrek-table jasrek-import-table">
                <thead><tr><th>Sarake tiedostossa</th><th>Esimerkkejä</th><th>Tuodaan kenttään</th></tr></thead>
                <tbody>
                <?php foreach ($t['otsikot'] as $i => $h):
                    $guess = jasrek_guess_field($h);
                    $ex = array();
                    foreach (array_slice($t['rivit'], 0, 3) as $r) if (isset($r[$i]) && $r[$i] !== '') $ex[] = $r[$i]; ?>
                    <tr>
                        <td><strong><?php echo esc_html($h); ?></strong></td>
                        <td class="jasrek-muted"><?php echo esc_html(implode(' · ', $ex)); ?></td>
                        <td><select name="kentta[<?php echo (int) $i; ?>]">
                            <?php foreach ($fields as $k => $label): ?>
                                <option value="<?php echo esc_attr($k); ?>" <?php selected($guess, $k); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="jasrek-panel">
            <h2>3. Asetukset</h2>
            <fieldset class="jasrek-fieldset">
                <legend>Jos jäsen on jo rekisterissä (sama nimi, tai sama sähköposti ja etunimi<?php if (jasrek_tunnus_on()) echo esc_html(' – eri ' . jasrek_lower(jasrek_tunnus_nimi()) . ' tarkoittaa eri henkilöä'); ?>)</legend>
                <label class="jasrek-radio"><input type="radio" name="tuplat" value="ohita" checked> Ohita – älä muuta olemassa olevaa jäsentä</label>
                <label class="jasrek-radio"><input type="radio" name="tuplat" value="paivita"> Päivitä tiedot tiedostosta (tyhjät kentät eivät korvaa nykyisiä)</label>
            </fieldset>
            <p><label for="jasrek-oletustila">Jäsenyys, jos tiedostossa ei ole tietoa</label>
               <select id="jasrek-oletustila" name="oletustila">
                   <?php foreach (jasrek_tilat() as $k => $label): ?><option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?>
               </select></p>
            <p class="jasrek-actions">
                <button type="submit" class="button button-primary button-large" data-confirm="<?php echo esc_attr('Tuodaanko ' . $n . ' riviä jäsenrekisteriin?'); ?>">Tuo jäsenet</button>
                <a class="button button-large" href="<?php echo esc_url(admin_url('admin.php?page=jasenrekisteri&view=tuonti')); ?>">Valitse toinen tiedosto</a>
            </p>
        </div>
    </form>
    <?php endif;
}
