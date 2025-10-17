<?php
declare(strict_types=1);

namespace WP2\Update;

use Psr\Container\ContainerInterface;
use Exception;

/**
 * A simple, PSR-11 compliant dependency injection container.
 *
 * This container allows for registering services via factory closures and ensures
 * that each service is resolved only once (singleton pattern).
 */
class Container implements ContainerInterface
{
    /**
     * Stores the service definitions (factories).
     *
     * @var array
     */
    private array $definitions = [];

    /**
     * Stores the resolved service instances.
     *
     * @var array
     */
    private array $instances = [];

    /**
     * Registers a new service definition in the container.
     *
     * This method is used in Init.php to define how each service should be constructed.
     *
     * @param string $id The service identifier, typically the fully qualified class name.
     * @param callable $factory The closure that creates the service instance. It receives the container itself as an argument.
     */
    public function register(string $id, callable $factory): void
    {
        $this->definitions[$id] = $factory;
    }

    /**
     * Finds and returns an entry of the container by its identifier.
     *
     * On the first request for a service, it is created using its factory,
     * cached, and returned. Subsequent requests will return the cached instance.
     *
     * @param string $id Identifier of the entry to look for.
     * @return mixed The resolved service instance.
     * @throws Exception If the service identifier is not found in the container.
     */
    public function get(string $id)
    {
        if (!isset($this->definitions[$id])) {
            \WP2\Update\Utils\Logger::error('Service not found in container.', ['id' => $id]);
            throw new \Exception("Service '{$id}' not found in container.");
        }

        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $factory = $this->definitions[$id];
        $this->instances[$id] = $factory($this);
        return $this->instances[$id];
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     *
     * @param string $id Identifier of the entry to look for.
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->definitions[$id]);
    }
}
