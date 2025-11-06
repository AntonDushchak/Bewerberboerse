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
            
            <tr>
                <th scope="row">
                    <label for="bewerberboerse_github_repo"><?php _e('GitHub Repository', 'bewerberboerse'); ?></label>
                </th>
                <td>
                    <input type="text" name="bewerberboerse_github_repo" id="bewerberboerse_github_repo" 
                           value="<?php echo esc_attr(get_option('bewerberboerse_github_repo', '')); ?>" 
                           class="regular-text" placeholder="username/repository">
                    <p class="description">
                        <?php _e('GitHub repository for automatic updates (e.g. username/repository). <strong>Free for public repositories!</strong> If specified, GitHub will be used instead of the regular server.', 'bewerberboerse'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="bewerberboerse_update_url"><?php _e('Update Server URL', 'bewerberboerse'); ?></label>
                </th>
                <td>
                    <input type="url" name="bewerberboerse_update_url" id="bewerberboerse_update_url" 
                           value="<?php echo esc_attr(get_option('bewerberboerse_update_url', 'https://example.com/updates/bewerberboerse')); ?>" 
                           class="regular-text" placeholder="https://example.com/updates/bewerberboerse"
                           <?php echo !empty(get_option('bewerberboerse_github_repo')) ? 'disabled' : ''; ?>>
                    <p class="description">
                        <?php _e('URL server for checking plugin updates. Used only if the GitHub repository is not specified.', 'bewerberboerse'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(); ?>
    </form>
    
    <hr>
    
    <h2><?php _e('Plugin Updates', 'bewerberboerse'); ?></h2>
    <p>
        <?php 
        printf(
            __('Текущая версия: <strong>%s</strong>', 'bewerberboerse'),
            BEWERBERBOERSE_VERSION
        );
        ?>
    </p>
    <p>
        <button type="button" id="bewerberboerse-check-updates" class="button button-secondary">
            <?php _e('Check Updates', 'bewerberboerse'); ?>
        </button>
        <span id="bewerberboerse-update-message" style="margin-left: 10px;"></span>
    </p>
    <p class="description">
        <?php _e('The plugin automatically checks for updates. You can also check for updates manually by clicking the button above.', 'bewerberboerse'); ?>
    </p>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#bewerberboerse_github_repo').on('input', function() {
            var githubRepo = $(this).val();
            var updateUrlField = $('#bewerberboerse_update_url');
            
            if (githubRepo) {
                updateUrlField.prop('disabled', true);
            } else {
                updateUrlField.prop('disabled', false);
            }
        });
        
        $('#bewerberboerse-check-updates').on('click', function() {
            var button = $(this);
            var message = $('#bewerberboerse-update-message');
            
            button.prop('disabled', true).text('<?php _e('Checking...', 'bewerberboerse'); ?>');
            message.text('');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bewerberboerse_check_updates',
                    nonce: '<?php echo wp_create_nonce('bewerberboerse_check_updates'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        message.text(response.data.message).css('color', 'green');
                        setTimeout(function() {
                            window.location.href = '<?php echo admin_url('plugins.php'); ?>';
                        }, 2000);
                    } else {
                        message.text(response.data.message || '<?php _e('Error checking updates', 'bewerberboerse'); ?>').css('color', 'red');
                    }
                },
                error: function() {
                    message.text('<?php _e('Connection error', 'bewerberboerse'); ?>').css('color', 'red');
                },
                complete: function() {
                    button.prop('disabled', false).text('<?php _e('Check Updates', 'bewerberboerse'); ?>');
                }
            });
        });
    });
    </script>
    
    <hr>
    
    <h2><?php _e('Instructions', 'bewerberboerse'); ?></h2>
    <ol>
        <li><?php _e('Select a page where you want to display the job board', 'bewerberboerse'); ?></li>
        <li><?php _e('Edit that page and add the shortcode: <code>[bewerberboerse]</code>', 'bewerberboerse'); ?></li>
        <li><?php _e('Optionally configure an external API URL to fetch applications from your Next.js backend', 'bewerberboerse'); ?></li>
        <li><?php _e('Save and view your page to see the job board', 'bewerberboerse'); ?></li>
    </ol>
</div>


