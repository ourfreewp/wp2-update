<?php
namespace WP2\Update\Admin\Pages;

use WP2\Update\Core\Connection\Init as Connection;
use WP2\Update\Core\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\Utils\Init as SharedUtils;

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
		$setup_url    = home_url( '/' );
		$webhook_url  = home_url( '/wp-json/wp2-update/v1/github/webhooks' );

		?>
		<div class="wp2-container">
			<header class="wp2-mb-8">
				<div class="wp2-flex wp2-items-center wp2-gap-4">
					<div class="wp2-flex wp2-items-center justify-center"
						style="background-color:var(--wp--preset--color--accent-blue); color:var(--wp--preset--color--white); width: 48px; height: 48px; border-radius:var(--wp--custom--border--radius-default);">
						<i class="bi bi-cloud-arrow-up-fill" style="font-size: 28px;" aria-hidden="true"></i>
					</div>
					<h1><?php echo esc_html__( 'WP2 Update', 'wp2' ); ?></h1>
				</div>
				<p class="wp2-mt-2 wp2-text-lead wp2-text-subtle">
					<?php echo esc_html__( 'A central hub for managing your private GitHub theme and plugin updates.', 'wp2' ); ?>
				</p>
			</header>

			<main class="wp2-grid wp2-grid--lg-3-cols">
				<div class="wp2-grid__col-span-2 wp2-space-y-8">
					<section class="wp2-card" aria-labelledby="health-status-heading">
						<h2 id="health-status-heading" class="wp2-mb-4 wp2-flex wp2-items-center">
							<i class="bi bi-heart-pulse-fill"
								style="color:var(--wp--preset--color--accent-blue); margin-right: var(--wp--preset--spacing--20);"
								aria-hidden="true"></i>
							<?php esc_html_e( 'Application Health At-a-Glance', 'wp2' ); ?>
						</h2>
						<div class="wp2-grid wp2-gap-4" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
							<div class="wp2-status-item wp2-status-item--success">
								<i class="bi bi-github" aria-hidden="true"></i>
								<p class="wp2-status-item__title wp2-mt-2">
									<?php esc_html_e( $connection_status['message'], 'wp2' ); ?></p>
							</div>
							<div class="wp2-status-item wp2-status-item--info">
								<i class="bi bi-box-seam" aria-hidden="true"></i>
								<p class="wp2-status-item__title wp2-mt-2">
									<?php printf( esc_html__( '%d Managed Packages', 'wp2' ), count( $managed_packages ) ); ?></p>
							</div>
							<div class="wp2-status-item wp2-status-item--warning">
								<i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
								<p class="wp2-status-item__title wp2-mt-2">
									<?php printf( esc_html__( '%d Updates Available', 'wp2' ), $updates_available ); ?></p>
							</div>
							<div class="wp2-status-item wp2-status-item--neutral">
								<i class="bi bi-clock-history" aria-hidden="true"></i>
								<p class="wp2-status-item__title wp2-mt-2">
									<?php printf( esc_html__( 'Last Checked: %s', 'wp2' ), $last_checked ); ?></p>
							</div>
						</div>
					</section>

					<section class="wp2-card" aria-labelledby="quick-start-heading">
						<h2 id="quick-start-heading" class="wp2-mb-4 wp2-flex wp2-items-center">
							<i class="bi bi-rocket-takeoff-fill"
								style="color:var(--wp--preset--color--accent-blue); margin-right: var(--wp--preset--spacing--20);"
								aria-hidden="true"></i>
							<?php esc_html_e( 'Quick Start Guide', 'wp2' ); ?>
						</h2>
						<div class="wp2-space-y-6">
							<div>
								<h3><?php esc_html_e( '1. Mark Your Packages for Management', 'wp2' ); ?></h3>
								<p class="wp2-text-subtle wp2-mt-1">
									<?php printf( esc_html__( 'Add an %s to your theme or plugin header file.', 'wp2' ), '<code class="wp2-inline-code">Update URI</code>' ); ?>
								</p>
								<div class="wp2-grid wp2-gap-4 wp2-mt-2" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
									<div>
										<p class="wp2-font-medium wp2-text-subtle wp2-mb-2">
											<?php esc_html_e( 'Theme Example (`style.css`):', 'wp2' ); ?></p>
										<div class="wp2-code-block">/*<br> Theme Name: ExamplePress<br> Version: 1.3.9<br> <span class="wp2-code-block--highlight">Update URI: example-owner/examplepress-theme</span><br>*/</div>
									</div>
									<div>
										<p class="wp2-font-medium wp2-text-subtle wp2-mb-2">
											<?php esc_html_e( 'Plugin Example (`my-plugin.php`):', 'wp2' ); ?></p>
										<div class="wp2-code-block">/*<br> Plugin Name: Example Plugin<br> Version: 1.2.0<br> <span class="wp2-code-block--highlight">Update URI: https://github.com/example-owner/example-plugin</span><br>*/</div>
									</div>
								</div>
							</div>

							<div>
								<h3><?php esc_html_e( '2. Create and Configure a GitHub App', 'wp2' ); ?></h3>
								<p class="wp2-text-subtle wp2-mt-1">
									<?php esc_html_e( 'Navigate to GitHub Developer Settings and create a new GitHub App. Use the following details:', 'wp2' ); ?>
								</p>
								<ul class="wp2-list wp2-mt-2">
									<li><strong><?php esc_html_e( 'Homepage URL:', 'wp2' ); ?></strong> <a href="<?php echo esc_url( $homepage_url ); ?>" class="wp2-link"> <?php echo esc_html( $homepage_url ); ?></a></li>
									<li><strong><?php esc_html_e( 'Callback URL:', 'wp2' ); ?></strong> <a href="<?php echo esc_url( $callback_url ); ?>" class="wp2-link"> <?php echo esc_html( $callback_url ); ?></a></li>
									<li><strong><?php esc_html_e( 'Webhook URL:', 'wp2' ); ?></strong> <a href="<?php echo esc_url( $webhook_url ); ?>" class="wp2-link"> <?php echo esc_html( $webhook_url ); ?></a></li>
								</ul>
							</div>

							<div>
								<h3><?php esc_html_e( '3. Finalize Setup in WordPress', 'wp2' ); ?></h3>
								<p class="wp2-text-subtle wp2-mt-1">
									<?php esc_html_e( 'Ensure the following are configured:', 'wp2' ); ?>
								</p>
								<ul class="wp2-list wp2-mt-2">
									<?php foreach ( $installation_requirements as $key => $value ) : ?>
										<li><strong><?php echo esc_html( $key ); ?>:</strong> <?php echo esc_html( $value ); ?></li>
									<?php endforeach; ?>
								</ul>
							</div>
						</div>
					</section>
				</div>

				<div class="wp2-space-y-8">
					<section class="wp2-card" aria-labelledby="about-heading">
						<h2 id="about-heading" class="wp2-mb-2"><?php esc_html_e( 'About WP2 Update', 'wp2' ); ?></h2>
						<p class="wp2-text-subtle wp2-mb-4">
							<?php esc_html_e( 'A WordPress plugin that delivers private GitHub theme and plugin updates via GitHub Apps, with a modern, responsive admin UI.', 'wp2' ); ?>
						</p>
						<div style="font-size: var(--wp--preset--font-size--small);">
							<strong class="wp2-font-semibold"><?php esc_html_e( 'Version:', 'wp2' ); ?></strong> 0.1.14<br>
							<strong class="wp2-font-semibold"><?php esc_html_e( 'Author:', 'wp2' ); ?></strong> Vinny S. Green
						</div>
					</section>
				</div>
			</main>
		</div>
		<?php
	}
}
