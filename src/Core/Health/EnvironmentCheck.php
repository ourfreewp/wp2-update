<?php

namespace WP2\Update\Core\Health;

/**
 * Environment check for verifying server requirements.
 */
class EnvironmentCheck extends AbstractCheck {

    /**
     * Run the environment health check.
     *
     * @return array An associative array with 'status' and 'message'.
     */
    public function run(): array {
        $status = 'success';
        $messages = [];

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $status = 'error';
            $messages[] = 'PHP version must be 7.4 or higher. Current version: ' . PHP_VERSION;
        }

        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, '5.8', '<')) {
            $status = 'error';
            $messages[] = 'WordPress version must be 5.8 or higher. Current version: ' . $wp_version;
        }

        // Check required PHP extensions
        $requiredExtensions = ['curl', 'json', 'mbstring'];
        foreach ($requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                $status = 'error';
                $messages[] = "Required PHP extension missing: $extension.";
            }
        }

        return [
            'status' => $status,
            'message' => implode(' ', $messages) ?: 'Environment meets all requirements.'
        ];
    }
}