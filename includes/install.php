<?php
if (!defined('ABSPATH')) exit;

/**
 * Luo / päivittää tietokantataulut. dbDelta lisää puuttuvat sarakkeet olemassa
 * olevaan jäsentauluun koskematta tietoihin.
 */
function jr_install() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $jasenet = jr_t('jasenet');
    $laskut  = jr_t('laskut');
    $rivit   = jr_t('rivit');

    $sql = array();

    $sql[] = "CREATE TABLE $jasenet (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        etunimi varchar(50) NOT NULL,
        sukunimi varchar(50) NOT NULL,
        tunnus varchar(20),
        osoite varchar(255),
        puhelin varchar(20),
        email varchar(100),
        liittynyt date DEFAULT NULL,
        tila varchar(20) NOT NULL DEFAULT 'aktiivinen',
        jasenlaji varchar(30) NOT NULL DEFAULT '',
        lisatiedot text,
        paperilasku tinyint(1) NOT NULL DEFAULT 0,
        lisakentat text,
        eronnut_pvm date DEFAULT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE $laskut (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        nimi varchar(200) NOT NULL,
        summa decimal(10,2) NOT NULL DEFAULT '0.00',
        laskupvm date NOT NULL,
        erapaiva date NOT NULL,
        viesti text,
        hinnat text,
        postimaksu decimal(10,2) NOT NULL DEFAULT '0.00',
        auto_muistutus tinyint(1) NOT NULL DEFAULT 1,
        luotu datetime NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE $rivit (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        lasku_id mediumint(9) NOT NULL,
        jasen_id mediumint(9) NOT NULL,
        nimi varchar(120) NOT NULL DEFAULT '',
        email varchar(100) NOT NULL DEFAULT '',
        viite varchar(25) NOT NULL,
        summa decimal(10,2) NOT NULL DEFAULT '0.00',
        jasenlaji varchar(30) NOT NULL DEFAULT '',
        postimaksu decimal(10,2) NOT NULL DEFAULT '0.00',
        erapaiva date DEFAULT NULL,
        lahetetty datetime DEFAULT NULL,
        muistutettu datetime DEFAULT NULL,
        muistutuksia smallint(5) unsigned NOT NULL DEFAULT 0,
        viim_yritys datetime DEFAULT NULL,
        virhe varchar(255) DEFAULT NULL,
        maksettu date DEFAULT NULL,
        maksettu_summa decimal(10,2) DEFAULT NULL,
        maksaja varchar(100) DEFAULT NULL,
        arkistotunnus varchar(40) DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY viite (viite),
        KEY arkistotunnus (arkistotunnus),
        KEY lasku_id (lasku_id),
        KEY jasen_id (jasen_id)
    ) $charset_collate;";

    $loki = jr_t('loki');
    $sql[] = "CREATE TABLE $loki (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        aika datetime NOT NULL,
        kayttaja_id bigint(20) unsigned NOT NULL DEFAULT 0,
        kayttaja varchar(100) NOT NULL DEFAULT '',
        kohde varchar(20) NOT NULL DEFAULT '',
        kohde_id bigint(20) unsigned NOT NULL DEFAULT 0,
        toiminto varchar(40) NOT NULL DEFAULT '',
        kuvaus text,
        muutokset longtext,
        PRIMARY KEY  (id),
        KEY aika (aika),
        KEY kohde (kohde,kohde_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    foreach ($sql as $q) {
        dbDelta($q);
    }
    update_option('jr_db_version', JR_DB_VERSION);
}

/** Ajetaan joka latauksella: päivittää taulut, jos plugin on päivitetty ilman uudelleenaktivointia. */
function jr_maybe_upgrade() {
    $old = get_option('jr_db_version');
    if ($old !== JR_DB_VERSION) {
        jr_install();
        if ($old !== false) jr_migrate((int) $old);
    }
}

/** Aiempien versioiden tietojen päivitys. */
function jr_migrate($old) {
    global $wpdb;
    if ($old < 4) {
        // Jo lähetetyt muistutukset lasketaan yhdeksi.
        $wpdb->query('UPDATE ' . jr_t('rivit') . ' SET muistutuksia = 1 WHERE muistutettu IS NOT NULL AND muistutuksia = 0');

        $s = get_option('jr_settings', array());
        if (!is_array($s)) $s = array();
        // Säilytetään aiempi toiminta: kenttä "Tunnus" isoilla kirjaimilla, jos sitä ei ole vielä asetettu.
        if (!isset($s['tunnus_nimi'])) {
            $s['tunnus_kaytossa'] = '1';
            $s['tunnus_nimi']     = 'Tunnus';
            $s['tunnus_isot']     = '1';
        }
        update_option('jr_settings', $s);
    }
    if ($old < 6) {
        // Erillinen "maksuvapaa"-tila poistuu: maksuvapaus määräytyy nyt jäsenluokasta (jäsenmaksu 0 €).
        $jt = jr_t('jasenet');
        $n = (int) $wpdb->get_var("SELECT COUNT(*) FROM $jt WHERE tila = 'maksuvapaa'");
        if ($n > 0) {
            $s = get_option('jr_settings', array());
            if (!is_array($s)) $s = array();
            $lajit = isset($s['jasenlajit']) && is_array($s['jasenlajit']) ? $s['jasenlajit'] : array();
            $id = '';
            foreach ($lajit as $lid => $l) {
                if ((float) $l['summa'] <= 0) { $id = $lid; break; }
            }
            if ($id === '') {
                $id = 'kunniajasen';
                $lajit[$id] = array('nimi' => 'Kunniajäsen', 'summa' => 0);
                $s['jasenlajit'] = $lajit;
                update_option('jr_settings', $s);
            }
            $wpdb->query($wpdb->prepare("UPDATE $jt SET tila = 'aktiivinen', jasenlaji = %s WHERE tila = 'maksuvapaa'", $id));
        }
    }
    if ($old < 8) {
        // Laskuriveille talteen jäsenluokka laskutushetkellä (vuosiraporttia varten); vanhoille riveille nykyinen luokka.
        $rt = jr_t('rivit'); $jt = jr_t('jasenet');
        $wpdb->query("UPDATE $rt SET jasenlaji = COALESCE((SELECT m.jasenlaji FROM $jt m WHERE m.id = $rt.jasen_id), '') WHERE jasenlaji = ''");
    }
}
