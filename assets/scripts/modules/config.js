// Centralized configuration for the WP2 Update plugin

export const API_ENDPOINTS = {
    APPS: '/wp-json/wp2-update/v1/apps',
    PACKAGES: '/wp-json/wp2-update/v1/packages',
    ASSIGN: '/wp-json/wp2-update/v1/assign',
};

export const CONSTANTS = {
    MIN_ENCRYPTION_KEY_LENGTH: 16,
    DEFAULT_RETRY_COUNT: 3,
};