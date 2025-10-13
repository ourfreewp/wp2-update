<?php

namespace WP2\Update\Utils;

/**
 * Handles symmetric encryption and decryption of sensitive data using OpenSSL.
 */
class Encryption
{
    private const CIPHER_METHOD = 'aes-256-cbc';
    private string $key;

    public function __construct(string $key)
    {
        // Ensure the key is of a sufficient length for the chosen cipher.
        if (mb_strlen($key, '8bit') < 32) {
            // Pad the key if it's not long enough.
            $key = str_pad($key, 32, "\0");
        }
        $this->key = mb_substr($key, 0, 32, '8bit');
    }

    /**
     * Encrypts a given string.
     *
     * @param string $data The plaintext data to encrypt.
     * @return string|false The base64-encoded encrypted data, or false on failure.
     */
    public function encrypt(string $data)
    {
        $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);
        $iv = openssl_random_pseudo_bytes($iv_length);

        $encrypted = openssl_encrypt($data, self::CIPHER_METHOD, $this->key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            return false;
        }

        // Prepend the IV to the encrypted data for use during decryption.
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypts a given string.
     *
     * @param string $data The base64-encoded string to decrypt.
     * @return string|false The decrypted plaintext data, or false on failure.
     */
    public function decrypt(string $data)
    {
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            return false;
        }

        $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);
        $iv = substr($decoded, 0, $iv_length);
        $encrypted_data = substr($decoded, $iv_length);

        $decrypted = openssl_decrypt($encrypted_data, self::CIPHER_METHOD, $this->key, OPENSSL_RAW_DATA, $iv);

        return $decrypted;
    }
}
