<?php
/*
Plugin Name: Jäsenrekisteri
Plugin URI: https://github.com/oh6kw/WP-Jasenrekisteri
Description: Yhdistyksen jäsenrekisteri: jäsenet ja jäsenluokat, jäsenmaksulaskut omilla viitenumeroilla, QR-koodi ja pankkiviivakoodi, maksujen tuonti pankista, muistutukset ja sähköpostitiedotteet.
Version: 4.4.0
Author: Jarno Jaskari OH6KW, Claude (Anthropic)
Author URI: https://github.com/oh6kw
License: AGPL-3.0-or-later
License URI: https://www.gnu.org/licenses/agpl-3.0.html
Update URI: https://github.com/oh6kw/WP-Jasenrekisteri
Requires at least: 5.8
Requires PHP: 7.4

Copyright (C) Jarno Jaskari OH6KW
SPDX-License-Identifier: AGPL-3.0-or-later
*/

if (!defined('ABSPATH')) exit; // Estetään suora pääsy tiedostoon

define('JR_VERSION', '4.4.0');
define('JR_DB_VERSION', '7');
define('JR_FILE', __FILE__);
define('JR_DIR', plugin_dir_path(__FILE__));
define('JR_URL', plugin_dir_url(__FILE__));

require_once JR_DIR . 'includes/helpers.php';
require_once JR_DIR . 'includes/install.php';
require_once JR_DIR . 'includes/email-templates.php';
require_once JR_DIR . 'includes/paycodes.php';
require_once JR_DIR . 'includes/members.php';
require_once JR_DIR . 'includes/import-members.php';
require_once JR_DIR . 'includes/invoices.php';
require_once JR_DIR . 'includes/import-payments.php';
require_once JR_DIR . 'includes/mailer.php';
require_once JR_DIR . 'includes/settings.php';
require_once JR_DIR . 'includes/cron.php';
require_once JR_DIR . 'includes/about.php';

// Tietokanta luodaan aktivoinnissa ja päivitetään automaattisesti, kun plugin päivittyy.
register_activation_hook(__FILE__, 'jr_install');
add_action('plugins_loaded', 'jr_maybe_upgrade');

// Hallintavalikko
add_action('admin_menu', 'jr_admin_menu');
function jr_admin_menu() {
    add_menu_page('Jäsenrekisteri', 'Jäsenrekisteri', 'manage_options', 'jasenrekisteri', 'jr_page_members', 'dashicons-groups', 26);
    add_submenu_page('jasenrekisteri', 'Jäsenet', 'Jäsenet', 'manage_options', 'jasenrekisteri', 'jr_page_members');
    add_submenu_page('jasenrekisteri', 'Jäsenmaksulaskutus', 'Laskutus', 'manage_options', 'jr-laskutus', 'jr_page_invoices');
    add_submenu_page('jasenrekisteri', 'Sähköposti jäsenille', 'Sähköposti', 'manage_options', 'jr-sahkoposti', 'jr_page_mail');
    add_submenu_page('jasenrekisteri', 'Jäsenrekisterin asetukset', 'Asetukset', 'manage_options', 'jr-asetukset', 'jr_page_settings');
    add_submenu_page('jasenrekisteri', 'Tietoja ja palaute', 'Tietoja ja palaute', 'manage_options', 'jr-tietoja', 'jr_page_about');
}

// Tyylit ja skriptit vain pluginin omille sivuille
add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($hook, 'jasenrekisteri') === false && strpos($hook, 'jr-') === false) return;
    wp_enqueue_style('jr-admin', JR_URL . 'assets/admin.css', array(), JR_VERSION);
    wp_enqueue_script('jr-admin', JR_URL . 'assets/admin.js', array('jquery'), JR_VERSION, true);
    wp_localize_script('jr-admin', 'JR', array('ajax' => admin_url('admin-ajax.php')));
});

// Lisäosat-sivu: kysytään käytöstä poistettaessa, poistetaanko tiedot myöhemmin lisäosan poiston yhteydessä.
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'plugins.php' || !current_user_can('manage_options')) return;
    wp_enqueue_script('jr-deactivate', JR_URL . 'assets/deactivate.js', array('jquery'), JR_VERSION, true);
    wp_localize_script('jr-deactivate', 'JRD', array(
        'plugin'  => plugin_basename(JR_FILE),
        'nonce'   => wp_create_nonce('jr_set_uninstall'),
        'current' => jr_settings()['poista_tiedot'],
        'export'  => wp_nonce_url(admin_url('admin-post.php?action=jr_export_members'), 'jr_export_members'),
    ));
    wp_add_inline_style('common', '.jrd-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100000;display:flex;align-items:center;justify-content:center;padding:16px}'
        . '.jrd-box{background:#fff;border-radius:8px;max-width:560px;width:100%;padding:20px 24px;box-shadow:0 10px 40px rgba(0,0,0,.3)}'
        . '.jrd-box h2{margin-top:0}.jrd-box label{display:block;margin:10px 0}.jrd-warn{background:#fdecea;color:#b42318;padding:8px 12px;border-radius:6px}'
        . '.jrd-actions{text-align:right;margin-bottom:0}');
});
add_action('wp_ajax_jr_set_uninstall', function () {
    if (!current_user_can('manage_options')) wp_send_json_error('Ei oikeuksia', 403);
    check_ajax_referer('jr_set_uninstall', 'nonce');
    $s = get_option('jr_settings', array());
    if (!is_array($s)) $s = array();
    $s['poista_tiedot'] = (isset($_POST['poista']) && $_POST['poista'] === '1') ? '1' : '0';
    update_option('jr_settings', $s);
    wp_send_json_success();
});

// Asetukset-linkki lisäosalistaan
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=jr-asetukset')) . '">Asetukset</a>');
    return $links;
});
