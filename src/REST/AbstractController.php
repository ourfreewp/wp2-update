<?php

namespace WP2\Update\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP2\Update\Utils\Permissions;
use WP2\Update\Utils\Logger;
use WP2\Update\Utils\CustomException;
use WP2\Update\Config;

/**
 * Base controller that provides common helpers for REST endpoints.
 */
abstract class AbstractController implements ControllerInterface {
    /**
     * REST API namespace used by the plugin.
     */
    protected string $namespace;

    public function __construct() {
        $this->namespace = Config::REST_NAMESPACE;
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
        if (defined('WP2_UPDATE_DEBUG') && WP2_UPDATE_DEBUG) {
            Logger::debug('Responding to REST request.', ['status' => $status, 'data_type' => gettype($data)]);
        }

        $is_error = $status >= 400;

        $response_data = [
            'success' => !$is_error,
        ];

        if ($is_error) {
            $response_data['message'] = is_string($data) ? $data : __('An unknown error occurred.', Config::TEXT_DOMAIN);
            if (is_array($data)) {
                $response_data['data'] = $data;
            }
            Logger::error('REST response error.', ['status' => $status, 'data' => $data]);
        } else {
            $response_data['data'] = $data;
            Logger::info('REST response success.', ['status' => $status, 'data' => $data]);
        }

        return new WP_REST_Response($response_data, $status);
    }

    /**
     * Provides a generic permission callback that checks for admin capabilities and a valid nonce.
     *
     * @param string $action The specific nonce action to verify.
     * @param bool $requireNonce Whether nonce validation is required.
     * @return callable
     */
    protected function permission_callback(string $action, bool $requireNonce = true): callable {
        return function (WP_REST_Request $request) use ($action, $requireNonce) {
            $has_permission = Permissions::current_user_can_manage($action, $request);

            if ($requireNonce) {
                $nonce = $request->get_header('X-WP-Nonce');
                if (empty($nonce) || !wp_verify_nonce($nonce, $action)) {
                    Logger::warning('Invalid or missing nonce.', ['action' => $action]);
                    return false;
                }
            }

            if ($has_permission) {
                Logger::info('Permission granted.', ['action' => $action]);
            } else {
                Logger::warning('Permission denied.', ['action' => $action]);
            }

            return $has_permission;
        };
    }

    /**
     * Handles a CustomException and converts it into a standardized WP_REST_Response.
     *
     * @param CustomException $e The CustomException instance.
     * @return WP_REST_Response
     */
    protected function handle_custom_exception(CustomException $e): WP_REST_Response {
        return $this->respond(
            ['message' => $e->getMessage()],
            $e->getStatusCode()
        );
    }

}
