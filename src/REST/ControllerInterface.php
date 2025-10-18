<?php
declare(strict_types=1);

namespace WP2\Update\REST;

defined('ABSPATH') || exit;

/**
 * Defines the contract for all REST controllers in the plugin.
 */
interface ControllerInterface {
    /**
     * Registers all REST routes managed by the controller.
     * This method is called by the Router during the 'rest_api_init' action.
     */
    public function register_routes(): void;
}
