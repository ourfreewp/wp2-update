<?php
declare(strict_types=1);

namespace WP2\Update\Utils;

defined('ABSPATH') || exit;

use Firebase\JWT\JWT as FirebaseJWT;
use WP2\Update\Utils\Logger;

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
            Logger::error('FirebaseJWT class not found. Ensure the library is installed.');
            return null;
        }

        $issued_at = time();
        $payload = [
            'iat' => $issued_at,         // Issued at: time when the token was generated
            'exp' => $issued_at + 600,   // Expiration time (10 minutes)
            'iss' => $app_id,           // Issuer: your GitHub App ID
        ];

        try {
            $jwt = FirebaseJWT::encode($payload, $private_key, 'RS256');
            Logger::info('JWT generated successfully.', ['app_id' => $app_id, 'issued_at' => $issued_at]);
            return $jwt;
        } catch (\Exception $e) {
            Logger::error('Failed to generate JWT.', ['app_id' => $app_id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Generates a JSON Web Token (JWT) for GitHub App authentication.
     *
     * @param string $payload The payload to encode.
     * @param string $secret The secret key for the JWT.
     * @return string The generated JWT.
     */
    public static function generate(string $payload, string $secret): string {
        \WP2\Update\Utils\Logger::start('jwt:generate');
        try {
            $token = base64_encode(json_encode($payload)) . '.' . base64_encode(hash_hmac('sha256', json_encode($payload), $secret, true));
            \WP2\Update\Utils\Logger::info('JWT generated successfully.', ['payload' => $payload]);
            return $token;
        } catch (\Exception $e) {
            \WP2\Update\Utils\Logger::error('JWT generation failed.', ['error' => $e->getMessage()]);
            throw $e;
        } finally {
            \WP2\Update\Utils\Logger::stop('jwt:generate');
        }
    }
}
