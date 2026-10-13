<?php
/*
Plugin Name: Jäsenrekisteri
Description: Plugin jäsenrekisterin ylläpitoon.
Version: 2.6
Author: Jarno Jaskari oh6kw
*/

if (!defined('ABSPATH')) exit; // Estetään suora pääsy tiedostoon

// Luo tietokantataulu pluginin aktivoidessa
function jasenrekisteri_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'jasenrekisteri';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        etunimi varchar(50) NOT NULL,
        sukunimi varchar(50) NOT NULL,
        tunnus varchar(10),
        osoite varchar(255),
        puhelin varchar(20),
        email varchar(100),
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'jasenrekisteri_create_table');

// Näytä jäsenrekisterin hallintanäkymä
function jasenrekisteri_display_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'jasenrekisteri';

    // Lisää tai päivitä jäsen
    if (isset($_POST['save_member'])) {
        $data = array(
            'etunimi' => sanitize_text_field($_POST['etunimi']),
            'sukunimi' => sanitize_text_field($_POST['sukunimi']),
            'tunnus' => sanitize_text_field($_POST['tunnus']),
            'osoite' => sanitize_text_field($_POST['osoite']),
            'puhelin' => sanitize_text_field($_POST['puhelin']),
            'email' => sanitize_email($_POST['email']),
        );

        if (!empty($_POST['member_id'])) {
            // Päivitä jäsen
            $wpdb->update($table_name, $data, array('id' => intval($_POST['member_id'])));
            echo "<div class='updated'><p>Jäsenen tiedot päivitetty onnistuneesti!</p></div>";
        } else {
            // Lisää uusi jäsen
            $wpdb->insert($table_name, $data);
            echo "<div class='updated'><p>Jäsen lisätty onnistuneesti!</p></div>";
        }
    }

    // Poista jäsen
    if (isset($_POST['delete_member'])) {
        $member_id = intval($_POST['delete_member']);
        $wpdb->delete($table_name, array('id' => $member_id));

        // Järjestä ID:t uudelleen
        $wpdb->query("SET @new_id = 0");
        $wpdb->query("UPDATE $table_name SET id = (@new_id := @new_id + 1)");
        $wpdb->query("ALTER TABLE $table_name AUTO_INCREMENT = " . ($wpdb->get_var("SELECT MAX(id) FROM $table_name") + 1));

        echo "<div class='updated'><p>Jäsen poistettu ja ID:t järjestetty uudelleen!</p></div>";
    }

    // Järjestäminen
    $order_by = isset($_GET['order_by']) ? sanitize_text_field($_GET['order_by']) : 'id';
    $order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'desc' : 'asc';
    $next_order = $order === 'asc' ? 'desc' : 'asc';
    $members = $wpdb->get_results("SELECT * FROM $table_name ORDER BY $order_by $order");

    // Hae jäsen muokkausta varten
    $edit_member = null;
    if (isset($_GET['edit_member'])) {
        $edit_member = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['edit_member'])));
    }

    // Lähetä sähköposti
    if (isset($_POST['send_email']) && isset($_POST['email_content']) && isset($_POST['email_subject'])) {
        $subject = sanitize_text_field($_POST['email_subject']);
        $message = sanitize_textarea_field($_POST['email_content']);
        $headers = array('Content-Type: text/html; charset=UTF-8');

        $members = $wpdb->get_results("SELECT email FROM $table_name");

        foreach ($members as $member) {
            wp_mail($member->email, $subject, $message, $headers);
        }

        echo "<div class='updated'><p>Sähköposti lähetetty kaikille jäsenille!</p></div>";
    }

    ?>
    <div class="wrap">
        <h1>Jäsenrekisteri</h1>

        <form method="post">
            <h2><?php echo $edit_member ? 'Muokkaa jäsentä' : 'Lisää uusi jäsen'; ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="etunimi">Etunimi</label></th>
                    <td><input type="text" name="etunimi" value="<?php echo $edit_member ? esc_attr($edit_member->etunimi) : ''; ?>" required></td>
                </tr>
                <tr>
                    <th><label for="sukunimi">Sukunimi</label></th>
                    <td><input type="text" name="sukunimi" value="<?php echo $edit_member ? esc_attr($edit_member->sukunimi) : ''; ?>" required></td>
                </tr>
                <tr>
                    <th><label for="tunnus">Tunnus</label></th>
                    <td><input type="text" name="tunnus" value="<?php echo $edit_member ? esc_attr($edit_member->tunnus) : ''; ?>"></td>
                </tr>
                <tr>
                    <th><label for="osoite">Osoite</label></th>
                    <td><input type="text" name="osoite" value="<?php echo $edit_member ? esc_attr($edit_member->osoite) : ''; ?>"></td>
                </tr>
                <tr>
                    <th><label for="puhelin">Puhelin</label></th>
                    <td><input type="text" name="puhelin" value="<?php echo $edit_member ? esc_attr($edit_member->puhelin) : ''; ?>"></td>
                </tr>
                <tr>
                    <th><label for="email">Sähköposti</label></th>
                    <td><input type="email" name="email" value="<?php echo $edit_member ? esc_attr($edit_member->email) : ''; ?>"></td>
                </tr>
            </table>
            <?php if ($edit_member): ?>
                <input type="hidden" name="member_id" value="<?php echo esc_attr($edit_member->id); ?>">
            <?php endif; ?>
            <p><input type="submit" name="save_member" class="button button-primary" value="<?php echo $edit_member ? 'Päivitä jäsen' : 'Tallenna'; ?>"></p>
        </form>

        <form method="post">
            <h2>Lähetä sähköposti kaikille jäsenille</h2>
            <p><label for="email_subject">Sähköpostin otsikko:</label></p>
            <input type="text" name="email_subject" value="" size="50" required>
            <p><textarea name="email_content" rows="5" cols="50" placeholder="Kirjoita viesti tähän..." required></textarea></p>
            <p><input type="submit" name="send_email" class="button button-primary" value="Lähetä"></p>
        </form>

        <h2>Jäsenet</h2>
        <table class="widefat fixed">
            <thead>
                <tr>
                    <th><a href="?page=jasenrekisteri&order_by=id&order=<?php echo $next_order; ?>">ID</a></th>
                    <th><a href="?page=jasenrekisteri&order_by=etunimi&order=<?php echo $next_order; ?>">Etunimi</a></th>
                    <th><a href="?page=jasenrekisteri&order_by=sukunimi&order=<?php echo $next_order; ?>">Sukunimi</a></th>
                    <th><a href="?page=jasenrekisteri&order_by=tunnus&order=<?php echo $next_order; ?>">Tunnus</a></th>
                    <th>Osoite</th>
                    <th>Puhelin</th>
                    <th>Sähköposti</th>
                    <th>Toiminnot</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $member): ?>
                    <tr>
                        <td><?php echo esc_html($member->id); ?></td>
                        <td><?php echo esc_html($member->etunimi); ?></td>
                        <td><?php echo esc_html($member->sukunimi); ?></td>
                        <td><?php echo esc_html($member->tunnus); ?></td>
                        <td><?php echo esc_html($member->osoite); ?></td>
                        <td><?php echo esc_html($member->puhelin); ?></td>
                        <td><?php echo esc_html($member->email); ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="delete_member" value="<?php echo esc_attr($member->id); ?>">
                                <input type="submit" value="Poista" class="button button-secondary">
                            </form>
                            <form method="get" style="display:inline;">
                                <input type="hidden" name="page" value="jasenrekisteri">
                                <input type="hidden" name="edit_member" value="<?php echo esc_attr($member->id); ?>">
                                <input type="submit" value="Muokkaa" class="button button-secondary">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

add_action('admin_menu', function() {
    add_menu_page('Jäsenrekisteri', 'Jäsenrekisteri', 'manage_options', 'jasenrekisteri', 'jasenrekisteri_display_admin_page');
});
?>
