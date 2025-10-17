<?php
declare(strict_types=1);

namespace WP2\Update\Health\Checks;
use WP2\Update\Config;
use WP2\Update\Health\AbstractCheck;
use WP2\Update\Services\Github\AppService;
use WP2\Update\Utils\Logger;

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
        // Log the start of the health check
        Logger::info('Starting ConnectivityCheck health check.');

        $status = 'pass';
        $message = __('Successfully connected to GitHub.', Config::TEXT_DOMAIN);

        try {
            // Retrieve a real app ID from the AppService
            $app = $this->appService->get_apps_with_status()[0] ?? null;

            if (!$app) {
                $status = 'warn';
                $message = __('No GitHub Apps are configured.', Config::TEXT_DOMAIN);
                return [
                    'label'   => $this->label,
                    'status'  => $status,
                    'message' => $message,
                ];
            }

            // Use the first app ID for the connectivity check
            $app_id = $app->id ?? null;

            if (!$app_id) {
                $status = 'warn';
                $message = __('No valid GitHub App ID found.', Config::TEXT_DOMAIN);
                return [
                    'label'   => $this->label,
                    'status'  => $status,
                    'message' => $message,
                ];
            }

            $connection_status = $this->appService->test_connection($app_id);

            if (!$connection_status['success']) {
                $status = 'fail';
                $message = __('Failed to connect to GitHub.', Config::TEXT_DOMAIN);
            }
        } catch (\Exception $e) {
            $status = 'fail';
            $message = __('Error during connectivity check: ', Config::TEXT_DOMAIN) . $e->getMessage();
        }

        if ($status === 'warn') {
            Logger::warning('ConnectivityCheck health check warning.', ['message' => $message]);
        } elseif ($status === 'error') {
            Logger::error('ConnectivityCheck health check failed.', ['message' => $message]);
        } else {
            Logger::info('ConnectivityCheck health check passed.');
        }

        return [
            'label'   => $this->label,
            'status'  => $status,
            'message' => $message,
        ];
    }

    /**
     * Returns the name of the health check.
     *
     * @return string The name of the health check.
     */
    public function getName(): string {
        return 'connectivity_check';
    }
}
