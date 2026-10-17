<?php
/* WordPress Playground -esikatselu: ilmoitus jäsenrekisterin sivuilla, eikä sähköposteja yritetä lähettää. */
add_action('admin_notices', function () {
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ($page !== 'jasenrekisteri' && strpos($page, 'jasrek-') !== 0) return;
    echo '<div class="notice notice-info"><p><strong>Esikatselu:</strong> tämä on WordPress Playground -kokeiluympäristö keksityillä jäsenillä. '
        . 'Kokeile vapaasti: lähetä laskuja, tulosta paperilasku, merkitse maksuja tai tuo jäseniä. Sähköpostit eivät oikeasti lähde, '
        . 'ja kaikki katoaa, kun suljet välilehden.</p></div>';
});
add_filter('pre_wp_mail', '__return_true');
