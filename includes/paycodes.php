<?php
if (!defined('ABSPATH')) exit;

/*
 * Maksukoodit laskuun:
 *  - EPC QR -koodi (Euroopan maksuneuvoston standardi EPC069-12, käytössä myös suomalaisissa pankkisovelluksissa)
 *  - Suomalainen pankkiviivakoodi (Code 128 C, versio 4 = kotimainen viite)
 * Kuvat tehdään PNG:nä puhtaalla PHP:llä (ei vaadi GD-laajennusta).
 */

/** Kansainvälinen RF-viite (ISO 11649) kotimaisesta viitteestä. */
function jasrek_rf_viite($viite) {
    $ref = preg_replace('/\D/', '', (string) $viite);
    if ($ref === '') return '';
    $num = $ref . '2715' . '00'; // R=27, F=15, tarkiste 00
    $mod = 0;
    foreach (str_split($num) as $d) $mod = ($mod * 10 + (int) $d) % 97;
    return 'RF' . sprintf('%02d', 98 - $mod) . $ref;
}

/** RF-viite takaisin kotimaiseksi (tiliotteiden kohdistusta varten). Palauttaa numerot ilman etunollia. */
function jasrek_normalize_viite($s) {
    $s = strtoupper(preg_replace('/\s+/', '', (string) $s));
    if (strpos($s, 'RF') === 0) $s = substr($s, 4);
    $s = preg_replace('/\D/', '', $s);
    return ltrim($s, '0');
}

/** EPC QR -koodin sisältö. */
function jasrek_epc_payload($lasku, $r) {
    $s = jasrek_settings();
    $iban = jasrek_iban_clean($s['iban']);
    if (!jasrek_iban_valid($iban) || (float) $r->summa <= 0 || (float) $r->summa > 999999.99) return '';
    $clean = function ($t, $max) {
        $t = trim(preg_replace('/[\r\n]+/', ' ', (string) $t));
        return function_exists('mb_substr') ? mb_substr($t, 0, $max) : substr($t, 0, $max);
    };
    $bic = strtoupper(preg_replace('/\s+/', '', $s['bic']));
    $lines = array(
        'BCD',
        $bic !== '' ? '001' : '002',   // versio 001 vaatii BIC:n, 002:ssa se on vapaaehtoinen
        '1',                           // UTF-8
        'SCT',
        $bic,
        $clean($s['yhdistys'], 70),
        $iban,
        'EUR' . number_format((float) $r->summa, 2, '.', ''),
        '',                            // tarkoituskoodi
        jasrek_rf_viite($r->viite),        // rakenteinen viite (RF)
        '',                            // vapaa viesti (ei käytetä, kun viite on annettu)
        $clean($lasku->nimi, 70),      // tieto maksajalle
    );
    return implode("\n", $lines);
}

/* ---------------------------- PNG ---------------------------- */

/** Muodostaa mustavalkoisen PNG-kuvan rivilistasta (jokainen rivi = taulukko true/false). */
function jasrek_png_from_rows($rows, $scale_x, $scale_y) {
    $h = count($rows);
    $w = $h ? count($rows[0]) : 0;
    $raw = '';
    foreach ($rows as $row) {
        $line = '';
        foreach ($row as $dark) $line .= str_repeat($dark ? "\x00" : "\xFF", $scale_x);
        $line = "\x00" . $line; // suodatin: ei mitään
        $raw .= str_repeat($line, $scale_y);
    }
    $chunk = function ($type, $data) {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    };
    return "\x89PNG\r\n\x1a\n"
        . $chunk('IHDR', pack('NNCCCCC', $w * $scale_x, $h * $scale_y, 8, 0, 0, 0, 0)) // 8-bit harmaasävy
        . $chunk('IDAT', gzcompress($raw, 9))
        . $chunk('IEND', '');
}

/** QR-koodin moduulit (true = musta) ilman reunusta, tai tyhjä taulukko. */
function jasrek_qr_matrix($payload) {
    if ($payload === '') return array();
    require_once JASREK_DIR . 'includes/lib/jasrek-qrcode.php';
    // Tavutila (UTF-8), virheenkorjaus M kuten EPC-standardi suosittelee
    $mode  = JASREK_QR_MODE_8BIT_BYTE;
    $level = JASREK_QR_ERROR_CORRECT_LEVEL_M;
    try {
        $qr = new JASREK_QRCode();
        $qr->setErrorCorrectLevel($level);
        $qr->addData($payload, $mode);
        $type = 0;
        for ($t = 1; $t <= 40; $t++) {
            if (strlen($payload) <= JASREK_QRUtil::getMaxLength($t, $mode, $level)) { $type = $t; break; }
        }
        if (!$type) return array();
        $qr->setTypeNumber($type);
        $qr->make();
    } catch (Exception $e) {
        return array(); // QR-koodia ei voitu muodostaa – laskussa on silti virtuaaliviivakoodi
    }
    $n = $qr->getModuleCount();
    $m = array();
    for ($y = 0; $y < $n; $y++) {
        $row = array();
        for ($x = 0; $x < $n; $x++) $row[] = (bool) $qr->isDark($y, $x);
        $m[] = $row;
    }
    return $m;
}

/** EPC QR -koodi PNG:nä. */
function jasrek_qr_png($payload, $scale = 5, $margin = 4) {
    $m = jasrek_qr_matrix($payload);
    if (!$m) return '';
    $n = count($m);
    $rows = array();
    for ($y = -$margin; $y < $n + $margin; $y++) {
        $row = array();
        for ($x = -$margin; $x < $n + $margin; $x++) {
            $row[] = ($x >= 0 && $y >= 0 && $x < $n && $y < $n) ? $m[$y][$x] : false;
        }
        $rows[] = $row;
    }
    return jasrek_png_from_rows($rows, $scale, $scale);
}

/** Code 128 -kuviot (palkki/väli-leveydet), arvot 0–106. */
function jasrek_code128_patterns() {
    return explode(' ', '212222 222122 222221 121223 121322 131222 122213 122312 132212 221213 '
        . '221312 231212 112232 122132 122231 113222 123122 123221 223211 221132 '
        . '221231 213212 223112 312131 311222 321122 321221 312212 322112 322211 '
        . '212123 212321 232121 111323 131123 131321 112313 132113 132311 211313 '
        . '231113 231311 112133 112331 132131 113123 113321 133121 313121 211331 '
        . '231131 213113 213311 213131 311123 311321 331121 312113 312311 332111 '
        . '314111 221411 431111 111224 111422 121124 121421 141122 141221 112214 '
        . '112412 122114 122411 142112 142211 241211 221114 413111 241112 134111 '
        . '111242 121142 121241 114212 124112 124211 411212 421112 421211 212141 '
        . '214121 412121 111143 111341 131141 114113 114311 411113 411311 113141 '
        . '114131 311141 411131 211412 211214 211232 2331112');
}

/** Pankkiviivakoodin (Code 128 C) moduulit (true = palkki) hiljaisine alueineen, tai tyhjä taulukko. */
function jasrek_barcode_modules($digits) {
    if ($digits === '' || strlen($digits) % 2 !== 0 || !ctype_digit($digits)) return array();
    $p = jasrek_code128_patterns();
    $values = array(105); // Start C
    foreach (str_split($digits, 2) as $pair) $values[] = (int) $pair;
    $sum = 105;
    for ($i = 1; $i < count($values); $i++) $sum += $i * $values[$i];
    $values[] = $sum % 103; // tarkiste
    $values[] = 106;        // Stop

    $mods = array_fill(0, 10, false); // hiljainen alue
    foreach ($values as $v) {
        $dark = true;
        foreach (str_split($p[$v]) as $w) {
            for ($k = 0; $k < (int) $w; $k++) $mods[] = $dark;
            $dark = !$dark;
        }
    }
    return array_merge($mods, array_fill(0, 10, false));
}

/** Pankkiviivakoodi (Code 128 C) PNG:nä virtuaaliviivakoodin numeroista. */
function jasrek_barcode_png($digits, $module = 2, $height = 60) {
    $mods = jasrek_barcode_modules($digits);
    return $mods ? jasrek_png_from_rows(array($mods), $module, $height) : '';
}

/** Laskun kuvat: ['jrqr' => png, 'jrbar' => png] asetusten mukaan. */
function jasrek_invoice_images($lasku, $r) {
    $s = jasrek_settings();
    $mode = $s['maksukoodi'];
    $out = array();
    if ($mode === 'qr' || $mode === 'molemmat') {
        $png = jasrek_qr_png(jasrek_epc_payload($lasku, $r));
        if ($png !== '') $out['jrqr'] = $png;
    }
    if ($mode === 'viivakoodi' || $mode === 'molemmat') {
        $png = jasrek_barcode_png(jasrek_virtuaaliviivakoodi($s['iban'], $r->summa, $r->viite, jasrek_rivi_era($lasku, $r)));
        if ($png !== '') $out['jrbar'] = $png;
    }
    return $out;
}
