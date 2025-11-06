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
        register_setting('bewerberboerse_settings', 'bewerberboerse_update_url');
        register_setting('bewerberboerse_settings', 'bewerberboerse_github_repo');
        
        add_action('wp_ajax_bewerberboerse_check_updates', array($this, 'ajax_check_updates'));
    }
    
    public function ajax_check_updates() {
        check_ajax_referer('bewerberboerse_check_updates', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'bewerberboerse')));
        }
        
        bewerberboerse_Updater::clear_update_cache();
        
        delete_site_transient('update_plugins');
        wp_update_plugins();
        
        wp_send_json_success(array('message' => __('Updates check completed. Please refresh the plugins page.', 'bewerberboerse')));
    }
    
    public function render_settings_page() {
        include BEWERBERBOERSE_PLUGIN_DIR . 'admin/views/settings-page.php';
    }
}


