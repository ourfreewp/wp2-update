<?php
namespace WP2\Update\Helpers;
use WP2\Update\Core\Upgrader as UpgraderInterface;
use WP_Error;
class SymlinkUpgrader implements UpgraderInterface {
    public function install(array $package_data, string $version, string $zip_url) {
        $slug = $package_data['file'];
        $wp_upload_dir = wp_upload_dir();
        $package_dir = trailingslashit($wp_upload_dir['basedir']) . 'wp2-releases/daemon/' . $slug;
        $release_path = $package_dir . '/' . $version;
        if (!is_dir($release_path)) {
            wp_mkdir_p($package_dir);
            $tmp_file = download_url($zip_url, 300);
            if (is_wp_error($tmp_file)) return $tmp_file;
            $unzip_result = unzip_file($tmp_file, $release_path);
            @unlink($tmp_file);
            if (is_wp_error($unzip_result)) return $unzip_result;
            if (file_exists($release_path . '/composer.json')) {
                shell_exec('cd ' . escapeshellarg($release_path) . ' && composer install --no-dev --optimize-autoloader');
            }
        }
        return $this->symlink_release($slug, $version);
    }
    private function symlink_release(string $slug, string $version): bool {
        $wp_upload_dir = wp_upload_dir();
        $release_path = trailingslashit($wp_upload_dir['basedir']) . "wp2-releases/daemon/{$slug}/{$version}";
        $current_symlink = WP_CONTENT_DIR . '/mu-plugins/' . $slug;
        $temp_symlink = $current_symlink . '_tmp_' . time();
        if (!symlink($release_path, $temp_symlink)) return false;
        if (!rename($temp_symlink, $current_symlink)) {
            @unlink($temp_symlink);
            return false;
        }
        return true;
    }
}
