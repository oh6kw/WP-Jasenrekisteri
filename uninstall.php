<?php
/**
 * Ajetaan, kun lisäosa poistetaan WordPressistä (Lisäosat → Poista).
 * Tiedot poistetaan vain, jos ylläpitäjä on valinnut niin (Asetukset → Lisäosan poistaminen,
 * tai kysely, joka näytetään lisäosaa poistettaessa käytöstä). Oletuksena tiedot säilyvät.
 */
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

function jr_uninstall_site() {
    global $wpdb;
    wp_clear_scheduled_hook('jr_auto_muistutus');

    $s = get_option('jr_settings');
    if (!is_array($s) || !isset($s['poista_tiedot']) || $s['poista_tiedot'] !== '1') {
        return; // Säilytetään jäsenet, laskut ja asetukset – lisäosan voi asentaa uudelleen ja jatkaa siitä.
    }

    foreach (array('jasenrekisteri_laskurivit', 'jasenrekisteri_laskut', 'jasenrekisteri') as $t) {
        $wpdb->query('DROP TABLE IF EXISTS `' . esc_sql($wpdb->prefix . $t) . '`');
    }
    delete_option('jr_settings');
    delete_option('jr_db_version');
    // Väliaikaiset tiedot (keskeneräiset tuonnit ja lähetykset)
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('_transient_jr_') . '%',
        $wpdb->esc_like('_transient_timeout_jr_') . '%'
    ));
}

if (is_multisite()) {
    foreach (get_sites(array('fields' => 'ids', 'number' => 0)) as $blog_id) {
        switch_to_blog($blog_id);
        jr_uninstall_site();
        restore_current_blog();
    }
} else {
    jr_uninstall_site();
}
