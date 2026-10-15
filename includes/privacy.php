<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own custom tables (members, invoices, audit log). The data is admin-only and changes on every write, so object caching is not used.

/*
 * Tietosuoja (GDPR):
 *  - WordPressin työkalut "Vie henkilötiedot" ja "Poista henkilötiedot" tunnistavat jäsenet sähköpostiosoitteella
 *  - ehdotus sivuston tietosuojaselosteeseen (Asetukset → Tietosuoja → opas)
 *  - jäsenrekisterin tietosuojaselosteen pohja yhdistykselle (Tietoja-sivu)
 */

/* ------------------------------------------------------------------
 * Henkilötietojen vienti
 * ------------------------------------------------------------------ */

add_filter('wp_privacy_personal_data_exporters', function ($exporters) {
    $exporters['oh6kw-jasenrekisteri'] = array(
        'exporter_friendly_name' => 'OH6KW Jäsenrekisteri',
        'callback'               => 'jasrek_privacy_export',
    );
    return $exporters;
});

/** Jäsenet, joiden sähköpostiosoite on annettu. */
function jasrek_privacy_members($email) {
    global $wpdb;
    $email = sanitize_email($email);
    if ($email === '') return array();
    return (array) $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->jasrek_jasenet} WHERE email = %s ORDER BY id", $email));
}

function jasrek_privacy_export($email, $page = 1) {
    global $wpdb;
    $items = array();
    $members = jasrek_privacy_members($email);
    $ids = array_map(function ($m) { return (int) $m->id; }, $members);
    foreach ($members as $m) {
        $data = array();
        foreach (jasrek_member_snapshot($m) as $name => $value) {
            if ((string) $value !== '') $data[] = array('name' => $name, 'value' => $value);
        }
        $items[] = array('group_id' => 'jasrek-jasen', 'group_label' => 'Jäsenrekisteri: jäsentiedot', 'item_id' => 'jasrek-jasen-' . (int) $m->id, 'data' => $data);
    }
    // Laskurivit: jäsenen omat sekä rivit, joille sama osoite on tallentunut (esim. poistettu jäsen)
    $sel = "SELECT r.*, l.nimi AS lasku_nimi FROM {$wpdb->jasrek_rivit} r LEFT JOIN {$wpdb->jasrek_laskut} l ON l.id = r.lasku_id";
    $rows = array();
    foreach ((array) $wpdb->get_results($wpdb->prepare("$sel WHERE r.email = %s", sanitize_email($email))) as $r) $rows[(int) $r->id] = $r; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $sel contains only the plugin's own table names.
    if ($ids) {
        foreach ((array) $wpdb->get_results($wpdb->prepare("$sel WHERE r.jasen_id IN (" . implode(',', array_fill(0, count($ids), '%d')) . ')', $ids)) as $r) $rows[(int) $r->id] = $r; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $sel contains only the plugin's own table names; IN list is placeholders.
    }
    ksort($rows);
    foreach ($rows as $r) {
        $data = array(
            array('name' => 'Lasku', 'value' => (string) $r->lasku_nimi),
            array('name' => 'Nimi laskulla', 'value' => (string) $r->nimi),
            array('name' => 'Viitenumero', 'value' => jasrek_viite_format($r->viite)),
            array('name' => 'Summa', 'value' => html_entity_decode(jasrek_money($r->summa))),
        );
        if ($r->erapaiva) $data[] = array('name' => 'Eräpäivä', 'value' => jasrek_date($r->erapaiva));
        if ($r->lahetetty) $data[] = array('name' => 'Lähetetty', 'value' => jasrek_date(substr($r->lahetetty, 0, 10)));
        if ($r->maksettu) $data[] = array('name' => 'Maksettu', 'value' => jasrek_date($r->maksettu));
        if ($r->maksaja) $data[] = array('name' => 'Maksaja', 'value' => (string) $r->maksaja);
        $items[] = array('group_id' => 'jasrek-maksut', 'group_label' => 'Jäsenrekisteri: jäsenmaksut', 'item_id' => 'jasrek-rivi-' . (int) $r->id, 'data' => $data);
    }
    return array('data' => $items, 'done' => true);
}

/* ------------------------------------------------------------------
 * Henkilötietojen poisto
 * ------------------------------------------------------------------ */

add_filter('wp_privacy_personal_data_erasers', function ($erasers) {
    $erasers['oh6kw-jasenrekisteri'] = array(
        'eraser_friendly_name' => 'OH6KW Jäsenrekisteri',
        'callback'             => 'jasrek_privacy_erase',
    );
    return $erasers;
});

/**
 * Poistaa jäsenen henkilötiedot. Jäsenrivi ja laskurivit jäävät nimettöminä, jotta jäsenmaksujen summat,
 * päivät ja vuosiraportin luvut säilyvät (kirjanpito ja tilastot). Muutoslokin merkinnät jäsenestä poistetaan
 * ja nimi korvataan muista merkinnöistä.
 */
function jasrek_privacy_erase($email, $page = 1) {
    global $wpdb;
    $email = sanitize_email($email);
    $members = jasrek_privacy_members($email);
    $removed = false;
    foreach ($members as $m) {
        jasrek_privacy_anonymize_member($m);
        $removed = true;
    }
    if ($email !== '') {
        $n = (int) $wpdb->query($wpdb->prepare("UPDATE {$wpdb->jasrek_rivit} SET nimi = 'Poistettu jäsen', email = '', maksaja = NULL WHERE email = %s", $email));
        if ($n) $removed = true;
    }
    $messages = $removed
        ? array('Jäsenrekisteri: jäsenen henkilötiedot poistettiin. Jäsenmaksujen summat ja päivät säilyvät nimettöminä kirjanpitoa ja tilastoja varten.')
        : array();
    return array('items_removed' => $removed, 'items_retained' => false, 'messages' => $messages, 'done' => true);
}

function jasrek_privacy_anonymize_member($m) {
    global $wpdb;
    $id = (int) $m->id;
    $nimi = trim($m->etunimi . ' ' . $m->sukunimi);
    $wpdb->update($wpdb->jasrek_jasenet, array(
        'etunimi' => 'Poistettu', 'sukunimi' => 'jäsen #' . $id, 'tunnus' => '', 'osoite' => '', 'puhelin' => '', 'email' => '',
        'syntynyt' => null, 'lisatiedot' => '', 'lisakentat' => null, 'paperilasku' => 0,
        'tila' => 'eronnut', 'eronnut_pvm' => $m->eronnut_pvm ? $m->eronnut_pvm : current_time('Y-m-d'),
    ), array('id' => $id));
    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->jasrek_rivit} SET nimi = 'Poistettu jäsen', email = '', maksaja = NULL WHERE jasen_id = %d", $id));
    // Muutosloki: jäsenen omat merkinnät pois, nimi muista merkinnöistä (myös JSON-muodossa tallennetuista)
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->jasrek_loki} WHERE kohde IN ('jasen', 'maksu') AND kohde_id = %d", $id));
    if ($nimi !== '') {
        foreach (array_unique(array($nimi, trim($m->sukunimi . ' ' . $m->etunimi), substr(wp_json_encode($nimi), 1, -1))) as $needle) {
            $like = '%' . $wpdb->esc_like($needle) . '%';
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->jasrek_loki} SET kuvaus = REPLACE(kuvaus, %s, '[poistettu]'), muutokset = REPLACE(muutokset, %s, '[poistettu]') WHERE kuvaus LIKE %s OR muutokset LIKE %s",
                $needle, $needle, $like, $like
            ));
        }
    }
    jasrek_log('jasen', 0, 'poistettu', 'Jäsenen #' . $id . ' henkilötiedot poistettu (WordPressin henkilötietojen poistopyyntö)');
}

/* ------------------------------------------------------------------
 * Ehdotus sivuston tietosuojaselosteeseen
 * ------------------------------------------------------------------ */

add_action('admin_init', function () {
    if (!function_exists('wp_add_privacy_policy_content')) return;
    $txt = '<p>Tämä sivusto käyttää OH6KW Jäsenrekisteri -lisäosaa yhdistyksen jäsenrekisterin ja jäsenmaksulaskutuksen hoitamiseen. '
        . 'Lisäosa ei kerää tietoja sivuston kävijöistä, eikä se käytä evästeitä tai ulkopuolisia palveluja.</p>'
        . '<p>Jäsenrekisteriin tallennetaan yhdistyksen jäsenten tiedot (esim. nimi, osoite, puhelinnumero, sähköpostiosoite, liittymispäivä, jäsenluokka ja jäsenmaksujen tiedot). '
        . 'Tiedot ovat vain sivuston ylläpitäjien nähtävissä WordPressin hallintapaneelissa. Laskut ja tiedotteet lähetetään jäsenille sivuston sähköpostipalvelun kautta.</p>'
        . '<p>Jäsenrekisterin tietosuojaseloste: [lisää linkki yhdistyksen jäsenrekisterin tietosuojaselosteeseen]. Pohjan siihen saa lisäosan Tietoja-sivulta.</p>';
    wp_add_privacy_policy_content('OH6KW Jäsenrekisteri', wp_kses_post($txt));
});

/* ------------------------------------------------------------------
 * Jäsenrekisterin tietosuojaselosteen pohja
 * ------------------------------------------------------------------ */

function jasrek_privacy_template_url() {
    return wp_nonce_url(admin_url('admin-post.php?action=jasrek_tietosuojaseloste'), 'jasrek_tietosuojaseloste');
}

add_action('admin_post_jasrek_tietosuojaseloste', 'jasrek_handle_privacy_template');
function jasrek_handle_privacy_template() {
    jasrek_check('jasrek_tietosuojaseloste');
    $s = jasrek_settings();
    $t = '[täydennä]';
    $yht = trim((string) $s['yhteystiedot']);
    $osoite = trim((string) $s['postiosoite']);
    $kk = (int) $s['loki_kk'];
    $tiedot = array('etu- ja sukunimi', 'postiosoite', 'puhelinnumero', 'sähköpostiosoite', 'liittymispäivä', 'jäsenluokka ja jäsenmaksu', 'jäsenyyden tila ja eroamispäivä', 'tieto siitä, haluaako jäsen laskun paperilla');
    if (jasrek_tunnus_on()) $tiedot[] = jasrek_lower(jasrek_tunnus_nimi());
    if (jasrek_merkki_on()) $tiedot[] = 'syntymävuosi tai -päivä (vapaaehtoinen, merkkipäiviä varten)';
    foreach (jasrek_lisakentat() as $k) $tiedot[] = jasrek_lower($k['nimi']);
    $tiedot[] = 'vapaamuotoiset lisätiedot';
    $tiedot[] = 'jäsenmaksulaskut: viitenumero, summa, eräpäivä, lähetys- ja muistutuspäivät, maksupäivä, maksettu summa ja maksajan nimi';
    $tiedot[] = 'muutosloki: kuka ylläpitäjä on muuttanut tietoja ja milloin (vanhat ja uudet arvot)';

    nocache_headers();
    header('Content-Type: text/html; charset=UTF-8');
    jasrek_log('vienti', 0, 'vienti', 'Tietosuojaselosteen pohja avattu');
    ?>
<!DOCTYPE html>
<html lang="fi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html('Tietosuojaseloste – ' . $s['yhdistys']); ?></title>
<?php wp_print_styles('jasrek-document'); ?>
</head>
<body>
<div class="tools">
  <button type="button" data-jasrek-print>🖨 Tulosta</button>
  <button type="button" data-jasrek-copy="#seloste">📋 Kopioi teksti</button>
  <a href="<?php echo esc_url(admin_url('admin.php?page=jasrek-tietoja')); ?>">← Takaisin</a>
  <span class="ohje">Tämä on <strong>pohja</strong>, ei oikeudellinen neuvo. Tarkista teksti ja täydennä <mark>[täydennä]</mark>-kohdat yhdistyksen hallituksessa. Voit muokata tekstiä suoraan tällä sivulla ennen tulostusta tai kopiointia.</span>
</div>
<div class="paperi" id="seloste" contenteditable="true" spellcheck="false">
  <h1>Tietosuojaseloste – jäsenrekisteri</h1>
  <p class="ala">EU:n yleinen tietosuoja-asetus (2016/679), artiklat 13 ja 14 · Laadittu <?php echo esc_html(jasrek_date(current_time('Y-m-d'))); ?></p>

  <h2>1. Rekisterinpitäjä</h2>
  <p><?php echo esc_html($s['yhdistys']); ?><?php echo $s['ytunnus'] !== '' ? esc_html(' (Y-tunnus ' . $s['ytunnus'] . ')') : ''; ?><br>
     <?php echo $osoite !== '' ? nl2br(esc_html($osoite)) : '<mark>' . esc_html($t) . ' postiosoite</mark>'; ?><br>
     <?php echo $yht !== '' ? nl2br(esc_html($yht)) : '<mark>' . esc_html($t) . ' yhteystiedot</mark>'; ?></p>

  <h2>2. Yhteyshenkilö rekisteriä koskevissa asioissa</h2>
  <p><mark><?php echo esc_html($t); ?> nimi, tehtävä (esim. jäsenrekisterin hoitaja tai rahastonhoitaja)</mark>, <?php echo esc_html($s['vastaus_email'] !== '' ? $s['vastaus_email'] : ($s['lahettaja_email'] !== '' ? $s['lahettaja_email'] : $t . ' sähköposti')); ?></p>

  <h2>3. Rekisterin nimi</h2>
  <p><?php echo esc_html($s['yhdistys']); ?>:n jäsenrekisteri</p>

  <h2>4. Henkilötietojen käsittelyn tarkoitus ja oikeusperuste</h2>
  <p>Tietoja käsitellään yhdistyksen jäsenyyden hoitamiseen: jäsenluettelon ylläpitoon, jäsenmaksujen laskutukseen ja seurantaan, jäsentiedotteiden lähettämiseen, kokousten äänioikeuden tarkistamiseen ja yhdistyksen toiminnan tilastointiin<?php echo jasrek_merkki_on() ? ' sekä jäsenten merkkipäivien muistamiseen' : ''; ?>.</p>
  <ul>
    <li>Yhdistyslain (503/1989) 11 §:n mukainen velvollisuus pitää luetteloa yhdistyksen jäsenistä (tietosuoja-asetuksen 6 art. 1 c, lakisääteinen velvoite).</li>
    <li>Jäsenyyteen perustuva suhde ja yhdistyksen oikeutettu etu hoitaa jäsenyyttä ja jäsenmaksuja (6 art. 1 b ja f).</li>
    <?php if (jasrek_merkki_on()): ?><li>Syntymävuosi tai -päivä tallennetaan vain, jos jäsen itse antaa sen (suostumus, 6 art. 1 a). Suostumuksen voi perua milloin tahansa.</li><?php endif; ?>
  </ul>

  <h2>5. Rekisterin tietosisältö</h2>
  <ul><?php foreach ($tiedot as $x): ?><li><?php echo esc_html($x); ?></li><?php endforeach; ?></ul>

  <h2>6. Säännönmukaiset tietolähteet</h2>
  <p>Tiedot saadaan jäseneltä itseltään liittymisen yhteydessä ja jäsenyyden aikana. Maksutiedot saadaan yhdistyksen pankkitilin tiliotteelta tai viitesiirtoaineistosta.</p>

  <h2>7. Tietojen luovutukset ja käsittelijät</h2>
  <p>Tietoja ei luovuteta yhdistyksen ulkopuolelle eikä käytetä suoramarkkinointiin, ellei laki sitä edellytä (esim. viranomaisille).</p>
  <p>Rekisteri sijaitsee yhdistyksen verkkosivuston tietokannassa. Henkilötietojen käsittelijöinä toimivat sivuston palveluntarjoaja <mark><?php echo esc_html($t); ?> webhotellin nimi</mark> ja sähköpostipalvelu <mark><?php echo esc_html($t); ?> sähköpostipalvelun nimi</mark>, joiden kautta laskut ja tiedotteet lähetetään.</p>

  <h2>8. Tietojen siirto EU:n tai ETA:n ulkopuolelle</h2>
  <p>Tietoja ei siirretä EU:n tai ETA:n ulkopuolelle. <mark><?php echo esc_html($t); ?> tarkista palveluntarjoajien sijainti</mark></p>

  <h2>9. Tietojen säilytysaika</h2>
  <ul>
    <li>Jäsenen tiedot säilytetään jäsenyyden ajan. Eronneen jäsenen tiedot poistetaan <mark><?php echo esc_html($t); ?> esim. vuoden kuluessa eroamisesta</mark>, ellei niitä tarvita jäsenmaksujen tai kirjanpidon selvittämiseen.</li>
    <li>Jäsenmaksujen tiedot säilytetään kirjanpitolain mukaisesti kuusi vuotta tilikauden päättymisestä.</li>
    <li>Muutosloki säilytetään <?php echo esc_html($kk > 0 ? $kk . ' kuukautta' : 'toistaiseksi'); ?>, minkä jälkeen merkinnät poistetaan automaattisesti.</li>
  </ul>

  <h2>10. Rekisterin suojaus</h2>
  <p>Rekisteriä käsitellään vain yhdistyksen verkkosivuston hallintapaneelissa. Tietoihin pääsevät vain sivuston ylläpitäjät henkilökohtaisilla tunnuksilla. Yhteys sivustoon on salattu (HTTPS). Rekisterin tietojen muutokset kirjataan muutoslokiin. Varmuuskopiot säilytetään suojattuina. <mark><?php echo esc_html($t); ?> keillä on pääsy (esim. hallituksen puheenjohtaja ja rahastonhoitaja)</mark></p>

  <h2>11. Rekisteröidyn oikeudet</h2>
  <p>Jäsenellä on oikeus tarkastaa itseään koskevat tiedot, vaatia virheellisten tietojen oikaisua, pyytää tietojensa poistamista (jos niitä ei tarvita lakisääteisiin velvoitteisiin), rajoittaa tai vastustaa käsittelyä sekä saada tietonsa siirrettävässä muodossa. Pyynnöt lähetetään kohdassa 2 mainitulle yhteyshenkilölle.</p>
  <p>Jos jäsen katsoo, että hänen henkilötietojaan käsitellään tietosuojalainsäädännön vastaisesti, hänellä on oikeus tehdä valitus tietosuojavaltuutetulle (tietosuoja.fi).</p>
</div>
<?php wp_print_scripts('jasrek-print-page'); ?>
</body></html>
    <?php
    exit;
}

/** Tietoja-sivun paneeli. */
function jasrek_privacy_panel() {
    ?>
    <div class="jasrek-panel" id="tietosuoja">
        <h2>Tietosuoja (GDPR)</h2>
        <p>Yhdistyksen on kerrottava jäsenille, miten jäsenrekisterin tietoja käsitellään (tietosuoja-asetuksen 13 artikla). Tältä sivulta saat yhdistyksen tiedoilla esitäytetyn tietosuojaselosteen pohjan.
            Siinä on mukana myös käytössä olevat lisäkentät ja säilytysajat. Pohjan voi muokata, tulostaa tai kopioida esimerkiksi yhdistyksen verkkosivuille.</p>
        <p><a class="button button-primary" href="<?php echo esc_url(jasrek_privacy_template_url()); ?>" target="_blank" rel="noopener">📄 Jäsenrekisterin tietosuojaselosteen pohja</a></p>
        <p class="description">Pohja on malli eikä oikeudellinen neuvo. Yhdistyksen hallitus vastaa selosteen sisällöstä.
            Jäsenen tiedot voi viedä tai poistaa myös WordPressin työkaluilla (<a href="<?php echo esc_url(admin_url('export-personal-data.php')); ?>">Työkalut → Vie henkilötiedot</a> ja
            <a href="<?php echo esc_url(admin_url('erase-personal-data.php')); ?>">Poista henkilötiedot</a>), jotka tunnistavat jäsenen sähköpostiosoitteella.
            Poisto nimettömöi jäsenen: jäsenmaksujen summat ja päivät jäävät kirjanpitoa ja tilastoja varten.</p>
    </div>
    <?php
}
