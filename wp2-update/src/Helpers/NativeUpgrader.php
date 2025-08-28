<?php
namespace WP2\Update\Helpers;
use WP2\Update\Core\Upgrader as UpgraderInterface;
use Plugin_Upgrader;
use Theme_Upgrader;
use Automatic_Upgrader_Skin;
use WP_Error;

class NativeUpgrader implements UpgraderInterface {
    private $type;
    public function __construct(string $type) { $this->type = $type; }
    public function install(array $package_data, string $version, string $zip_url) {
        if (!class_exists('WP_Upgrader')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }
        if (!class_exists('Automatic_Upgrader_Skin')) {
            require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
        }
        $skin = new Automatic_Upgrader_Skin();
        if ($this->type === 'plugin') {
            $upgrader = new Plugin_Upgrader($skin);
            return $upgrader->install($zip_url, ['overwrite_package' => true]);
        }
        if ($this->type === 'theme') {
            $upgrader = new Theme_Upgrader($skin);
            return $upgrader->install($zip_url, ['overwrite_package' => true]);
        }
        return new WP_Error('invalid_type', 'Invalid package type provided to NativeUpgrader.');
    }
}
