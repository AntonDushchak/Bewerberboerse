<?php
if (!defined('ABSPATH')) {
    exit;
}

class bewerberboerse_Assets {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
    }
    
    public function register_assets() {
        wp_register_style(
            'bewerberboerse-frontend',
            BEWERBERBOERSE_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            BEWERBERBOERSE_VERSION
        );
        
        wp_register_script(
            'bewerberboerse-frontend',
            BEWERBERBOERSE_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            BEWERBERBOERSE_VERSION,
            true
        );
    }
}
