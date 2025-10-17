<?php

declare(strict_types=1);

namespace WP2\Update\Repositories;

class ThemeRepository {
    public function getAll(): array {
        return wp_get_themes();
    }
}