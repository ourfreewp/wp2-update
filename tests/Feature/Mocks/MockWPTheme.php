<?php

namespace Tests\Feature\Mocks;

// Mock class for testing purposes
class MockWPTheme {
    public function get($header) {
        if ($header === 'Update URI') return 'owner/my-theme';
        if ($header === 'Name') return 'My Awesome Theme';
        return '';
    }
}