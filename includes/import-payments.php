<?php
if (!defined('ABSPATH')) exit;

/*
 * Maksujen tuonti pankin tiedostosta ja kohdistus laskuihin viitteen perusteella.
 * Tuetut muodot:
 *  - KTL-viitesiirtoaineisto (.txt, kiinteä tietuepituus 90 merkkiä)
 *  - ISO 20022 camt.054 (viiteaineisto) ja camt.053 (tiliote), XML
 *  - Pankkien CSV-tiliotteet (sarakkeet tunnistetaan otsikoista)
 */

/* ------------------------------------------------------------------
 * Jäsentimet
 * ------------------------------------------------------------------ */

function jr_to_utf8($s) {
    $s = preg_replace('/^\xEF\xBB\xBF/', '', $s);
    if (function_exists('mb_check_encoding') && !mb_check_encoding($s, 'UTF-8')) {
        $s = function_exists('mb_convert_encoding') ? mb_convert_encoding($s, 'UTF-8', 'Windows-1252') : utf8_encode($s);
    }
    return $s;
}

function jr_parse_date_any($s) {
    $s = trim($s);
    if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/', $s, $m)) $d = array($m[1], $m[2], $m[3]);
    elseif (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})/', $s, $m)) $d = array($m[3], $m[2], $m[1]);
    elseif (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2})$/', $s, $m)) $d = array('20' . $m[3], $m[2], $m[1]);
    elseif (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $s, $m)) $d = array($m[1], $m[2], $m[3]);
    elseif (preg_match('/^(\d{2})(\d{2})(\d{2})$/', $s, $m)) $d = array('20' . $m[1], $m[2], $m[3]);
    else return null;
    if (!checkdate((int) $d[1], (int) $d[2], (int) $d[0])) return null;
    return sprintf('%04d-%02d-%02d', $d[0], $d[1], $d[2]);
}

/** Palauttaa array('muoto' => ..., 'maksut' => array(...)). Jokainen maksu: pvm, summa, viite, maksaja, arkisto. */
function jr_parse_payment_file($content) {
    $content = jr_to_utf8((string) $content);
    $trim = ltrim($content);

    if ($trim !== '' && $trim[0] === '<') {
        return array('muoto' => 'ISO 20022 XML (camt)', 'maksut' => jr_parse_camt($trim));
    }

    $lines = preg_split('/\r\n|\r|\n/', $content);
    $ktl = 0;
    foreach ($lines as $l) {
        if (strlen($l) >= 87 && ($l[0] === '3' || $l[0] === '5') && ctype_digit(substr($l, 77, 10))) $ktl++;
    }
    if ($ktl > 0) {
        return array('muoto' => 'KTL-viitesiirtoaineisto', 'maksut' => jr_parse_ktl($lines));
    }
    return array('muoto' => 'CSV-tiliote', 'maksut' => jr_parse_bank_csv($lines));
}

function jr_parse_ktl($lines) {
    $out = array();
    foreach ($lines as $l) {
        if (strlen($l) < 87 || ($l[0] !== '3' && $l[0] !== '5')) continue;
        $oikaisu = $l[0] === '5' || substr($l, 87, 1) === '1';
        $kirjaus = jr_parse_date_any(substr($l, 15, 6));
        $out[] = array(
            'pvm'     => $kirjaus ? $kirjaus : jr_parse_date_any(substr($l, 21, 6)),
            'arkisto' => trim(substr($l, 27, 16)),
            'viite'   => trim(substr($l, 43, 20)),
            'maksaja' => trim(substr($l, 63, 12)),
            'summa'   => ((int) substr($l, 77, 10)) / 100 * ($oikaisu ? -1 : 1),
        );
    }
    return $out;
}

function jr_parse_camt($xml) {
    // Nimiavaruudet pois, jotta polut toimivat kaikissa camt-versioissa.
    $xml = preg_replace('/\sxmlns(:\w+)?="[^"]*"/', '', $xml);
    $xml = preg_replace('/<(\/?)\w+:/', '<$1', $xml);
    $prev = libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
    libxml_use_internal_errors($prev);
    if (!$doc) return array();

    $out = array();
    foreach ($doc->xpath('//Ntry') as $ntry) {
        if ((string) $ntry->CdtDbtInd !== 'CRDT') continue; // vain saapuvat maksut
        $pvm = jr_parse_date_any((string) ($ntry->BookgDt->Dt ?: $ntry->BookgDt->DtTm ?: $ntry->ValDt->Dt));
        $txs = $ntry->xpath('NtryDtls/TxDtls');
        if (!$txs) $txs = array($ntry);
        foreach ($txs as $tx) {
            $amt = (string) ($tx->AmtDtls->TxAmt->Amt ?: $tx->Amt ?: $ntry->Amt);
            $ref = (string) ($tx->RmtInf->Strd->CdtrRefInf->Ref ?: '');
            if ($ref === '' && isset($tx->RmtInf->Ustrd)) {
                // Joskus viite on vapaassa viestikentässä
                if (preg_match('/\b(RF\d{2}[\dA-Z]{1,21}|\d{4,20})\b/', preg_replace('/\s+/', '', (string) $tx->RmtInf->Ustrd), $m)) $ref = $m[1];
            }
            $nimi = (string) ($tx->RltdPties->Dbtr->Nm ?: $tx->RltdPties->Dbtr->Pty->Nm ?: '');
            $out[] = array(
                'pvm'     => $pvm,
                'arkisto' => (string) ($tx->Refs->AcctSvcrRef ?: $ntry->AcctSvcrRef ?: ''),
                'viite'   => $ref,
                'maksaja' => $nimi,
                'summa'   => (float) $amt,
            );
        }
    }
    return $out;
}

function jr_parse_bank_csv($lines) {
    $lines = array_values(array_filter($lines, function ($l) { return trim($l) !== ''; }));
    if (count($lines) < 2) return array();

    // Erotin: yleisin otsikkorivillä
    $head = $lines[0];
    $delim = ';';
    $best = 0;
    foreach (array(';', ',', "\t") as $d) {
        $c = substr_count($head, $d);
        if ($c > $best) { $best = $c; $delim = $d; }
    }
    $cols = array_map(function ($h) {
        return strtolower(remove_accents(trim($h, " \"'")));
    }, str_getcsv($head, $delim));

    $find = function ($patterns) use ($cols) {
        foreach ($patterns as $p) {
            foreach ($cols as $i => $c) if (preg_match($p, $c)) return $i;
        }
        return null;
    };
    $i_ref  = $find(array('/^viite/', '/viitenumero/', '/reference/', '/^ref/'));
    $i_amt  = $find(array('/^maara/', '/maara/', '/summa/', '/amount/', '/euro/'));
    $i_date = $find(array('/kirjauspaiva/', '/maksupaiva/', '/arvopaiva/', '/paivamaara/', '/^pvm/', '/date/'));
    $i_name = $find(array('/maksaja/', '/saaja\/maksaja/', '/^nimi/', '/payer/', '/name/'));
    $i_arch = $find(array('/arkist/', '/archive/'));
    if ($i_ref === null || $i_amt === null) return array();

    $out = array();
    for ($k = 1; $k < count($lines); $k++) {
        $row = str_getcsv($lines[$k], $delim);
        if (!isset($row[$i_amt])) continue;
        $amt = str_replace(array(' ', "\xC2\xA0", '+'), '', $row[$i_amt]);
        if (strpos($amt, ',') !== false) $amt = str_replace(array('.', ','), array('', '.'), $amt);
        if (!is_numeric($amt)) continue;
        $out[] = array(
            'pvm'     => $i_date !== null && isset($row[$i_date]) ? jr_parse_date_any($row[$i_date]) : null,
            'arkisto' => $i_arch !== null && isset($row[$i_arch]) ? trim($row[$i_arch]) : '',
            'viite'   => isset($row[$i_ref]) ? trim($row[$i_ref]) : '',
            'maksaja' => $i_name !== null && isset($row[$i_name]) ? trim($row[$i_name]) : '',
            'summa'   => (float) $amt,
        );
    }
    return $out;
}

/* ------------------------------------------------------------------
 * Kohdistus
 * ------------------------------------------------------------------ */

/**
 * Lisää jokaiseen maksuun: tila (ok|ero|maksettu|kirjattu|tuntematon|ohita), rivi (laskurivi), viesti.
 */
function jr_match_payments($maksut) {
    global $wpdb;
    $refs = array(); $archs = array();
    foreach ($maksut as $p) {
        $n = jr_normalize_viite($p['viite']);
        if ($n !== '') $refs[$n] = true;
        if ($p['arkisto'] !== '') $archs[$p['arkisto']] = true;
    }
    $rows = array();
    if ($refs) {
        $ph = implode(',', array_fill(0, count($refs), '%s'));
        $sql = 'SELECT r.*, m.etunimi, m.sukunimi, m.tunnus, l.nimi AS lasku_nimi FROM ' . jr_t('rivit') . ' r '
             . 'LEFT JOIN ' . jr_t('jasenet') . ' m ON m.id = r.jasen_id JOIN ' . jr_t('laskut') . ' l ON l.id = r.lasku_id '
             . "WHERE r.viite IN ($ph)";
        foreach ($wpdb->get_results($wpdb->prepare($sql, array_keys($refs))) as $r) $rows[$r->viite] = $r;
    }
    $used_arch = array();
    if ($archs) {
        $ph = implode(',', array_fill(0, count($archs), '%s'));
        $used_arch = array_flip($wpdb->get_col($wpdb->prepare('SELECT arkistotunnus FROM ' . jr_t('rivit') . " WHERE arkistotunnus IN ($ph)", array_keys($archs))));
    }

    $seen = array();
    foreach ($maksut as &$p) {
        $n = jr_normalize_viite($p['viite']);
        $p['rivi'] = isset($rows[$n]) ? $rows[$n] : null;
        if ($p['summa'] <= 0) {
            $p['tila'] = 'ohita'; $p['viesti'] = 'Oikaisu tai veloitus – ei käsitellä';
        } elseif ($p['arkisto'] !== '' && isset($used_arch[$p['arkisto']])) {
            $p['tila'] = 'kirjattu'; $p['viesti'] = 'Tämä maksu on jo tuotu aiemmin';
        } elseif (!$p['rivi']) {
            $p['tila'] = 'tuntematon';
            $p['viesti'] = $n === '' ? 'Ei viitettä' : (jr_viite_tarkiste(substr($n, 0, -1)) !== (int) substr($n, -1) ? 'Viitteen tarkiste ei täsmää' : 'Viitettä ei löydy laskuilta');
        } elseif ($p['rivi']->maksettu || isset($seen[$n])) {
            $p['tila'] = 'maksettu';
            $p['viesti'] = $p['rivi']->maksettu ? 'Merkitty jo maksetuksi ' . jr_date($p['rivi']->maksettu) : 'Sama viite toistamiseen tiedostossa';
        } elseif (abs((float) $p['rivi']->summa - $p['summa']) > 0.004) {
            $p['tila'] = 'ero';
            $p['viesti'] = 'Summa poikkeaa: laskulla ' . jr_money($p['rivi']->summa);
        } else {
            $p['tila'] = 'ok'; $p['viesti'] = 'Täsmää';
        }
        if ($n !== '' && $p['rivi']) $seen[$n] = true;
    }
    unset($p);
    return $maksut;
}

/* ------------------------------------------------------------------
 * Käsittelijät
 * ------------------------------------------------------------------ */

add_action('admin_post_jr_payments_upload', 'jr_handle_payments_upload');
function jr_handle_payments_upload() {
    jr_check('jr_payments_upload');
    $f = isset($_FILES['tiedosto']) ? $_FILES['tiedosto'] : null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name']) || $f['size'] > 5 * MB_IN_BYTES) {
        jr_redirect('jr-laskutus', array('jr_msg' => 'upload_failed'));
    }
    $parsed = jr_parse_payment_file(file_get_contents($f['tmp_name']));
    if (!$parsed['maksut']) {
        jr_redirect('jr-laskutus', array('jr_msg' => 'no_payments'));
    }
    $parsed['tiedosto'] = sanitize_file_name($f['name']);
    set_transient('jr_payimp_' . get_current_user_id(), $parsed, HOUR_IN_SECONDS);
    jr_redirect('jr-laskutus', array('view' => 'maksutuonti'));
}

add_action('admin_post_jr_payments_confirm', 'jr_handle_payments_confirm');
function jr_handle_payments_confirm() {
    jr_check('jr_payments_confirm');
    global $wpdb;
    $key = 'jr_payimp_' . get_current_user_id();
    $parsed = get_transient($key);
    if (!$parsed) jr_redirect('jr-laskutus', array('jr_msg' => 'no_payments'));

    $valitut = array_flip(array_map('intval', (array) (isset($_POST['valitut']) ? $_POST['valitut'] : array())));
    $maksut = jr_match_payments($parsed['maksut']); // tarkistetaan uudelleen
    $n = 0;
    foreach ($maksut as $i => $p) {
        if (!isset($valitut[$i]) || !$p['rivi'] || !in_array($p['tila'], array('ok', 'ero'), true)) continue;
        $ok = $wpdb->query($wpdb->prepare(
            'UPDATE ' . jr_t('rivit') . ' SET maksettu = %s, maksettu_summa = %f, maksaja = %s, arkistotunnus = %s WHERE id = %d AND maksettu IS NULL',
            $p['pvm'] ? $p['pvm'] : current_time('Y-m-d'), $p['summa'],
            function_exists('mb_substr') ? mb_substr($p['maksaja'], 0, 100) : substr($p['maksaja'], 0, 100),
            substr($p['arkisto'], 0, 40), $p['rivi']->id
        ));
        if ($ok) $n++;
    }
    delete_transient($key);
    jr_redirect('jr-laskutus', array('jr_msg' => 'payments_imported', 'jr_n' => $n));
}

/* ------------------------------------------------------------------
 * Näkymät
 * ------------------------------------------------------------------ */

function jr_payments_upload_panel() {
    ?>
    <div class="jr-panel" id="jr-maksutuonti">
        <h2>Tuo maksut pankista</h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="jr-upload">
            <input type="hidden" name="action" value="jr_payments_upload">
            <?php wp_nonce_field('jr_payments_upload'); ?>
            <input type="file" name="tiedosto" accept=".txt,.xml,.csv,.dat,.nda" required>
            <button type="submit" class="button button-primary">Lue tiedosto</button>
        </form>
        <p class="description">Lataa verkkopankista <strong>viitesiirtoaineisto</strong> (KTL, .txt), <strong>camt.054</strong>- tai <strong>camt.053</strong>-tiedosto (XML) tai <strong>tiliote CSV-muodossa</strong>. Maksut kohdistetaan laskuihin viitteen perusteella – maksajan nimellä ei ole väliä. Näet ennen tallennusta, mitä merkitään maksetuksi. Samaa maksua ei kirjata kahdesti.</p>
    </div>
    <?php
}

function jr_payments_preview() {
    $parsed = get_transient('jr_payimp_' . get_current_user_id());
    echo '<h1>Maksujen tuonti</h1>';
    echo '<p><a href="' . esc_url(admin_url('admin.php?page=jr-laskutus')) . '">&larr; Takaisin laskuihin</a></p>';
    if (!$parsed) {
        echo '<p>Tuontia ei löytynyt tai se on vanhentunut. Lataa tiedosto uudelleen.</p>';
        return;
    }
    $maksut = jr_match_payments($parsed['maksut']);
    $c = array('ok' => 0, 'ero' => 0, 'maksettu' => 0, 'kirjattu' => 0, 'tuntematon' => 0, 'ohita' => 0);
    $sum_ok = 0;
    foreach ($maksut as $p) { $c[$p['tila']]++; if ($p['tila'] === 'ok') $sum_ok += $p['summa']; }
    $badge = array('ok' => 'ok', 'ero' => 'orange', 'maksettu' => 'muted', 'kirjattu' => 'muted', 'tuntematon' => 'warn', 'ohita' => 'muted');
    ?>
    <p><?php echo esc_html($parsed['tiedosto'] . ' · ' . $parsed['muoto'] . ' · ' . count($maksut) . ' tapahtumaa'); ?></p>
    <div class="jr-cards">
        <div class="jr-card jr-card-ok"><span class="jr-card-num"><?php echo $c['ok']; ?></span><span class="jr-card-label">Täsmää (<?php echo esc_html(jr_money($sum_ok)); ?>)</span></div>
        <div class="jr-card jr-card-warn"><span class="jr-card-num"><?php echo $c['ero']; ?></span><span class="jr-card-label">Summa poikkeaa – tarkista</span></div>
        <div class="jr-card"><span class="jr-card-num"><?php echo $c['maksettu'] + $c['kirjattu']; ?></span><span class="jr-card-label">Jo kirjattu</span></div>
        <div class="jr-card"><span class="jr-card-num"><?php echo $c['tuntematon'] + $c['ohita']; ?></span><span class="jr-card-label">Ei kohdistu jäsenmaksuihin</span></div>
    </div>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="jr_payments_confirm">
        <?php wp_nonce_field('jr_payments_confirm'); ?>
        <table class="widefat striped jr-table jr-import-table" id="jr-payrows">
            <thead><tr>
                <td class="check-column"><input type="checkbox" class="jr-check-all" data-target="#jr-payrows"></td>
                <th>Päivä</th><th>Maksaja</th><th>Viite</th><th>Summa</th><th>Jäsen ja lasku</th><th>Tila</th>
            </tr></thead>
            <tbody>
            <?php foreach ($maksut as $i => $p):
                $can = in_array($p['tila'], array('ok', 'ero'), true);
                $person = $p['rivi'] ? jr_rivi_person($p['rivi']) : null; ?>
                <tr class="<?php echo $p['tila'] === 'ok' ? 'jr-row-ok' : ''; ?>" data-search="x">
                    <th class="check-column"><?php if ($can): ?><input type="checkbox" name="valitut[]" value="<?php echo (int) $i; ?>" <?php checked($p['tila'], 'ok'); ?>><?php endif; ?></th>
                    <td><?php echo esc_html(jr_date($p['pvm'])); ?></td>
                    <td><?php echo esc_html($p['maksaja']); ?></td>
                    <td><code class="jr-ref"><?php echo esc_html($p['viite'] !== '' ? (stripos($p['viite'], 'RF') === 0 ? $p['viite'] : jr_viite_format(ltrim($p['viite'], '0'))) : '–'); ?></code></td>
                    <td><?php echo esc_html(jr_money($p['summa'])); ?></td>
                    <td><?php if ($person): ?><strong><?php echo esc_html($person['nimi']); ?></strong>
                            <?php if ($person['tunnus'] && jr_tunnus_on()): ?><span class="jr-call"><?php echo esc_html($person['tunnus']); ?></span><?php endif; ?>
                            <div class="jr-sub"><?php echo esc_html($p['rivi']->lasku_nimi); ?></div>
                        <?php else: ?><span class="jr-muted">–</span><?php endif; ?></td>
                    <td><span class="jr-badge jr-badge-<?php echo esc_attr($badge[$p['tila']]); ?>"><?php echo esc_html($p['viesti']); ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="jr-actions">
            <button type="submit" class="button button-primary button-large">Merkitse valitut maksetuiksi</button>
            <a class="button button-large" href="<?php echo esc_url(admin_url('admin.php?page=jr-laskutus')); ?>">Peruuta</a>
            <span class="description">Täsmäävät on valittu valmiiksi. Jos summa poikkeaa (esim. osamaksu), valitse rivi itse, jos haluat kuitata laskun maksetuksi. Maksupäivä, maksaja ja maksettu summa tallennetaan laskulle.</span>
        </p>
    </form>
    <?php
}
