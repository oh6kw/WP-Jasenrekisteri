<?php
/*
Plugin Name: Jäsenrekisteri
Plugin URI: https://github.com/oh6kw/WP-Jasenrekisteri
Description: Yhdistyksen jäsenrekisteri: jäsenet, jäsenluokat ja omat lisäkentät, jäsenmaksulaskut omilla viitenumeroilla sähköpostilla tai paperilla, QR-koodi ja pankkiviivakoodi, maksujen tuonti pankista, muistutukset, tiedotteet, vuosiraportti ja muutosloki.
Version: 4.7.0
Author: Jarno Jaskari OH6KW, Claude (Anthropic)
Author URI: https://github.com/oh6kw
License: AGPL-3.0-or-later
License URI: https://www.gnu.org/licenses/agpl-3.0.html
Text Domain: jasenrekisteri
Requires at least: 6.2
Requires PHP: 7.4

Copyright (C) Jarno Jaskari OH6KW
SPDX-License-Identifier: AGPL-3.0-or-later
*/

if (!defined('ABSPATH')) exit; // Estetään suora pääsy tiedostoon

define('JASREK_VERSION', '4.7.0');
define('JASREK_DB_VERSION', '9');
define('JASREK_FILE', __FILE__);
define('JASREK_DIR', plugin_dir_path(__FILE__));
define('JASREK_URL', plugin_dir_url(__FILE__));

require_once JASREK_DIR . 'includes/helpers.php';
require_once JASREK_DIR . 'includes/log.php';
require_once JASREK_DIR . 'includes/install.php';
require_once JASREK_DIR . 'includes/email-templates.php';
require_once JASREK_DIR . 'includes/paycodes.php';
require_once JASREK_DIR . 'includes/members.php';
require_once JASREK_DIR . 'includes/import-members.php';
require_once JASREK_DIR . 'includes/invoices.php';
require_once JASREK_DIR . 'includes/import-payments.php';
require_once JASREK_DIR . 'includes/export-xlsx.php';
require_once JASREK_DIR . 'includes/print.php';
require_once JASREK_DIR . 'includes/report.php';
require_once JASREK_DIR . 'includes/voters.php';
require_once JASREK_DIR . 'includes/join.php';
require_once JASREK_DIR . 'includes/mailer.php';
require_once JASREK_DIR . 'includes/settings.php';
require_once JASREK_DIR . 'includes/cron.php';
require_once JASREK_DIR . 'includes/about.php';

// Tietokanta luodaan aktivoinnissa ja päivitetään automaattisesti, kun plugin päivittyy.
register_activation_hook(__FILE__, 'jasrek_install');
add_action('plugins_loaded', 'jasrek_maybe_upgrade');

// Hallintavalikko
add_action('admin_menu', 'jasrek_admin_menu');
function jasrek_admin_menu() {
    $bubble = current_user_can('manage_options') ? jasrek_menu_bubble() : ''; // odottavat liittymishakemukset
    add_menu_page('Jäsenrekisteri', 'Jäsenrekisteri' . $bubble, 'manage_options', 'jasenrekisteri', 'jasrek_page_members', 'dashicons-groups', 26);
    add_submenu_page('jasenrekisteri', 'Jäsenet', 'Jäsenet' . $bubble, 'manage_options', 'jasenrekisteri', 'jasrek_page_members');
    add_submenu_page('jasenrekisteri', 'Jäsenmaksulaskutus', 'Laskutus', 'manage_options', 'jasrek-laskutus', 'jasrek_page_invoices');
    add_submenu_page('jasenrekisteri', 'Sähköposti jäsenille', 'Sähköposti', 'manage_options', 'jasrek-sahkoposti', 'jasrek_page_mail');
    add_submenu_page('jasenrekisteri', 'Vuosiraportti', 'Vuosiraportti', 'manage_options', 'jasrek-raportti', 'jasrek_page_report');
    add_submenu_page('jasenrekisteri', 'Muutosloki', 'Muutosloki', 'manage_options', 'jasrek-loki', 'jasrek_page_log');
    add_submenu_page('jasenrekisteri', 'Jäsenrekisterin asetukset', 'Asetukset', 'manage_options', 'jasrek-asetukset', 'jasrek_page_settings');
    add_submenu_page('jasenrekisteri', 'Tietoja ja palaute', 'Tietoja ja palaute', 'manage_options', 'jasrek-tietoja', 'jasrek_page_about');
}

// Tyylit ja skriptit vain pluginin omille sivuille
add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($hook, 'jasenrekisteri') === false && strpos($hook, 'jasrek-') === false) return;
    wp_enqueue_style('jasrek-admin', JASREK_URL . 'assets/admin.css', array(), JASREK_VERSION);
    wp_enqueue_script('jasrek-admin', JASREK_URL . 'assets/admin.js', array('jquery'), JASREK_VERSION, true);
    wp_localize_script('jasrek-admin', 'JASREK', array('ajax' => admin_url('admin-ajax.php')));
});

// Lisäosat-sivu: kysytään käytöstä poistettaessa, poistetaanko tiedot myöhemmin lisäosan poiston yhteydessä.
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'plugins.php' || !current_user_can('manage_options')) return;
    wp_enqueue_script('jasrek-deactivate', JASREK_URL . 'assets/deactivate.js', array('jquery'), JASREK_VERSION, true);
    wp_localize_script('jasrek-deactivate', 'JASREKD', array(
        'plugin'  => plugin_basename(JASREK_FILE),
        'nonce'   => wp_create_nonce('jasrek_set_uninstall'),
        'current' => jasrek_settings()['poista_tiedot'],
        'export'  => wp_nonce_url(admin_url('admin-post.php?action=jasrek_export_members'), 'jasrek_export_members'),
    ));
    wp_add_inline_style('common', '.jasrekd-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100000;display:flex;align-items:center;justify-content:center;padding:16px}'
        . '.jasrekd-box{background:#fff;border-radius:8px;max-width:560px;width:100%;padding:20px 24px;box-shadow:0 10px 40px rgba(0,0,0,.3)}'
        . '.jasrekd-box h2{margin-top:0}.jasrekd-box label{display:block;margin:10px 0}.jasrekd-warn{background:#fdecea;color:#b42318;padding:8px 12px;border-radius:6px}'
        . '.jasrekd-actions{text-align:right;margin-bottom:0}');
});
add_action('wp_ajax_jasrek_set_uninstall', function () {
    if (!current_user_can('manage_options')) wp_send_json_error('Ei oikeuksia', 403);
    check_ajax_referer('jasrek_set_uninstall', 'nonce');
    $s = get_option('jasrek_settings', array());
    if (!is_array($s)) $s = array();
    $s['poista_tiedot'] = jasrek_post('poista') === '1' ? '1' : '0';
    update_option('jasrek_settings', $s);
    wp_send_json_success();
});

// Asetukset-linkki lisäosalistaan
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=jasrek-asetukset')) . '">Asetukset</a>');
    return $links;
});
