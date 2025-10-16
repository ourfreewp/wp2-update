<?php
declare(strict_types=1);

namespace WP2\Update\Health\Checks;
use WP2\Update\Config;
use WP2\Update\Health\AbstractCheck;
use WP2\Update\Services\Github\AppService;

/**
 * Health check for verifying connectivity to GitHub.
 */
class ConnectivityCheck extends AbstractCheck {

    protected string $label = 'GitHub Connection';
    private AppService $appService;

    public function __construct(AppService $appService) {
        $this->appService = $appService;
    }

    /**
     * Runs the connectivity check.
     *
     * @return array The result of the health check.
     */
    public function run(): array {
        $status = 'pass';
        $message = __('Successfully connected to GitHub.', Config::TEXT_DOMAIN);

        try {
            // Retrieve the app ID (this is a placeholder; replace with actual logic to get the app ID)
            $app_id = 'default_app_id';

            $connection_status = $this->appService->get_connection_status($app_id);

            switch ($connection_status['status']) {
                case 'not_configured':
                    $status = 'warn';
                    $message = __('GitHub App is not configured.', Config::TEXT_DOMAIN);
                    break;
                case 'app_created':
                    $status = 'warn';
                    $message = __('GitHub App is created but not yet installed on any repositories.', Config::TEXT_DOMAIN);
                    break;
                case 'connection_error':
                    $status = 'error';
                    $message = __('Could not establish a connection to GitHub. Check credentials and network.', Config::TEXT_DOMAIN);
                    break;
                case 'installed':
                    // This is the success case.
                    break;
                default:
                    $status = 'warn';
                    $message = __('Connection status is unknown.', Config::TEXT_DOMAIN);
                    break;
            }
        } catch (\Exception $e) {
            $status = 'error';
            $message = __('An error occurred while checking the GitHub connection: ', Config::TEXT_DOMAIN) . $e->getMessage();
        }

        return [
            'label'   => $this->label,
            'status'  => $status,
            'message' => $message,
        ];
    }
}
