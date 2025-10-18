<?php

declare(strict_types=1);

namespace WP2\Update\Health\Checks;

use WP2\Update\Health\AbstractCheck;
use WP2\Update\Config;

/**
 * Checks for the existence and integrity of WP2 Update backups.
 */
class BackupCheck extends AbstractCheck
{
    public function run(): array
    {
        $backupDir = WP_CONTENT_DIR . '/uploads/wp2-backups/';
        $result = [
            'status' => 'success',
            'message' => __('Backups directory exists and is writable.', Config::TEXT_DOMAIN),
            'backups' => [],
        ];

        if (!is_dir($backupDir)) {
            $result['status'] = 'error';
            $result['message'] = __('Backups directory does not exist: ', Config::TEXT_DOMAIN) . $backupDir;
            return $result;
        }
        if (!is_writable($backupDir)) {
            $result['status'] = 'error';
            $result['message'] = __('Backups directory is not writable: ', Config::TEXT_DOMAIN) . $backupDir;
            return $result;
        }
        $files = array_diff(scandir($backupDir), ['.', '..']);
        if (empty($files)) {
            $result['status'] = 'warning';
            $result['message'] = __('No backup files found in: ', Config::TEXT_DOMAIN) . $backupDir;
        } else {
            foreach ($files as $file) {
                $filePath = $backupDir . $file;
                $result['backups'][] = [
                    'file' => $file,
                    'size' => filesize($filePath),
                    'modified' => date('c', filemtime($filePath)),
                ];
            }
            $result['message'] = sprintf(
                /* translators: %d = number of backup files */
                __('%d backup file(s) found.', Config::TEXT_DOMAIN),
                count($files)
            );
        }
        return $result;
    }
}
