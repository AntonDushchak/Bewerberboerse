<?php

if (!defined('ABSPATH')) {
    exit;
}

class bewerberboerse_Database {
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $templates_table = $wpdb->prefix . 'bewerberboerse_templates';
        $templates_sql = "CREATE TABLE IF NOT EXISTS $templates_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            template_id varchar(255) NOT NULL,
            template_name varchar(255) NOT NULL,
            fields longtext NOT NULL,
            filterable_fields longtext DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY template_id (template_id),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        $applications_table = $wpdb->prefix . 'bewerberboerse_applications';
        $applications_sql = "CREATE TABLE IF NOT EXISTS $applications_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            hash varchar(8) NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            template_id varchar(255) DEFAULT NULL,
            filled_data longtext NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            wordpress_application_id bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY hash (hash),
            KEY user_id (user_id),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        $contact_requests_table = $wpdb->prefix . 'bewerberboerse_contact_requests';
        $contact_requests_sql = "CREATE TABLE IF NOT EXISTS $contact_requests_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            application_hash varchar(8) NOT NULL,
            name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(50) DEFAULT NULL,
            message longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY application_hash (application_hash)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($templates_sql);
        dbDelta($applications_sql);
        dbDelta($contact_requests_sql);
    }
    
    public static function drop_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'bewerberboerse_applications';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }
    
    public static function get_applications($limit = 100, $offset = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bewerberboerse_applications';
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE is_active = 1 ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }
    
    public static function get_application($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bewerberboerse_applications';
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $id
            )
        );
    }
    
    public static function get_application_by_hash($hash) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bewerberboerse_applications';
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE hash = %s",
                $hash
            )
        );
    }
    
    public static function delete_application_by_hash($hash) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bewerberboerse_applications';
        
        $application = self::get_application_by_hash($hash);
        
        if (!$application) {
            return false;
        }
        
        $result = $wpdb->delete(
            $table_name,
            array('hash' => $hash),
            array('%s')
        );
        
        return $result !== false;
    }
    
    public static function save_application($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bewerberboerse_applications';
        
        $hash = isset($data['hash']) ? $data['hash'] : self::generate_hash();
        $user_id = isset($data['userId']) ? intval($data['userId']) : 0;
        $template_id = isset($data['templateId']) ? $data['templateId'] : null;
        $wordpress_application_id = isset($data['wordpressApplicationId']) ? intval($data['wordpressApplicationId']) : null;
        $is_active = isset($data['isActive']) ? intval($data['isActive']) : 1;
        
        $filled_data = $data['filledData'] ?? $data;
        
        unset($filled_data['hash']);
        unset($filled_data['userId']);
        unset($filled_data['templateId']);
        unset($filled_data['wordpressApplicationId']);
        unset($filled_data['isActive']);
        
        $existing = null;
        if (!empty($hash)) {
            $existing = self::get_application_by_hash($hash);
        }
        if (!$existing && !empty($wordpress_application_id)) {
            $existing = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $table_name WHERE wordpress_application_id = %d",
                    $wordpress_application_id
                )
            );
        }
        
        if ($existing) {
            $wpdb->update(
                $table_name,
                array(
                    'filled_data' => json_encode($filled_data, JSON_UNESCAPED_UNICODE),
                    'is_active' => $is_active,
                    'template_id' => $template_id,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $existing->id),
                array('%s', '%d', '%s', '%s'),
                array('%d')
            );
            return $existing->id;
        } else {
            $wpdb->insert(
                $table_name,
                array(
                    'hash' => $hash,
                    'user_id' => $user_id,
                    'template_id' => $template_id,
                    'filled_data' => json_encode($filled_data, JSON_UNESCAPED_UNICODE),
                    'is_active' => $is_active,
                    'wordpress_application_id' => $wordpress_application_id
                ),
                array('%s', '%d', '%s', '%s', '%d', '%d')
            );
            return $wpdb->insert_id;
        }
    }
    
    private static function generate_hash() {
        return strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    }
    
    public static function save_template($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bewerberboerse_templates';

        $template_id = isset($data['template_id']) ? $data['template_id'] : (isset($data['templateId']) ? $data['templateId'] : '');
        $template_name = isset($data['template_name']) ? $data['template_name'] : (isset($data['templateName']) ? $data['templateName'] : 'Untitled Template');
        $fields = isset($data['fields']) ? $data['fields'] : array();
        $filterable_fields = isset($data['filterable_fields']) ? $data['filterable_fields'] : array();
        $is_active = isset($data['isActive']) ? intval($data['isActive']) : 1;

        $fields_json = is_array($fields) ? json_encode($fields, JSON_UNESCAPED_UNICODE) : $fields;
        $filterable_fields_json = is_array($filterable_fields) ? json_encode($filterable_fields, JSON_UNESCAPED_UNICODE) : $filterable_fields;

        $wpdb->update(
            $table_name,
            array('is_active' => 0),
            array('is_active' => 1),
            array('%d'),
            array('%d')
        );

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE template_id = %s",
                $template_id
            )
        );

        if ($existing) {
            $wpdb->update(
                $table_name,
                array(
                    'template_name' => $template_name,
                    'fields' => $fields_json,
                    'filterable_fields' => $filterable_fields_json,
                    'is_active' => 1,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $existing->id),
                array('%s', '%s', '%s', '%d', '%s'),
                array('%d')
            );
            return $existing->id;
        } else {
            $wpdb->insert(
                $table_name,
                array(
                    'template_id' => $template_id,
                    'template_name' => $template_name,
                    'fields' => $fields_json,
                    'filterable_fields' => $filterable_fields_json,
                    'is_active' => 1
                ),
                array('%s', '%s', '%s', '%s', '%d')
            );
            return $wpdb->insert_id;
        }
    }
    
    public static function save_contact_request($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bewerberboerse_contact_requests';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'application_hash' => $data['application_hash'],
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => isset($data['phone']) ? $data['phone'] : null,
                'message' => isset($data['message']) ? $data['message'] : null
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
        
        return $result !== false ? $wpdb->insert_id : false;
    }
    
    public static function get_contact_requests($limit = 100, $offset = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bewerberboerse_contact_requests';
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }
    
    public static function delete_all_contact_requests() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bewerberboerse_contact_requests';
        
        return $wpdb->query("DELETE FROM $table_name");
    }
}

