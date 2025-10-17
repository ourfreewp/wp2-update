<?php

declare(strict_types=1);

namespace WP2\Update\Repositories;

class PluginRepository {
    public function getAll(): array {
        return get_plugins();
    }
}