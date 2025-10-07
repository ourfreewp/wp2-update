<?php
namespace WP2\Update\Admin\Pages;

use WP2\Update\Core\Connection\Init as Connection;
use WP2\Update\Core\API\GitHubApp\Init as GitHubApp;
use WP2\Update\Utils\SharedUtils;

class OverviewPage {
	private $connection;
	private $github_app;
	private $utils;

	public function __construct( Connection $connection, GitHubApp $github_app, SharedUtils $utils ) {
		$this->connection = $connection;
		$this->github_app = $github_app;
		$this->utils      = $utils;
	}

	public function render() {
		$connection_status         = $this->github_app->get_connection_status();
		$managed_packages          = $this->connection->get_managed_packages();
		$last_checked              = $this->utils->get_last_checked_time();
		$updates_available         = $this->utils->get_updates_count();
		$installation_requirements = $this->github_app->get_installation_requirements();

		$homepage_url = home_url( '/' );
		$callback_url = admin_url( 'admin.php?page=wp2-update-settings' );
		$webhook_url  = home_url( '/wp-json/wp2-update/v1/github/webhooks' );

		?>
		<div class="wrap wp2-update-page">
			<header class="wp2-update-header">
				<h1><?php echo esc_html__( 'WP2 Update Overview', 'wp2-update' ); ?></h1>
				<p class="description">
					<?php echo esc_html__( 'A central hub for managing your private GitHub theme and plugin updates.', 'wp2-update' ); ?>
				</p>
			</header>

			<div class="wp2-overview-grid">
				<div class="wp2-main-content">
					<section class="wp2-update-card" aria-labelledby="health-status-heading">
						<h2 id="health-status-heading" class="wp2-card-title">
							<?php echo esc_html__( 'Application Health At-a-Glance', 'wp2-update' ); ?>
						</h2>
						<div class="wp2-status-grid">
							<div class="wp2-status-item">
								<span class="wp2-status-icon <?php echo $connection_status['connected'] ? 'is-success' : 'is-error'; ?>"></span>
								<p class="wp2-status-label"><?php echo esc_html__( 'GitHub Connection', 'wp2-update' ); ?></p>
								<p class="wp2-status-value"><?php echo esc_html( $connection_status['message'] ); ?></p>
							</div>
							<div class="wp2-status-item">
								<span class="wp2-status-icon is-info"></span>
								<p class="wp2-status-label"><?php echo esc_html__( 'Managed Packages', 'wp2-update' ); ?></p>
								<p class="wp2-status-value"><?php echo count( $managed_packages ); ?></p>
							</div>
							<div class="wp2-status-item">
								<span class="wp2-status-icon <?php echo $updates_available > 0 ? 'is-warning' : 'is-success'; ?>"></span>
								<p class="wp2-status-label"><?php echo esc_html__( 'Updates Available', 'wp2-update' ); ?></p>
								<p class="wp2-status-value"><?php echo $updates_available; ?></p>
							</div>
							<div class="wp2-status-item">
								<span class="wp2-status-icon is-neutral"></span>
								<p class="wp2-status-label"><?php echo esc_html__( 'Last Checked', 'wp2-update' ); ?></p>
								<p class="wp2-status-value"><?php echo esc_html( $last_checked ); ?></p>
							</div>
						</div>
					</section>

					<section class="wp2-update-card" aria-labelledby="quick-start-heading">
						<h2 id="quick-start-heading" class="wp2-card-title">
							<?php echo esc_html__( 'Quick Start Guide', 'wp2-update' ); ?>
						</h2>
						<div class="wp2-quick-start">
							<div>
								<h3><?php echo esc_html__( '1. Mark Your Packages for Management', 'wp2-update' ); ?></h3>
								<p><?php printf( esc_html__( 'Add an %s to your theme or plugin header file.', 'wp2-update' ), '<code>Update URI</code>' ); ?></p>
								<div class="wp2-code-examples">
									<div>
										<p><strong><?php echo esc_html__( 'Theme Example (`style.css`):', 'wp2-update' ); ?></strong></p>
										<pre><code>/*\n Theme Name: ExamplePress\n Version: 1.3.9\n Update URI: example-owner/examplepress-theme\n*/</code></pre>
									</div>
									<div>
										<p><strong><?php echo esc_html__( 'Plugin Example (`my-plugin.php`):', 'wp2-update' ); ?></strong></p>
										<pre><code>/*\n Plugin Name: Example Plugin\n Version: 1.2.0\n Update URI: https://github.com/example-owner/example-plugin\n*/</code></pre>
									</div>
								</div>
							</div>
						</div>
					</section>
				</div>
			</div>
		</div>
		<?php
	}
}
