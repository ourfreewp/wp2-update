<?php

namespace WP2\Update\Admin;

use WP2\Update\REST\Controllers\HealthController;
use WP2\Update\Utils\Logger;

/**
 * Handles rendering the admin screen for the WP2 Update plugin.
 * Renders the initial HTML structure and the SPA shell.
 */
final class Screens {
    private HealthController $healthController;
    private Data $data;

    public function __construct(HealthController $healthController, Data $data) {
        $this->healthController = $healthController;
        $this->data = $data;
    }

    /**
     * Renders the main admin page, which acts as the root for the SPA.
     */
    public function render(): void {
        $activeTab = $this->data->get_active_tab();
        ?>
        <div id="wp2-update-app" class="wp2-wrap">
            <h1 class="wp2-main-title"><?php esc_html_e('WP2 Update', \WP2\Update\Config::TEXT_DOMAIN); ?></h1>

            <nav class="nav-tab-wrapper wp2-tabs">
                <a data-tab="dashboard" class="nav-tab js-tab-link <?php echo 'dashboard' === $activeTab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Dashboard', \WP2\Update\Config::TEXT_DOMAIN); ?>
                </a>
                <a data-tab="packages" class="nav-tab js-tab-link <?php echo 'packages' === $activeTab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Packages', \WP2\Update\Config::TEXT_DOMAIN); ?>
                </a>
                <a data-tab="apps" class="nav-tab js-tab-link <?php echo 'apps' === $activeTab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Apps', \WP2\Update\Config::TEXT_DOMAIN); ?>
                </a>
                <a data-tab="health" class="nav-tab js-tab-link <?php echo 'health' === $activeTab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Health', \WP2\Update\Config::TEXT_DOMAIN); ?>
                </a>
            </nav>

            <div class="wp2-tab-content">
                <?php
                // The content for each tab is now primarily rendered by the JavaScript application.
                // We render the container for the active tab.
                $this->render_tab_panel('dashboard', $activeTab);
                $this->render_tab_panel('packages', $activeTab);
                $this->render_tab_panel('apps', $activeTab);
                $this->render_tab_panel('health', $activeTab);
                ?>
            </div>

            <?php // The main SPA container and modals are rendered client-side. ?>
        </div>
        <?php
    }

    /**
     * Renders the shell for a tab panel. The content is filled by JavaScript.
     * @param string $tabName The name of the tab.
     * @param string $activeTab The currently active tab.
     */
    private function render_tab_panel(string $tabName, string $activeTab): void {
        $display = $tabName === $activeTab ? 'block' : 'none';
        echo '<div id="wp2-' . esc_attr($tabName) . '-panel' . esc_attr($tabName) . '-panel" class="wp2-tab-panel" style="display: ' . esc_attr($display) . ';">';

        // Specific server-side content can be pre-rendered here if needed.
        if ($tabName === 'health' && $activeTab === 'health') {
            $this->render_partial('health', ['health_checks' => $this->get_health_check_data()]);
        } else {
            // Generic loading message for JS-driven tabs
            echo '<p>' . esc_html__('Loading...', \WP2\Update\Config::TEXT_DOMAIN) . '</p>';
        }

        echo '</div>';
    }


    /**
     * Renders the page that handles the OAuth callback from GitHub.
     */
    public function render_github_callback(): void {
        ?>
        <div id="wp2-update-github-callback" class="wp2-wrap">
            <h1 class="wp2-main-title"><?php esc_html_e('Connecting to GitHub...', \WP2\Update\Config::TEXT_DOMAIN); ?></h1>
            <p><?php esc_html_e('Please wait while we finalize the connection. You will be redirected shortly.', \WP2\Update\Config::TEXT_DOMAIN); ?></p>
            <div class="wp2-spinner"></div>
        </div>
        <?php
    }

    /**
     * Includes a view file from the Admin/Views directory.
     * @param string $template The name of the template file (without .php).
     * @param array $data Data to be extracted for use in the template.
     */
    private function render_partial(string $template, array $data = []): void {
        $file = WP2_UPDATE_PLUGIN_DIR . "src/Admin/Views/{$template}.php";
        if (file_exists($file)) {
            extract($data, EXTR_SKIP);
            include $file;
        } else {
            Logger::log('ERROR', "Admin view file not found: {$template}.php");
        }
    }

    /**
     * Fetches health check data from the controller.
     * @return array
     */
    private function get_health_check_data(): array {
        $response = $this->healthController->get_health_status(new \WP_REST_Request());
        return $response->get_data()['data'] ?? [];
    }
}
