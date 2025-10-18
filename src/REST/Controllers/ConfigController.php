<?php
declare(strict_types=1);

namespace WP2\Update\REST\Controllers;

defined('ABSPATH') || exit;

use WP2\Update\REST\AbstractController;
use WP2\Update\Config;

final class ConfigController extends AbstractController
{
    public function register_routes(): void
    {
        register_rest_route($this->get_namespace(), '/config/export', [
            'methods'  => 'GET',
            'callback' => [$this, 'export'],
            'permission_callback' => function() {
                return current_user_can(Config::CAP_MANAGE);
            },
        ]);

        register_rest_route($this->get_namespace(), '/config/import', [
            'methods'  => 'POST',
            'callback' => [$this, 'import'],
            'permission_callback' => function() {
                return current_user_can(Config::CAP_MANAGE);
            },
            'args' => [
                'payload' => ['required' => true, 'type' => 'object'],
            ],
        ]);
    }

    public function export(): \WP_REST_Response
    {
        $data = [
            'version' => get_plugin_data(WP2_UPDATE_PLUGIN_FILE)['Version'] ?? '0.0.0',
            'timestamp' => current_time('mysql'),
            'options' => [
                Config::OPTION_APPS => get_option(Config::OPTION_APPS, []),
                Config::OPTION_PACKAGES_DATA => get_option(Config::OPTION_PACKAGES_DATA, []),
            ],
        ];

        return $this->respond($data);
    }

    public function import(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            return $this->respond('Invalid payload.', 400);
        }

        $payload = $body['payload'] ?? null;
        if (!is_array($payload) || !isset($payload['options']) || !is_array($payload['options'])) {
            return $this->respond('Invalid configuration object.', 400);
        }

        // Simple size guard (1MB)
        if (strlen(json_encode($payload)) > 1024 * 1024) {
            return $this->respond('Config too large.', 413);
        }

        $opts = $payload['options'];
        $updated = [];
        if (array_key_exists(Config::OPTION_APPS, $opts)) {
            update_option(Config::OPTION_APPS, $opts[Config::OPTION_APPS]);
            $updated[] = Config::OPTION_APPS;
        }
        if (array_key_exists(Config::OPTION_PACKAGES_DATA, $opts)) {
            update_option(Config::OPTION_PACKAGES_DATA, $opts[Config::OPTION_PACKAGES_DATA]);
            $updated[] = Config::OPTION_PACKAGES_DATA;
        }

        return $this->respond(['updated' => $updated]);
    }
}

