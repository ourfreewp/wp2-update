<?php
declare(strict_types=1);

namespace WP2\Update\Services;

defined('ABSPATH') || exit;

use WP_Filesystem_Base;
use WP_Filesystem_Direct;
use WP2\Update\Utils\Logger;
use WP2\Update\Config;

final class BackupService
{
    private const BACKUP_DIR = 'uploads/wp2-backups/';

    private function filesystem(): WP_Filesystem_Base
    {
        global $wp_filesystem;

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if (!$wp_filesystem) {
            WP_Filesystem();
        }

        if (!$wp_filesystem) {
            throw new \RuntimeException(__('Unable to initialize WordPress filesystem API.', Config::TEXT_DOMAIN));
        }

        return $wp_filesystem;
    }

    private function backupDirPath(): string
    {
        return trailingslashit(WP_CONTENT_DIR) . self::BACKUP_DIR;
    }

    public function ensure_backup_dir(): string
    {
        $fs = $this->filesystem();
        $dir = $this->backupDirPath();

        if (!$fs->is_dir($dir)) {
            if (!$fs->mkdir($dir, FS_CHMOD_DIR)) {
                throw new \RuntimeException(__('Unable to create backups directory.', Config::TEXT_DOMAIN));
            }
        }

        $isWritable = method_exists($fs, 'is_writable') ? $fs->is_writable($dir) : wp_is_writable($dir);
        if (!$isWritable) {
            throw new \RuntimeException(__('Backups directory is not writable.', Config::TEXT_DOMAIN));
        }

        return trailingslashit($dir);
    }

    public function sanity_check_bytes_needed(int $bytesNeeded): void
    {
        $free = @disk_free_space(WP_CONTENT_DIR) ?: 0;
        if ($free < $bytesNeeded) {
            throw new \RuntimeException(
                sprintf(
                    /* translators: 1: needed bytes, 2: free bytes */
                    __('Insufficient disk space. Need %1$s bytes, have %2$s bytes.', Config::TEXT_DOMAIN),
                    (string) $bytesNeeded,
                    (string) $free
                )
            );
        }
    }

    public function get_package_path(string $packageType, string $identifier): ?string
    {
        $fs = $this->filesystem();

        if ($packageType === 'plugin') {
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $plugins = function_exists('get_plugins') ? get_plugins() : [];
            foreach ($plugins as $file => $data) {
                $path = trailingslashit(WP_PLUGIN_DIR) . dirname($file);
                $updateUri = $data['UpdateURI'] ?? '';
                if (stripos((string) $updateUri, $identifier) !== false || stripos($file, $identifier) !== false) {
                    return $fs->is_dir($path) ? $path : null;
                }
            }
        }

        if ($packageType === 'theme') {
            $themes = function_exists('wp_get_themes') ? wp_get_themes() : [];
            foreach ($themes as $slug => $theme) {
                $path = trailingslashit((string) get_theme_root()) . $slug;
                $updateUri = $theme->get('UpdateURI') ?: '';
                if (stripos((string) $updateUri, $identifier) !== false || stripos($slug, $identifier) !== false) {
                    return $fs->is_dir($path) ? $path : null;
                }
            }
        }

        return null;
    }

    public function estimate_dir_size(string $dir): int
    {
        $fs = $this->filesystem();
        $dir = trailingslashit($dir);
        $entries = $fs->dirlist($dir, false, true);
        if (!is_array($entries)) {
            return 0;
        }

        $size = 0;
        foreach ($entries as $name => $info) {
            $path = $dir . $name;
            $type = $info['type'] ?? '';
            if ($type === 'f') {
                $size += (int) ($info['size'] ?? 0);
            } elseif ($type === 'd') {
                $size += $this->estimate_dir_size($path);
            }
        }

        return $size;
    }

    public function create_backup(string $packageType, string $identifier): ?string
    {
        $fs = $this->filesystem();
        if (!($fs instanceof WP_Filesystem_Direct)) {
            throw new \RuntimeException(__('Backups require direct filesystem access.', Config::TEXT_DOMAIN));
        }

        $source = $this->get_package_path($packageType, $identifier);
        if (!$source) {
            Logger::warning('Backup skipped; package path not found.', ['type' => $packageType, 'id' => $identifier]);
            return null;
        }

        $size = $this->estimate_dir_size($source);
        $this->sanity_check_bytes_needed((int) ($size * 1.5) + 2_000_000);

        $backupDir = $this->ensure_backup_dir();
        $timestamp = gmdate('Ymd_His');
        $slug = basename($source);
        $zipPath = $backupDir . $packageType . '-' . $slug . '-' . $timestamp . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
            throw new \RuntimeException(__('Unable to create backup archive.', Config::TEXT_DOMAIN));
        }

        $this->add_directory_to_zip($zip, $fs, trailingslashit($source), trailingslashit($source));
        $zip->close();

        Logger::info('Backup created.', ['path' => $zipPath]);
        return $zipPath;
    }

    private function add_directory_to_zip(\ZipArchive $zip, \WP_Filesystem_Base $fs, string $directory, string $base): void
    {
        $entries = $fs->dirlist($directory, false, true);
        if (!is_array($entries)) {
            return;
        }

        foreach ($entries as $name => $info) {
            $path = $directory . $name;
            $local = ltrim(str_replace($base, '', $path), '/');
            $type = $info['type'] ?? '';
            if ($type === 'd') {
                $zip->addEmptyDir($local);
                $this->add_directory_to_zip($zip, $fs, trailingslashit($path), $base);
            } elseif ($type === 'f') {
                $contents = $fs->get_contents($path);
                if ($contents === false) {
                    Logger::warning('Failed to read file during backup.', ['path' => $path]);
                    continue;
                }
                $zip->addFromString($local, $contents);
            }
        }
    }

    public function list_backups(?string $filter = null): array
    {
        $fs = $this->filesystem();
        $dir = $this->ensure_backup_dir();
        $entries = $fs->dirlist($dir, false, true);
        if (!is_array($entries)) {
            return [];
        }

        $output = [];
        foreach ($entries as $name => $info) {
            if ($filter && stripos($name, $filter) === false) {
                continue;
            }
            if (($info['type'] ?? '') !== 'f') {
                continue;
            }
            $path = $dir . $name;
            $output[] = [
                'file' => $name,
                'path' => $path,
                'size' => (int) ($info['size'] ?? 0),
                'modified' => date('c', (int) ($info['lastmodunix'] ?? time())),
            ];
        }

        return $output;
    }

    /**
     * Deletes a backup file.
     *
     * @param string $backupFile The name of the backup file to delete.
     * @return bool True if the file was deleted, false otherwise.
     */
    public function delete_backup(string $backupFile): bool
    {
        $fs = $this->filesystem();
        $backupDir = $this->ensure_backup_dir();
        $filePath = trailingslashit($backupDir) . $backupFile;

        if (!$fs->exists($filePath)) {
            Logger::warning('Backup file does not exist.', ['file' => $backupFile]);
            return false;
        }

        if ($fs->delete($filePath)) {
            Logger::info('Backup file deleted successfully.', ['file' => $backupFile]);
            return true;
        } else {
            Logger::warning('Failed to delete backup file.', ['file' => $backupFile]);
            return false;
        }
    }

    /**
     * Restores a backup file.
     *
     * @param string $backupFile The name of the backup file to restore.
     * @param string $destination The destination directory to restore the backup to.
     * @return bool True if the backup was restored successfully, false otherwise.
     */
    public function restore_backup(string $backupFile, string $destination): bool
    {
        $fs = $this->filesystem();
        $backupDir = $this->ensure_backup_dir();
        $filePath = trailingslashit($backupDir) . $backupFile;

        if (!$fs->exists($filePath)) {
            Logger::warning('Backup file does not exist.', ['file' => $backupFile]);
            return false;
        }

        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            Logger::warning('Failed to open backup file.', ['file' => $backupFile]);
            return false;
        }

        if (!$zip->extractTo($destination)) {
            Logger::warning('Failed to extract backup file.', ['file' => $backupFile, 'destination' => $destination]);
            $zip->close();
            return false;
        }

        $zip->close();
        Logger::info('Backup restored successfully.', ['file' => $backupFile, 'destination' => $destination]);
        return true;
    }

    private function copy_directory(\WP_Filesystem_Base $fs, string $source, string $destination): void
    {
        $fs->mkdir($destination, FS_CHMOD_DIR);
        $entries = $fs->dirlist(trailingslashit($source), false, true);
        if (!is_array($entries)) {
            return;
        }

        foreach ($entries as $name => $info) {
            $from = trailingslashit($source) . $name;
            $to = trailingslashit($destination) . $name;
            $type = $info['type'] ?? '';
            if ($type === 'd') {
                $this->copy_directory($fs, $from, $to);
            } elseif ($type === 'f') {
                $contents = $fs->get_contents($from);
                if ($contents === false) {
                    Logger::warning('Failed to read file during restore.', ['path' => $from]);
                    continue;
                }
                $fs->put_contents($to, $contents, FS_CHMOD_FILE);
            }
        }
    }

    private function delete_path(string $path): void
    {
        $fs = $this->filesystem();
        if ($fs->exists($path)) {
            $fs->delete($path, true);
        }
    }

    public function delete_all_backups(): void
    {
        $fs = $this->filesystem();
        $backupDir = $this->ensure_backup_dir();
        $entries = $fs->dirlist($backupDir, false, true);
        if (is_array($entries)) {
            foreach ($entries as $name => $info) {
                $path = $backupDir . $name;
                $fs->delete($path);
            }
        }
    }
}
