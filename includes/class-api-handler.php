<?php
if (!defined('ABSPATH')) {
    exit;
}

class bewerberboerse_API_Handler {
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'), 20);
    }
    
    public function register_routes() {
        if (!function_exists('wp_get_theme') || !did_action('after_setup_theme')) {
            return;
        }
        
        error_log('Bewerberboerse: Registering REST API routes');
        
        register_rest_route('bewerberboerse/v1', '/applications', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_applications'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('bewerberboerse/v1', '/applications/check/(?P<hash>[a-z0-9]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'check_application'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('bewerberboerse/v1', '/applications/(?P<id>[a-z0-9]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_application'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('bewerberboerse/v1', '/applications/receive', array(
            'methods' => 'POST',
            'callback' => array($this, 'receive_application'),
            'permission_callback' => array($this, 'check_api_key')
        ));
        error_log('Bewerberboerse: Registered /applications/receive route');
        
        register_rest_route('bewerberboerse/v1', '/templates/receive', array(
            'methods' => 'POST',
            'callback' => array($this, 'receive_template'),
            'permission_callback' => array($this, 'check_api_key')
        ));
        
        register_rest_route('bewerberboerse/v1', '/templates', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_templates'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('bewerberboerse/v1', '/contact-request', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_contact_request'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('bewerberboerse/v1', '/contact-requests/check', array(
            'methods' => 'GET',
            'callback' => array($this, 'check_contact_requests'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('bewerberboerse/v1', '/contact-requests', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_contact_requests'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('bewerberboerse/v1', '/contact-requests/delete-all', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_all_contact_requests'),
            'permission_callback' => '__return_true'
        ));
    }
    
    public function check_api_key($request) {
        $api_key = get_option('bewerberboerse_api_key');
        
        if (empty($api_key)) {
            return true;
        }
        
        $provided_key = $request->get_header('X-API-Key');
        
        if (!$provided_key) {
            error_log('Bewerberboerse: Missing X-API-Key header');
            return false;
        }
        
        if ($provided_key === $api_key) {
            return true;
        }
        
        error_log('Bewerberboerse: Invalid API key provided: ' . $provided_key);
        return false;
    }
    
    private function get_active_template() {
        global $wpdb;
        $templates_table = $wpdb->prefix . 'bewerberboerse_templates';
        $template = $wpdb->get_row(
            "SELECT * FROM $templates_table WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 1"
        );
        
        if ($template && $template->fields) {
            $template->fields = json_decode($template->fields, true);
        }
        
        return $template;
    }
    
    private function migrate_application_data($filled_data, $template = null) {
        if (!$template) {
            $template = $this->get_active_template();
        }
        
        if (!$template || !is_array($template->fields)) {
            return $this->filter_empty_values($filled_data);
        }
        
        $migrated_data = array();
        $template_field_names = array();
        
        foreach ($template->fields as $field) {
            if (is_array($field)) {
                $field_name = isset($field['name']) ? $field['name'] : 
                             (isset($field['field_id']) ? $field['field_id'] : 
                             (isset($field['id']) ? $field['id'] : null));
                if ($field_name) {
                    $template_field_names[] = $field_name;
                }
            } elseif (is_string($field)) {
                $template_field_names[] = $field;
            }
        }
        
        foreach ($template_field_names as $field_name) {
            if (isset($filled_data[$field_name])) {
                $value = $filled_data[$field_name];
                
                if ($this->is_empty_or_default($value, $field_name, $template->fields)) {
                    continue;
                }
                
                $migrated_data[$field_name] = $value;
            }
        }
        
        return $migrated_data;
    }
    
    private function is_empty_or_default($value, $field_name, $template_fields) {
        if ($value === null || $value === '' || $value === false) {
            return true;
        }
        
        if (is_array($value)) {
            if (empty($value)) {
                return true;
            }
            $has_non_empty = false;
            foreach ($value as $item) {
                if ($item !== null && $item !== '' && $item !== false) {
                    if (is_array($item)) {
                        if (!empty($item)) {
                            $has_non_empty = true;
                            break;
                        }
                    } else {
                        $has_non_empty = true;
                        break;
                    }
                }
            }
            return !$has_non_empty;
        }
        
        $field_config = null;
        foreach ($template_fields as $field) {
            if (is_array($field)) {
                $field_name_check = isset($field['name']) ? $field['name'] : 
                                  (isset($field['field_id']) ? $field['field_id'] : 
                                  (isset($field['id']) ? $field['id'] : null));
                if ($field_name_check === $field_name) {
                    $field_config = $field;
                    break;
                }
            }
        }
        
        if ($field_config && isset($field_config['default'])) {
            $default_value = $field_config['default'];
            if ($value === $default_value || (is_string($value) && trim($value) === trim($default_value))) {
                return true;
            }
        }
        
        return false;
    }
    
    private function filter_empty_values($data) {
        $filtered = array();
        foreach ($data as $key => $value) {
            if ($value === null || $value === '' || $value === false) {
                continue;
            }
            if (is_array($value) && empty($value)) {
                continue;
            }
            $filtered[$key] = $value;
        }
        return $filtered;
    }
    
    public function get_applications($request) {
        $applications = bewerberboerse_Database::get_applications();
        $active_template = $this->get_active_template();
        
        $formatted_applications = array();
        foreach ($applications as $app) {
            $filled_data = json_decode($app->filled_data, true);
            if ($filled_data) {
                $migrated_data = $this->migrate_application_data($filled_data, $active_template);
                
                $formatted_applications[] = array_merge(
                    array(
                        'id' => $app->id,
                        'hash' => $app->hash,
                        'isActive' => (bool)$app->is_active,
                        'createdAt' => $app->created_at,
                        'updatedAt' => $app->updated_at
                    ),
                    $migrated_data
                );
            }
        }
        
        return rest_ensure_response($formatted_applications);
    }
    
    public function get_application($request) {
        $id = $request['id'];
        
        $application = bewerberboerse_Database::get_application_by_hash($id);
        
        if (!$application) {
            $application = bewerberboerse_Database::get_application($id);
        }
        
        if (!$application) {
            return new WP_Error('not_found', 'Application not found', array('status' => 404));
        }
        
        $filled_data = json_decode($application->filled_data, true);
        $active_template = $this->get_active_template();
        
        $migrated_data = $this->migrate_application_data($filled_data ? $filled_data : array(), $active_template);
        
        $response_data = array_merge(
            array(
                'id' => $application->id,
                'hash' => $application->hash,
                'isActive' => (bool)$application->is_active,
                'createdAt' => $application->created_at,
                'updatedAt' => $application->updated_at
            ),
            $migrated_data
        );
        
        return rest_ensure_response($response_data);
    }
    
    public function check_application($request) {
        $hash = $request['hash'];
        
        $application = bewerberboerse_Database::get_application_by_hash($hash);
        
        if (!$application) {
            return rest_ensure_response(array(
                'exists' => false,
                'hash' => $hash
            ));
        }
        
        return rest_ensure_response(array(
            'exists' => true,
            'hash' => $application->hash,
            'id' => $application->id,
            'isActive' => (bool)$application->is_active,
            'createdAt' => $application->created_at,
            'updatedAt' => $application->updated_at
        ));
    }
    
    public function receive_application($request) {
        error_log('Bewerberboerse: receive_application called');
        $data = $request->get_json_params();
        error_log('Bewerberboerse: Received data: ' . json_encode($data));
        
        if (empty($data)) {
            error_log('Bewerberboerse: Missing request data');
            return new WP_Error('bad_request', 'Missing request data', array('status' => 400));
        }
        
        if (!isset($data['hash'])) {
            return new WP_Error('bad_request', 'Missing required field: hash', array('status' => 400));
        }
        
        $action = isset($data['action']) ? $data['action'] : 'create';
        if ($action === 'delete') {
            return $this->delete_application($data['hash']);
        }
        
        if (!isset($data['filled_data']) || !is_array($data['filled_data'])) {
            return new WP_Error('bad_request', 'Missing or invalid filled_data', array('status' => 400));
        }
        
        $template_id = isset($data['template_id']) ? $data['template_id'] : null;
        $template = null;
        
        if ($template_id) {
            global $wpdb;
            $templates_table = $wpdb->prefix . 'bewerberboerse_templates';
            $template = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $templates_table WHERE template_id = %s AND is_active = 1",
                    $template_id
                )
            );
            
            if (!$template) {
                error_log('Bewerberboerse: Template not found (template_id: ' . $template_id . '), proceeding without template validation');
            }
        }
        
        $application_data = array(
            'templateId' => $template_id,
            'hash' => $data['hash'],
            'userId' => isset($data['user_id']) ? intval($data['user_id']) : 0,
            'wordpressApplicationId' => isset($data['wordpress_application_id']) ? intval($data['wordpress_application_id']) : null,
            'isActive' => isset($data['is_active']) ? intval($data['is_active']) : 1,
            'filledData' => $data['filled_data']
        );
        
        $result = bewerberboerse_Database::save_application($application_data);
        
        if ($result) {
            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Application received successfully',
                'id' => $result,
                'template_id' => $template_id,
                'hash' => $data['hash']
            ));
        } else {
            return new WP_Error('save_failed', 'Failed to save application', array('status' => 500));
        }
    }
    
    public function delete_application($hash) {
        error_log('Bewerberboerse: delete_application called for hash: ' . $hash);
        
        if (empty($hash)) {
            return new WP_Error('bad_request', 'Missing required field: hash', array('status' => 400));
        }
        
        $application = bewerberboerse_Database::get_application_by_hash($hash);
        
        if (!$application) {
            error_log('Bewerberboerse: Application not found for hash: ' . $hash);
            return new WP_Error('not_found', 'Application not found', array('status' => 404));
        }
        
        $result = bewerberboerse_Database::delete_application_by_hash($hash);
        
        if ($result) {
            error_log('Bewerberboerse: Application deleted successfully for hash: ' . $hash);
            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Application deleted successfully',
                'hash' => $hash
            ));
        } else {
            error_log('Bewerberboerse: Failed to delete application for hash: ' . $hash);
            return new WP_Error('delete_failed', 'Failed to delete application', array('status' => 500));
        }
    }
    
    public function handle_contact_request($request) {
        $data = $request->get_json_params();
        
        if (!isset($data['application_hash']) || !isset($data['name']) || !isset($data['email'])) {
            return new WP_Error('bad_request', 'Missing required fields: application_hash, name, email', array('status' => 400));
        }
        
        $result = bewerberboerse_Database::save_contact_request($data);
        
        if ($result) {
            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Contact request saved successfully',
                'id' => $result
            ));
        } else {
            return new WP_Error('save_failed', 'Failed to save contact request', array('status' => 500));
        }
    }
    
    public function check_contact_requests($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bewerberboerse_contact_requests';
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        return rest_ensure_response(array(
            'has_data' => $count > 0,
            'count' => intval($count)
        ));
    }
    
    public function get_contact_requests($request) {
        $limit = isset($request['limit']) ? intval($request['limit']) : 100;
        $offset = isset($request['offset']) ? intval($request['offset']) : 0;
        
        $requests = bewerberboerse_Database::get_contact_requests($limit, $offset);
        
        return rest_ensure_response($requests);
    }
    
    public function delete_all_contact_requests($request) {
        $result = bewerberboerse_Database::delete_all_contact_requests();
        
        if ($result !== false) {
            return rest_ensure_response(array(
                'success' => true,
                'message' => 'All contact requests deleted successfully',
                'deleted_count' => $result
            ));
        } else {
            return new WP_Error('delete_failed', 'Failed to delete contact requests', array('status' => 500));
        }
    }
    
    public function receive_template($request) {
        $data = $request->get_json_params();
        
        if (empty($data)) {
            return new WP_Error('bad_request', 'Missing request data', array('status' => 400));
        }
        
        $result = bewerberboerse_Database::save_template($data);
        
        if ($result) {
            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Template received successfully',
                'id' => $result
            ));
        } else {
            return new WP_Error('save_failed', 'Failed to save template', array('status' => 500));
        }
    }
    
    public function get_templates($request) {
        global $wpdb;
        
        $templates_table = $wpdb->prefix . 'bewerberboerse_templates';
        $templates = $wpdb->get_results(
            "SELECT * FROM $templates_table WHERE is_active = 1 ORDER BY created_at DESC"
        );
        
        $formatted_templates = array();
        foreach ($templates as $template) {
            $formatted_templates[] = array(
                'id' => $template->id,
                'template_id' => $template->template_id,
                'template_name' => $template->template_name,
                'fields' => $template->fields,
                'filterable_fields' => $template->filterable_fields,
                'is_active' => (bool)$template->is_active,
                'created_at' => $template->created_at,
                'updated_at' => $template->updated_at
            );
        }
        
        return rest_ensure_response($formatted_templates);
    }
} 