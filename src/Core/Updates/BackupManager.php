<?php
namespace WP2\Update\Core\Updates;

class BackupManager {

    private string $backupDir;

    public function __construct() {
        $this->backupDir = WP_CONTENT_DIR . '/uploads/wp2-backups';
        $this->ensureBackupDirectoryExists();
    }

    private function ensureBackupDirectoryExists(): void {
        if (!file_exists($this->backupDir)) {
            wp_mkdir_p($this->backupDir);
        }
    }

    public function createBackup(string $slug, string $type): bool {
        $source = ($type === 'plugin') ? WP_PLUGIN_DIR . '/' . $slug : get_theme_root() . '/' . $slug;
        $destination = $this->backupDir . '/' . $slug . '-' . date('YmdHis') . '.zip';

        if (!file_exists($source)) {
            return false;
        }

        $zip = new \ZipArchive();
        if ($zip->open($destination, \ZipArchive::CREATE) !== true) {
            return false;
        }

        $this->addFilesToZip($zip, $source, $slug);
        $zip->close();

        return file_exists($destination);
    }

    private function addFilesToZip(\ZipArchive $zip, string $folder, string $base): void {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = $base . '/' . substr($filePath, strlen($folder) + 1);

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }
    }

    public function listBackups(): array {
        $backups = glob($this->backupDir . '/*.zip');
        return array_map('basename', $backups);
    }

    public function deleteBackup(string $backupFile): bool {
        $filePath = $this->backupDir . '/' . $backupFile;
        return file_exists($filePath) && unlink($filePath);
    }
}