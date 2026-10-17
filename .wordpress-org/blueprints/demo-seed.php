<?php
/*
 * WordPress Playground -esikatselun demoaineisto (blueprint.json → runPHP).
 * Kaikki nimet, osoitteet ja yhteystiedot ovat keksittyjä. Päivämäärät ovat suhteessa esikatselun päivään.
 * blueprint.json tehdään tästä tiedostosta: python3 .wordpress-org/blueprints/build.py
 */
if (!defined('ABSPATH')) require_once '/wordpress/wp-load.php';
if (!function_exists('jasrek_settings')) return;
global $wpdb;

$s = array_merge(jasrek_settings(), array(
    'yhdistys' => 'Esimerkkiyhdistys ry',
    'iban' => 'FI7944052020036082',
    'ytunnus' => '1234567-8',
    'osoite' => "Kerhotie 1\n00100 Esimerkkilä",
    'maksukoodi' => 'molemmat',
    'lahettaja_nimi' => 'Esimerkkiyhdistys ry',
    'lahettaja_email' => 'kerho@example.com',
    'vastaus_email' => 'rahastonhoitaja@example.com',
    'yhteystiedot' => 'Esimerkkiyhdistys ry · rahastonhoitaja@example.com · www.example.com',
    'tunnus_kaytossa' => '1', 'tunnus_nimi' => 'Jäsennumero', 'tunnus_isot' => '0',
    'jasenlajit' => array(
        'jasen'   => array('nimi' => 'Jäsen', 'summa' => 25),
        'nuoriso' => array('nimi' => 'Nuorisojäsen', 'summa' => 10),
        'kunnia'  => array('nimi' => 'Kunniajäsen', 'summa' => 0),
    ),
    'lisakentat' => array(
        'avain' => array('nimi' => 'Avain', 'tyyppi' => 'teksti', 'lista' => '1'),
    ),
    'postimaksu' => '2.50',
    'viesti_oletus' => "Hei {etunimi},\n\non taas aika uusia yhdistyksen jäsenyys. Tässä {laskun_nimi}.\n\nJäsenluokkasi: {jasenluokka}\nJäsenmaksu: {summa}\nEräpäivä: {erapaiva}\n\nMaksaessasi käytä viitenumeroa {viite}, niin maksu kohdistuu oikein.\n\nKiitos, että olet mukana yhdistyksen toiminnassa!\n\nYstävällisin terveisin\n{yhdistys}",
));
update_option('jasrek_settings', $s);

$jt = jasrek_t('jasenet');
if ((int) $wpdb->get_var("SELECT COUNT(*) FROM $jt") > 0) return; // ajetaan vain tyhjään rekisteriin

$m = array(
    // etunimi, sukunimi, jäsennumero, luokka, liittymisvuosia sitten, sähköposti?, paperilasku?
    array('Anna', 'Aaltonen', '1001', 'jasen', 22, 1, 0),
    array('Kalle', 'Kivinen', '1002', 'jasen', 28, 1, 0),
    array('Mikko', 'Mäkelä', '1003', 'jasen', 15, 1, 0),
    array('Tuula', 'Tuominen', '1004', 'jasen', 11, 1, 1),
    array('Ville', 'Virtanen', '1005', 'nuoriso', 3, 1, 0),
    array('Sanna', 'Salminen', '1006', 'nuoriso', 2, 1, 0),
    array('Pekka', 'Peltonen', '1007', 'jasen', 18, 1, 0),
    array('Olli', 'Ojala', '1008', 'kunnia', 58, 1, 0),
    array('Liisa', 'Laine', '1009', 'jasen', 7, 1, 0),
    array('Reijo', 'Rantanen', '1010', 'jasen', 25, 1, 0),
    array('Yrjö', 'Ylönen', '1011', 'jasen', 10, 0, 1),
    array('Eeva', 'Lehtonen', '1012', 'jasen', 5, 1, 0),
);
$ids = array();
$vuosi = (int) current_time('Y');
foreach ($m as $i => $r) {
    $wpdb->insert($jt, array(
        'etunimi' => $r[0], 'sukunimi' => $r[1], 'tunnus' => $r[2], 'jasenlaji' => $r[3],
        'liittynyt' => ($vuosi - $r[4]) . '-0' . (1 + $i % 9) . '-15',
        'email' => $r[5] ? strtolower(remove_accents($r[0] . '.' . $r[1])) . '@example.com' : '',
        'osoite' => 'Esimerkkitie ' . ($i + 1) . ', 00100 Esimerkkilä',
        'puhelin' => '050 000 01' . sprintf('%02d', $i + 1),
        'paperilasku' => $r[6], 'tila' => 'aktiivinen', 'lisatiedot' => '',
    ));
    $ids[$r[0]] = (int) $wpdb->insert_id;
}
$wpdb->update($jt, array('lisakentat' => '{"avain":"8.59"}'), array('id' => $ids['Anna']));
$wpdb->update($jt, array('lisatiedot' => 'Ei sähköpostia – lasku postitse'), array('id' => $ids['Yrjö']));

// Lasku: päivätty 10 päivää sitten, eräpäivä 3 viikon päästä. Sähköpostilaskut lähetetty, osa maksettu.
$hinnat = array();
foreach (jasrek_jasenlajit() as $k => $l) $hinnat[$k] = (float) $l['summa'];
$tanaan = current_time('Y-m-d');
$pvm = gmdate('Y-m-d', strtotime($tanaan . ' -10 days'));
$wpdb->insert(jasrek_t('laskut'), array(
    'nimi' => 'Jäsenmaksu ' . ($vuosi + 1), 'summa' => 0, 'laskupvm' => $pvm,
    'erapaiva' => gmdate('Y-m-d', strtotime($tanaan . ' +21 days')), 'viesti' => $s['viesti_oletus'],
    'hinnat' => wp_json_encode($hinnat), 'postimaksu' => 2.5, 'auto_muistutus' => 0, 'luotu' => $pvm . ' 18:00:00',
));
$lid = (int) $wpdb->insert_id;
$skip = 0;
jasrek_add_rows($lid, $wpdb->get_results("SELECT * FROM $jt WHERE tila = 'aktiivinen'"), 0, $hinnat, $skip, null, 2.5);
$t = jasrek_t('rivit');
$wpdb->query($wpdb->prepare("UPDATE $t SET lahetetty = %s WHERE lasku_id = %d AND jasen_id IN (SELECT id FROM $jt WHERE paperilasku = 0 AND email <> '')", $pvm . ' 18:05:00', $lid));
foreach (array('Anna' => 9, 'Kalle' => 8, 'Pekka' => 6, 'Reijo' => 3) as $n => $d) {
    $wpdb->query($wpdb->prepare("UPDATE $t SET maksettu = %s, maksettu_summa = summa WHERE lasku_id = %d AND jasen_id = %d", gmdate('Y-m-d', strtotime($tanaan . " -$d days")), $lid, $ids[$n]));
}
