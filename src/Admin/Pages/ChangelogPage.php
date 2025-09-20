<?php
namespace WP2\Update\Admin\Pages;

use WP2\Update\Core\Connection\Init as Connection;
use WP2\Update\Utils\SharedUtils;

/**
 * Handles the rendering of the Changelog page.
 */
class ChangelogPage {
    private $connection;
    private $utils;

    /**
     * Constructor.
     *
     * @param Connection $connection The connection instance.
     * @param SharedUtils $utils The shared utilities instance.
     */
    public function __construct(Connection $connection, SharedUtils $utils) {
        $this->connection = $connection;
        $this->utils = $utils;
    }

    /**
     * Renders the Changelog page.
     */
    public function render() {
        echo '<h1>Changelog</h1>';
        echo '<p>This page displays the changelog for managed packages.</p>';
    }
}