<?php
declare(strict_types=1);

namespace WP2\Update\Health\Checks;

use WP2\Update\Health\AbstractCheck;

use WP2\Update\Config;

/**
 * Health check for verifying server environment requirements (PHP version, extensions, etc.).
 */
class EnvironmentCheck extends AbstractCheck {

    protected string $label = 'Server Environment';

    /**
     * Runs the environment health check.
     *
     * @return array The result of the health check.
     */
    public function run(): array {
        $errors = [];

        // Check PHP version
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            $errors[] = sprintf(
                __('PHP version is %s, but 8.0 or higher is required.', Config::TEXT_DOMAIN),
                PHP_VERSION
            );
        }

        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, '6.0', '<')) {
            $errors[] = sprintf(
                __('WordPress version is %s, but 6.0 or higher is required.', Config::TEXT_DOMAIN),
                $wp_version
            );
        }

        // Check required PHP extensions
        $required_extensions = ['curl', 'json', 'openssl'];
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                $errors[] = sprintf(
                    __('The required PHP extension "%s" is not enabled.', Config::TEXT_DOMAIN),
                    $extension
                );
            }
        }

        if (!empty($errors)) {
            return [
                'label'   => $this->label,
                'status'  => 'error',
                'message' => implode(' ', $errors),
            ];
        }

        return [
            'label'   => $this->label,
            'status'  => 'pass',
            'message' => __('The server environment meets all requirements.', Config::TEXT_DOMAIN),
        ];
    }
}
