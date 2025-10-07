// ========================================================================
// File: src-js/modules/api.js
// Description: A centralized helper for making REST API requests.
// ========================================================================

const restNonce = window.wpApiSettings?.nonce || '';

if (!restNonce) {
    console.warn('REST API nonce is missing. Ensure wpApiSettings is properly localized.');
}

/**
 * A reusable wrapper for the Fetch API to interact with the WordPress REST API.
 * @param {string} endpoint - The API endpoint (e.g., '/wp2-update/v1/status').
 * @param {object} [options={}] - Configuration options for the fetch request.
 * @returns {Promise<any>} - The JSON response from the API.
 */
export const apiRequest = async (endpoint, options = {}) => {
    const defaultOptions = {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': restNonce,
        },
    };
    const mergedOptions = { ...defaultOptions, ...options };
    
    if (mergedOptions.body && typeof mergedOptions.body !== 'string') {
        mergedOptions.body = JSON.stringify(mergedOptions.body);
    }

    const response = await fetch(`/wp-json${endpoint}`, mergedOptions);

    if (!response.ok) {
        const errorData = await response.json().catch(() => ({
            message: 'An unknown API error occurred. The server response was not valid JSON.',
        }));
        throw new Error(errorData.message || `HTTP error! Status: ${response.status}`);
    }

    return response.json();
};
