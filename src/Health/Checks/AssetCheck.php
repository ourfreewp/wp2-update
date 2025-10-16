<?php
declare(strict_types=1);

namespace WP2\Update\Health\Checks;

use WP2\Update\Health\AbstractCheck;
use WP2\Update\Config;

/**
 * Health check for verifying asset loading, Vite build integrity, and localized data.
 */
class AssetCheck extends AbstractCheck {

    protected string $label = 'Admin Asset & Localization';

    public function run(): array {
        $errors = [];
        
        // 1. Check for Vite Manifest existence (Critical build step)
        $manifest_path = Config::PLUGIN_DIR . '/dist/.vite/manifest.json';
        if (!file_exists($manifest_path)) {
            $errors[] = __('Vite manifest file is missing. The asset pipeline requires a rebuild.', Config::TEXT_DOMAIN);
        }

        // 2. Check for main script enqueue status (Verifies Assets::enqueue_assets hook ran)
        $script_handle = 'wp2-update-admin-main';
        if (!wp_script_is($script_handle, 'enqueued')) {
            $errors[] = sprintf(__('Main script (%s) failed to be enqueued. Check the Assets class for correct screen hooks.', Config::TEXT_DOMAIN), $script_handle);
        }

        // 3. Check for localized data presence (Crucial for SPA bootstrap)
        $localized_script_check = 'wp2UpdateData';
        if (!wp_script_is($script_handle, 'localized')) {
            $errors[] = sprintf(__('Localization data (%s) is missing. The SPA cannot retrieve bootstrap variables.', Config::TEXT_DOMAIN), $localized_script_check);
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
            'message' => __('Vite Assets and localized data successfully prepared for the SPA.', Config::TEXT_DOMAIN),
        ];
    }
}