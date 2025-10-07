<?php
namespace WP2\Update\Admin\Pages;

use WP2\Update\Core\Connection\Init as Connection;
use WP2\Update\Utils\SharedUtils;
use WP2\Update\Core\Updates\ThemeUpdater;
use WP2\Update\Core\Updates\PluginUpdater;
use WP2\Update\Utils\Logger;

/**
 * Renders the "Version History" tab content.
 */
class PackageHistoryPage {
    private $connection;
    private $utils;

    /**
     * Constructor.
     */
    public function __construct( Connection $connection, SharedUtils $utils ) {
        $this->connection = $connection;
        $this->utils      = $utils;
    }

    /**
     * Renders the tab content.
     */
    public function render( ?string $type, ?string $slug ) {
        if ( ! $slug || ! $type ) {
            echo '<h2>' . esc_html__( 'Version History', 'wp2-update' ) . '</h2><p>' . esc_html__( 'No managed item selected.', 'wp2-update' ) . '</p>';
            return;
        }

        $is_theme  = ( 'theme' === $type );
        $items     = $is_theme ? $this->connection->get_managed_themes() : $this->connection->get_managed_plugins();
        $item_data = $items[ $slug ] ?? null;
        if ( ! $item_data ) {
            echo '<h2>' . esc_html__( 'Version History', 'wp2-update' ) . '</h2><p>' . esc_html__( 'Selected item not found.', 'wp2-update' ) . '</p>';
            return;
        }

        $releases  = $this->utils->get_all_releases( $item_data['app_slug'], $item_data['repo'] );
        // Ensure the required file is included before calling get_plugin_data
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $installed = null;
        if ( ! $is_theme ) {
            $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $slug );
            if ( isset( $plugin_data['Version'] ) ) {
                $installed = $plugin_data['Version'];
            } else {
                Logger::log( 'Failed to retrieve plugin version for slug: ' . $slug, 'warning', 'plugin' );
            }
        } else {
            $installed = wp_get_theme( $slug )->get( 'Version' );
        }

        // Ensure all dynamic content is properly validated and sanitized.
        $slug = sanitize_text_field( $slug );
        $type = sanitize_text_field( $type );

        // Sanitize item data before usage.
        $item_data['name'] = esc_html( $item_data['name'] );

        ?>
        <h2><?php echo esc_html( $item_data['name'] . ' ' . __( 'Version History', 'wp2-update' ) ); ?></h2>
        <table class="wp2-data-table" aria-label="Version History Table">
            <thead>
                <tr>
                    <th scope="col"><label for="version-column">Version</label></th>
                    <th scope="col"><label for="release-date-column">Release Date</label></th>
                    <th scope="col"><label for="action-column">Action</label></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $releases ) ) : ?>
                    <tr><td colspan="3"><?php esc_html_e( 'No releases found.', 'wp2-update' ); ?></td></tr>
                <?php else : foreach ( $releases as $release ) :
                        $norm_release_v = $this->normalize_version( $release['tag_name'] ?? '' );
                        $norm_installed_v = $this->normalize_version( $installed );
                        $is_current   = version_compare( $norm_release_v, $norm_installed_v, '==' );
                        $is_older     = version_compare( $norm_release_v, $norm_installed_v, '<' );

                        $button_text = __('Install', 'wp2-update');
                        $button_class = 'wp2-button--primary'; // Default to primary for upgrades

                        if (version_compare($norm_release_v, $norm_installed_v, '==')) {
                            $button_text = __('Re-install', 'wp2-update');
                            $button_class = 'wp2-button--secondary';
                        } elseif (version_compare($norm_release_v, $norm_installed_v, '<')) {
                            $button_text = __('Rollback', 'wp2-update');
                            $button_class = 'wp2-button--destructive'; // A new class for caution
                        }

                        // Sanitize release data.
                        $release['tag_name'] = esc_html( $release['tag_name'] ?? 'Unknown' );
                        $release['published_at'] = esc_html( isset( $release['published_at'] ) ? wp_date( 'M j, Y', strtotime( $release['published_at'] ) ) : 'Unknown' );

                        // Sanitize button attributes.
                        $button_text = esc_html( $button_text );
                        $button_class = esc_attr( $button_class );
                        ?>
                        <tr class="<?php echo $is_current ? 'current-version' : ''; ?>">
                            <td data-label="Version"><strong><?php echo esc_html( $release['tag_name'] ?? 'Unknown' ); ?></strong></td>
                            <td data-label="Date"><?php echo esc_html( isset( $release['published_at'] ) ? wp_date( 'M j, Y', strtotime( $release['published_at'] ) ) : 'Unknown' ); ?></td>
                            <td data-label="Action" class="cell-action">
                                <?php if ( $this->utils->get_zip_url_from_release( $release ) ) : ?>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                        <input type="hidden" name="action" value="wp2_<?php echo esc_attr( $type ); ?>_install">
                                        <input type="hidden" name="slug" value="<?php echo esc_attr( $slug ); ?>">
                                        <input type="hidden" name="version" value="<?php echo esc_attr( $release['tag_name'] ?? '' ); ?>">
                                        <?php wp_nonce_field( 'wp2_install_' . $type . '_' . $slug . '_' . ( $release['tag_name'] ?? '' ) ); ?>
                                        <button type="submit" class="wp2-button wp2-button--small <?php echo esc_attr($button_class); ?>"><?php echo esc_html($button_text); ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Normalizes a version string.
     *
     * @param string $version The version string to normalize.
     * @return string The normalized version string.
     */
    private function normalize_version($version) {
        return $this->utils->normalize_version($version);
    }
}
