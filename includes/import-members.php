<?php
if (!defined('ABSPATH')) exit;

/*
 * Jäsenten tuonti CSV-tiedostosta (Excel: Tallenna nimellä → CSV UTF-8).
 * Vaihe 1: tiedoston lataus. Vaihe 2: sarakkeiden valinta ja tuplien käsittely. Vaihe 3: tuonti.
 */

function jr_import_fields() {
    return array(
        ''                => '— ei tuoda —',
        'etunimi'         => 'Etunimi',
        'sukunimi'        => 'Sukunimi',
        'nimi_es'         => 'Koko nimi (Etunimi Sukunimi)',
        'nimi_se'         => 'Koko nimi (Sukunimi Etunimi)',
        'tunnus'          => jr_tunnus_nimi(),
        'osoite'          => 'Osoite',
        'postinumero'     => 'Postinumero',
        'postitoimipaikka'=> 'Postitoimipaikka',
        'puhelin'         => 'Puhelin',
        'email'           => 'Sähköposti',
        'liittynyt'       => 'Liittynyt',
        'jasenlaji'       => 'Jäsenluokka',
        'tila'            => 'Jäsenyys (voimassa / eronnut)',
        'lisatiedot'      => 'Lisätiedot',
    );
}

/** Arvaa kentän sarakeotsikosta. */
function jr_guess_field($header) {
    $h = strtolower(remove_accents(trim($header)));
    $h = preg_replace('/[^a-z0-9]+/', '', $h);
    $tn = preg_replace('/[^a-z0-9]+/', '', strtolower(remove_accents(jr_tunnus_nimi())));
    $map = array(
        'etunimi' => 'etunimi', 'etunimet' => 'etunimi', 'firstname' => 'etunimi',
        'sukunimi' => 'sukunimi', 'lastname' => 'sukunimi',
        'nimi' => 'nimi_es', 'kokonimi' => 'nimi_es', 'name' => 'nimi_es',
        'tunnus' => 'tunnus', 'kutsutunnus' => 'tunnus', 'kutsu' => 'tunnus', 'jasennumero' => 'tunnus', 'jasennro' => 'tunnus', 'callsign' => 'tunnus',
        'osoite' => 'osoite', 'katuosoite' => 'osoite', 'lahiosoite' => 'osoite', 'address' => 'osoite',
        'postinumero' => 'postinumero', 'postinro' => 'postinumero', 'postnr' => 'postinumero',
        'postitoimipaikka' => 'postitoimipaikka', 'paikkakunta' => 'postitoimipaikka', 'kaupunki' => 'postitoimipaikka', 'kunta' => 'postitoimipaikka',
        'puhelin' => 'puhelin', 'puh' => 'puhelin', 'puhelinnumero' => 'puhelin', 'matkapuhelin' => 'puhelin', 'gsm' => 'puhelin', 'phone' => 'puhelin',
        'sahkoposti' => 'email', 'email' => 'email', 'sposti' => 'email', 'emailosoite' => 'email', 'sahkopostiosoite' => 'email',
        'liittynyt' => 'liittynyt', 'liittymispaiva' => 'liittynyt', 'liittymisvuosi' => 'liittynyt', 'jasenalkaen' => 'liittynyt',
        'jasenlaji' => 'jasenlaji', 'laji' => 'jasenlaji', 'jasentyyppi' => 'jasenlaji', 'jasenluokka' => 'jasenlaji', 'luokka' => 'jasenlaji',
        'jasenyys' => 'tila', 'tila' => 'tila', 'status' => 'tila',
        'lisatiedot' => 'lisatiedot', 'huom' => 'lisatiedot', 'huomautukset' => 'lisatiedot', 'muistiinpanot' => 'lisatiedot',
    );
    if ($tn !== '' && $h === $tn) return 'tunnus';
    return isset($map[$h]) ? $map[$h] : '';
}

function jr_parse_csv_table($content) {
    $content = jr_to_utf8((string) $content);
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
    $fh = fopen('php://temp', 'r+');
    fwrite($fh, implode("\n", $lines));
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
    fclose($fh);
    if (count($rows) < 2) return null;
    return array('otsikot' => array_shift($rows), 'rivit' => $rows);
}

function jr_import_tila($s, $oletus) {
    $s = strtolower(remove_accents(trim($s)));
    if ($s === '') return $oletus;
    if (strpos($s, 'eronn') !== false || $s === 'ero' || strpos($s, 'erosi') !== false) return 'eronnut';
    return 'aktiivinen';
}

/* ------------------------------------------------------------------
 * Käsittelijät
 * ------------------------------------------------------------------ */

add_action('admin_post_jr_members_template', function () {
    jr_check('jr_members_template');
    $head = array('Etunimi', 'Sukunimi', jr_tunnus_nimi(), 'Osoite', 'Puhelin', 'Sähköposti', 'Liittynyt', 'Jäsenluokka', 'Jäsenyys', 'Lisätiedot');
    $nimet = array_values(array_map(function ($l) { return $l['nimi']; }, jr_jasenlajit()));
    jr_csv_output('jasenrekisteri-malli.csv', $head, array(
        array('Matti', 'Meikäläinen', '', 'Esimerkkitie 1, 00100 Helsinki', '040 123 4567', 'matti@example.com', '1.1.2020', isset($nimet[0]) ? $nimet[0] : 'Jäsen', 'Voimassa', ''),
        array('Maija', 'Mallikas', '', '', '', 'maija@example.com', '2015', isset($nimet[1]) ? $nimet[1] : 'Kunniajäsen', 'Voimassa', ''),
        array('Erkki', 'Esimerkki', '', '', '', '', '2001', '', 'Eronnut', ''),
    ));
});

add_action('admin_post_jr_members_upload', 'jr_handle_members_upload');
function jr_handle_members_upload() {
    jr_check('jr_members_upload');
    $f = isset($_FILES['tiedosto']) ? $_FILES['tiedosto'] : null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name']) || $f['size'] > 5 * MB_IN_BYTES) {
        jr_redirect('jasenrekisteri', array('view' => 'tuonti', 'jr_msg' => 'upload_failed'));
    }
    $t = jr_parse_csv_table(file_get_contents($f['tmp_name']));
    if (!$t) jr_redirect('jasenrekisteri', array('view' => 'tuonti', 'jr_msg' => 'csv_empty'));
    $t['tiedosto'] = sanitize_file_name($f['name']);
    set_transient('jr_memimp_' . get_current_user_id(), $t, HOUR_IN_SECONDS);
    jr_redirect('jasenrekisteri', array('view' => 'tuonti', 'vaihe' => 2));
}

add_action('admin_post_jr_members_import', 'jr_handle_members_import');
function jr_handle_members_import() {
    jr_check('jr_members_import');
    global $wpdb;
    $key = 'jr_memimp_' . get_current_user_id();
    $t = get_transient($key);
    if (!$t) jr_redirect('jasenrekisteri', array('view' => 'tuonti', 'jr_msg' => 'csv_empty'));

    $fields  = jr_import_fields();
    $map     = array();
    foreach ((array) jr_post('kentta', array()) as $i => $f) {
        if ($f !== '' && isset($fields[$f])) $map[(int) $i] = $f;
    }
    $tuplat  = jr_post('tuplat') === 'paivita' ? 'paivita' : 'ohita';
    $oletus  = array_key_exists(jr_post('oletustila'), jr_tilat()) ? jr_post('oletustila') : 'aktiivinen';
    $isot    = jr_settings()['tunnus_isot'] === '1';
    $table   = jr_t('jasenet');
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
        $liitt = preg_match('/^\d{4}$/', $liitt) ? $liitt . '-01-01' : jr_parse_date_any($liitt);
        $laji = '';
        if ($g('jasenlaji') !== '') {
            $laji = jr_laji_by_name($g('jasenlaji'));
            if ($laji === null) { $laji = ''; $c['laji']++; }
        } elseif (isset($v['tila']) && $v['tila'] !== '') {
            // Jäsenyys-sarakkeessa voi olla luokan nimi, esim. "Kunniajäsen"
            $byname = jr_laji_by_name($v['tila']);
            if ($byname) $laji = $byname;
        }
        $tunnus = sanitize_text_field($g('tunnus'));
        if ($isot) $tunnus = function_exists('mb_strtoupper') ? mb_strtoupper($tunnus) : strtoupper($tunnus);

        $data = array(
            'etunimi'    => $etu,
            'sukunimi'   => $suku,
            'tunnus'     => $tunnus,
            'osoite'     => sanitize_text_field($osoite),
            'puhelin'    => sanitize_text_field(jr_normalize_phone($g('puhelin'))),
            'email'      => sanitize_email($g('email')),
            'liittynyt'  => $liitt,
            'tila'       => jr_import_tila(isset($v['tila']) ? $v['tila'] : '', $oletus),
            'jasenlaji'  => $laji,
            'lisatiedot' => sanitize_textarea_field($g('lisatiedot')),
        );

        // Onko jäsen jo rekisterissä? 1) nimi + sähköposti, 2) nimi (yksiselitteinen),
        // 3) sähköposti (yksiselitteinen – esim. pariskunnalla voi olla yhteinen osoite).
        $existing = 0;
        if ($data['email'] !== '') {
            $existing = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE etunimi = %s AND sukunimi = %s AND email = %s ORDER BY id LIMIT 1", $etu, $suku, $data['email']));
        }
        if (!$existing) {
            $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table WHERE etunimi = %s AND sukunimi = %s", $etu, $suku));
            if (count($ids) === 1) $existing = (int) $ids[0];
        }
        if (!$existing && $data['email'] !== '') {
            $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table WHERE email = %s", $data['email']));
            if (count($ids) === 1) $existing = (int) $ids[0];
        }

        if ($existing) {
            if ($tuplat === 'ohita') { $c['ohitettu']++; continue; }
            // Päivitetään vain tiedostossa annetut (ei-tyhjät) kentät
            $upd = array();
            foreach ($data as $k => $val) {
                $src = array('osoite' => array('osoite', 'postinumero', 'postitoimipaikka'), 'etunimi' => array('etunimi', 'nimi_es', 'nimi_se'), 'sukunimi' => array('sukunimi', 'nimi_es', 'nimi_se'));
                $keys = isset($src[$k]) ? $src[$k] : array($k);
                $given = false;
                foreach ($keys as $kk) if ($g($kk) !== '') $given = true;
                if ($given && $val !== null && $val !== '') $upd[$k] = $val;
            }
            if ($upd) $wpdb->update($table, $upd, array('id' => $existing));
            $c['paivitetty']++;
        } else {
            $wpdb->insert($table, $data);
            $c['lisatty']++;
        }
    }
    delete_transient($key);
    jr_redirect('jasenrekisteri', array('jr_msg' => 'members_imported', 'jr_n' => $c['lisatty'], 'jr_u' => $c['paivitetty'],
        'jr_s' => $c['ohitettu'], 'jr_e' => $c['virheelliset'], 'jr_l' => $c['laji']));
}

/* ------------------------------------------------------------------
 * Näkymä
 * ------------------------------------------------------------------ */

function jr_members_import_page() {
    $t = get_transient('jr_memimp_' . get_current_user_id());
    $post = admin_url('admin-post.php');
    ?>
    <h1>Tuo jäseniä CSV-tiedostosta</h1>
    <p><a href="<?php echo esc_url(admin_url('admin.php?page=jasenrekisteri')); ?>">&larr; Takaisin jäsenlistaan</a></p>
    <?php jr_notice(); ?>

    <?php if (empty($_GET['vaihe']) || !$t): ?>
    <div class="jr-panel">
        <h2>1. Valitse tiedosto</h2>
        <form method="post" action="<?php echo esc_url($post); ?>" enctype="multipart/form-data" class="jr-upload">
            <input type="hidden" name="action" value="jr_members_upload">
            <?php wp_nonce_field('jr_members_upload'); ?>
            <input type="file" name="tiedosto" accept=".csv,.txt" required>
            <button type="submit" class="button button-primary">Lue tiedosto</button>
        </form>
        <p class="description">Ensimmäisellä rivillä pitää olla sarakkeiden otsikot. Excelissä: <em>Tiedosto → Tallenna nimellä → CSV UTF-8</em>. Sarakkeet tunnistetaan otsikoista, ja voit tarkistaa ne seuraavassa vaiheessa. Jäsenrekisterin oma CSV-vienti kelpaa sellaisenaan.</p>
        <p><a class="button" href="<?php echo esc_url(wp_nonce_url($post . '?action=jr_members_template', 'jr_members_template')); ?>">Lataa malli-CSV</a></p>
    </div>
    <?php else:
        $fields = jr_import_fields();
        $n = count($t['rivit']); ?>
    <form method="post" action="<?php echo esc_url($post); ?>" class="jr-form">
        <input type="hidden" name="action" value="jr_members_import">
        <?php wp_nonce_field('jr_members_import'); ?>
        <div class="jr-panel">
            <h2>2. Tarkista sarakkeet</h2>
            <p><?php echo esc_html($t['tiedosto'] . ' · ' . $n . ' riviä'); ?>. Valitse jokaiselle sarakkeelle, mihin kenttään se tuodaan. Etunimi ja sukunimi (tai koko nimi) ovat pakollisia.</p>
            <table class="widefat striped jr-table jr-import-table">
                <thead><tr><th>Sarake tiedostossa</th><th>Esimerkkejä</th><th>Tuodaan kenttään</th></tr></thead>
                <tbody>
                <?php foreach ($t['otsikot'] as $i => $h):
                    $guess = jr_guess_field($h);
                    $ex = array();
                    foreach (array_slice($t['rivit'], 0, 3) as $r) if (isset($r[$i]) && $r[$i] !== '') $ex[] = $r[$i]; ?>
                    <tr>
                        <td><strong><?php echo esc_html($h); ?></strong></td>
                        <td class="jr-muted"><?php echo esc_html(implode(' · ', $ex)); ?></td>
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
        <div class="jr-panel">
            <h2>3. Asetukset</h2>
            <fieldset class="jr-fieldset">
                <legend>Jos jäsen on jo rekisterissä (sama sähköposti tai nimi)</legend>
                <label class="jr-radio"><input type="radio" name="tuplat" value="ohita" checked> Ohita – älä muuta olemassa olevaa jäsentä</label>
                <label class="jr-radio"><input type="radio" name="tuplat" value="paivita"> Päivitä tiedot tiedostosta (tyhjät kentät eivät korvaa nykyisiä)</label>
            </fieldset>
            <p><label for="jr-oletustila">Jäsenyys, jos tiedostossa ei ole tietoa</label>
               <select id="jr-oletustila" name="oletustila">
                   <?php foreach (jr_tilat() as $k => $label): ?><option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?>
               </select></p>
            <p class="jr-actions">
                <button type="submit" class="button button-primary button-large" data-confirm="<?php echo esc_attr('Tuodaanko ' . $n . ' riviä jäsenrekisteriin?'); ?>">Tuo jäsenet</button>
                <a class="button button-large" href="<?php echo esc_url(admin_url('admin.php?page=jasenrekisteri&view=tuonti')); ?>">Valitse toinen tiedosto</a>
            </p>
        </div>
    </form>
    <?php endif;
}
