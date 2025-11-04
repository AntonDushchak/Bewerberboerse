<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('bewerberboerse_settings');
        do_settings_sections('bewerberboerse_settings');
        ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="bewerberboerse_display_page"><?php _e('Display Page', 'bewerberboerse'); ?></label>
                </th>
                <td>
                    <?php
                    $pages = get_pages();
                    $selected_page = get_option('bewerberboerse_display_page');
                    ?>
                    <select name="bewerberboerse_display_page" id="bewerberboerse_display_page">
                        <option value=""><?php _e('-- Select Page --', 'bewerberboerse'); ?></option>
                        <?php foreach ($pages as $page): ?>
                            <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($selected_page, $page->ID); ?>>
                                <?php echo esc_html($page->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php _e('Select the page where you will add the [bewerberboerse] shortcode', 'bewerberboerse'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="bewerberboerse_api_key"><?php _e('API Key', 'bewerberboerse'); ?></label>
                </th>
                <td>
                    <input type="text" name="bewerberboerse_api_key" id="bewerberboerse_api_key" 
                           value="<?php echo esc_attr(get_option('bewerberboerse_api_key')); ?>" 
                           class="regular-text" placeholder="your-api-key-here">
                    <p class="description">
                        <?php _e('API key for receiving data from job-board-integration plugin', 'bewerberboerse'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php _e('API Endpoint', 'bewerberboerse'); ?></label>
                </th>
                <td>
                    <code><?php echo esc_url(rest_url('bewerberboerse/v1/applications/receive')); ?></code>
                    <p class="description">
                        <?php _e('Use this URL in job-board-integration plugin settings', 'bewerberboerse'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(); ?>
    </form>
    
    <hr>
    
    <h2><?php _e('Instructions', 'bewerberboerse'); ?></h2>
    <ol>
        <li><?php _e('Select a page where you want to display the job board', 'bewerberboerse'); ?></li>
        <li><?php _e('Edit that page and add the shortcode: <code>[bewerberboerse]</code>', 'bewerberboerse'); ?></li>
        <li><?php _e('Optionally configure an external API URL to fetch applications from your Next.js backend', 'bewerberboerse'); ?></li>
        <li><?php _e('Save and view your page to see the job board', 'bewerberboerse'); ?></li>
    </ol>
</div>


