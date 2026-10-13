<?php
if (!defined('ABSPATH')) exit;

/*
 * Paperilaskut: tulostettava A4-lasku ikkunakuoreen (C5 tai DL, ikkuna vasemmalla ylhäällä)
 * ja postitetuksi merkitseminen.
 */

/** Tulostusnäkymän osoite. $args: rivi, joukko (postittamattomat|muistutukset|kaikki), muistutus, tulosta. */
function jr_print_url($lasku_id, $args = array()) {
    return wp_nonce_url(add_query_arg(array_merge(array('action' => 'jr_print', 'id' => (int) $lasku_id), $args), admin_url('admin-post.php')), 'jr_print_' . (int) $lasku_id);
}

/** Laskun paperilaskurivit valitun joukon mukaan. */
function jr_paper_rows($lasku, $joukko) {
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(jr_rivi_select_sql() . 'WHERE r.lasku_id = %d ORDER BY m.sukunimi, m.etunimi, r.nimi', $lasku->id));
    $today = current_time('Y-m-d');
    return array_values(array_filter($rows, function ($r) use ($lasku, $joukko, $today) {
        if (!jr_is_paperi($r)) return false;
        if ($joukko === 'postittamattomat') return !$r->lahetetty;
        if ($joukko === 'muistutukset') return $r->lahetetty && !$r->maksettu && jr_rivi_era($lasku, $r) < $today;
        return true;
    }));
}

add_action('admin_post_jr_print', 'jr_handle_print');
function jr_handle_print() {
    $id = intval(isset($_GET['id']) ? $_GET['id'] : 0);
    jr_check('jr_print_' . $id);
    global $wpdb;
    $lasku = jr_get_lasku($id);
    if (!$lasku) wp_die('Laskua ei löytynyt.');

    $rivi   = isset($_GET['rivi']) ? intval($_GET['rivi']) : 0;
    $joukko = isset($_GET['joukko']) ? sanitize_key($_GET['joukko']) : '';
    $muist  = !empty($_GET['muistutus']) || $joukko === 'muistutukset';
    if ($rivi) {
        $r = $wpdb->get_row($wpdb->prepare(jr_rivi_select_sql() . 'WHERE r.id = %d AND r.lasku_id = %d', $rivi, $id));
        $rows = $r ? array($r) : array();
    } else {
        $rows = jr_paper_rows($lasku, in_array($joukko, array('postittamattomat', 'muistutukset'), true) ? $joukko : 'kaikki');
    }
    nocache_headers();
    header('Content-Type: text/html; charset=UTF-8');
    echo jr_print_document($lasku, $rows, $muist, !empty($_GET['tulosta'])); // phpcs:ignore -- valmiiksi escapattu HTML
    exit;
}

add_action('admin_post_jr_paper_mark', 'jr_handle_paper_mark');
function jr_handle_paper_mark() {
    $lasku_id = intval(jr_post('lasku_id', 0));
    jr_check('jr_paper_mark_' . $lasku_id);
    global $wpdb;
    $lasku = jr_get_lasku($lasku_id);
    if (!$lasku) jr_redirect('jr-laskutus');
    $ids = array_filter(array_map('intval', (array) (isset($_POST['rivit']) ? $_POST['rivit'] : array())));
    if (!$ids) jr_redirect('jr-laskutus', array('view' => 'lasku', 'id' => $lasku_id, 'jr_msg' => 'nothing_selected'));
    $muist = jr_post('tapa') === 'muistutus';
    $now = current_time('mysql');
    $n = 0;
    $rows = $wpdb->get_results($wpdb->prepare(jr_rivi_select_sql() . 'WHERE r.lasku_id = %d AND r.id IN (' . implode(',', $ids) . ')', $lasku_id));
    foreach ($rows as $r) {
        if ($muist) {
            if ($r->maksettu) continue;
            $upd = array('muistutettu' => $now, 'muistutuksia' => (int) $r->muistutuksia + 1, 'viim_yritys' => $now, 'virhe' => null);
            if (!$r->lahetetty) $upd['lahetetty'] = $now;
        } else {
            $upd = array('lahetetty' => $now, 'viim_yritys' => $now, 'virhe' => null);
        }
        $wpdb->update(jr_t('rivit'), $upd, array('id' => $r->id));
        jr_log_rivi($r, $muist ? 'muistutus_postitettu' : 'postitettu', 'paperilla', $lasku);
        $n++;
    }
    jr_redirect('jr-laskutus', array('view' => 'lasku', 'id' => $lasku_id, 'jr_msg' => $muist ? 'paper_reminded' : 'paper_marked', 'jr_n' => $n));
}

/** Postiosoitteen rivit: nimi ja osoite pilkkujen kohdalta riveiksi. */
function jr_address_lines($r) {
    $p = jr_rivi_person($r);
    $lines = array($p['nimi']);
    foreach (preg_split('/\s*[,\n]\s*/', (string) (isset($r->osoite) ? $r->osoite : '')) as $l) {
        if (trim($l) !== '') $lines[] = trim($l);
    }
    return $lines;
}

function jr_print_document($lasku, $rows, $reminder, $autoprint) {
    $s = jr_settings();
    $title = ($reminder ? 'Maksumuistutukset: ' : 'Paperilaskut: ') . $lasku->nimi;
    $ids = array_map(function ($r) { return (int) $r->id; }, $rows);
    $no_addr = array();
    foreach ($rows as $r) if (count(jr_address_lines($r)) < 2) $no_addr[] = jr_rivi_person($r)['nimi'];
    $back = admin_url('admin.php?page=jr-laskutus&view=lasku&id=' . (int) $lasku->id);

    ob_start(); ?>
<!DOCTYPE html>
<html lang="fi"><head><meta charset="UTF-8"><title><?php echo esc_html($title); ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
@page { size: A4; margin: 0; }
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; }
body { background: #e9ecef; font-family: Arial, Helvetica, sans-serif; color: #1f2933; font-size: 10.5pt; line-height: 1.45; }
.jr-tools { position: sticky; top: 0; z-index: 5; background: #1d3557; color: #fff; padding: 10px 20px; display: flex; flex-wrap: wrap; gap: 10px 16px; align-items: center; font-size: 14px; }
.jr-tools a { color: #fff; }
.jr-tools button { font-size: 14px; padding: 6px 14px; border-radius: 4px; border: 0; cursor: pointer; background: #fff; color: #1d3557; font-weight: bold; }
.jr-tools button.jr-sec { background: transparent; color: #fff; border: 1px solid #fff; font-weight: normal; }
.jr-tools form { display: inline; margin: 0; }
.jr-tools .jr-warn { background: #fff4d6; color: #6b4b00; padding: 4px 10px; border-radius: 4px; flex-basis: 100%; }
.jr-tools .jr-info { opacity: .85; font-size: 13px; flex-basis: 100%; }
.sivu { width: 210mm; height: 297mm; margin: 10mm auto; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.2); position: relative; overflow: hidden; display: flex; flex-direction: column; padding: 0 15mm 12mm 20mm; page-break-after: always; break-after: page; }
.sivu:last-child { page-break-after: auto; break-after: auto; }
.taitto { position: absolute; left: 4mm; width: 4mm; border-top: 0.3pt solid #999; }
.ylaosa { position: relative; height: 72mm; flex: none; }
/* Ikkunakuoren ikkuna: vasemmalla 20 mm, ylhäältä 15 mm, koko 90 × 45 mm (C5 ja DL). Osoite sijoitetaan ikkunan keskelle. */
.ikkuna { position: absolute; left: 0; top: 19mm; width: 85mm; height: 37mm; }
.palautus { font-size: 7.5pt; color: #555; border-bottom: 0.4pt solid #999; padding-bottom: 1mm; margin-bottom: 3mm; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.vastaanottaja { font-size: 11pt; line-height: 1.35; }
.vastaanottaja .puuttuu { color: #b42318; font-size: 9pt; }
.lahettaja { position: absolute; left: 0; top: 8mm; font-weight: bold; font-size: 12pt; color: #1d3557; }
.tiedot { position: absolute; right: 0; top: 10mm; width: 80mm; }
.tiedot .laji { font-size: 16pt; font-weight: bold; letter-spacing: .06em; color: #1d3557; margin-bottom: 3mm; }
.tiedot table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
.tiedot td { padding: 1mm 0; vertical-align: top; }
.tiedot td:first-child { color: #555; width: 32mm; }
.tiedot .vahva { font-weight: bold; }
.mono { font-family: Consolas, Menlo, "Courier New", monospace; }
.saate { margin: 0 0 6mm; }
.erittely { width: 100%; border-collapse: collapse; margin: 0 0 4mm; }
.erittely th { text-align: left; font-size: 9pt; color: #555; border-bottom: 0.6pt solid #333; padding: 1.5mm 0; }
.erittely td { padding: 2mm 0; border-bottom: 0.3pt solid #ccc; }
.erittely .summa { text-align: right; white-space: nowrap; }
.yhteensa { display: flex; justify-content: space-between; align-items: center; background: #eaf2fb; border-radius: 2mm; padding: 3mm 5mm; font-size: 12pt; }
.yhteensa strong { font-size: 16pt; color: #1d3557; }
.alaosa { margin-top: auto; border-top: 0.6pt dashed #999; padding-top: 5mm; }
.maksurivi { display: flex; gap: 6mm; align-items: center; }
.maksurivi img.qr { width: 30mm; height: 30mm; image-rendering: pixelated; flex: none; }
.maksutiedot { flex: 1; font-size: 9.5pt; }
.maksutiedot table { border-collapse: collapse; }
.maksutiedot td { padding: .6mm 4mm .6mm 0; }
.maksutiedot td:first-child { color: #555; }
.viivakoodi { margin-top: 5mm; padding: 0 4mm; }
.viivakoodi img { height: 13mm; image-rendering: pixelated; display: block; }
.vvk { font-size: 8.5pt; margin-top: 2mm; word-break: break-all; }
.huom { font-size: 8.5pt; color: #555; margin-top: 3mm; }
.alatunniste { font-size: 8pt; color: #555; margin-top: 4mm; border-top: 0.3pt solid #ccc; padding-top: 2mm; }
.tyhja { max-width: 600px; margin: 40px auto; background: #fff; padding: 24px; border-radius: 8px; }
@media print {
    body { background: #fff; }
    .jr-tools { display: none; }
    .sivu { margin: 0; box-shadow: none; }
}
</style></head>
<body<?php echo $autoprint && $rows ? ' onload="window.print()"' : ''; ?>>
<div class="jr-tools">
    <strong><?php echo esc_html($title); ?></strong>
    <span><?php echo count($rows); ?> kpl</span>
    <?php if ($rows): ?>
    <button type="button" onclick="window.print()">🖨 Tulosta</button>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="jr_paper_mark">
        <input type="hidden" name="lasku_id" value="<?php echo (int) $lasku->id; ?>">
        <input type="hidden" name="tapa" value="<?php echo $reminder ? 'muistutus' : 'lasku'; ?>">
        <?php wp_nonce_field('jr_paper_mark_' . $lasku->id); ?>
        <?php foreach ($ids as $rid): ?><input type="hidden" name="rivit[]" value="<?php echo (int) $rid; ?>"><?php endforeach; ?>
        <button type="submit" class="jr-sec" onclick="return confirm('Merkitäänkö <?php echo count($rows); ?> <?php echo $reminder ? 'muistutusta' : 'laskua'; ?> postitetuksi tänään?')">✓ Merkitse <?php echo count($rows) === 1 ? 'postitetuksi' : 'kaikki postitetuiksi'; ?></button>
    </form>
    <?php endif; ?>
    <a href="<?php echo esc_url($back); ?>">← Takaisin laskuun</a>
    <?php if ($rows): ?><span class="jr-info">Tulosta A4-paperille ilman sivun reunuksia (selaimen tulostusasetus ”Marginaalit: ei mitään” tai ”oletus”, skaalaus 100 %). Taita paperi reunan merkkien kohdalta ja laita ikkunakuoreen (C5 tai DL, ikkuna vasemmalla).</span><?php endif; ?>
    <?php if ($no_addr): ?><span class="jr-warn">⚠ Osoite puuttuu: <?php echo esc_html(implode(', ', $no_addr)); ?>. Lisää osoite jäsenen tietoihin ennen tulostusta.</span><?php endif; ?>
</div>
<?php if (!$rows): ?>
    <div class="tyhja"><p>Ei tulostettavia laskuja tässä joukossa.</p><p><a href="<?php echo esc_url($back); ?>">← Takaisin laskuun</a></p></div>
<?php endif; ?>
<?php foreach ($rows as $r) echo jr_print_page($lasku, $r, $reminder, $s); // phpcs:ignore ?>
</body></html>
    <?php
    return ob_get_clean();
}

/** Yksi A4-sivu. */
function jr_print_page($lasku, $r, $reminder, $s) {
    $vars = jr_invoice_vars($lasku, $r);
    $text = $reminder ? $s['muistutus_teksti'] : ($lasku->viesti !== '' ? $lasku->viesti : $s['viesti_oletus']);
    $images = jr_invoice_images($lasku, $r);
    $vvk = jr_virtuaaliviivakoodi($s['iban'], $r->summa, $r->viite, jr_rivi_era($lasku, $r));
    $addr = jr_address_lines($r);
    $luokka = isset($r->jasenlaji) ? jr_laji_nimi((string) $r->jasenlaji) : '';
    $palautus = $s['yhdistys'];
    $yt = trim(preg_replace('/\s*\n\s*/', ' · ', (string) $s['yhteystiedot']));

    $h  = '<div class="sivu">';
    // Taittomerkit: puolitaitto C5-kuoreen (148,5 mm) ja kolmitaitto DL-kuoreen (99 ja 198 mm)
    $h .= '<span class="taitto" style="top:99mm"></span><span class="taitto" style="top:148.5mm"></span><span class="taitto" style="top:198mm"></span>';
    $h .= '<div class="ylaosa">';
    $h .= '<div class="lahettaja">' . esc_html($s['yhdistys']) . '</div>';
    $h .= '<div class="ikkuna"><div class="palautus">' . esc_html($palautus) . '</div><div class="vastaanottaja">';
    foreach ($addr as $i => $l) $h .= ($i ? '<br>' : '') . ($i === 0 ? '<strong>' . esc_html($l) . '</strong>' : esc_html($l));
    if (count($addr) < 2) $h .= '<br><span class="puuttuu">(osoite puuttuu)</span>';
    $h .= '</div></div>';
    $h .= '<div class="tiedot"><div class="laji">' . ($reminder ? 'MAKSUMUISTUTUS' : 'LASKU') . '</div><table>';
    $h .= '<tr><td>Päiväys</td><td>' . esc_html($reminder ? jr_date(current_time('Y-m-d')) : $vars['laskupvm']) . '</td></tr>';
    if ($reminder) $h .= '<tr><td>Alkuperäinen lasku</td><td>' . esc_html($vars['laskupvm']) . '</td></tr>';
    if (jr_tunnus_on() && $vars['tunnus'] !== '') $h .= '<tr><td>' . esc_html(jr_tunnus_nimi()) . '</td><td>' . esc_html($vars['tunnus']) . '</td></tr>';
    $h .= '<tr><td>Viitenumero</td><td class="vahva mono">' . esc_html($vars['viite']) . '</td></tr>';
    $h .= '<tr><td>Eräpäivä</td><td class="vahva">' . esc_html($vars['erapaiva']) . '</td></tr>';
    $h .= '<tr><td>Summa</td><td class="vahva">' . esc_html($vars['summa']) . '</td></tr>';
    $h .= '</table></div></div>';

    $h .= '<div class="saate">' . jr_fill($text, $vars) . '</div>';
    $h .= '<table class="erittely"><thead><tr><th>Erittely</th><th class="summa">Summa</th></tr></thead><tbody>';
    $h .= '<tr><td>' . esc_html($lasku->nimi) . ' – ' . esc_html($vars['nimi']) . ($luokka !== '' ? ' (' . esc_html($luokka) . ')' : '') . '</td><td class="summa">' . esc_html($vars['jasenmaksu']) . '</td></tr>';
    if (jr_rivi_postimaksu($r) > 0) $h .= '<tr><td>Postimaksu (paperilasku)</td><td class="summa">' . esc_html($vars['postimaksu']) . '</td></tr>';
    $h .= '</tbody></table>';
    $h .= '<div class="yhteensa"><span>Maksettava viimeistään ' . esc_html($vars['erapaiva']) . '</span><strong>' . esc_html($vars['summa']) . '</strong></div>';

    $h .= '<div class="alaosa"><div class="maksurivi">';
    if (isset($images['jrqr'])) {
        $h .= '<img class="qr" alt="QR-koodi" src="data:image/png;base64,' . base64_encode($images['jrqr']) . '">';
    }
    $h .= '<div class="maksutiedot"><table>'
        . '<tr><td>Saaja</td><td><strong>' . esc_html($s['yhdistys']) . '</strong></td></tr>'
        . '<tr><td>Tilinumero (IBAN)</td><td class="mono"><strong>' . esc_html($vars['iban']) . '</strong></td></tr>'
        . ($s['bic'] !== '' ? '<tr><td>BIC</td><td>' . esc_html($s['bic']) . '</td></tr>' : '')
        . '<tr><td>Viitenumero</td><td class="mono"><strong>' . esc_html($vars['viite']) . '</strong></td></tr>'
        . '<tr><td>Eräpäivä</td><td>' . esc_html($vars['erapaiva']) . '</td></tr>'
        . '<tr><td>Euro</td><td><strong>' . esc_html($vars['summa']) . '</strong></td></tr>'
        . '</table>'
        . (isset($images['jrqr']) ? '<div class="huom">Skannaa QR-koodi pankkisovelluksella, niin maksun tiedot täyttyvät valmiiksi.</div>' : '')
        . '</div></div>';
    if (isset($images['jrbar'])) {
        // Kuva on piirretty 2 px/moduuli; tulosteessa 0,3 mm/moduuli
        $px = unpack('N', substr($images['jrbar'], 16, 4))[1];
        $mm = round($px / 2 * 0.3, 1);
        $h .= '<div class="viivakoodi"><img alt="Pankkiviivakoodi" style="width:' . esc_attr($mm) . 'mm" src="data:image/png;base64,' . base64_encode($images['jrbar']) . '"></div>';
    }
    if ($vvk !== '') $h .= '<div class="vvk">Virtuaaliviivakoodi: <span class="mono">' . esc_html($vvk) . '</span></div>';
    $h .= '<div class="huom">Käytäthän maksaessasi viitenumeroa, jotta maksu kohdistuu sinulle.</div>';
    $foot = ($yt !== '' ? $yt : $s['yhdistys']) . ($s['ytunnus'] !== '' ? ' · Y-tunnus ' . $s['ytunnus'] : '');
    $h .= '<div class="alatunniste">' . esc_html($foot) . '</div>';
    $h .= '</div></div>';
    return $h;
}
