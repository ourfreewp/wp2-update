<?php
namespace WP2\Update\Admin\Pages;

use WP2\Update\Core\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\API\Service as GitHubService;

/**
 * Renders the "Settings" page content for adding/editing GitHub Apps.
 */
class PackageSettingsPage {
    private GitHubService $github_service;

    public function __construct(GitHubService $github_service) {
        $this->github_service = $github_service;
    }

    public function render() {
        $app_post_id = isset($_GET['app_id']) ? intval($_GET['app_id']) : null;
        $app_post = $app_post_id ? get_post($app_post_id) : null;
        
        // Only render the form if an app post ID is provided for editing or for adding a new one (post_id = 0)
        if ($app_post_id && $app_post) {
            $app_id_meta = get_post_meta($app_post->ID, '_wp2_app_id', true);
            $installation_id_meta = get_post_meta($app_post->ID, '_wp2_installation_id', true);
            $private_key_meta = get_post_meta($app_post->ID, '_wp2_private_key', true);
            $webhook_secret_meta = get_post_meta($app_post->ID, '_wp2_webhook_secret', true);
        } else {
            // Handle new app creation.
            $app_id_meta = '';
            $installation_id_meta = '';
            $private_key_meta = '';
            $webhook_secret_meta = '';
        }

        ?>
        <div class="wrap wp2-update-page">
            <div class="wp2-update-header">
                <h1><?php echo esc_html($app_post ? 'Edit ' . $app_post->post_title . ' App' : 'Add New GitHub App'); ?></h1>
                <p class="description"><?php esc_html_e( 'Configure your GitHub App credentials.', 'wp2-update' ); ?></p>
            </div>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="wp2_save_github_app">
                <input type="hidden" name="app_post_id" value="<?php echo esc_attr($app_post_id); ?>">
                <?php wp_nonce_field('wp2_save_github_app', 'wp2_github_app_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="post_title"><?php esc_html_e('App Name', 'wp2-update'); ?></label></th>
                        <td><input type="text" name="post_title" id="post_title" value="<?php echo esc_attr($app_post ? $app_post->post_title : ''); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="wp2_app_id"><?php esc_html_e('App ID', 'wp2-update'); ?></label></th>
                        <td><input type="text" name="_wp2_app_id" id="wp2_app_id" value="<?php echo esc_attr($app_id_meta); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="wp2_installation_id"><?php esc_html_e('Installation ID', 'wp2-update'); ?></label></th>
                        <td><input type="text" name="_wp2_installation_id" id="wp2_installation_id" value="<?php echo esc_attr($installation_id_meta); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="wp2_private_key"><?php esc_html_e('Private Key (.pem)', 'wp2-update'); ?></label></th>
                        <td><textarea name="_wp2_private_key" id="wp2_private_key" rows="10" class="large-text code" required><?php echo esc_textarea($private_key_meta); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="wp2_webhook_secret"><?php esc_html_e('Webhook Secret', 'wp2-update'); ?></label></th>
                        <td><input type="text" name="_wp2_webhook_secret" id="wp2_webhook_secret" value="<?php echo esc_attr($webhook_secret_meta); ?>" class="regular-text"></td>
                    </tr>
                </table>

                <?php submit_button('Save App', 'primary', 'submit', true); ?>
            </form>
        </div>
        <?php
    }
}
