<?php

namespace WP2\Update\Core\Updates;

use WP2\Update\Core\API\ReleaseService;
use WP2\Update\Core\API\GitHubClientFactory;
use WP2\Update\Utils\Formatting;
use WP_Error;
use WP2\Update\Config;

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

/**
 * Hooks WordPress' plugin update API into GitHub releases.
 */
class PluginUpdater extends AbstractUpdater
{
    protected PackageFinder $packages;
    protected ReleaseService $releaseService;

    public function __construct(PackageFinder $packages, ReleaseService $releaseService, GitHubClientFactory $clientFactory)
    {
        parent::__construct($packages, $releaseService, $clientFactory);
    }

    /**
     * Add GitHub release information to the plugin update transient.
     *
     * @param object $transient
     * @return object
     */
    public function inject_updates(object $transient): object
    {
        if (empty($transient->checked) || !is_array($transient->checked)) {
            return $transient;
        }

        foreach ($this->packages->get_managed_plugins() as $slug => $plugin) {
            $repoParts = explode('/', $plugin['repo']);
            if (count($repoParts) !== 2) {
                continue;
            }

            [$owner, $repo] = $repoParts;

            $release = $this->releaseService->get_latest_release($owner, $repo);
            if (!$release) {
                continue;
            }

            $latestVersion  = Formatting::normalize_version($release['tag_name'] ?? '');
            $currentVersion = Formatting::normalize_version($transient->checked[$slug] ?? $plugin['version']);

            if (!$latestVersion || version_compare($latestVersion, $currentVersion, '<=')) {
                continue;
            }

            $packageUrl = $this->releaseService->get_zip_url_from_release($release);
            if (!$packageUrl) {
                continue;
            }

            $transient->response[$slug] = (object) [
                'slug'        => $this->normalize_plugin_slug($slug),
                'plugin'      => $slug,
                'new_version' => $latestVersion,
                'url'         => $release['html_url'] ?? '',
                'package'     => $packageUrl,
            ];
        }

        return $transient;
    }

    /**
     * Provide authenticated downloads for private releases.
     *
     * @param mixed         $reply
     * @param string        $package
     * @param mixed         $upgrader
     * @param array         $hookExtra
     * @return mixed
     */
    public function maybe_provide_authenticated_package($reply, string $package, $upgrader, array $hookExtra)
    {
        $slug = $hookExtra['plugin'] ?? '';

        if (!$slug) {
            return $reply;
        }

        $managed = $this->packages->get_managed_plugins();
        if (!isset($managed[$slug])) {
            return $reply;
        }

        $token = $this->releaseService->getInstallationToken();
        $file = $this->releaseService->download_to_temp_file($package, $token);
        if (!$file) {
            return new WP_Error('wp2_download_failed', __('WP2 Update could not download the package from GitHub.', Config::TEXT_DOMAIN));
        }

        return $file;
    }

    private function normalize_plugin_slug(string $pluginFile): string
    {
        $parts = explode('/', $pluginFile);

        if (count($parts) > 1) {
            return $parts[0];
        }

        return preg_replace('/\.php$/', '', $pluginFile);
    }

    protected function get_managed_items(): array
    {
        return $this->packages->get_managed_plugins();
    }

    protected function get_transient_hook(): string
    {
        return 'pre_set_site_transient_update_plugins';
    }
}
