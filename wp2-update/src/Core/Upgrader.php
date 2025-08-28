<?php
namespace WP2\Update\Core;
use WP_Error;
interface Upgrader {
    public function install(array $package_data, string $version, string $zip_url);
}
