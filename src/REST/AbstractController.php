<?php

namespace WP2\Update\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP2\Update\Utils\Permissions;

/**
 * Base controller that provides common helpers for REST endpoints.
 */
abstract class AbstractController implements ControllerInterface {
    /**
     * REST API namespace used by the plugin.
     */
    protected string $namespace;

    public function __construct() {
        $this->namespace = \WP2\Update\Config::REST_NAMESPACE;
    }

    /**
     * Returns the namespace for the controller's routes.
     */
    public function get_namespace(): string {
        return $this->namespace;
    }

    /**
     * Creates a standardized REST response with a consistent JSON structure.
     *
     * @param mixed $data    Response payload. Can be data on success or an error message string.
     * @param int   $status  HTTP status code.
     * @return WP_REST_Response
     */
    protected function respond($data, int $status = 200): WP_REST_Response {
        $is_error = $status >= 400;

        $response_data = [
            'success' => !$is_error,
        ];

        if ($is_error) {
            $response_data['message'] = is_string($data) ? $data : __('An unknown error occurred.', \WP2\Update\Config::TEXT_DOMAIN);
            if (is_array($data)) {
                 $response_data['data'] = $data;
            }
        } else {
            $response_data['data'] = $data;
        }

        return new WP_REST_Response($response_data, $status);
    }

    /**
     * Provides a generic permission callback that checks for admin capabilities and a valid nonce.
     *
     * @param string $action The specific nonce action to verify.
     * @return callable
     */
    protected function permission_callback(string $action): callable {
        return function (WP_REST_Request $request) use ($action) {
            return Permissions::current_user_can_manage($action, $request);
        };
    }
}
