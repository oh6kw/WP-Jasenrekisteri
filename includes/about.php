<?php
if (!defined('ABSPATH')) exit;

/*
 * Tietoja-sivu: tekijät, lisenssi, linkit, palautelomake ja Ko-fi.
 */

define('JR_DEV_EMAIL', 'jarno.jaskari@gmail.com');
define('JR_GITHUB', 'https://github.com/oh6kw/WP-Jasenrekisteri');
define('JR_KOFI', 'https://ko-fi.com/oh6kw');

// Linkit Lisäosat-sivulle lisäosan kuvauksen alle
add_filter('plugin_row_meta', function ($links, $file) {
    if ($file !== plugin_basename(JR_FILE)) return $links;
    $links[] = '<a href="' . esc_url(JR_GITHUB) . '" target="_blank" rel="noopener">GitHub</a>';
    $links[] = '<a href="' . esc_url(admin_url('admin.php?page=jr-tietoja#palaute')) . '">Palaute ja kehitysehdotukset</a>';
    $links[] = '<a href="' . esc_url(JR_KOFI) . '" target="_blank" rel="noopener">☕ Tarjoa kahvit (Ko-fi)</a>';
    return $links;
}, 10, 2);

add_action('admin_post_jr_feedback', 'jr_handle_feedback');
function jr_handle_feedback() {
    jr_check('jr_feedback');
    $uid = get_current_user_id();
    if (get_transient('jr_feedback_sent_' . $uid)) {
        jr_redirect('jr-tietoja', array('jr_msg' => 'feedback_wait'));
    }
    $tyypit = array('ehdotus' => 'Kehitysehdotus', 'virhe' => 'Virheilmoitus', 'kysymys' => 'Kysymys', 'muu' => 'Muu palaute');
    $tyyppi = isset($tyypit[jr_post('tyyppi')]) ? $tyypit[jr_post('tyyppi')] : 'Muu palaute';
    $viesti = trim(sanitize_textarea_field(jr_post('viesti')));
    $nimi   = trim(str_replace(array("\r", "\n", '<', '>', '"'), '', sanitize_text_field(jr_post('nimi'))));
    $email  = sanitize_email(jr_post('email'));
    if ($viesti === '') jr_redirect('jr-tietoja', array('jr_msg' => 'feedback_empty'));

    $body = $tyyppi . "\n\n" . $viesti . "\n\n--\n"
          . 'Lähettäjä: ' . ($nimi !== '' ? $nimi : '(ei nimeä)') . ($email ? ' <' . $email . '>' : '') . "\n";
    if (jr_post('tekniset') === '1') {
        global $wp_version;
        $body .= 'Jäsenrekisteri ' . JR_VERSION . ' · WordPress ' . $wp_version . ' · PHP ' . PHP_VERSION . ' · ' . home_url() . "\n";
    }
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    if ($email) $headers[] = 'Reply-To: ' . ($nimi !== '' ? $nimi . ' ' : '') . '<' . $email . '>';

    $ok = wp_mail(JR_DEV_EMAIL, '[Jäsenrekisteri] ' . $tyyppi . ': ' . wp_trim_words($viesti, 8, '…'), $body, $headers);
    if ($ok) set_transient('jr_feedback_sent_' . $uid, 1, MINUTE_IN_SECONDS);
    jr_redirect('jr-tietoja', array('jr_msg' => $ok ? 'feedback_sent' : 'send_failed'));
}

function jr_page_about() {
    global $wp_version;
    $me = wp_get_current_user();
    ?>
    <div class="wrap jr-wrap jr-about">
        <h1>Tietoja ja palaute</h1>
        <?php jr_notice(); ?>

        <div class="jr-panel">
            <h2>Jäsenrekisteri <?php echo esc_html(JR_VERSION); ?></h2>
            <p>Jäsenrekisteri ja jäsenmaksulaskutus yhdistyksille.</p>
            <dl>
                <dt>Tekijä</dt>
                <dd>Jarno Jaskari, OH6KW – alkuperäinen lisäosa, ideointi ja ylläpito</dd>
                <dt>Jatkokehitys</dt>
                <dd>Jarno Jaskari yhdessä Claude-tekoälyn (Anthropic) kanssa</dd>
                <dt>Yhteys</dt>
                <dd><a href="mailto:<?php echo esc_attr(JR_DEV_EMAIL); ?>"><?php echo esc_html(JR_DEV_EMAIL); ?></a></dd>
                <dt>Lähdekoodi</dt>
                <dd><a href="<?php echo esc_url(JR_GITHUB); ?>" target="_blank" rel="noopener">GitHub</a> ·
                    <a href="<?php echo esc_url(JR_GITHUB . '/issues'); ?>" target="_blank" rel="noopener">vikailmoitukset</a> ·
                    <a href="<?php echo esc_url(JR_GITHUB . '/blob/main/CHANGELOG.md'); ?>" target="_blank" rel="noopener">muutosloki</a> ·
                    <a href="<?php echo esc_url(JR_GITHUB . '/releases'); ?>" target="_blank" rel="noopener">uusimmat versiot</a></dd>
                <dt>Lisenssi</dt>
                <dd><a href="https://www.gnu.org/licenses/agpl-3.0.html" target="_blank" rel="noopener">GNU Affero General Public License v3.0</a> (AGPL-3.0-or-later). QR-koodikirjasto: Kazuhiko Arase, MIT-lisenssi.</dd>
                <dt>Ympäristö</dt>
                <dd>WordPress <?php echo esc_html($wp_version); ?> · PHP <?php echo esc_html(PHP_VERSION); ?></dd>
            </dl>
        </div>

        <div class="jr-panel">
            <h2>Tarjoa kahvit</h2>
            <p>Jos lisäosasta on hyötyä yhdistyksellesi, voit tarjota kehittäjälle kahvit. Kiitos!</p>
            <p><a class="jr-kofi" href="<?php echo esc_url(JR_KOFI); ?>" target="_blank" rel="noopener">☕ Tarjoa kahvit Ko-fissa</a></p>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="jr-panel jr-form" id="palaute">
            <input type="hidden" name="action" value="jr_feedback">
            <?php wp_nonce_field('jr_feedback'); ?>
            <h2>Palaute ja kehitysehdotukset</h2>
            <p class="description">Viesti lähetetään kehittäjälle osoitteeseen <?php echo esc_html(JR_DEV_EMAIL); ?> sivustosi sähköpostiasetuksilla. Jäsenrekisterin tietoja ei lähetetä. Voit myös avata <a href="<?php echo esc_url(JR_GITHUB . '/issues'); ?>" target="_blank" rel="noopener">GitHub-issuen</a>.</p>
            <div class="jr-grid">
                <p><label for="jr-fb-tyyppi">Aihe</label>
                   <select id="jr-fb-tyyppi" name="tyyppi">
                       <option value="ehdotus">Kehitysehdotus</option>
                       <option value="virhe">Virheilmoitus</option>
                       <option value="kysymys">Kysymys</option>
                       <option value="muu">Muu palaute</option>
                   </select></p>
                <p><label for="jr-fb-nimi">Nimesi</label>
                   <input type="text" id="jr-fb-nimi" name="nimi" value="<?php echo esc_attr($me->display_name); ?>"></p>
                <p><label for="jr-fb-email">Sähköpostisi (vastausta varten)</label>
                   <input type="email" id="jr-fb-email" name="email" value="<?php echo esc_attr($me->user_email); ?>"></p>
            </div>
            <p><label for="jr-fb-viesti">Viesti *</label>
               <textarea id="jr-fb-viesti" name="viesti" rows="6" required placeholder="Mitä toivoisit lisäosaan, tai mikä ei toiminut?"></textarea></p>
            <p><label class="jr-radio"><input type="checkbox" name="tekniset" value="1" checked> Liitä mukaan versiotiedot (Jäsenrekisteri <?php echo esc_html(JR_VERSION); ?>, WordPress, PHP, sivuston osoite) – helpottaa vikojen selvittämistä</label></p>
            <p class="jr-actions"><button type="submit" class="button button-primary">Lähetä palaute</button></p>
        </form>
    </div>
    <?php
}
