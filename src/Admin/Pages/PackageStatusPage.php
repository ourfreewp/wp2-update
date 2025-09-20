<?php
namespace WP2\Update\Admin\Pages;

use WP2\Update\Core\Connection\Init as Connection;
use WP2\Update\Core\GitHubApp\Init as GitHubApp;
use WP2\Update\Utils\SharedUtils;
use WP2\Update\Core\Health\PackageHealth;
use WP2\Update\Utils\Logger;

/**
 * Renders the "Status" tab content.
 */
class PackageStatusPage {
    private $connection;
    private $github_app;
    private $utils;

    /**
     * Constructor.
     */
    public function __construct( Connection $connection, GitHubApp $github_app, SharedUtils $utils ) {
        $this->connection = $connection;
        $this->github_app = $github_app;
        $this->utils      = $utils;
    }

    /**
     * Renders the tab content.
     */
    public function render( ?string $type, ?string $slug ) {
        if ( ! $slug || ! $type ) {
            echo '<p>' . esc_html__( 'No managed item selected.', 'wp2-update' ) . '</p>';
            return;
        }

        $is_theme   = ( 'theme' === $type );
        $items      = $is_theme ? $this->connection->get_managed_themes() : $this->connection->get_managed_plugins();
        $item_data  = $items[ $slug ] ?? null;
        if ( ! $item_data ) {
            echo '<p>' . esc_html__( 'Selected item not found.', 'wp2-update' ) . '</p>';
            return;
        }

        // Use the new, unified health check system.
        $health_checker = new PackageHealth($item_data['repo']);
        $health_status = $health_checker->get_status();
        
        $installed = null;
        if ( ! $is_theme ) {
            if ( ! function_exists( 'get_plugin_data' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $slug );
            if ( isset( $plugin_data['Version'] ) ) {
                $installed = $plugin_data['Version'];
            } else {
                Logger::log( 'Failed to retrieve plugin version for slug: ' . $slug, 'warning', 'plugin' );
            }
        } else {
            $installed = wp_get_theme( $slug )->get( 'Version' );
        }

        $updates    = $is_theme ? get_site_transient( 'update_themes' ) : get_site_transient( 'update_plugins' );
        $available  = $updates->response[ $slug ] ?? null;

        ?>
        <div class="wp2-detail-grid">
            <div>
                <h3 class="wp2-detail-label"><?php esc_html_e( 'Repository Slug', 'wp2-update' ); ?></h3>
                <p><code class="wp2-mono-text"><?php echo esc_html($item_data['repo']); ?></code></p>
            </div>
            <div>
                <h3 class="wp2-detail-label"><?php esc_html_e( 'Connection', 'wp2-update' ); ?></h3>
                <p class="wp2-status-text <?php echo $health_status['status'] === 'ok' ? 'is-success' : 'is-error'; ?>">
                    <?php echo esc_html($health_status['message']); ?>
                </p>
            </div>
            <div>
                <h3 class="wp2-detail-label"><?php esc_html_e( 'Installed Version', 'wp2-update' ); ?></h3>
                <p><code class="wp2-mono-text"><?php echo esc_html( $installed ); ?></code></p>
            </div>
        </div>

        <div class="wp2-detail-actions">
            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wp2-update-packages&force-check=1' ), 'wp2-force-check' ) ); ?>" class="wp2-btn wp2-btn--primary">
                <?php esc_html_e( 'Force Check for Updates', 'wp2-update' ); ?>
            </a>
        </div>
        <?php
    }
}
