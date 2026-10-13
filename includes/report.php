<?php
if (!defined('ABSPATH')) exit;

/*
 * Vuosiraportti vuosikokoukseen: jäsenmäärät jäsenluokittain sekä jäsenmaksujen laskutus ja kertymä.
 */

function jr_report_years() {
    global $wpdb;
    $now = (int) current_time('Y');
    $min = (int) substr((string) $wpdb->get_var('SELECT MIN(laskupvm) FROM ' . jr_t('laskut')), 0, 4);
    $minj = (int) substr((string) $wpdb->get_var('SELECT MIN(liittynyt) FROM ' . jr_t('jasenet') . " WHERE liittynyt IS NOT NULL AND liittynyt > '1900-01-01'"), 0, 4);
    $from = min(array_filter(array($min ?: $now, $minj ?: $now, $now - 1)));
    $from = max($from, $now - 30);
    return range($now, $from);
}

function jr_report_data($year) {
    global $wpdb;
    $year  = (int) $year;
    $today = current_time('Y-m-d');
    $alku  = sprintf('%04d-01-01', $year);
    $loppu = sprintf('%04d-12-31', $year);
    $raja  = $loppu > $today ? $today : $loppu;   // kuluva vuosi: tilanne tänään
    $edel  = sprintf('%04d-12-31', $year - 1);
    $lajit = jr_jasenlajit();

    $active = function ($m, $d) {
        if ($m->liittynyt && $m->liittynyt !== '0000-00-00' && $m->liittynyt > $d) return false;
        if ($m->tila !== 'eronnut') return true;
        return $m->eronnut_pvm && $m->eronnut_pvm > $d;
    };

    $keys = array_merge(array_keys($lajit), array(''));
    $j = array();
    foreach ($keys as $k) $j[$k] = array('alku' => 0, 'liittyi' => 0, 'erosi' => 0, 'loppu' => 0);
    $ero_ei_pvm = 0; $ei_liitt = 0;
    foreach ($wpdb->get_results('SELECT id, jasenlaji, tila, liittynyt, eronnut_pvm FROM ' . jr_t('jasenet')) as $m) {
        $k = isset($lajit[$m->jasenlaji]) ? $m->jasenlaji : '';
        if ($active($m, $edel)) $j[$k]['alku']++;
        if ($active($m, $raja)) {
            $j[$k]['loppu']++;
            if (!$m->liittynyt || $m->liittynyt === '0000-00-00') $ei_liitt++;
        }
        if ($m->liittynyt && $m->liittynyt >= $alku && $m->liittynyt <= $raja) $j[$k]['liittyi']++;
        if ($m->tila === 'eronnut') {
            if (!$m->eronnut_pvm) $ero_ei_pvm++;
            elseif ($m->eronnut_pvm >= $alku && $m->eronnut_pvm <= $raja) $j[$k]['erosi']++;
        }
    }
    if (!array_sum($j[''])) unset($j['']);

    // Vuoden laskut (laskun päiväyksen mukaan)
    $rt = jr_t('rivit'); $lt = jr_t('laskut'); $mt = jr_t('jasenet');
    $laskut = $wpdb->get_results($wpdb->prepare(
        "SELECT l.id, l.nimi, l.laskupvm, l.erapaiva, COUNT(r.id) AS n, COALESCE(SUM(r.summa), 0) AS laskutettu, "
        . "SUM(r.maksettu IS NOT NULL) AS n_mak, COALESCE(SUM(CASE WHEN r.maksettu IS NOT NULL THEN COALESCE(r.maksettu_summa, r.summa) ELSE 0 END), 0) AS maksettu, "
        . "COALESCE(SUM(CASE WHEN r.maksettu IS NULL THEN r.summa ELSE 0 END), 0) AS avoin, "
        . "COALESCE(SUM(CASE WHEN r.maksettu IS NULL AND COALESCE(r.erapaiva, l.erapaiva) < %s THEN r.summa ELSE 0 END), 0) AS erant "
        . "FROM $lt l LEFT JOIN $rt r ON r.lasku_id = l.id WHERE l.laskupvm BETWEEN %s AND %s GROUP BY l.id, l.nimi, l.laskupvm, l.erapaiva ORDER BY l.laskupvm, l.id",
        $today, $alku, $loppu
    ));

    // Luokittain (jäsenluokka laskutushetkellä)
    $luokat = array();
    foreach ($wpdb->get_results($wpdb->prepare(
        "SELECT COALESCE(NULLIF(r.jasenlaji, ''), m.jasenlaji, '') AS laji, COUNT(*) AS n, SUM(r.summa) AS laskutettu, "
        . "SUM(r.maksettu IS NOT NULL) AS n_mak, SUM(CASE WHEN r.maksettu IS NOT NULL THEN COALESCE(r.maksettu_summa, r.summa) ELSE 0 END) AS maksettu, "
        . "SUM(CASE WHEN r.maksettu IS NULL THEN r.summa ELSE 0 END) AS avoin "
        . "FROM $rt r JOIN $lt l ON l.id = r.lasku_id LEFT JOIN $mt m ON m.id = r.jasen_id "
        . "WHERE l.laskupvm BETWEEN %s AND %s GROUP BY COALESCE(NULLIF(r.jasenlaji, ''), m.jasenlaji, '')", $alku, $loppu)) as $row) {
        $k = isset($lajit[$row->laji]) ? $row->laji : '';
        if (!isset($luokat[$k])) $luokat[$k] = array('n' => 0, 'laskutettu' => 0, 'n_mak' => 0, 'maksettu' => 0, 'avoin' => 0);
        foreach ($luokat[$k] as $f => $v) $luokat[$k][$f] += (float) $row->$f;
    }
    uksort($luokat, function ($a, $b) use ($lajit) {
        $ka = array_search($a, array_keys($lajit), true); $kb = array_search($b, array_keys($lajit), true);
        return ($ka === false ? 999 : $ka) - ($kb === false ? 999 : $kb);
    });

    // Kassaan vuoden aikana (maksupäivän mukaan, myös aiempien vuosien laskuista)
    $kassa = $wpdb->get_results($wpdb->prepare(
        "SELECT l.nimi, COUNT(*) AS n, SUM(COALESCE(r.maksettu_summa, r.summa)) AS summa FROM $rt r JOIN $lt l ON l.id = r.lasku_id "
        . "WHERE r.maksettu BETWEEN %s AND %s GROUP BY l.id, l.nimi ORDER BY MIN(l.laskupvm), l.id", $alku, $loppu
    ));

    return compact('year', 'raja', 'edel', 'j', 'ero_ei_pvm', 'ei_liitt', 'laskut', 'luokat', 'kassa', 'today');
}

function jr_report_laji_label($k) {
    return $k === '' ? 'Ilman jäsenluokkaa' : jr_laji_nimi($k);
}

/* ------------------------------------------------------------------
 * Excel
 * ------------------------------------------------------------------ */

add_action('admin_post_jr_report_xlsx', 'jr_handle_report_xlsx');
function jr_handle_report_xlsx() {
    jr_check('jr_report_xlsx');
    require_once JR_DIR . 'includes/xlsx.php';
    $year = intval(isset($_REQUEST['vuosi']) ? $_REQUEST['vuosi'] : current_time('Y'));
    $d = jr_report_data($year);
    $x = new JR_Xlsx();
    $T = function ($v) { return JR_Xlsx::text($v); };
    $sumf = function ($col, $money = false) { return JR_Xlsx::formula("SUM({$col}2:{$col}{last})", $money ? JR_Xlsx::S_BOLD_MONEY : JR_Xlsx::S_BOLD); };

    $rows = array();
    foreach ($d['j'] as $k => $v) $rows[] = array($T(jr_report_laji_label($k)), $v['alku'], $v['liittyi'], $v['erosi'], $v['loppu']);
    $x->add_sheet('Jäsenmäärät ' . $year,
        array('Jäsenluokka', 'Jäseniä ' . jr_date($d['edel']), 'Liittyi ' . $year, 'Erosi ' . $year, 'Jäseniä ' . jr_date($d['raja'])),
        $rows, array(26, 18, 14, 14, 18),
        array(JR_Xlsx::text('Yhteensä', JR_Xlsx::S_BOLD), $sumf('B'), $sumf('C'), $sumf('D'), $sumf('E')));

    $rows = array();
    foreach ($d['laskut'] as $l) $rows[] = array($T($l->nimi), JR_Xlsx::date($l->laskupvm), JR_Xlsx::date($l->erapaiva), (int) $l->n, (int) $l->n_mak,
        JR_Xlsx::money($l->laskutettu), JR_Xlsx::money($l->maksettu), JR_Xlsx::money($l->avoin), JR_Xlsx::money($l->erant));
    $x->add_sheet('Laskut ' . $year,
        array('Lasku', 'Laskun pvm', 'Eräpäivä', 'Laskuja', 'Maksettu kpl', 'Laskutettu €', 'Maksettu €', 'Avoinna €', 'Josta erääntynyt €'),
        $rows, array(28, 12, 12, 10, 12, 14, 14, 14, 18),
        array(JR_Xlsx::text('Yhteensä', JR_Xlsx::S_BOLD), '', '', $sumf('D'), $sumf('E'), $sumf('F', true), $sumf('G', true), $sumf('H', true), $sumf('I', true)));

    $rows = array();
    foreach ($d['luokat'] as $k => $v) $rows[] = array($T(jr_report_laji_label($k)), (int) $v['n'], (int) $v['n_mak'],
        JR_Xlsx::money($v['laskutettu']), JR_Xlsx::money($v['maksettu']), JR_Xlsx::money($v['avoin']));
    $x->add_sheet('Luokittain ' . $year,
        array('Jäsenluokka', 'Laskuja', 'Maksettu kpl', 'Laskutettu €', 'Maksettu €', 'Avoinna €'),
        $rows, array(26, 10, 12, 14, 14, 14),
        array(JR_Xlsx::text('Yhteensä', JR_Xlsx::S_BOLD), $sumf('B'), $sumf('C'), $sumf('D', true), $sumf('E', true), $sumf('F', true)));

    $rows = array();
    foreach ($d['kassa'] as $k) $rows[] = array($T($k->nimi), (int) $k->n, JR_Xlsx::money($k->summa));
    $x->add_sheet('Kertymä ' . $year, array('Lasku', 'Maksuja', 'Kertynyt € (maksupäivä ' . $year . ')'), $rows, array(30, 10, 26),
        array(JR_Xlsx::text('Yhteensä', JR_Xlsx::S_BOLD), $sumf('B'), $sumf('C', true)));

    $bin = $x->build();
    jr_log('vienti', 0, 'vienti', 'Vuosiraportti ' . $year . ' Exceliin');
    nocache_headers();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="vuosiraportti-' . $year . '.xlsx"');
    header('Content-Length: ' . strlen($bin));
    echo $bin; // phpcs:ignore -- binääritiedosto
    exit;
}

/* ------------------------------------------------------------------
 * Sivu
 * ------------------------------------------------------------------ */

function jr_page_report() {
    $years = jr_report_years();
    $def = ((int) current_time('n') <= 6) ? (int) current_time('Y') - 1 : (int) current_time('Y');
    $year = isset($_GET['vuosi']) ? intval($_GET['vuosi']) : $def;
    if (!in_array($year, $years, true)) $year = $def;
    $d = jr_report_data($year);
    $s = jr_settings();
    $kesken = $d['raja'] < sprintf('%04d-12-31', $year);
    $tot = array('alku' => 0, 'liittyi' => 0, 'erosi' => 0, 'loppu' => 0);
    foreach ($d['j'] as $v) foreach ($tot as $f => $x) $tot[$f] += $v[$f];
    $sum = function ($rows, $f) { $t = 0; foreach ($rows as $r) $t += (float) (is_array($r) ? $r[$f] : $r->$f); return $t; };
    $kassa = $sum($d['kassa'], 'summa');
    ?>
    <div class="wrap jr-wrap jr-report">
        <h1 class="wp-heading-inline">Vuosiraportti <?php echo (int) $year; ?></h1>
        <form method="get" class="jr-noprint jr-inline">
            <input type="hidden" name="page" value="jr-raportti">
            <select name="vuosi" onchange="this.form.submit()">
                <?php foreach ($years as $y): ?><option value="<?php echo (int) $y; ?>" <?php selected($year, $y); ?>><?php echo (int) $y; ?></option><?php endforeach; ?>
            </select>
            <noscript><button class="button">Näytä</button></noscript>
            <button type="button" class="button" onclick="window.print()">🖨 Tulosta</button>
            <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=jr_report_xlsx&vuosi=' . $year), 'jr_report_xlsx')); ?>">Vie Exceliin</a>
        </form>
        <hr class="wp-header-end">
        <p class="jr-report-sub"><?php echo esc_html($s['yhdistys']); ?> · <?php echo $kesken ? 'tilanne ' . esc_html(jr_date($d['raja'])) . ' (vuosi kesken)' : 'tilikausi 1.1.–31.12.' . (int) $year; ?> · tulostettu <?php echo esc_html(jr_date($d['today'])); ?></p>

        <div class="jr-cards">
            <div class="jr-card"><span class="jr-card-num"><?php echo (int) $tot['loppu']; ?></span><span class="jr-card-label">Jäseniä <?php echo esc_html(jr_date($d['raja'])); ?> (<?php echo ($tot['loppu'] - $tot['alku'] >= 0 ? '+' : '') . (int) ($tot['loppu'] - $tot['alku']); ?>)</span></div>
            <div class="jr-card jr-card-ok"><span class="jr-card-num"><?php echo (int) $tot['liittyi']; ?></span><span class="jr-card-label">Liittyi vuonna <?php echo (int) $year; ?></span></div>
            <div class="jr-card"><span class="jr-card-num"><?php echo (int) $tot['erosi']; ?></span><span class="jr-card-label">Erosi vuonna <?php echo (int) $year; ?></span></div>
            <div class="jr-card jr-card-ok"><span class="jr-card-num"><?php echo esc_html(jr_money($kassa)); ?></span><span class="jr-card-label">Jäsenmaksuja kertyi <?php echo (int) $year; ?></span></div>
        </div>

        <div class="jr-panel">
            <h2>Jäsenmäärä jäsenluokittain</h2>
            <table class="widefat striped jr-table jr-num">
                <thead><tr><th>Jäsenluokka</th><th>Jäseniä <?php echo esc_html(jr_date($d['edel'])); ?></th><th>Liittyi</th><th>Erosi</th><th>Jäseniä <?php echo esc_html(jr_date($d['raja'])); ?></th><th>Muutos</th></tr></thead>
                <tbody>
                <?php foreach ($d['j'] as $k => $v): $mu = $v['loppu'] - $v['alku']; ?>
                    <tr><td><?php echo esc_html(jr_report_laji_label($k)); ?></td><td><?php echo (int) $v['alku']; ?></td><td><?php echo (int) $v['liittyi']; ?></td><td><?php echo (int) $v['erosi']; ?></td>
                        <td><strong><?php echo (int) $v['loppu']; ?></strong></td><td><?php echo ($mu > 0 ? '+' : '') . (int) $mu; ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th>Yhteensä</th><th><?php echo (int) $tot['alku']; ?></th><th><?php echo (int) $tot['liittyi']; ?></th><th><?php echo (int) $tot['erosi']; ?></th><th><?php echo (int) $tot['loppu']; ?></th><th><?php echo ($tot['loppu'] - $tot['alku'] > 0 ? '+' : '') . (int) ($tot['loppu'] - $tot['alku']); ?></th></tr></tfoot>
            </table>
            <p class="description">Jäsenmäärät lasketaan liittymis- ja eroamispäivistä. Jäsenluokka on jäsenen nykyinen luokka.
                <?php if ($d['ei_liitt']): ?><?php echo (int) $d['ei_liitt']; ?> jäseneltä puuttuu liittymispäivä – heidät lasketaan jäseniksi koko vuoden.<?php endif; ?>
                <?php if ($d['ero_ei_pvm']): ?><br><strong><?php echo (int) $d['ero_ei_pvm']; ?> eronnutta ilman eroamispäivää</strong> ei ole mukana luvuissa. Lisää eroamispäivä jäsenen tietoihin, jos ero osui tälle vuodelle.<?php endif; ?></p>
        </div>

        <div class="jr-panel">
            <h2>Jäsenmaksut <?php echo (int) $year; ?></h2>
            <?php if (!$d['laskut']): ?>
                <p class="jr-muted">Vuodelle <?php echo (int) $year; ?> päivättyjä laskuja ei ole.</p>
            <?php else: ?>
            <table class="widefat striped jr-table jr-num">
                <thead><tr><th>Lasku</th><th>Eräpäivä</th><th>Laskuja</th><th>Maksettu</th><th>Laskutettu</th><th>Maksettu €</th><th>Avoinna</th></tr></thead>
                <tbody>
                <?php foreach ($d['laskut'] as $l): ?>
                    <tr><td><?php echo esc_html($l->nimi); ?></td><td><?php echo esc_html(jr_date($l->erapaiva)); ?></td><td><?php echo (int) $l->n; ?></td><td><?php echo (int) $l->n_mak; ?></td>
                        <td><?php echo esc_html(jr_money($l->laskutettu)); ?></td><td><?php echo esc_html(jr_money($l->maksettu)); ?></td>
                        <td><?php echo esc_html(jr_money($l->avoin)); ?><?php if ($l->erant > 0): ?><div class="jr-sub">josta erääntynyt <?php echo esc_html(jr_money($l->erant)); ?></div><?php endif; ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <?php if (count($d['laskut']) > 1): ?>
                <tfoot><tr><th>Yhteensä</th><th></th><th><?php echo (int) $sum($d['laskut'], 'n'); ?></th><th><?php echo (int) $sum($d['laskut'], 'n_mak'); ?></th>
                    <th><?php echo esc_html(jr_money($sum($d['laskut'], 'laskutettu'))); ?></th><th><?php echo esc_html(jr_money($sum($d['laskut'], 'maksettu'))); ?></th><th><?php echo esc_html(jr_money($sum($d['laskut'], 'avoin'))); ?></th></tr></tfoot>
                <?php endif; ?>
            </table>

            <h3>Jäsenluokittain</h3>
            <table class="widefat striped jr-table jr-num">
                <thead><tr><th>Jäsenluokka</th><th>Laskuja</th><th>Maksettu</th><th>Laskutettu</th><th>Maksettu €</th><th>Avoinna</th></tr></thead>
                <tbody>
                <?php foreach ($d['luokat'] as $k => $v): ?>
                    <tr><td><?php echo esc_html(jr_report_laji_label($k)); ?></td><td><?php echo (int) $v['n']; ?></td><td><?php echo (int) $v['n_mak']; ?></td>
                        <td><?php echo esc_html(jr_money($v['laskutettu'])); ?></td><td><?php echo esc_html(jr_money($v['maksettu'])); ?></td><td><?php echo esc_html(jr_money($v['avoin'])); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th>Yhteensä</th><th><?php echo (int) $sum($d['luokat'], 'n'); ?></th><th><?php echo (int) $sum($d['luokat'], 'n_mak'); ?></th>
                    <th><?php echo esc_html(jr_money($sum($d['luokat'], 'laskutettu'))); ?></th><th><?php echo esc_html(jr_money($sum($d['luokat'], 'maksettu'))); ?></th><th><?php echo esc_html(jr_money($sum($d['luokat'], 'avoin'))); ?></th></tr></tfoot>
            </table>
            <p class="description">Laskut, joiden päiväys on vuonna <?php echo (int) $year; ?>. Jäsenluokka laskutushetken mukaan. Avoinna-sarake näyttää tämänhetkisen tilanteen.</p>
            <?php endif; ?>
        </div>

        <div class="jr-panel">
            <h2>Jäsenmaksukertymä <?php echo (int) $year; ?></h2>
            <?php if (!$d['kassa']): ?>
                <p class="jr-muted">Vuonna <?php echo (int) $year; ?> ei kirjattu maksuja.</p>
            <?php else: ?>
            <table class="widefat striped jr-table jr-num">
                <thead><tr><th>Lasku</th><th>Maksuja</th><th>Kertynyt</th></tr></thead>
                <tbody>
                <?php foreach ($d['kassa'] as $k): ?>
                    <tr><td><?php echo esc_html($k->nimi); ?></td><td><?php echo (int) $k->n; ?></td><td><?php echo esc_html(jr_money($k->summa)); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th>Yhteensä</th><th><?php echo (int) $sum($d['kassa'], 'n'); ?></th><th><?php echo esc_html(jr_money($kassa)); ?></th></tr></tfoot>
            </table>
            <p class="description">Maksupäivän mukaan vuonna <?php echo (int) $year; ?> kirjatut maksut, myös aiempien vuosien laskuista. Vertaa kirjanpidon jäsenmaksutuottoihin.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
