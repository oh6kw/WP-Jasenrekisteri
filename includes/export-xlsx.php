<?php
if (!defined('ABSPATH')) exit;

/*
 * Laskujen vienti Exceliin: yhteenveto, kaikki avoimet maksut ja jokainen lasku omalla välilehdellään.
 */

add_action('admin_post_jr_export_xlsx', 'jr_handle_export_xlsx');
function jr_handle_export_xlsx() {
    $id = intval(isset($_REQUEST['id']) ? $_REQUEST['id'] : 0); // 0 = kaikki laskut
    jr_check('jr_export_xlsx_' . $id);
    global $wpdb;
    require_once JR_DIR . 'includes/xlsx.php';

    $lt = jr_t('laskut');
    $laskut = $id
        ? $wpdb->get_results($wpdb->prepare("SELECT * FROM $lt WHERE id = %d", $id))
        : $wpdb->get_results("SELECT * FROM $lt ORDER BY laskupvm DESC, id DESC");
    if (!$laskut) jr_redirect('jr-laskutus');

    $today  = current_time('Y-m-d');
    $tunnus = jr_tunnus_on();
    $x = new JR_Xlsx();
    $sum = array(); $avoimet = array(); $sheets = array();

    foreach ($laskut as $l) {
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT r.*, m.etunimi, m.sukunimi, m.tunnus, m.tila, m.jasenlaji, m.puhelin, '
            . "COALESCE(NULLIF(m.email, ''), r.email) AS to_email "
            . 'FROM ' . jr_t('rivit') . ' r LEFT JOIN ' . jr_t('jasenet') . ' m ON m.id = r.jasen_id '
            . 'WHERE r.lasku_id = %d ORDER BY m.sukunimi, m.etunimi, r.nimi', $l->id
        ));
        $n = 0; $n_mak = 0; $n_era = 0; $e_lask = 0; $e_mak = 0; $e_avoin = 0;
        $out = array();
        foreach ($rows as $r) {
            $p    = jr_rivi_person($r);
            $era  = jr_rivi_era($l, $r);
            $n++; $e_lask += $r->summa;
            if ($r->maksettu) {
                $n_mak++;
                $paid = $r->maksettu_summa !== null ? (float) $r->maksettu_summa : (float) $r->summa;
                $e_mak += $paid;
                $poikkeaa = abs($paid - (float) $r->summa) > 0.004;
                $tila = JR_Xlsx::text($poikkeaa ? 'Maksettu (eri summa)' : 'Maksettu', $poikkeaa ? JR_Xlsx::S_WARN : JR_Xlsx::S_OK);
            } else {
                $e_avoin += $r->summa;
                if ($era < $today) { $n_era++; $tila = JR_Xlsx::text('Erääntynyt', JR_Xlsx::S_ERR); }
                else { $tila = JR_Xlsx::text('Avoin'); }
                $avoimet[] = array_merge(
                    array(JR_Xlsx::text($l->nimi), JR_Xlsx::text($p['nimi'])),
                    $tunnus ? array(JR_Xlsx::text($p['tunnus'])) : array(),
                    array(JR_Xlsx::text(jr_laji_nimi((string) $r->jasenlaji)), JR_Xlsx::text((string) $r->to_email), JR_Xlsx::text((string) $r->puhelin),
                        JR_Xlsx::text(jr_viite_format($r->viite)), JR_Xlsx::money($r->summa), JR_Xlsx::date($era), $tila,
                        (int) $r->muistutuksia ?: '', $r->muistutettu ? JR_Xlsx::date($r->muistutettu) : '')
                );
            }
            $out[] = array_merge(
                array(JR_Xlsx::text($p['nimi'])),
                $tunnus ? array(JR_Xlsx::text($p['tunnus'])) : array(),
                array(
                    JR_Xlsx::text(jr_laji_nimi((string) $r->jasenlaji)),
                    JR_Xlsx::text((string) $r->to_email),
                    JR_Xlsx::text(jr_viite_format($r->viite)),
                    JR_Xlsx::money($r->summa),
                    JR_Xlsx::date($era),
                    $tila,
                    $r->maksettu ? JR_Xlsx::date($r->maksettu) : '',
                    $r->maksettu ? JR_Xlsx::money($r->maksettu_summa !== null ? $r->maksettu_summa : $r->summa) : '',
                    JR_Xlsx::text((string) $r->maksaja),
                    $r->lahetetty ? JR_Xlsx::date($r->lahetetty) : '',
                    (int) $r->muistutuksia ?: '',
                )
            );
        }
        $sum[] = array(JR_Xlsx::text($l->nimi), JR_Xlsx::date($l->laskupvm), JR_Xlsx::date($l->erapaiva), $n, $n_mak, $n - $n_mak, $n_era,
            JR_Xlsx::money($e_lask), JR_Xlsx::money($e_mak), JR_Xlsx::money($e_avoin));
        $sheets[] = array($l, $out);
    }

    $t = $tunnus ? 1 : 0; // sarakesiirtymä, jos lisätunniste on käytössä

    // 1. Yhteenveto
    $x->add_sheet('Yhteenveto',
        array('Lasku', 'Laskun pvm', 'Eräpäivä', 'Laskuja', 'Maksettu', 'Avoinna', 'Erääntynyt', 'Laskutettu €', 'Maksettu €', 'Avoinna €'),
        $sum, array(30, 12, 12, 9, 10, 9, 11, 14, 14, 14),
        count($sum) > 1 ? array(JR_Xlsx::text('Yhteensä', JR_Xlsx::S_BOLD), '', '',
            JR_Xlsx::formula('SUM(D2:D{last})', JR_Xlsx::S_BOLD), JR_Xlsx::formula('SUM(E2:E{last})', JR_Xlsx::S_BOLD),
            JR_Xlsx::formula('SUM(F2:F{last})', JR_Xlsx::S_BOLD), JR_Xlsx::formula('SUM(G2:G{last})', JR_Xlsx::S_BOLD),
            JR_Xlsx::formula('SUM(H2:H{last})', JR_Xlsx::S_BOLD_MONEY), JR_Xlsx::formula('SUM(I2:I{last})', JR_Xlsx::S_BOLD_MONEY),
            JR_Xlsx::formula('SUM(J2:J{last})', JR_Xlsx::S_BOLD_MONEY)) : null);

    // 2. Kaikki avoimet maksut (vain kun viedään kaikki laskut)
    if (!$id) {
        $x->add_sheet('Avoimet maksut',
            array_merge(array('Lasku', 'Nimi'), $tunnus ? array(jr_tunnus_nimi()) : array(),
                array('Jäsenluokka', 'Sähköposti', 'Puhelin', 'Viite', 'Summa', 'Eräpäivä', 'Tila', 'Muistutuksia', 'Viim. muistutus')),
            $avoimet, array_merge(array(24, 24), $tunnus ? array(12) : array(), array(16, 30, 16, 14, 11, 12, 12, 12, 14)),
            $avoimet ? array_merge(array(JR_Xlsx::text('Yhteensä', JR_Xlsx::S_BOLD), ''), $tunnus ? array('') : array(), array('', '', '', '',
                JR_Xlsx::formula('SUM(' . chr(71 + $t) . '2:' . chr(71 + $t) . '{last})', JR_Xlsx::S_BOLD_MONEY))) : null);
    }

    // 3. Jokainen lasku omalle välilehdelle
    foreach ($sheets as $sh) {
        list($l, $out) = $sh;
        $sumcol = chr(69 + $t);  // Summa
        $paidcol = chr(73 + $t); // Maksettu €
        $x->add_sheet($l->nimi,
            array_merge(array('Nimi'), $tunnus ? array(jr_tunnus_nimi()) : array(),
                array('Jäsenluokka', 'Sähköposti', 'Viite', 'Summa', 'Eräpäivä', 'Tila', 'Maksupäivä', 'Maksettu €', 'Maksaja', 'Lähetetty', 'Muistutuksia')),
            $out, array_merge(array(26), $tunnus ? array(12) : array(), array(16, 30, 14, 11, 12, 20, 12, 12, 22, 12, 12)),
            array_merge(array(JR_Xlsx::text('Yhteensä', JR_Xlsx::S_BOLD)), $tunnus ? array('') : array(), array('', '', '',
                JR_Xlsx::formula("SUM({$sumcol}2:{$sumcol}{last})", JR_Xlsx::S_BOLD_MONEY), '', '', '',
                JR_Xlsx::formula("SUM({$paidcol}2:{$paidcol}{last})", JR_Xlsx::S_BOLD_MONEY))));
    }

    $nimi = $id ? sanitize_file_name($laskut[0]->nimi) : 'jasenmaksut-' . current_time('Y-m-d');
    $bin = $x->build();
    nocache_headers();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nimi . '.xlsx"');
    header('Content-Length: ' . strlen($bin));
    echo $bin; // phpcs:ignore -- binääritiedosto
    exit;
}
