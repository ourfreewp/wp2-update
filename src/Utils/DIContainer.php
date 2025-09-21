<?php
namespace WP2\Update\Utils;

/**
 * A simple Dependency Injection Container for managing plugin services.
 */
class DIContainer {
    private $services = [];

    /**
     * Registers a service in the container.
     *
     * @param string   $key      The service key.
     * @param callable $resolver A callable that returns the service instance.
     */
    public function register(string $key, callable $resolver) {
        $this->services[$key] = $resolver;
    }

    /**
     * Resolves a service from the container.
     *
     * @param string $key The service key.
     * @return mixed The resolved service instance.
     */
    public function resolve(string $key) {
        if (!isset($this->services[$key])) {
            throw new ServiceNotFoundException("Service not found: {$key}");
        }

        return $this->services[$key]($this);
    }
}

class ServiceNotFoundException extends \Exception {}