<?php
if (!defined('ABSPATH')) {
    exit;
}

class bewerberboerse_Updater {
    
    private $api_url;
    private $plugin_slug;
    private $plugin_file;
    private $current_version;
    private $github_repo;
    private $use_github;
    
    public function __construct() {
        $this->plugin_file = 'bewerberboerse/bewerberboerse.php';
        $this->plugin_slug = 'bewerberboerse';
        $this->current_version = BEWERBERBOERSE_VERSION;
        
        $this->github_repo = get_option('bewerberboerse_github_repo', '');
        $this->use_github = !empty($this->github_repo);
        
        if ($this->use_github) {
            $repo_parts = explode(':', $this->github_repo);
            $repo = $repo_parts[0];
            $this->api_url = 'https://api.github.com/repos/' . $repo . '/releases/latest';
        } else {
            $saved_url = get_option('bewerberboerse_update_url');
            $default_url = 'https://example.com/updates/bewerberboerse';
            $this->api_url = apply_filters('bewerberboerse_update_api_url', !empty($saved_url) ? $saved_url : $default_url);
        }
        
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
        add_filter('plugins_api', array($this, 'plugin_api_call'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
        
        add_action('in_plugin_update_message-' . $this->plugin_file, array($this, 'update_message'), 10, 2);
    }
    
    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $remote_version = $this->get_remote_version();
        
        if ($remote_version && version_compare($this->current_version, $remote_version, '<')) {
            $obj = new stdClass();
            $obj->slug = $this->plugin_slug;
            $obj->new_version = $remote_version;
            $obj->url = $this->get_plugin_info_url();
            $obj->package = $this->get_download_url($remote_version);
            $obj->plugin = $this->plugin_file;
            
            $transient->response[$this->plugin_file] = $obj;
        }
        
        return $transient;
    }
    
    public function plugin_api_call($def, $action, $args) {
        if (!isset($args->slug) || $args->slug !== $this->plugin_slug) {
            return $def;
        }
        
        $plugin_info = $this->get_remote_plugin_info();
        
        if (!$plugin_info) {
            return $def;
        }
        
        return $plugin_info;
    }
    
    private function get_remote_version() {
        $cache_key = 'bewerberboerse_remote_version';
        $version = get_transient($cache_key);
        
        if (false === $version) {
            if ($this->use_github) {
                $version = $this->get_github_version();
            } else {
                $response = wp_remote_get($this->api_url . '/version.json', array(
                    'timeout' => 10,
                    'headers' => array(
                        'Accept' => 'application/json'
                    )
                ));
                
                if (is_wp_error($response)) {
                    return false;
                }
                
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (isset($data['version'])) {
                    $version = $data['version'];
                } else {
                    return false;
                }
            }
            
            if ($version) {
                set_transient($cache_key, $version, 12 * HOUR_IN_SECONDS);
            }
        }
        
        return $version;
    }
    
    private function get_github_version() {
        $response = wp_remote_get($this->api_url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress-Bewerberboerse-Plugin'
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['tag_name'])) {
            $version = ltrim($data['tag_name'], 'v');
            return $version;
        }
        
        return false;
    }
    
    private function get_remote_plugin_info() {
        $cache_key = 'bewerberboerse_remote_info';
        $info = get_transient($cache_key);
        
        if (false === $info) {
            if ($this->use_github) {
                $info = $this->get_github_plugin_info();
            } else {
                $response = wp_remote_get($this->api_url . '/info.json', array(
                    'timeout' => 10,
                    'headers' => array(
                        'Accept' => 'application/json'
                    )
                ));
                
                if (is_wp_error($response)) {
                    return false;
                }
                
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if ($data) {
                    $info = (object) array(
                        'name' => isset($data['name']) ? $data['name'] : 'Bewerberbörse',
                        'slug' => $this->plugin_slug,
                        'version' => isset($data['version']) ? $data['version'] : '',
                        'author' => isset($data['author']) ? $data['author'] : 'EMA',
                        'requires' => isset($data['requires']) ? $data['requires'] : '5.0',
                        'tested' => isset($data['tested']) ? $data['tested'] : get_bloginfo('version'),
                        'last_updated' => isset($data['last_updated']) ? $data['last_updated'] : '',
                        'homepage' => isset($data['homepage']) ? $data['homepage'] : '',
                        'download_link' => isset($data['download_link']) ? $data['download_link'] : '',
                        'sections' => array(
                            'description' => isset($data['description']) ? $data['description'] : '',
                            'changelog' => isset($data['changelog']) ? $data['changelog'] : ''
                        ),
                        'banners' => isset($data['banners']) ? $data['banners'] : array()
                    );
                } else {
                    return false;
                }
            }
            
            if ($info) {
                set_transient($cache_key, $info, 12 * HOUR_IN_SECONDS);
            }
        }
        
        return $info;
    }
    
    private function get_github_plugin_info() {
        $response = wp_remote_get($this->api_url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress-Bewerberboerse-Plugin'
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data) {
            return false;
        }
        
        $download_url = '';
        if (isset($data['assets']) && is_array($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if (isset($asset['browser_download_url']) && 
                    pathinfo($asset['browser_download_url'], PATHINFO_EXTENSION) === 'zip') {
                    $download_url = $asset['browser_download_url'];
                    break;
                }
            }
        }
        
        if (empty($download_url)) {
            $download_url = isset($data['zipball_url']) ? $data['zipball_url'] : '';
        }
        
        $repo_parts = explode(':', $this->github_repo);
        $repo = $repo_parts[0];
        $version = isset($data['tag_name']) ? ltrim($data['tag_name'], 'v') : '';
        
        return (object) array(
            'name' => isset($data['name']) ? $data['name'] : 'Bewerberbörse',
            'slug' => $this->plugin_slug,
            'version' => $version,
            'author' => 'EMA',
            'requires' => '5.0',
            'tested' => get_bloginfo('version'),
            'last_updated' => isset($data['published_at']) ? $data['published_at'] : '',
            'homepage' => 'https://github.com/' . $repo,
            'download_link' => $download_url,
            'sections' => array(
                'description' => 'Plugin zur Anzeige von Stellenanzeigen und Bewerbungen von Arbeitssuchenden',
                'changelog' => isset($data['body']) ? $data['body'] : ''
            ),
            'banners' => array()
        );
    }
    
    private function get_download_url($version) {
        if ($this->use_github) {
            $response = wp_remote_get($this->api_url, array(
                'timeout' => 10,
                'headers' => array(
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'WordPress-Bewerberboerse-Plugin'
                )
            ));
            
            if (!is_wp_error($response)) {
                $code = wp_remote_retrieve_response_code($response);
                if ($code === 200) {
                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body, true);
                    
                    if (isset($data['assets']) && is_array($data['assets'])) {
                        foreach ($data['assets'] as $asset) {
                            if (isset($asset['browser_download_url']) && 
                                pathinfo($asset['browser_download_url'], PATHINFO_EXTENSION) === 'zip') {
                                return $asset['browser_download_url'];
                            }
                        }
                    }
                    
                    if (isset($data['zipball_url'])) {
                        return $data['zipball_url'];
                    }
                }
            }
        }
        
        return apply_filters('bewerberboerse_download_url', $this->api_url . '/download/bewerberboerse-' . $version . '.zip', $version);
    }
    
    private function get_plugin_info_url() {
        return apply_filters('bewerberboerse_info_url', 'https://example.com/bewerberboerse');
    }
    
    public function after_install($true, $hook_extra, $result) {
        global $wp_filesystem;
        
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin_file) {
            $plugin_folder = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname($this->plugin_file);
            $wp_filesystem->move($result['destination'], $plugin_folder);
            $result['destination'] = $plugin_folder;
        }
        
        return $result;
    }
    
    public function update_message($plugin_data, $response) {
        if (isset($response->new_version)) {
            echo '<br /><strong>' . __('Важно:', 'bewerberboerse') . '</strong> ' . __('Перед обновлением рекомендуется сделать резервную копию сайта.', 'bewerberboerse');
        }
    }
    
    public static function clear_update_cache() {
        delete_transient('bewerberboerse_remote_version');
        delete_transient('bewerberboerse_remote_info');
        delete_site_transient('update_plugins');
    }
}

