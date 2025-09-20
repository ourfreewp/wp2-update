<?php
namespace WP2\Update\Core\Health;

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
     * @return array An associative array with 'status' and 'message'.
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
            $repo_message = get_post_meta($repo_post->ID, '_health_message', true);
            return [
                'status' => 'error',
                'message' => $repo_message,
            ];
        }

        return [
            'status' => 'ok',
            'message' => 'Healthy: This package is being managed correctly.',
        ];
    }
}
