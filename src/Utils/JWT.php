<?php

namespace WP2\Update\Utils;

// Ensure the JWT library from Composer is loaded.
if (file_exists(WP2_UPDATE_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once WP2_UPDATE_PLUGIN_DIR . 'vendor/autoload.php';
}

use Firebase\JWT\JWT as FirebaseJWT;

/**
 * A wrapper for the firebase/php-jwt library to handle JWT generation for GitHub App authentication.
 */
class JWT {
    /**
     * Generates a JSON Web Token (JWT) for GitHub App authentication.
     *
     * @param string $app_id The GitHub App ID.
     * @param string $private_key The private key (.pem file contents) for the GitHub App.
     * @return string|null The generated JWT or null on failure.
     */
    public function generate_jwt(string $app_id, string $private_key): ?string {
        if (!class_exists(FirebaseJWT::class)) {
            Logger::log('ERROR', 'JWT library is not loaded. Please run `composer install`.');
            return null;
        }

        $issued_at = time();
        $payload = [
            'iat' => $issued_at,         // Issued at: time when the token was generated
            'exp' => $issued_at + 600,   // Expiration time (10 minutes)
            'iss' => $app_id,           // Issuer: your GitHub App ID
        ];

        try {
            return FirebaseJWT::encode($payload, $private_key, 'RS256');
        } catch (\Exception $e) {
            Logger::log('ERROR', 'Failed to generate JWT: ' . $e->getMessage());
            return null;
        }
    }
}
