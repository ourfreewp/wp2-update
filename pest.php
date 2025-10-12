<?php

require __DIR__ . '/tests/Helpers/wp-stubs.php';

uses()
    ->beforeEach(function (): void {
        Tests\Helpers\WordPressStubs::reset();
    });
