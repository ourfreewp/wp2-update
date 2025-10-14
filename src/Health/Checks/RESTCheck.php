<?php

namespace WP2\Update\Health\Checks;

use WP2\Update\Health\AbstractCheck;
use WP2\Update\Config;

/**
 * Health check for verifying REST API registration.
 */
class RESTCheck extends AbstractCheck {

    protected string $label = 'REST API Registration';

    public function run(): array {
        if (!function_exists('rest_get_server')) {
            return [
                'label'   => $this->label,
                'status'  => 'warn',
                'message' => __('WordPress REST API is not active.', Config::TEXT_DOMAIN),
            ];
        }

        $namespace = Config::REST_NAMESPACE;
        $routes = rest_get_server()->get_routes();
        
        $routes_registered = 0;
        foreach ($routes as $route => $handlers) {
            // Check if the route starts with the required namespace
            if (str_starts_with($route, '/'.$namespace)) {
                $routes_registered++;
            }
        }

        if ($routes_registered === 0) {
             return [
                'label'   => $this->label,
                'status'  => 'error',
                'message' => sprintf(__('No routes found for namespace %s. The Router failed to initialize.', Config::TEXT_DOMAIN), $namespace),
            ];
        }

        return [
            'label'   => $this->label,
            'status'  => 'pass',
            'message' => sprintf(__('REST Namespace %s is active with %d routes registered. This confirms the Router is working.', Config::TEXT_DOMAIN), $namespace, $routes_registered),
        ];
    }
}