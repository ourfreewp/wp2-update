<?php
namespace WP2\Update\Utils;

use WP2\Update\Core\API\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\API\Service as GitHubService;

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

    /**
     * Logs the service resolution process for debugging.
     *
     * @param string $key The service key.
     */
    public function debug_resolve(string $key) {
        try {
            $service = $this->resolve($key);
            error_log("Service '{$key}' resolved successfully.");
        } catch (ServiceNotFoundException $e) {
            error_log("Service '{$key}' could not be resolved: " . $e->getMessage());
        }
    }

    /**
     * Registers the default services for the application.
     */
    public function register_services() {
        $this->register('SharedUtils', function($container) {
            return new SharedUtils($container->resolve('GitHubApp'));
        });

        $this->register('GitHubApp', function($container) {
            return new GitHubApp($container->resolve('GitHubService'));
        });

        $this->register('GitHubService', function() {
            return new GitHubService(); // Assuming no dependencies for GitHubService
        });
    }
}

class ServiceNotFoundException extends \Exception {}