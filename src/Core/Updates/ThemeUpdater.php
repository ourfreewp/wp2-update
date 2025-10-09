<?php

namespace WP2\Update\Core\Updates;

use WP2\Update\Core\API\ReleaseService;
use WP2\Update\Core\API\GitHubClientFactory;
use WP2\Update\Utils\SharedUtils;
use WP_Error;

/**
 * Integrates theme updates with GitHub releases.
 */
class ThemeUpdater extends AbstractUpdater
{
    protected ReleaseService $releaseService;
    protected SharedUtils $utils;

    public function __construct(PackageFinder $packages, ReleaseService $releaseService, SharedUtils $utils, GitHubClientFactory $clientFactory)
    {
        parent::__construct($packages, $releaseService, $utils, $clientFactory);
    }

    protected function get_managed_items(): array
    {
        return $this->packages->get_managed_themes();
    }

    protected function get_transient_hook(): string
    {
        return 'pre_set_site_transient_update_themes';
    }

    /**
     * Add update data to the theme transient.
     *
     * @param object $transient
     * @return object
     */
    public function inject_updates(object $transient): object
    {
        if (empty($transient->checked) || !is_array($transient->checked)) {
            return $transient;
        }

        foreach ($this->get_managed_items() as $slug => $theme) {
            $repoParts = explode('/', $theme['repo']);
            if (count($repoParts) !== 2) {
                continue;
            }

            [$owner, $repo] = $repoParts;

            $release = $this->releaseService->get_latest_release($owner, $repo);
            if (!$release) {
                continue;
            }

            $latestVersion  = $this->utils->normalize_version($release['tag_name'] ?? '');
            $currentVersion = $this->utils->normalize_version($transient->checked[$slug] ?? $theme['version']);

            if (!$latestVersion || version_compare($latestVersion, $currentVersion, '<=')) {
                continue;
            }

            $packageUrl = $this->releaseService->get_zip_url_from_release($release);
            if (!$packageUrl) {
                continue;
            }

            $transient->response[$slug] = [
                'theme'       => $slug,
                'new_version' => $latestVersion,
                'url'         => $release['html_url'] ?? '',
                'package'     => $packageUrl,
            ];
        }

        return $transient;
    }

    /**
     * Handle private downloads during theme updates.
     *
     * @param mixed         $reply
     * @param string        $package
     * @param mixed         $upgrader
     * @param array         $hookExtra
     * @return mixed
     */
    public function maybe_provide_authenticated_package($reply, string $package, $upgrader, array $hookExtra)
    {
        $slug = $hookExtra['theme'] ?? '';

        if (!$slug) {
            return $reply;
        }

        $managed = $this->packages->get_managed_themes();
        if (!isset($managed[$slug])) {
            return $reply;
        }

        $token = $this->releaseService->getInstallationToken();
        $file = $this->releaseService->download_to_temp_file($package, $token);
        if (!$file) {
            return new WP_Error('wp2_download_failed', __('WP2 Update could not download the package from GitHub.', \WP2\Update\Config::TEXT_DOMAIN));
        }

        return $file;
    }
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
