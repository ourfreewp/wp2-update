<?php

namespace WP2\Update\REST;

/**
 * Coordinates the registration of all REST API routes across modular controllers.
 */
final class Router {
    /**
     * @var ControllerInterface[] An array of controller instances.
     */
    private array $controllers;

    /**
     * @param ControllerInterface[] $controllers All controllers to be registered.
     */
    public function __construct(array $controllers) {
        $this->controllers = $controllers;
    }

    /**
     * Iterates through all provided controllers and calls their register_routes method.
     * This is the main callback for the 'rest_api_init' action.
     */
    public function register_routes(): void {
        foreach ($this->controllers as $controller) {
            $controller->register_routes();
        }
    }
}
