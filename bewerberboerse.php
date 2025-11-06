<?php
/**
 * Plugin Name: Bewerberbörse
 * Plugin URI: https://example.com/bewerberboerse
 * Description: Plugin zur Anzeige von Stellenanzeigen und Bewerbungen von Arbeitssuchenden
 * Version: 1.5.3
 * Author: EMA
 * License: GPL v2 or later
 * Text Domain: bewerberboerse
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BEWERBERBOERSE_VERSION', '1.5.3');
define('BEWERBERBOERSE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BEWERBERBOERSE_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once BEWERBERBOERSE_PLUGIN_DIR . 'includes/class-database.php';
require_once BEWERBERBOERSE_PLUGIN_DIR . 'includes/class-admin.php';
require_once BEWERBERBOERSE_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once BEWERBERBOERSE_PLUGIN_DIR . 'includes/class-assets.php';
require_once BEWERBERBOERSE_PLUGIN_DIR . 'includes/class-api-handler.php';
require_once BEWERBERBOERSE_PLUGIN_DIR . 'includes/class-updater.php';

register_activation_hook(__FILE__, 'bewerberboerse_activate');
function bewerberboerse_activate() {
    bewerberboerse_Database::create_tables();
    flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'bewerberboerse_deactivate');
function bewerberboerse_deactivate() {
    flush_rewrite_rules();
}

register_uninstall_hook(__FILE__, 'bewerberboerse_uninstall');
function bewerberboerse_uninstall() {
    // bewerberboerse_Database::drop_tables();
}

add_action('plugins_loaded', 'bewerberboerse_init');
function bewerberboerse_init() {
    new bewerberboerse_Assets();
    new bewerberboerse_Admin();
    new bewerberboerse_Shortcode();
    new bewerberboerse_API_Handler();
    new bewerberboerse_Updater();
}
