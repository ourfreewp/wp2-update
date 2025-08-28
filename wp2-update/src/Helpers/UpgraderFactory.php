<?php
namespace WP2\Update\Helpers;
use WP2\Update\Core\Upgrader as UpgraderInterface;
class UpgraderFactory {
    public static function create(string $type): ?UpgraderInterface {
        switch ($type) {
            case 'plugin':
            case 'theme':
                return new NativeUpgrader($type);
            case 'daemon':
                return new SymlinkUpgrader();
            default:
                return null;
        }
    }
}
