<?php

namespace WP2\Update\Admin\Models;

/**
 * Registers the lightweight data storage used by the plugin.
 */
final class Init
{
    public const POST_TYPE = 'wp2_github_app';

    public function register(): void
    {
        add_action('init', [$this, 'register_post_type']);
    }

    /**
     * Minimal post type used to persist GitHub App credentials.
     */
    public function register_post_type(): void
    {
        register_post_type(
            self::POST_TYPE,
            [
                'label'           => __('GitHub App Connection', 'wp2-update'),
                'public'          => false,
                'show_ui'         => false,
                'show_in_menu'    => false,
                'supports'        => ['title'],
                'rewrite'         => false,
                'query_var'       => false,
                'can_export'      => false,
                'capability_type' => 'post',
            ]
        );
    }
}
