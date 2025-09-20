<?php
namespace WP2\Update\Admin\Pages;

use WP2\Update\Core\Connection\Init as Connection;
use WP2\Update\Core\Utils\Init as SharedUtils;
use WP2\Update\Core\Updates\ThemeUpdater;
use WP2\Update\Core\Updates\PluginUpdater;

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
        $installed = $is_theme ? wp_get_theme( $slug )->get( 'Version' ) : get_plugin_data( WP_PLUGIN_DIR . '/' . $slug )['Version'];
        ?>
        <h2><?php echo esc_html( $item_data['name'] . ' ' . __( 'Version History', 'wp2-update' ) ); ?></h2>
        <table class="wp2-data-table">
            <thead><tr><th>Version</th><th>Release Date</th><th>Action</th></tr></thead>
            <tbody>
                <?php if ( empty( $releases ) ) : ?>
                    <tr><td colspan="3"><?php esc_html_e( 'No releases found.', 'wp2-update' ); ?></td></tr>
                <?php else : foreach ( $releases as $release ) :
                        $norm_release_v = $this->utils->normalize_version( $release['tag_name'] ?? '' );
                        $norm_installed_v = $this->utils->normalize_version( $installed );
                        $is_current   = version_compare( $norm_release_v, $norm_installed_v, '==' );
                        $is_older     = version_compare( $norm_release_v, $norm_installed_v, '<' );
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
                                        <button type="submit" class="wp2-button wp2-button--small"><?php echo $is_current ? 'Re-install' : ( $is_older ? 'Rollback' : 'Upgrade' ); ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php
    }
}
