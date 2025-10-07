<?php

namespace WP2\Update\Core\Updates;

use WP2\Update\Core\API\Service as GitHubService;
use WP2\Update\Utils\SharedUtils;
use WP_Error;

/**
 * Hooks WordPress' plugin update API into GitHub releases.
 */
class PluginUpdater
{
    private PackageFinder $packages;
    private GitHubService $githubService;
    private SharedUtils $utils;

    public function __construct(PackageFinder $packages, GitHubService $githubService, SharedUtils $utils)
    {
        $this->packages      = $packages;
        $this->githubService = $githubService;
        $this->utils         = $utils;
    }

    public function register_hooks(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_updates']);
        add_filter('upgrader_pre_download', [$this, 'maybe_provide_authenticated_package'], 10, 4);
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

            $release = $this->githubService->get_latest_release($owner, $repo);
            if (!$release) {
                continue;
            }

            $latestVersion  = $this->utils->normalize_version($release['tag_name'] ?? '');
            $currentVersion = $this->utils->normalize_version($transient->checked[$slug] ?? $plugin['version']);

            if (!$latestVersion || version_compare($latestVersion, $currentVersion, '<=')) {
                continue;
            }

            $packageUrl = $this->utils->get_zip_url_from_release($release);
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
     * @param \WP_Upgrader  $upgrader
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

        $file = $this->githubService->download_to_temp_file($package);
        if (!$file) {
            return new WP_Error('wp2_download_failed', __('WP2 Update could not download the package from GitHub.', 'wp2-update'));
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
}
