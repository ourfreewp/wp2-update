<?php
namespace WP2\Update\Admin\Pages;

use WP2\Update\Core\Connection\Init as Connection;
use WP2\Update\Core\Utils\Init as SharedUtils;

class ChangelogPage {
    private $connection;
    private $utils;

    public function __construct( Connection $connection, SharedUtils $utils ) {
        $this->connection = $connection;
        $this->utils      = $utils;
    }

    public function render() {
        $packages = $this->connection->get_managed_packages();
        $current_slug = sanitize_key($_GET['package'] ?? '');
        $current_package = null;
        
        if ($current_slug) {
            foreach ($packages as $pkg) {
                if ($pkg['slug'] === $current_slug) {
                    $current_package = $pkg;
                    break;
                }
            }
        }

        ?>
        <div class="wrap wp2-update-page">
             <div class="wp2-update-header" style="margin-bottom: 1.5rem;">
                <h1><?php esc_html_e( 'Package Changelogs', 'wp2-update' ); ?></h1>
                <p class="description"><?php esc_html_e( 'See what\'s new in the latest versions of your managed themes and plugins.', 'wp2-update' ); ?></p>
            </div>
            
            <div class="wp2-changelog-container">
                <aside class="wp2-changelog-sidebar">
                    <div class="wp2-update-card">
                         <h2 class="wp2-health-header"><?php esc_html_e( 'Select a Package', 'wp2-update' ); ?></h2>
                         <ul class="wp2-package-list">
                            <?php foreach ( $packages as $pkg ) : ?>
                                <li class="<?php echo ($current_package && $current_package['slug'] === $pkg['slug']) ? 'current' : ''; ?>">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=wp2-update-changelog&package=' . $pkg['slug'])); ?>">
                                        <?php echo esc_html( $pkg['name'] ); ?>
                                        <small>(<?php echo esc_html($pkg['type']); ?>)</small>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </aside>
                <main class="wp2-changelog-main">
                     <div class="wp2-update-card">
                        <div class="wp2-tab-content" style="padding: 1.5rem;">
                        <?php if ($current_package) : 
                            $releases = $this->utils->get_all_releases($current_package['app_slug'], $current_package['repo'], 20);
                        ?>
                            <h2 class="wp2-changelog-title"><?php echo esc_html($current_package['name']); ?></h2>
                            <?php if (empty($releases)): ?>
                                <p><?php esc_html_e( 'No releases found for this package.', 'wp2-update' ); ?></p>
                            <?php else: ?>
                                <div class="wp2-releases">
                                <?php foreach ($releases as $release): ?>
                                    <div class="wp2-release">
                                        <h3>
                                            <span class="version-tag"><?php echo esc_html($release['tag_name']); ?></span> - 
                                            <span class="release-date"><?php echo esc_html( wp_date( 'M j, Y', strtotime( $release['published_at'] ) ) ); ?></span>
                                        </h3>
                                        <div class="release-body">
                                            <?php echo wp_kses_post( $this->parse_markdown($release['body']) ); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p><?php esc_html_e( 'Please select a package from the list to view its changelog.', 'wp2-update' ); ?></p>
                        <?php endif; ?>
                        </div>
                    </div>
                </main>
            </div>
        </div>
        <?php
    }

    /**
     * A simple markdown parser.
     * Note: For a more robust solution, consider a library like Parsedown.
     */
    private function parse_markdown($text) {
        $text = html_entity_decode( $text );
        $text = preg_replace( '/^### (.*)$/m', '<h4>$1</h4>', $text );
        $text = preg_replace( '/^\* (.*)$/m', '<li>$1</li>', $text );
        $text = preg_replace( '/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $text );
        $text = preg_replace( '/`(.*?)`/s', '<code>$1</code>', $text );

        // Wrap list items in <ul>
        $text = preg_replace( '/(<li>.*<\/li>)/s', '<ul>$1</ul>', $text );
        $text = str_replace( "</ul>\n<ul>", '', $text );

        return nl2br( $text );
    }
}
