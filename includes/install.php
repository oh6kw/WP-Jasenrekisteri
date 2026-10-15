<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own custom tables (members, invoices, audit log). The data is admin-only and changes on every write, so object caching is not used.

/**
 * Luo / päivittää tietokantataulut. dbDelta lisää puuttuvat sarakkeet olemassa
 * olevaan jäsentauluun koskematta tietoihin.
 */
function jasrek_install() {
    jasrek_migrate_prefix();
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $jasenet = jasrek_t('jasenet');
    $laskut  = jasrek_t('laskut');
    $rivit   = jasrek_t('rivit');

    $sql = array();

    $sql[] = "CREATE TABLE {$wpdb->jasrek_jasenet} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        etunimi varchar(50) NOT NULL,
        sukunimi varchar(50) NOT NULL,
        tunnus varchar(20),
        osoite varchar(255),
        puhelin varchar(20),
        email varchar(100),
        liittynyt date DEFAULT NULL,
        syntynyt varchar(10) DEFAULT NULL,
        tila varchar(20) NOT NULL DEFAULT 'aktiivinen',
        jasenlaji varchar(30) NOT NULL DEFAULT '',
        lisatiedot text,
        paperilasku tinyint(1) NOT NULL DEFAULT 0,
        lisakentat text,
        eronnut_pvm date DEFAULT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE {$wpdb->jasrek_laskut} (
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

    $sql[] = "CREATE TABLE {$wpdb->jasrek_rivit} (
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

    $loki = jasrek_t('loki');
    $sql[] = "CREATE TABLE {$wpdb->jasrek_loki} (
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
    update_option('jasrek_db_version', JASREK_DB_VERSION);
}

/**
 * Versio 4.6.2 vaihtoi lisäosan etuliitteen (jr_ → jasrek_) WordPress.orgin vaatimusten mukaiseksi.
 * Siirretään asetukset ja tietokantaversio vanhoilta nimiltä ja poistetaan vanha ajastus.
 */
function jasrek_migrate_prefix() {
    $vanhat = get_option('jr_settings', null);
    if ($vanhat !== null) {
        if (get_option('jasrek_settings', null) === null) update_option('jasrek_settings', $vanhat);
        delete_option('jr_settings');
    }
    $versio = get_option('jr_db_version', null);
    if ($versio !== null) {
        if (get_option('jasrek_db_version', null) === null) update_option('jasrek_db_version', $versio);
        delete_option('jr_db_version');
    }
    if (wp_next_scheduled('jr_auto_muistutus')) wp_clear_scheduled_hook('jr_auto_muistutus');
}

/** Ajetaan joka latauksella: päivittää taulut, jos plugin on päivitetty ilman uudelleenaktivointia. */
function jasrek_maybe_upgrade() {
    jasrek_migrate_prefix();
    $old = get_option('jasrek_db_version');
    if ($old !== JASREK_DB_VERSION) {
        jasrek_install();
        if ($old !== false) jasrek_migrate((int) $old);
    }
}

/** Aiempien versioiden tietojen päivitys. */
function jasrek_migrate($old) {
    global $wpdb;
    if ($old < 4) {
        // Jo lähetetyt muistutukset lasketaan yhdeksi.
        $wpdb->query("UPDATE {$wpdb->jasrek_rivit} SET muistutuksia = 1 WHERE muistutettu IS NOT NULL AND muistutuksia = 0");

        $s = get_option('jasrek_settings', array());
        if (!is_array($s)) $s = array();
        // Säilytetään aiempi toiminta: kenttä "Tunnus" isoilla kirjaimilla, jos sitä ei ole vielä asetettu.
        if (!isset($s['tunnus_nimi'])) {
            $s['tunnus_kaytossa'] = '1';
            $s['tunnus_nimi']     = 'Tunnus';
            $s['tunnus_isot']     = '1';
        }
        update_option('jasrek_settings', $s);
    }
    if ($old < 6) {
        // Erillinen "maksuvapaa"-tila poistuu: maksuvapaus määräytyy nyt jäsenluokasta (jäsenmaksu 0 €).
        $jt = jasrek_t('jasenet');
        $n = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->jasrek_jasenet} WHERE tila = 'maksuvapaa'");
        if ($n > 0) {
            $s = get_option('jasrek_settings', array());
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
                update_option('jasrek_settings', $s);
            }
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->jasrek_jasenet} SET tila = 'aktiivinen', jasenlaji = %s WHERE tila = 'maksuvapaa'", $id));
        }
    }
    if ($old < 8) {
        // Laskuriveille talteen jäsenluokka laskutushetkellä (vuosiraporttia varten); vanhoille riveille nykyinen luokka.
        $rt = jasrek_t('rivit'); $jt = jasrek_t('jasenet');
        $wpdb->query("UPDATE {$wpdb->jasrek_rivit} SET jasenlaji = COALESCE((SELECT m.jasenlaji FROM {$wpdb->jasrek_jasenet} m WHERE m.id = {$wpdb->jasrek_rivit}.jasen_id), '') WHERE jasenlaji = ''");
    }
    if ($old < 10) {
        // Kokeiluversion "Syntymäaika"-lisäkenttä siirtyy jäsenen omaan Syntynyt-kenttään.
        $s = get_option('jasrek_settings', array());
        $kentat = is_array($s) && isset($s['lisakentat']) && is_array($s['lisakentat']) ? $s['lisakentat'] : array();
        $siirretyt = array();
        foreach ($kentat as $kid => $k) {
            if (!isset($k['tyyppi']) || $k['tyyppi'] !== 'syntyma') continue;
            foreach ($wpdb->get_results("SELECT id, lisakentat FROM {$wpdb->jasrek_jasenet} WHERE syntynyt IS NULL AND lisakentat IS NOT NULL") as $m) {
                $lisa = json_decode((string) $m->lisakentat, true);
                if (is_array($lisa) && isset($lisa[$kid]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $lisa[$kid])) {
                    $wpdb->update($wpdb->jasrek_jasenet, array('syntynyt' => $lisa[$kid]), array('id' => (int) $m->id));
                }
            }
            $siirretyt[] = $k['nimi'];
            unset($kentat[$kid]);
        }
        if ($siirretyt) {
            $s['lisakentat'] = $kentat;
            update_option('jasrek_settings', $s);
            jasrek_log('asetukset', 0, 'asetukset', 'Lisäkenttä ' . implode(', ', $siirretyt) . ' siirretty jäsenen Syntynyt-kenttään');
        }
    }
}
