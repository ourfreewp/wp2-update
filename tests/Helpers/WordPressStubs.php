<?php

namespace Tests\Helpers;

/**
 * Minimal in-memory implementation of common WordPress helpers for unit tests.
 */
final class WordPressStubs
{
    public static array $options = [];
    public static array $transients = [];
    public static array $siteTransients = [];
    public static array $actions = [];
    public static array $actionCalls = [];
    public static array $filters = [];
    public static array $generatedUuids = [];
    public static array $plugins = [];
    public static array $themes = [];
    public static int $uuidCounter = 1;
    public static int $pluginUpdateCalls = 0;
    public static int $themeUpdateCalls = 0;
    public static array $localizedScripts = [];
    public static array $enqueuedScripts = [];
    public static array $enqueuedStyles = [];

    public static function reset(): void
    {
        self::$options = [];
        self::$transients = [];
        self::$siteTransients = [];
        self::$actions = [];
        self::$actionCalls = [];
        self::$filters = [];
        self::$generatedUuids = [];
        self::$plugins = [];
        self::$themes = [];
        self::$uuidCounter = 1;
        self::$pluginUpdateCalls = 0;
        self::$themeUpdateCalls = 0;
        self::$localizedScripts = [];
        self::$enqueuedScripts = [];
        self::$enqueuedStyles = [];
    }
}
