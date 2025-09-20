<?php
namespace WP2\Update\Health;

/**
 * Validates the health of an installed package (theme or plugin).
 * This is a runtime checker, not a background task.
 */
class PackageHealth {

    /**
     * The repository slug from the package header (e.g., 'owner/repo').
     * @var string
     */
    private string $repo_slug;

    public function __construct(string $repo_slug) {
        $this->repo_slug = $repo_slug;
    }

    /**
     * Gets the health status of the package.
     *
     * @return array ['status' => 'ok'|'error', 'message' => '...']
     */
    public function get_status(): array {
        // 1. Check if a corresponding repository post exists.
        $repo_post = get_page_by_path($this->repo_slug, OBJECT, 'wp2_repository');
        if (!$repo_post) {
            return [
                'status' => 'error',
                'message' => 'Unmanaged: This package\'s repository has not been discovered by any configured GitHub App.',
            ];
        }

        // 2. Check if the corresponding repository itself is healthy.
        $repo_health = get_post_meta($repo_post->ID, '_health_status', true);
        if ($repo_health !== 'ok') {
            return [
                'status' => 'error',
                'message' => 'Repository Error: The discovered repository is not healthy. Check the Repositories page for details.',
            ];
        }

        return [
            'status' => 'ok',
            'message' => 'Managed and Healthy: This package is ready to receive updates.',
        ];
    }

    /**
     * Legacy: Runs health checks and updates status (for backward compatibility).
     */
    public function run_checks() {
        $status = $this->get_status();
        $this->update_status($status['status'], $status['message']);
    }

    /**
     * Updates the status of the package.
     * (Assumes this method is implemented elsewhere or is a stub.)
     */
    protected function update_status(string $status, string $message): void {
        // Implement status update logic here.
    }
}
