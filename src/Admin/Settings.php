<?php
namespace WP2\Update\Admin;

/**
 * Handles the registration of settings for the GitHub App.
 */
class Settings {

    /**
     * Registers settings, sections, and fields.
     */
    public static function register() {
        // Register a new setting for GitHub App credentials.
        register_setting( 'wp2_update_github_app_settings', 'wp2_update_github_app_credentials', [
            'sanitize_callback' => [ __CLASS__, 'sanitize_credentials' ],
            'default' => [
                'app_id' => '',
                'installation_id' => '',
                'private_key' => '',
                'webhook_secret' => ''
            ]
        ]);

        // Add a new section to the settings page.
        add_settings_section(
            'wp2_update_github_app_section',
            __( 'GitHub App Credentials', 'wp2-update' ),
            [ __CLASS__, 'render_section_description' ],
            'wp2-update-settings'
        );

        // Add fields for each credential.
        self::add_field( 'app_id', __( 'App ID', 'wp2-update' ) );
        self::add_field( 'installation_id', __( 'Installation ID', 'wp2-update' ) );
        self::add_field( 'private_key', __( 'Private Key (PEM)', 'wp2-update' ), 'textarea' );
        self::add_field( 'webhook_secret', __( 'Webhook Secret', 'wp2-update' ) );
    }

    /**
     * Adds a field to the settings section.
     */
    private static function add_field( $id, $label, $type = 'text' ) {
        add_settings_field(
            "wp2_update_github_app_{$id}",
            $label,
            function() use ( $id, $type ) {
                $options = get_option( 'wp2_update_github_app_credentials' );
                $value = isset( $options[ $id ] ) ? esc_attr( $options[ $id ] ) : '';
                if ( $type === 'textarea' ) {
                    echo "<textarea name='wp2_update_github_app_credentials[{$id}]' rows='5' cols='50'>{$value}</textarea>";
                } else {
                    echo "<input type='{$type}' name='wp2_update_github_app_credentials[{$id}]' value='{$value}' class='regular-text' />";
                }
            },
            'wp2-update-settings',
            'wp2_update_github_app_section'
        );
    }

    /**
     * Renders the section description.
     */
    public static function render_section_description() {
        echo '<p>' . esc_html__( 'Enter your GitHub App credentials below.', 'wp2-update' ) . '</p>';
    }

    /**
     * Sanitizes the credentials before saving.
     */
    public static function sanitize_credentials( $input ) {
        return [
            'app_id' => sanitize_text_field( $input['app_id'] ?? '' ),
            'installation_id' => sanitize_text_field( $input['installation_id'] ?? '' ),
            'private_key' => sanitize_textarea_field( $input['private_key'] ?? '' ),
            'webhook_secret' => sanitize_text_field( $input['webhook_secret'] ?? '' ),
        ];
    }
}

// Register the settings on admin_init.
add_action( 'admin_init', [ 'WP2\Update\Admin\Settings', 'register' ] );