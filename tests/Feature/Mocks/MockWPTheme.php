<?php

namespace Tests\Feature\Mocks;

use Brain\Monkey;

class MockWPTheme {
    public static function mock() {
        Monkey\Functions\when('get')->alias([
            'Update URI' => 'owner/my-theme',
            'Name' => 'My Awesome Theme',
        ]);
    }
}