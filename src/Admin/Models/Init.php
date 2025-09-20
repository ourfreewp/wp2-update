<?php
namespace WP2\Update\Admin\Models;

/**
 * Encapsulates the registration of Custom Post Types and their associated
 * meta boxes, admin columns, and metadata handling.
 */
final class Init {

    /**
     * Registers all necessary WordPress hooks for the models.
     */
    public function register(): void {
        add_action('init', [$this, 'register_cpts']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_metadata'], 10, 2);

        // Custom columns for the 'wp2_repository' CPT
        add_filter('manage_wp2_repository_posts_columns', [$this, 'add_repository_custom_columns']);
        add_action('manage_wp2_repository_posts_custom_column', [$this, 'populate_repository_custom_columns'], 10, 2);
    }

    /**
     * Registers the Custom Post Types for the plugin.
     */
    public function register_cpts(): void {
        register_post_type('wp2_github_app', [
            'label'         => __('GitHub Apps', 'wp2-update'),
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => 'wp2-update-overview',
            'menu_icon'     => 'dashicons-github',
            'supports'      => ['title'],
            'rewrite'       => false,
        ]);
        register_post_type('wp2_repository', [
            'label'         => __('Repositories', 'wp2-update'),
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => 'wp2-update-overview',
            'menu_icon'     => 'dashicons-book-alt',
            'supports'      => ['title', 'editor'],
            'rewrite'       => false,
            'capabilities'  => [
                'create_posts' => 'do_not_allow', // Disallow manual creation
            ],
            'map_meta_cap' => true, // Ensures the 'create_posts' capability is respected
        ]);
    }

    /**
     * Adds meta boxes for the Custom Post Types.
     */
    public function add_meta_boxes(): void {
        add_meta_box(
            'wp2_github_app_credentials',
            __('GitHub App Credentials', 'wp2-update'),
            [$this, 'render_github_app_meta_box'],
            'wp2_github_app',
            'normal',
            'high'
        );
        add_meta_box(
            'wp2_repository_details',
            __('Repository Details', 'wp2-update'),
            [$this, 'render_repository_meta_box'],
            'wp2_repository',
            'normal',
            'high'
        );
    }

    /**
     * Renders the meta box for the 'wp2_github_app' CPT.
     *
     * @param \WP_Post $post The current post object.
     */
    public function render_github_app_meta_box(\WP_Post $post): void {
        // Add a nonce field for security.
        wp_nonce_field('wp2_github_app_save_meta', 'wp2_github_app_nonce');

        $app_id = get_post_meta($post->ID, '_wp2_app_id', true);
        $installation_id = get_post_meta($post->ID, '_wp2_installation_id', true);
        $private_key = get_post_meta($post->ID, '_wp2_private_key', true);
        ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th><label for="wp2_app_id"><?php esc_html_e('App ID', 'wp2-update'); ?></label></th>
                    <td><input type="text" id="wp2_app_id" name="_wp2_app_id" value="<?php echo esc_attr($app_id); ?>" class="widefat" /></td>
                </tr>
                <tr>
                    <th><label for="wp2_installation_id"><?php esc_html_e('Installation ID', 'wp2-update'); ?></label></th>
                    <td><input type="text" id="wp2_installation_id" name="_wp2_installation_id" value="<?php echo esc_attr($installation_id); ?>" class="widefat" /></td>
                </tr>
                <tr>
                    <th><label for="wp2_private_key"><?php esc_html_e('Private Key (.pem)', 'wp2-update'); ?></label></th>
                    <td><textarea id="wp2_private_key" name="_wp2_private_key" class="widefat" rows="10"><?php echo esc_textarea($private_key); ?></textarea></td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * Renders the meta box for the 'wp2_repository' CPT.
     *
     * @param \WP_Post $post The current post object.
     */
    public function render_repository_meta_box(\WP_Post $post): void {
        $managing_app_id = get_post_meta($post->ID, '_managing_app_post_id', true);
        $health_status = get_post_meta($post->ID, '_health_status', true);
        $health_message = get_post_meta($post->ID, '_health_message', true);
        $last_synced = get_post_meta($post->ID, '_last_synced_timestamp', true);
        ?>
        <p><?php esc_html_e('This data is automatically managed by the background sync process.', 'wp2-update'); ?></p>
        <table class="form-table">
            <tbody>
                <tr>
                    <th><?php esc_html_e('Managing App', 'wp2-update'); ?></th>
                    <td><?php echo $managing_app_id ? esc_html(get_the_title($managing_app_id)) : 'N/A'; ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Health Status', 'wp2-update'); ?></th>
                    <td>
                        <strong style="color: <?php echo ($health_status === 'ok') ? '#28a745' : '#dc3545'; ?>;">
                            <?php echo esc_html(ucfirst($health_status)); ?>
                        </strong>
                        <p><em><?php echo esc_html($health_message); ?></em></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Last Synced', 'wp2-update'); ?></th>
                    <td><?php echo $last_synced ? esc_html(wp_date('Y-m-d H:i:s', (int) $last_synced)) : 'Never'; ?></td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * Handles saving the metadata for our CPTs.
     *
     * @param int $post_id The ID of the post being saved.
     * @param \WP_Post $post The post object.
     */
    public function save_metadata(int $post_id, \WP_Post $post): void {
        if ($post->post_type === 'wp2_github_app') {
            // Verify nonce
            if (!isset($_POST['wp2_github_app_nonce']) || !wp_verify_nonce($_POST['wp2_github_app_nonce'], 'wp2_github_app_save_meta')) {
                return;
            }

            // Check user permissions
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }

            // Sanitize and save GitHub App fields
            update_post_meta($post_id, '_wp2_app_id', sanitize_text_field($_POST['_wp2_app_id'] ?? ''));
            update_post_meta($post_id, '_wp2_installation_id', sanitize_text_field($_POST['_wp2_installation_id'] ?? ''));
            // Use sanitize_textarea_field for multi-line private key
            update_post_meta($post_id, '_wp2_private_key', sanitize_textarea_field($_POST['_wp2_private_key'] ?? ''));
        }
    }

    /**
     * Defines custom columns for the Repository CPT list table.
     */
    public function add_repository_custom_columns(array $columns): array {
        $new_columns = [];
        foreach ($columns as $key => $title) {
            $new_columns[$key] = $title;
            if ($key === 'title') {
                $new_columns['managing_app'] = __('Managing App', 'wp2-update');
                $new_columns['health_status'] = __('Health Status', 'wp2-update');
                $new_columns['last_synced'] = __('Last Synced', 'wp2-update');
            }
        }
        return $new_columns;
    }

    /**
     * Populates the custom columns with metadata.
     */
    public function populate_repository_custom_columns(string $column, int $post_id): void {
        switch ($column) {
            case 'managing_app':
                $app_post_id = get_post_meta($post_id, '_managing_app_post_id', true);
                echo $app_post_id ? esc_html(get_the_title($app_post_id)) : 'N/A';
                break;
            case 'health_status':
                $status = get_post_meta($post_id, '_health_status', true);
                $message = get_post_meta($post_id, '_health_message', true);
                if ($status === 'ok') {
                    echo '<span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="' . esc_attr($message) . '"></span>';
                } else {
                    echo '<span class="dashicons dashicons-warning" style="color: #d63638;" title="' . esc_attr($message) . '"></span>';
                }
                break;
            case 'last_synced':
                $timestamp = get_post_meta($post_id, '_last_synced_timestamp', true);
                echo $timestamp ? esc_html(wp_date('Y-m-d H:i:s', (int) $timestamp)) : 'Never';
                break;
        }
    }
}

