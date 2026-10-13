<?php
if (!defined('ABSPATH')) exit;

/* ------------------------------------------------------------------
 * Käsittelijät (admin-post.php)
 * ------------------------------------------------------------------ */

add_action('admin_post_jr_save_member', 'jr_handle_save_member');
function jr_handle_save_member() {
    jr_check('jr_save_member');
    global $wpdb;

    $id        = intval(jr_post('member_id', 0));
    $raw_email = trim(jr_post('email'));
    $email     = sanitize_email($raw_email);
    $tila      = jr_post('tila', 'aktiivinen');
    $tunnus    = sanitize_text_field(jr_post('tunnus'));
    if (jr_settings()['tunnus_isot'] === '1') $tunnus = function_exists('mb_strtoupper') ? mb_strtoupper($tunnus) : strtoupper($tunnus);

    $data = array(
        'etunimi'    => sanitize_text_field(jr_post('etunimi')),
        'sukunimi'   => sanitize_text_field(jr_post('sukunimi')),
        'tunnus'     => $tunnus,
        'osoite'     => sanitize_text_field(jr_post('osoite')),
        'puhelin'    => sanitize_text_field(jr_post('puhelin')),
        'email'      => $email,
        'liittynyt'  => jr_date_or_null(jr_post('liittynyt')),
        'tila'       => array_key_exists($tila, jr_tilat()) ? $tila : 'aktiivinen',
        'lisatiedot' => sanitize_textarea_field(jr_post('lisatiedot')),
        'jasenlaji'  => array_key_exists(jr_post('jasenlaji'), jr_jasenlajit()) ? jr_post('jasenlaji') : '',
        'paperilasku'=> jr_post('paperilasku') === '1' ? 1 : 0,
    );

    // Omat lisäkentät
    $lisa = array();
    $posted = isset($_POST['lisa']) && is_array($_POST['lisa']) ? wp_unslash($_POST['lisa']) : array();
    foreach (jr_lisakentat() as $kid => $k) {
        $val = jr_lisa_parse($k, isset($posted[$kid]) ? $posted[$kid] : '');
        if ($val !== '') $lisa[$kid] = $val;
    }
    $data['lisakentat'] = $lisa ? wp_json_encode($lisa) : null;

    $old = $id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . jr_t('jasenet') . ' WHERE id = %d', $id)) : null;
    if ($id && !$old) jr_redirect('jasenrekisteri');

    // Eroamispäivä: annettu päivä, aiempi päivä tai tämä päivä, kun jäsen merkitään eronneeksi
    if ($data['tila'] === 'eronnut') {
        $ero = jr_date_or_null(jr_post('eronnut_pvm'));
        $data['eronnut_pvm'] = $ero ? $ero : (($old && $old->eronnut_pvm) ? $old->eronnut_pvm : current_time('Y-m-d'));
    } else {
        $data['eronnut_pvm'] = null;
    }

    if ($data['etunimi'] === '' || $data['sukunimi'] === '') {
        jr_redirect('jasenrekisteri', array('view' => $id ? 'edit' : 'new', 'id' => $id ?: null, 'jr_msg' => 'name_required'));
    }

    // Onko rekisterissä jo toinen jäsen samalla nimellä tai sähköpostilla?
    $dup = (int) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM ' . jr_t('jasenet') . " WHERE id <> %d AND ((etunimi = %s AND sukunimi = %s) OR (email <> '' AND email = %s))",
        $id, $data['etunimi'], $data['sukunimi'], $email
    ));

    if ($id) {
        $wpdb->update(jr_t('jasenet'), $data, array('id' => $id));
        $msg = 'member_updated';
        $diff = jr_log_diff(jr_member_snapshot($old), jr_member_snapshot(array_merge((array) $old, $data)));
        if ($diff) jr_log('jasen', $id, 'muokattu', jr_log_member_name($data), $diff);
    } else {
        $wpdb->insert(jr_t('jasenet'), $data);
        $id  = (int) $wpdb->insert_id;
        $msg = 'member_added';
        jr_log('jasen', $id, 'lisatty', jr_log_member_name($data), jr_log_diff(array(), jr_member_snapshot($data)));
    }
    if ($dup) $msg = 'member_duplicate';
    if ($raw_email !== '' && $email === '') $msg = 'email_invalid';

    // Lisäys jo tehdylle laskulle (jäsen liittyi laskutuksen jälkeen)
    $valitut = array_filter(array_map('intval', (array) (isset($_POST['lisaa_lasku']) ? $_POST['lisaa_lasku'] : array())));
    if ($valitut && $id) {
        $m = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . jr_t('jasenet') . ' WHERE id = %d', $id));
        $summat = isset($_POST['lasku_summa']) ? (array) wp_unslash($_POST['lasku_summa']) : array();
        $erat   = isset($_POST['lasku_era']) ? (array) wp_unslash($_POST['lasku_era']) : array();
        $laheta = jr_post('laheta_heti') === '1';
        $lisatty = 0; $lahetetty = 0; $virhe = 0;
        foreach ($valitut as $lid) {
            $lasku = jr_get_lasku($lid);
            if (!$lasku) continue;
            $summa = isset($summat[$lid]) ? min(999999.99, jr_parse_money($summat[$lid])) : 0;
            if ($summa <= 0) continue;
            $era = isset($erat[$lid]) ? jr_date_or_null($erat[$lid]) : null;
            $rid = jr_add_member_to_lasku($lasku, $m, $summa, $era ? $era : jr_late_era($lasku));
            if (!$rid) continue;
            $lisatty++;
            jr_log('maksu', $id, 'laskulle', $lasku->nimi . ': ' . jr_log_member_name($m) . ', ' . html_entity_decode(jr_money($summa)) . ', eräpäivä ' . jr_date($era ? $era : jr_late_era($lasku)));
            if ($laheta && $m->email && empty($m->paperilasku)) {
                if (jr_send_rivi($lasku, jr_get_rivi($rid))) $lahetetty++; else $virhe++;
            }
        }
        if ($lisatty) {
            jr_redirect('jasenrekisteri', array('view' => 'edit', 'id' => $id, 'jr_msg' => 'member_invoiced',
                'jr_n' => $lisatty, 'jr_s' => $lahetetty, 'jr_e' => $virhe));
        }
    }

    jr_redirect('jasenrekisteri', array('jr_msg' => $msg));
}

add_action('admin_post_jr_delete_member', 'jr_handle_delete_member');
function jr_handle_delete_member() {
    $id = intval(isset($_REQUEST['id']) ? $_REQUEST['id'] : 0);
    jr_check('jr_delete_member_' . $id);
    global $wpdb;
    // Laskurivit säilyvät (nimi ja sähköposti on tallennettu riville), jotta kirjanpito pysyy ehjänä.
    $old = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . jr_t('jasenet') . ' WHERE id = %d', $id));
    $wpdb->delete(jr_t('jasenet'), array('id' => $id));
    if ($old) jr_log('jasen', $id, 'poistettu', jr_log_member_name($old));
    jr_redirect('jasenrekisteri', array('jr_msg' => 'member_deleted'));
}

add_action('admin_post_jr_bulk_members', 'jr_handle_bulk_members');
function jr_handle_bulk_members() {
    jr_check('jr_bulk_members');
    global $wpdb;
    $nayta = sanitize_key(jr_post('nayta', 'jasenet'));
    $ids = array_filter(array_map('intval', (array) (isset($_POST['jasenet']) ? $_POST['jasenet'] : array())));
    if (!$ids) jr_redirect('jasenrekisteri', array('nayta' => $nayta, 'jr_msg' => 'nothing_selected'));
    $op = jr_post('bulk_op');
    $in = implode(',', $ids);
    $t  = jr_t('jasenet');
    $n  = 0;
    $before = $wpdb->get_results("SELECT * FROM $t WHERE id IN ($in)", OBJECT_K);
    if (strpos($op, 'laji:') === 0) {
        $laji = substr($op, 5);
        if (array_key_exists($laji, jr_jasenlajit())) {
            $n = (int) $wpdb->query($wpdb->prepare("UPDATE $t SET jasenlaji = %s WHERE id IN ($in)", $laji));
        }
    } elseif ($op === 'eronnut') {
        $n = (int) $wpdb->query($wpdb->prepare("UPDATE $t SET tila = 'eronnut', eronnut_pvm = %s WHERE tila <> 'eronnut' AND id IN ($in)", current_time('Y-m-d')));
    } elseif ($op === 'aktiivinen') {
        $n = (int) $wpdb->query("UPDATE $t SET tila = 'aktiivinen', eronnut_pvm = NULL WHERE tila = 'eronnut' AND id IN ($in)");
    } elseif ($op === 'paperi_on' || $op === 'paperi_off') {
        $n = (int) $wpdb->query($wpdb->prepare("UPDATE $t SET paperilasku = %d WHERE id IN ($in)", $op === 'paperi_on' ? 1 : 0));
    }
    if ($n) {
        $after = $wpdb->get_results("SELECT * FROM $t WHERE id IN ($in)", OBJECT_K);
        foreach ($after as $mid => $m) {
            if (!isset($before[$mid])) continue;
            $diff = jr_log_diff(jr_member_snapshot($before[$mid]), jr_member_snapshot($m));
            if ($diff) jr_log('jasen', $mid, 'muokattu', jr_log_member_name($m) . ' (joukkomuutos)', $diff);
        }
    }
    jr_redirect('jasenrekisteri', array('nayta' => $nayta, 'jr_msg' => 'members_bulk', 'jr_n' => $n));
}

add_action('admin_post_jr_export_members', 'jr_handle_export_members');
function jr_handle_export_members() {
    jr_check('jr_export_members');
    global $wpdb;
    $rows = $wpdb->get_results('SELECT * FROM ' . jr_t('jasenet') . ' ORDER BY sukunimi, etunimi');
    $kentat = jr_lisakentat();
    $tilat = jr_tilat();
    $out = array();
    foreach ($rows as $m) {
        $row = array($m->id, $m->etunimi, $m->sukunimi, $m->tunnus, $m->osoite, $m->puhelin, $m->email, jr_date($m->liittynyt),
            jr_laji_nimi($m->jasenlaji), isset($tilat[$m->tila]) ? $tilat[$m->tila] : $m->tila, jr_date($m->eronnut_pvm),
            $m->paperilasku ? 'Kyllä' : '', $m->lisatiedot);
        $lisa = jr_member_lisa($m);
        foreach ($kentat as $kid => $k) $row[] = jr_lisa_value($k, isset($lisa[$kid]) ? $lisa[$kid] : '', 'Kyllä');
        $out[] = $row;
    }
    jr_log('vienti', 0, 'vienti', 'Jäsenrekisteri CSV-tiedostoon (' . count($rows) . ' jäsentä)');
    jr_csv_output('jasenrekisteri-' . current_time('Y-m-d') . '.csv',
        array_merge(array('ID', 'Etunimi', 'Sukunimi', jr_tunnus_nimi(), 'Osoite', 'Puhelin', 'Sähköposti', 'Liittynyt', 'Jäsenluokka', 'Jäsenyys', 'Eronnut', 'Paperilasku', 'Lisätiedot'),
            array_values(array_map(function ($k) { return $k['nimi']; }, $kentat))), $out);
}

/* ------------------------------------------------------------------
 * Sivu
 * ------------------------------------------------------------------ */

function jr_page_members() {
    $view = isset($_GET['view']) ? sanitize_key($_GET['view']) : '';
    echo '<div class="wrap jr-wrap">';
    if ($view === 'tuonti') {
        jr_members_import_page();
    } elseif ($view === 'edit' || $view === 'new') {
        jr_member_form($view === 'edit' ? intval(isset($_GET['id']) ? $_GET['id'] : 0) : 0);
    } else {
        jr_member_list();
    }
    echo '</div>';
}

function jr_member_form($id) {
    global $wpdb;
    $m = $id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . jr_t('jasenet') . ' WHERE id = %d', $id)) : null;
    $v = function ($field, $default = '') use ($m) {
        return $m && isset($m->$field) && $m->$field !== null ? $m->$field : $default;
    };
    ?>
    <h1><?php echo $m ? 'Muokkaa jäsentä' : 'Lisää uusi jäsen'; ?></h1>
    <p><a href="<?php echo esc_url(admin_url('admin.php?page=jasenrekisteri')); ?>">&larr; Takaisin jäsenlistaan</a></p>
    <?php jr_notice(); ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="jr-panel jr-form">
        <input type="hidden" name="action" value="jr_save_member">
        <?php wp_nonce_field('jr_save_member'); ?>
        <?php if ($m): ?><input type="hidden" name="member_id" value="<?php echo esc_attr($m->id); ?>"><?php endif; ?>

        <div class="jr-grid">
            <p><label for="jr-etunimi">Etunimi *</label>
               <input type="text" id="jr-etunimi" name="etunimi" value="<?php echo esc_attr($v('etunimi')); ?>" required></p>
            <p><label for="jr-sukunimi">Sukunimi *</label>
               <input type="text" id="jr-sukunimi" name="sukunimi" value="<?php echo esc_attr($v('sukunimi')); ?>" required></p>
            <?php if (jr_tunnus_on()): ?>
            <p><label for="jr-tunnus"><?php echo esc_html(jr_tunnus_nimi()); ?></label>
               <input type="text" id="jr-tunnus" name="tunnus" value="<?php echo esc_attr($v('tunnus')); ?>" maxlength="20" class="<?php echo jr_settings()['tunnus_isot'] === '1' ? 'jr-upper' : ''; ?>"></p>
            <?php else: ?>
               <input type="hidden" name="tunnus" value="<?php echo esc_attr($v('tunnus')); ?>">
            <?php endif; ?>
            <p><label for="jr-email">Sähköposti</label>
               <input type="email" id="jr-email" name="email" value="<?php echo esc_attr($v('email')); ?>"></p>
            <p><label for="jr-puhelin">Puhelin</label>
               <input type="tel" id="jr-puhelin" name="puhelin" value="<?php echo esc_attr($v('puhelin')); ?>"></p>
            <p><label for="jr-osoite">Osoite</label>
               <input type="text" id="jr-osoite" name="osoite" value="<?php echo esc_attr($v('osoite')); ?>"></p>
            <p><label for="jr-liittynyt">Liittynyt</label>
               <input type="date" id="jr-liittynyt" name="liittynyt" value="<?php echo esc_attr($v('liittynyt')); ?>"></p>
            <?php if (jr_jasenlajit()):
                $cur_laji = $v('jasenlaji');
                // Uudelle jäsenelle esivalitaan välilehdellä valittu luokka tai ensimmäinen maksullinen luokka
                if (!$m) {
                    $pre = isset($_GET['laji']) ? sanitize_key($_GET['laji']) : '';
                    if ($pre === '' || !isset(jr_jasenlajit()[$pre])) {
                        foreach (jr_jasenlajit() as $lid => $l) { if ((float) $l['summa'] > 0) { $pre = $lid; break; } }
                    }
                    $cur_laji = $pre;
                } ?>
            <p><label for="jr-laji">Jäsenluokka</label>
               <select id="jr-laji" name="jasenlaji">
                   <option value="">— ei jäsenluokkaa —</option>
                   <?php foreach (jr_jasenlajit() as $lid => $l): ?>
                       <option value="<?php echo esc_attr($lid); ?>" <?php selected($cur_laji, $lid); ?>><?php echo esc_html($l['nimi'] . ' – ' . ((float) $l['summa'] > 0 ? jr_money($l['summa']) : 'ei jäsenmaksua')); ?></option>
                   <?php endforeach; ?>
               </select>
               <span class="description">Määrää jäsenmaksun. Luokat ja hinnat muutetaan <a href="<?php echo esc_url(admin_url('admin.php?page=jr-asetukset#jr-luokat')); ?>">asetuksissa</a>.</span></p>
            <?php else: ?>
               <input type="hidden" name="jasenlaji" value="<?php echo esc_attr($v('jasenlaji')); ?>">
            <?php endif; ?>
            <p><label for="jr-tila">Jäsenyys</label>
               <select id="jr-tila" name="tila">
                   <?php foreach (jr_tilat() as $k => $label): ?>
                       <option value="<?php echo esc_attr($k); ?>" <?php selected($v('tila', 'aktiivinen'), $k); ?>><?php echo esc_html($label); ?></option>
                   <?php endforeach; ?>
               </select>
               <span class="description">Eronneet eivät saa laskuja eivätkä tiedotteita, mutta heidän tietonsa ja maksuhistoriansa säilyvät.</span></p>
            <p class="jr-ero" <?php echo $v('tila', 'aktiivinen') === 'eronnut' ? '' : 'hidden'; ?>><label for="jr-ero">Eroamispäivä</label>
               <input type="date" id="jr-ero" name="eronnut_pvm" value="<?php echo esc_attr($v('eronnut_pvm')); ?>">
               <span class="description">Tyhjänä tallennetaan tämä päivä. Käytetään vuosiraportin jäsenmäärissä.</span></p>
        </div>
        <p><label class="jr-radio"><input type="checkbox" name="paperilasku" value="1" <?php checked((int) $v('paperilasku', 0), 1); ?>>
            <strong>Haluaa laskun paperilla</strong> – laskua ei lähetetä sähköpostilla, vaan se tulostetaan laskun sivulta postitettavaksi.
            Jäsenet, joilla ei ole sähköpostia, saavat laskun paperilla aina.</label></p>
        <?php $kentat = jr_lisakentat(); if ($kentat): $lisa = jr_member_lisa($m); ?>
        <fieldset class="jr-fieldset">
            <legend>Lisäkentät</legend>
            <div class="jr-grid">
            <?php foreach ($kentat as $kid => $k): $val = isset($lisa[$kid]) ? (string) $lisa[$kid] : ''; ?>
                <?php if ($k['tyyppi'] === 'valinta'): ?>
                    <p><label class="jr-radio" style="margin-top:26px"><input type="checkbox" name="lisa[<?php echo esc_attr($kid); ?>]" value="1" <?php checked($val !== ''); ?>> <strong><?php echo esc_html($k['nimi']); ?></strong></label></p>
                <?php else: ?>
                    <p><label for="jr-lisa-<?php echo esc_attr($kid); ?>"><?php echo esc_html($k['nimi']); ?></label>
                       <input type="text" id="jr-lisa-<?php echo esc_attr($kid); ?>" name="lisa[<?php echo esc_attr($kid); ?>]" value="<?php echo esc_attr($val); ?>" maxlength="200"></p>
                <?php endif; ?>
            <?php endforeach; ?>
            </div>
            <p class="description">Lisäkentät määritellään <a href="<?php echo esc_url(admin_url('admin.php?page=jr-asetukset#jr-lisakentat')); ?>">asetuksissa</a>.</p>
        </fieldset>
        <?php endif; ?>
        <p><label for="jr-lisatiedot">Lisätiedot</label>
           <textarea id="jr-lisatiedot" name="lisatiedot" rows="3"><?php echo esc_textarea($v('lisatiedot')); ?></textarea></p>

        <?php
        // Jo tehdyt laskut, joilla jäsen ei ole: esim. jäsen liittyy jäsenmaksulaskujen lähettämisen jälkeen.
        $ehdokkaat = jr_laskut_ilman_jasenta($m ? $m->id : 0, 3);
        if ($ehdokkaat):
            $lajit = jr_jasenlajit(); ?>
        <fieldset class="jr-fieldset" id="jr-laskulle">
            <legend>Jäsenmaksu jo tehdyltä laskulta</legend>
            <p class="description"><?php echo $m ? 'Jäsen ei ole alla olevilla laskuilla.' : 'Jos jäsen liittyy jäsenmaksulaskujen lähettämisen jälkeen, hänet voi lisätä jo tehdylle laskulle.'; ?> Hän saa oman viitenumeron, ja eräpäivä on vähintään kolmen viikon päästä. Summaa voi muuttaa, esim. jos kauden aikana liittyvältä peritään vain osa jäsenmaksusta.</p>
            <?php foreach ($ehdokkaat as $l):
                $hinnat = jr_lasku_hinnat($l);
                $map = array('' => (float) $l->summa);
                foreach ($lajit as $lid => $ll) $map[$lid] = isset($hinnat[$lid]) ? (float) $hinnat[$lid] : (float) $ll['summa'];
                $mm = (object) array('jasenlaji' => (string) $v('jasenlaji', isset($cur_laji) ? $cur_laji : ''));
                $hinta = jr_member_price($mm, $hinnat, $l->summa); ?>
                <div class="jr-laskulle-rivi" data-hinnat="<?php echo esc_attr(wp_json_encode($map)); ?>">
                    <label class="jr-radio"><input type="checkbox" name="lisaa_lasku[]" value="<?php echo esc_attr($l->id); ?>">
                        Lisää laskulle <strong><?php echo esc_html($l->nimi); ?></strong>
                        <span class="jr-muted">(laskutettu <?php echo esc_html(jr_date($l->laskupvm)); ?>, eräpäivä <?php echo esc_html(jr_date($l->erapaiva)); ?>)</span></label>
                    <span class="jr-laskulle-kentat">
                        <label for="jr-ls-<?php echo esc_attr($l->id); ?>">Summa</label>
                        <input type="text" inputmode="decimal" id="jr-ls-<?php echo esc_attr($l->id); ?>" name="lasku_summa[<?php echo esc_attr($l->id); ?>]" value="<?php echo esc_attr(number_format($hinta, 2, ',', '')); ?>"> €
                        <label for="jr-le-<?php echo esc_attr($l->id); ?>">Eräpäivä</label>
                        <input type="date" id="jr-le-<?php echo esc_attr($l->id); ?>" name="lasku_era[<?php echo esc_attr($l->id); ?>]" value="<?php echo esc_attr(jr_late_era($l)); ?>">
                    </span>
                </div>
            <?php endforeach; ?>
            <label class="jr-radio"><input type="checkbox" name="laheta_heti" value="1" checked> Lähetä lasku heti sähköpostilla (jos jäsenellä on sähköpostiosoite eikä hän ole valinnut paperilaskua)</label>
        </fieldset>
        <?php endif; ?>

        <p class="jr-actions">
            <button type="submit" class="button button-primary button-large"><?php echo $m ? 'Tallenna muutokset' : 'Lisää jäsen'; ?></button>
            <a class="button button-large" href="<?php echo esc_url(admin_url('admin.php?page=jasenrekisteri')); ?>">Peruuta</a>
        </p>
    </form>
    <?php if ($m) { jr_member_history($m); jr_member_log_panel($m); } ?>
    <?php
}

/** Jäsenen kaikki laskut ja maksut. */
function jr_member_history($m) {
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT r.*, l.nimi AS lasku_nimi, COALESCE(r.erapaiva, l.erapaiva) AS era FROM ' . jr_t('rivit') . ' r JOIN ' . jr_t('laskut') . ' l ON l.id = r.lasku_id WHERE r.jasen_id = %d ORDER BY l.id DESC',
        $m->id
    ));
    $today = current_time('Y-m-d');
    ?>
    <div class="jr-panel">
        <h2>Laskut ja maksut</h2>
        <?php if (!$rows): ?>
            <p class="jr-muted">Jäsenelle ei ole vielä tehty laskuja.</p>
        <?php else: ?>
        <table class="widefat striped jr-table">
            <thead><tr><th>Lasku</th><th>Viite</th><th>Summa</th><th>Eräpäivä</th><th>Tila</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a href="<?php echo esc_url(admin_url('admin.php?page=jr-laskutus&view=lasku&id=' . $r->lasku_id)); ?>"><?php echo esc_html($r->lasku_nimi); ?></a></td>
                    <td><code class="jr-ref"><?php echo esc_html(jr_viite_format($r->viite)); ?></code></td>
                    <td><?php echo esc_html(jr_money($r->summa)); ?></td>
                    <td><?php echo esc_html(jr_date($r->era)); ?></td>
                    <td><?php
                        if ($r->maksettu) echo '<span class="jr-badge jr-badge-ok">Maksettu ' . esc_html(jr_date($r->maksettu)) . '</span>';
                        elseif ($r->era < $today) echo '<span class="jr-badge jr-badge-err">Erääntynyt</span>';
                        else echo '<span class="jr-badge jr-badge-info">Avoin</span>';
                        if ($r->muistutettu) echo ' <span class="jr-badge jr-badge-orange">Muistutettu ' . ((int) $r->muistutuksia > 1 ? (int) $r->muistutuksia . '× ' : '') . esc_html(jr_date($r->muistutettu)) . '</span>';
                    ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php
}

function jr_member_list() {
    global $wpdb;
    $t = jr_t('jasenet');
    $tunnus_on = jr_tunnus_on();
    $lajit = jr_jasenlajit();

    // Järjestäminen: vain sallitut sarakkeet (korjaa SQL-injektion)
    $allowed  = array('id', 'etunimi', 'sukunimi', 'tunnus', 'liittynyt');
    $order_by = isset($_GET['order_by']) && in_array($_GET['order_by'], $allowed, true) ? $_GET['order_by'] : 'sukunimi';
    $order    = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';

    // Lukumäärät jäsenluokittain (vain voimassa olevat) ja eronneet
    $by_laji = $wpdb->get_results("SELECT jasenlaji, COUNT(*) AS n FROM $t WHERE " . jr_sql_jasenet() . ' GROUP BY jasenlaji', OBJECT_K);
    $n_jas = 0; $n_ei = 0;
    foreach ($by_laji as $k => $row) {
        $n_jas += (int) $row->n;
        if (!isset($lajit[$k])) $n_ei += (int) $row->n;
    }
    $n_ero = (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE tila = 'eronnut'");
    $n_free = 0;
    foreach ($lajit as $lid => $l) if ((float) $l['summa'] <= 0 && isset($by_laji[$lid])) $n_free += (int) $by_laji[$lid]->n;

    // Välilehdet: kaikki jäsenet, jokainen jäsenluokka, ilman luokkaa (jos on), eronneet
    $tabs = array('jasenet' => array('Kaikki jäsenet', $n_jas));
    foreach ($lajit as $lid => $l) {
        $tabs['l_' . $lid] = array($l['nimi'], isset($by_laji[$lid]) ? (int) $by_laji[$lid]->n : 0);
    }
    if ($lajit && $n_ei > 0) $tabs['ei'] = array('Ilman jäsenluokkaa', $n_ei);
    $tabs['eronnut'] = array('Eronneet', $n_ero);

    $nayta = isset($_GET['nayta']) ? sanitize_key($_GET['nayta']) : 'jasenet';
    if (!isset($tabs[$nayta])) $nayta = 'jasenet';
    if ($nayta === 'eronnut') {
        $where = "WHERE tila = 'eronnut'";
    } elseif ($nayta === 'ei') {
        $known = array_keys($lajit);
        $where = 'WHERE ' . jr_sql_jasenet() . ($known
            ? $wpdb->prepare(' AND jasenlaji NOT IN (' . implode(',', array_fill(0, count($known), '%s')) . ')', $known)
            : '');
    } elseif (strpos($nayta, 'l_') === 0) {
        $where = 'WHERE ' . jr_sql_jasenet() . $wpdb->prepare(' AND jasenlaji = %s', substr($nayta, 2));
    } else {
        $where = 'WHERE ' . jr_sql_jasenet();
    }

    $members = $wpdb->get_results("SELECT * FROM $t $where ORDER BY $order_by $order, sukunimi ASC, etunimi ASC");
    $n_noemail = (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE " . jr_sql_jasenet() . " AND (email IS NULL OR email = '')");
    $n_paperi  = (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE " . jr_sql_jasenet() . " AND (paperilasku = 1 OR email IS NULL OR email = '')");
    $kentat = jr_lisakentat();
    $sarakkeet = array_filter($kentat, function ($k) { return !empty($k['lista']) && $k['lista'] === '1'; });

    // Viimeisimmän laskun maksutilanne jäsenittäin
    $latest = $wpdb->get_row('SELECT * FROM ' . jr_t('laskut') . ' ORDER BY id DESC LIMIT 1');
    $paystatus = $latest ? $wpdb->get_results($wpdb->prepare('SELECT jasen_id, maksettu, erapaiva FROM ' . jr_t('rivit') . ' WHERE lasku_id = %d', $latest->id), OBJECT_K) : array();
    $today = current_time('Y-m-d');

    $base = admin_url('admin.php?page=jasenrekisteri');
    $sort_link = function ($col, $label) use ($base, $order_by, $order, $nayta) {
        $next = ($order_by === $col && $order === 'ASC') ? 'desc' : 'asc';
        $arrow = $order_by === $col ? ($order === 'ASC' ? ' ▲' : ' ▼') : '';
        return '<a href="' . esc_url(add_query_arg(array('order_by' => $col, 'order' => $next, 'nayta' => $nayta), $base)) . '">' . esc_html($label) . $arrow . '</a>';
    };
    $cols = ($tunnus_on ? 8 : 7) + count($sarakkeet);
    ?>
    <h1 class="wp-heading-inline">Jäsenrekisteri</h1>
    <a href="<?php echo esc_url(add_query_arg(array('view' => 'new', 'laji' => strpos($nayta, 'l_') === 0 ? substr($nayta, 2) : null), $base)); ?>" class="page-title-action">Lisää uusi jäsen</a>
    <hr class="wp-header-end">
    <?php jr_notice(); ?>

    <div class="jr-cards">
        <div class="jr-card"><span class="jr-card-num"><?php echo $n_jas; ?></span><span class="jr-card-label">Jäsentä<?php echo $n_free ? ', joista ' . $n_free . ' ilman jäsenmaksua' : ''; ?></span></div>
        <div class="jr-card"><span class="jr-card-num"><?php echo $n_ero; ?></span><span class="jr-card-label">Eronnutta</span></div>
        <div class="jr-card <?php echo $n_noemail ? 'jr-card-warn' : ''; ?>"><span class="jr-card-num"><?php echo $n_noemail; ?></span><span class="jr-card-label">Jäsentä ilman sähköpostia</span></div>
        <?php if ($n_paperi): ?>
        <div class="jr-card"><span class="jr-card-num"><?php echo $n_paperi; ?></span><span class="jr-card-label">Saa laskun paperilla</span></div>
        <?php endif; ?>
        <?php if ($latest):
            $paid = 0; foreach ($paystatus as $p) { if ($p->maksettu) $paid++; } ?>
        <div class="jr-card"><span class="jr-card-num"><?php echo $paid . ' / ' . count($paystatus); ?></span>
            <span class="jr-card-label">Maksanut: <?php echo esc_html($latest->nimi); ?></span></div>
        <?php endif; ?>
    </div>

    <?php if (!$lajit): ?>
        <p class="jr-hint">Vinkki: määrittele <a href="<?php echo esc_url(admin_url('admin.php?page=jr-asetukset#jr-luokat')); ?>">asetuksissa</a> jäsenluokat (esim. Jäsen, Nuorisojäsen, Kunniajäsen) ja niiden jäsenmaksut. Luokat näkyvät tässä välilehtinä.</p>
    <?php elseif ($n_ei > 0): ?>
        <p class="jr-hint"><?php echo (int) $n_ei; ?> jäsenellä ei ole jäsenluokkaa. Avaa välilehti ”Ilman jäsenluokkaa”, valitse jäsenet ja aseta luokka taulukon alta.</p>
    <?php endif; ?>

    <div class="jr-toolbar">
        <ul class="subsubsub jr-tabs">
            <?php foreach ($tabs as $k => $tab): ?>
                <li><a href="<?php echo esc_url(add_query_arg('nayta', $k, $base)); ?>" class="<?php echo $nayta === $k ? 'current' : ''; ?>"><?php echo esc_html($tab[0]); ?> <span class="count">(<?php echo (int) $tab[1]; ?>)</span></a></li>
            <?php endforeach; ?>
        </ul>
        <div class="jr-toolbar-right">
            <input type="search" class="jr-search" data-jr-filter="#jr-members" placeholder="<?php echo esc_attr('Hae: nimi, sähköposti, puhelin' . ($tunnus_on ? ', ' . mb_strtolower(jr_tunnus_nimi()) : '') . ($kentat ? ', lisäkenttä' : '') . '…'); ?>">
            <a class="button" href="<?php echo esc_url(add_query_arg('view', 'tuonti', $base)); ?>">Tuo CSV</a>
            <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=jr_export_members'), 'jr_export_members')); ?>">Vie CSV</a>
        </div>
    </div>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <input type="hidden" name="action" value="jr_bulk_members">
    <input type="hidden" name="nayta" value="<?php echo esc_attr($nayta); ?>">
    <?php wp_nonce_field('jr_bulk_members'); ?>
    <table class="widefat striped jr-table" id="jr-members">
        <thead>
            <tr>
                <td class="check-column"><input type="checkbox" class="jr-check-all" data-target="#jr-members"></td>
                <th class="jr-col-id"><?php echo $sort_link('id', 'ID'); ?></th>
                <th><?php echo $sort_link('sukunimi', 'Nimi'); ?></th>
                <?php if ($tunnus_on): ?><th><?php echo $sort_link('tunnus', jr_tunnus_nimi()); ?></th><?php endif; ?>
                <th>Yhteystiedot</th>
                <th><?php echo $sort_link('liittynyt', 'Liittynyt'); ?></th>
                <?php foreach ($sarakkeet as $k): ?><th><?php echo esc_html($k['nimi']); ?></th><?php endforeach; ?>
                <th><?php echo $latest ? esc_html($latest->nimi) : 'Jäsenmaksu'; ?></th>
                <th class="jr-col-actions">Toiminnot</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$members): ?>
            <tr><td colspan="<?php echo $cols; ?>" class="jr-empty">Ei jäseniä tässä näkymässä.</td></tr>
        <?php endif; ?>
        <?php foreach ($members as $m):
            $lisa = jr_member_lisa($m);
            // Hakuun myös lisäkentät: kentän nimi (esim. "avain") löytää kaikki, joilla kenttä on täytetty
            $lisa_haku = '';
            foreach ($kentat as $kid => $k) if (isset($lisa[$kid]) && $lisa[$kid] !== '') $lisa_haku .= $k['nimi'] . ($k['tyyppi'] === 'valinta' ? '' : $lisa[$kid]);
            $search = preg_replace('/\s+/', '', strtolower(remove_accents($m->etunimi . $m->sukunimi . $m->etunimi . $m->tunnus . $m->email . $m->puhelin . jr_laji_nimi($m->jasenlaji) . $lisa_haku . ($m->paperilasku ? 'paperilasku' : ''))));
            $edit_url = add_query_arg(array('view' => 'edit', 'id' => $m->id), $base);
            $del_url  = wp_nonce_url(admin_url('admin-post.php?action=jr_delete_member&id=' . $m->id), 'jr_delete_member_' . $m->id);
            ?>
            <tr data-search="<?php echo esc_attr($search); ?>">
                <th class="check-column"><input type="checkbox" name="jasenet[]" value="<?php echo esc_attr($m->id); ?>"></th>
                <td class="jr-col-id"><?php echo esc_html($m->id); ?></td>
                <td><a class="jr-name" href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html($m->sukunimi . ' ' . $m->etunimi); ?></a>
                    <?php echo jr_laji_badge($m->jasenlaji); ?>
                    <?php echo jr_tila_badge($m->tila); ?>
                    <?php if ($m->lisatiedot): ?><div class="jr-sub"><?php echo esc_html(wp_trim_words($m->lisatiedot, 10)); ?></div><?php endif; ?>
                </td>
                <?php if ($tunnus_on): ?><td><?php echo $m->tunnus ? '<span class="jr-call">' . esc_html($m->tunnus) . '</span>' : ''; ?></td><?php endif; ?>
                <td>
                    <?php if ($m->email): ?><a href="mailto:<?php echo esc_attr($m->email); ?>"><?php echo esc_html($m->email); ?></a><?php else: ?><span class="jr-badge jr-badge-warn">ei sähköpostia</span><?php endif; ?>
                    <?php if ($m->paperilasku): ?><span class="jr-badge jr-badge-info" title="Lasku tulostetaan ja postitetaan">paperilasku</span><?php endif; ?>
                    <?php if ($m->puhelin): ?><div class="jr-sub"><?php echo esc_html($m->puhelin); ?></div><?php endif; ?>
                    <?php if ($m->osoite): ?><div class="jr-sub"><?php echo esc_html($m->osoite); ?></div><?php endif; ?>
                </td>
                <td><?php echo esc_html(jr_date($m->liittynyt)); ?></td>
                <?php foreach ($sarakkeet as $kid => $k): ?><td><?php echo esc_html(jr_lisa_value($k, isset($lisa[$kid]) ? $lisa[$kid] : '')); ?></td><?php endforeach; ?>
                <td><?php
                    if ($latest && isset($paystatus[$m->id])) {
                        if ($paystatus[$m->id]->maksettu) {
                            echo '<span class="jr-badge jr-badge-ok">Maksettu ' . esc_html(jr_date($paystatus[$m->id]->maksettu)) . '</span>';
                        } elseif (jr_rivi_era($latest, $paystatus[$m->id]) < $today) {
                            echo '<span class="jr-badge jr-badge-err">Erääntynyt</span>';
                        } else {
                            echo '<span class="jr-badge jr-badge-info">Avoin</span>';
                        }
                    } elseif ($m->tila !== 'eronnut' && jr_is_maksuvapaa($m)) {
                        echo '<span class="jr-muted">ei jäsenmaksua</span>';
                    } else {
                        echo '<span class="jr-muted">–</span>';
                    }
                ?></td>
                <td class="jr-col-actions">
                    <a class="button button-small" href="<?php echo esc_url($edit_url); ?>">Muokkaa</a>
                    <a class="button button-small jr-btn-danger" href="<?php echo esc_url($del_url); ?>"
                       data-confirm="<?php echo esc_attr('Poistetaanko ' . $m->etunimi . ' ' . $m->sukunimi . ' pysyvästi? Jos jäsen eroaa, kannattaa mieluummin merkitä hänet eronneeksi, jolloin tiedot ja maksuhistoria säilyvät.'); ?>">Poista</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($members): ?>
    <div class="jr-bulk">
        <label for="jr-mbulk"><strong>Valituille:</strong></label>
        <select name="bulk_op" id="jr-mbulk">
            <?php if ($lajit): ?>
            <optgroup label="Aseta jäsenluokka">
                <?php foreach ($lajit as $lid => $l): ?>
                    <option value="laji:<?php echo esc_attr($lid); ?>"><?php echo esc_html($l['nimi'] . ' (' . jr_money($l['summa']) . ')'); ?></option>
                <?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
            <optgroup label="Jäsenyys">
                <option value="eronnut">Merkitse eronneeksi</option>
                <option value="aktiivinen">Palauta jäseneksi</option>
            </optgroup>
            <optgroup label="Laskun toimitus">
                <option value="paperi_on">Lasku paperilla</option>
                <option value="paperi_off">Lasku sähköpostilla</option>
            </optgroup>
        </select>
        <button type="submit" class="button" data-confirm="Muutetaanko valittujen jäsenten tiedot?">Suorita</button>
    </div>
    <?php endif; ?>
    </form>
    <p class="jr-muted jr-count"><?php echo count($members); ?> henkilöä näkymässä.</p>
    <?php
}
