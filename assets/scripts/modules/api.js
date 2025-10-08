// ========================================================================
// File: src-js/modules/api.js
// Description: A centralized helper for making REST API requests.
// ========================================================================

const restNonce = window.wpApiSettings?.nonce || '';
const wp2UpdateNonce = window.wp2UpdateData?.nonce || '';

if (!restNonce) {
    console.warn('REST API nonce is missing. Ensure wpApiSettings is properly localized.');
}

if (!wp2UpdateNonce) {
    console.warn('WP2 Update nonce is missing. Ensure wp2UpdateData is properly localized.');
}

// Normalize the API root URL to avoid double slashes
const apiRoot = (window.wpApiSettings?.root.replace(/\/+$/, '') || '') + '/';

if (!apiRoot) {
    console.warn('REST API root URL is missing. Ensure wpApiSettings is properly localized.');
}

/**
 * A reusable wrapper for the Fetch API to interact with the WordPress REST API.
 * @param {string} endpoint - The API endpoint (e.g., '/wp2-update/v1/status').
 * @param {object} [options={}] - Configuration options for the fetch request.
 * @returns {Promise<any>} - The JSON response from the API.
 */
export const apiRequest = async (endpoint, options = {}) => {
    console.log('[DEBUG] API Request - restNonce:', restNonce);
    console.log('[DEBUG] API Request - apiRoot:', apiRoot);

    const defaultOptions = {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': restNonce,
            'X-WP2-Update-Nonce': wp2UpdateNonce, // Include the wp2_update_nonce
        },
    };
    const mergedOptions = { ...defaultOptions, ...options };
    
    if (mergedOptions.body && typeof mergedOptions.body !== 'string') {
        mergedOptions.body = JSON.stringify(mergedOptions.body);
    }

    const response = await fetch(`${apiRoot}${endpoint.replace(/^\/+/, '')}`, mergedOptions);

    if (!response.ok) {
        const errorData = await response.json().catch(() => ({
            message: 'An unknown API error occurred. The server response was not valid JSON.',
        }));
        throw new Error(errorData.message || `HTTP error! Status: ${response.status}`);
    }

    return response.json();
};
