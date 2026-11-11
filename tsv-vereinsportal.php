<?php
/**
 * Plugin Name:       TSV Vereinsportal
 * Description:       Interner Bereich für Vorstand, Abteilungsleitungen und Übungsleiter: Abrechnungen, Auszahlungsanträge und Dokumente – mit eigenen Zugängen.
 * Version:           0.4.0
 * Author:            Florian
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Text Domain:       tsv-vereinsportal
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TSVP_VERSION', '0.4.0');
define('TSVP_FILE', __FILE__);
define('TSVP_DIR', plugin_dir_path(__FILE__));
define('TSVP_URL', plugin_dir_url(__FILE__));

require_once TSVP_DIR . 'includes/helpers.php';
require_once TSVP_DIR . 'includes/install.php';
require_once TSVP_DIR . 'includes/auth.php';
require_once TSVP_DIR . 'includes/actions.php';
require_once TSVP_DIR . 'includes/views.php';
require_once TSVP_DIR . 'includes/app.php';
require_once TSVP_DIR . 'includes/updater.php';

register_activation_hook(__FILE__, 'tsvp_install');

add_action('plugins_loaded', function () {
    if (get_option('tsvp_db_version') !== TSVP_VERSION) {
        tsvp_install();
    }
});

add_shortcode('tsv_vereinsportal', 'tsvp_render_portal');

add_action('wp_enqueue_scripts', function () {
    if (!tsvp_is_portal_page()) {
        return;
    }
    wp_enqueue_style('tsvp', TSVP_URL . 'assets/portal.css', [], TSVP_VERSION);
    wp_enqueue_script('tsvp', TSVP_URL . 'assets/portal.js', [], TSVP_VERSION, true);
});

// Link zum Portal in der Plugin-Liste.
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    array_unshift($links, '<a href="' . esc_url(tsvp_page_url()) . '">Portal öffnen</a>');
    return $links;
});
