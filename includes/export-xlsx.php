<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own custom tables (members, invoices, audit log). The data is admin-only and changes on every write, so object caching is not used.

/*
 * Laskujen vienti Exceliin: yhteenveto, kaikki avoimet maksut ja jokainen lasku omalla välilehdellään.
 */

add_action('admin_post_jasrek_export_xlsx', 'jasrek_handle_export_xlsx');
function jasrek_handle_export_xlsx() {
    $id = jasrek_request_int('id'); // 0 = kaikki laskut
    jasrek_check('jasrek_export_xlsx_' . $id);
    global $wpdb;
    require_once JASREK_DIR . 'includes/xlsx.php';

    $lt = jasrek_t('laskut');
    $laskut = $id
        ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->jasrek_laskut} WHERE id = %d", $id))
        : $wpdb->get_results("SELECT * FROM {$wpdb->jasrek_laskut} ORDER BY laskupvm DESC, id DESC");
    if (!$laskut) jasrek_redirect('jasrek-laskutus');

    $today  = current_time('Y-m-d');
    $tunnus = jasrek_tunnus_on();
    $x = new JASREK_Xlsx();
    $sum = array(); $avoimet = array(); $sheets = array();

    foreach ($laskut as $l) {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, m.etunimi, m.sukunimi, m.tunnus, m.tila, COALESCE(NULLIF(r.jasenlaji, ''), m.jasenlaji, '') AS jasenlaji, m.puhelin, COALESCE(NULLIF(m.email, ''), r.email) AS to_email FROM {$wpdb->jasrek_rivit} r LEFT JOIN {$wpdb->jasrek_jasenet} m ON m.id = r.jasen_id WHERE r.lasku_id = %d ORDER BY m.sukunimi, m.etunimi, r.nimi", $l->id
        ));
        $n = 0; $n_mak = 0; $n_era = 0; $e_lask = 0; $e_mak = 0; $e_avoin = 0;
        $out = array();
        foreach ($rows as $r) {
            $p    = jasrek_rivi_person($r);
            $era  = jasrek_rivi_era($l, $r);
            $n++; $e_lask += $r->summa;
            if ($r->maksettu) {
                $n_mak++;
                $paid = $r->maksettu_summa !== null ? (float) $r->maksettu_summa : (float) $r->summa;
                $e_mak += $paid;
                $poikkeaa = abs($paid - (float) $r->summa) > 0.004;
                $tila = JASREK_Xlsx::text($poikkeaa ? 'Maksettu (eri summa)' : 'Maksettu', $poikkeaa ? JASREK_Xlsx::S_WARN : JASREK_Xlsx::S_OK);
            } else {
                $e_avoin += $r->summa;
                if ($era < $today) { $n_era++; $tila = JASREK_Xlsx::text('Erääntynyt', JASREK_Xlsx::S_ERR); }
                else { $tila = JASREK_Xlsx::text('Avoin'); }
                $avoimet[] = array_merge(
                    array(JASREK_Xlsx::text($l->nimi), JASREK_Xlsx::text($p['nimi'])),
                    $tunnus ? array(JASREK_Xlsx::text($p['tunnus'])) : array(),
                    array(JASREK_Xlsx::text(jasrek_laji_nimi((string) $r->jasenlaji)), JASREK_Xlsx::text((string) $r->to_email), JASREK_Xlsx::text((string) $r->puhelin),
                        JASREK_Xlsx::text(jasrek_viite_format($r->viite)), JASREK_Xlsx::money($r->summa), JASREK_Xlsx::date($era), $tila,
                        (int) $r->muistutuksia ?: '', $r->muistutettu ? JASREK_Xlsx::date($r->muistutettu) : '')
                );
            }
            $out[] = array_merge(
                array(JASREK_Xlsx::text($p['nimi'])),
                $tunnus ? array(JASREK_Xlsx::text($p['tunnus'])) : array(),
                array(
                    JASREK_Xlsx::text(jasrek_laji_nimi((string) $r->jasenlaji)),
                    JASREK_Xlsx::text((string) $r->to_email),
                    JASREK_Xlsx::text(jasrek_viite_format($r->viite)),
                    JASREK_Xlsx::money($r->summa),
                    JASREK_Xlsx::date($era),
                    $tila,
                    $r->maksettu ? JASREK_Xlsx::date($r->maksettu) : '',
                    $r->maksettu ? JASREK_Xlsx::money($r->maksettu_summa !== null ? $r->maksettu_summa : $r->summa) : '',
                    JASREK_Xlsx::text((string) $r->maksaja),
                    $r->lahetetty ? JASREK_Xlsx::date($r->lahetetty) : '',
                    (int) $r->muistutuksia ?: '',
                )
            );
        }
        $sum[] = array(JASREK_Xlsx::text($l->nimi), JASREK_Xlsx::date($l->laskupvm), JASREK_Xlsx::date($l->erapaiva), $n, $n_mak, $n - $n_mak, $n_era,
            JASREK_Xlsx::money($e_lask), JASREK_Xlsx::money($e_mak), JASREK_Xlsx::money($e_avoin));
        $sheets[] = array($l, $out);
    }

    $t = $tunnus ? 1 : 0; // sarakesiirtymä, jos lisätunniste on käytössä

    // 1. Yhteenveto
    $x->add_sheet('Yhteenveto',
        array('Lasku', 'Laskun pvm', 'Eräpäivä', 'Laskuja', 'Maksettu', 'Avoinna', 'Erääntynyt', 'Laskutettu €', 'Maksettu €', 'Avoinna €'),
        $sum, array(30, 12, 12, 9, 10, 9, 11, 14, 14, 14),
        count($sum) > 1 ? array(JASREK_Xlsx::text('Yhteensä', JASREK_Xlsx::S_BOLD), '', '',
            JASREK_Xlsx::formula('SUM(D2:D{last})', JASREK_Xlsx::S_BOLD), JASREK_Xlsx::formula('SUM(E2:E{last})', JASREK_Xlsx::S_BOLD),
            JASREK_Xlsx::formula('SUM(F2:F{last})', JASREK_Xlsx::S_BOLD), JASREK_Xlsx::formula('SUM(G2:G{last})', JASREK_Xlsx::S_BOLD),
            JASREK_Xlsx::formula('SUM(H2:H{last})', JASREK_Xlsx::S_BOLD_MONEY), JASREK_Xlsx::formula('SUM(I2:I{last})', JASREK_Xlsx::S_BOLD_MONEY),
            JASREK_Xlsx::formula('SUM(J2:J{last})', JASREK_Xlsx::S_BOLD_MONEY)) : null);

    // 2. Kaikki avoimet maksut (vain kun viedään kaikki laskut)
    if (!$id) {
        $x->add_sheet('Avoimet maksut',
            array_merge(array('Lasku', 'Nimi'), $tunnus ? array(jasrek_tunnus_nimi()) : array(),
                array('Jäsenluokka', 'Sähköposti', 'Puhelin', 'Viite', 'Summa', 'Eräpäivä', 'Tila', 'Muistutuksia', 'Viim. muistutus')),
            $avoimet, array_merge(array(24, 24), $tunnus ? array(12) : array(), array(16, 30, 16, 14, 11, 12, 12, 12, 14)),
            $avoimet ? array_merge(array(JASREK_Xlsx::text('Yhteensä', JASREK_Xlsx::S_BOLD), ''), $tunnus ? array('') : array(), array('', '', '', '',
                JASREK_Xlsx::formula('SUM(' . chr(71 + $t) . '2:' . chr(71 + $t) . '{last})', JASREK_Xlsx::S_BOLD_MONEY))) : null);
    }

    // 3. Jokainen lasku omalle välilehdelle
    foreach ($sheets as $sh) {
        list($l, $out) = $sh;
        $sumcol = chr(69 + $t);  // Summa
        $paidcol = chr(73 + $t); // Maksettu €
        $x->add_sheet($l->nimi,
            array_merge(array('Nimi'), $tunnus ? array(jasrek_tunnus_nimi()) : array(),
                array('Jäsenluokka', 'Sähköposti', 'Viite', 'Summa', 'Eräpäivä', 'Tila', 'Maksupäivä', 'Maksettu €', 'Maksaja', 'Lähetetty', 'Muistutuksia')),
            $out, array_merge(array(26), $tunnus ? array(12) : array(), array(16, 30, 14, 11, 12, 20, 12, 12, 22, 12, 12)),
            array_merge(array(JASREK_Xlsx::text('Yhteensä', JASREK_Xlsx::S_BOLD)), $tunnus ? array('') : array(), array('', '', '',
                JASREK_Xlsx::formula("SUM({$sumcol}2:{$sumcol}{last})", JASREK_Xlsx::S_BOLD_MONEY), '', '', '',
                JASREK_Xlsx::formula("SUM({$paidcol}2:{$paidcol}{last})", JASREK_Xlsx::S_BOLD_MONEY))));
    }

    $nimi = $id ? sanitize_file_name($laskut[0]->nimi) : 'jasenmaksut-' . current_time('Y-m-d');
    $bin = $x->build();
    jasrek_log('vienti', 0, 'vienti', $id ? 'Lasku ' . $laskut[0]->nimi . ' Exceliin' : 'Kaikki laskut Exceliin (' . count($laskut) . ' laskua)');
    nocache_headers();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nimi . '.xlsx"');
    header('Content-Length: ' . strlen($bin));
    echo $bin; // phpcs:ignore -- binääritiedosto
    exit;
}
