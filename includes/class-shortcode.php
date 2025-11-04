<?php

if (!defined('ABSPATH')) {
    exit;
}

class bewerberboerse_Shortcode {
    
    private static $has_shortcode = false;
    private static $shortcode_atts = array();
    
    public function __construct() {
        add_shortcode('bewerberboerse', array($this, 'render'));
        add_action('wp_head', array($this, 'add_script_data'));
    }
    
    public function render($atts) {
        $atts = shortcode_atts(array(
            'limit' => 25
        ), $atts);
        
        self::$has_shortcode = true;
        self::$shortcode_atts = $atts;
        
        wp_enqueue_style(
            'bewerberboerse-frontend',
            BEWERBERBOERSE_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            BEWERBERBOERSE_VERSION
        );
        wp_enqueue_script(
            'bewerberboerse-frontend',
            BEWERBERBOERSE_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            BEWERBERBOERSE_VERSION,
            true
        );
        
        return '<div id="bewerberboerse-app"></div>';
    }
    
    public function add_script_data() {
        if (self::$has_shortcode) {
            ?>
            <script type="text/javascript">
                var bewerberboerseData = {
                    apiUrl: '<?php echo esc_js(rest_url('bewerberboerse/v1')); ?>',
                    nonce: '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>',
                    limit: <?php echo intval(self::$shortcode_atts['limit']); ?>
                };
            </script>
            <?php
        }
    }
}


