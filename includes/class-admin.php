<?php
if (!defined('ABSPATH')) {
    exit;
}

class bewerberboerse_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function add_admin_menu() {
        add_options_page(
            __('Bewerberbörse Settings', 'bewerberboerse'),
            __('Bewerberbörse', 'bewerberboerse'),
            'manage_options',
            'bewerberboerse',
            array($this, 'render_settings_page')
        );
    }
    
    public function register_settings() {
        register_setting('bewerberboerse_settings', 'bewerberboerse_display_page');
        register_setting('bewerberboerse_settings', 'bewerberboerse_api_key');
    }
    
    public function render_settings_page() {
        include BEWERBERBOERSE_PLUGIN_DIR . 'admin/views/settings-page.php';
    }
}


