/**
 * @file src-js/modules/api.js
 * @description Centralized helper for making REST API requests.
 */

// Localize wp2UpdateData from your PHP with 'nonce' and 'apiRoot' properties.
const { nonce, apiRoot } = window.wp2UpdateData || {};

if (!nonce || !apiRoot) {
    console.error(
        'WP2 Update Fatal Error: `wp2UpdateData` object with `nonce` and `apiRoot` is not available. Please ensure it is correctly localized via wp_localize_script.'
    );
}

/**
 * A reusable wrapper for the Fetch API to interact with the WordPress REST API.
 * It automatically handles the security nonce and JSON formatting.
 *
 * @param {string} endpoint - The API endpoint (e.g., 'wp2-update/v1/status').
 * @param {object} [options={}] - Configuration options for the fetch request.
 * @returns {Promise<any>} - The JSON response from the API.
 */
export const apiRequest = async (endpoint, options = {}) => {
    const url = `${apiRoot.replace(/\/+$/, '')}/${endpoint.replace(/^\/+/, '')}`;

    const defaultOptions = {
        method: 'POST', // Default to POST for actions, can be overridden.
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce,
        },
    };

    const mergedOptions = {
        ...defaultOptions,
        ...options,
        headers: {
            ...defaultOptions.headers,
            ...options.headers,
        },
    };

    // Automatically stringify body if it's an object
    if (mergedOptions.body && typeof mergedOptions.body !== 'string') {
        mergedOptions.body = JSON.stringify(mergedOptions.body);
    }

    try {
        const response = await fetch(url, mergedOptions);

        if (!response.ok) {
            // Try to parse a JSON error message from the body, otherwise use status text.
            const errorData = await response.json().catch(() => ({
                message: `HTTP Error: ${response.status} ${response.statusText}`,
            }));
            throw new Error(errorData.message || 'An unknown API error occurred.');
        }

        // Handle responses that might not have a body (e.g., 204 No Content)
        if (response.status === 204) {
            return null;
        }

        return response.json();
    } catch (error) {
        console.error(`[API Error] Failed to fetch from endpoint: ${endpoint}`, error);
        // Re-throw the error so the calling function can handle it.
        throw error;
    }
};

let currentNonce = nonce;

const refreshNonce = async () => {
    try {
        const response = await fetch(`${apiRoot}/wp2-update/v1/refresh-nonce`, {
            method: 'GET',
        });
        if (!response.ok) {
            throw new Error('Failed to refresh nonce');
        }
        const data = await response.json();
        currentNonce = data.nonce;
    } catch (error) {
        console.error('Failed to refresh nonce:', error);
        throw error;
    }
};

/**
 * A reusable wrapper for the Fetch API to interact with the WordPress REST API with retry logic.
 * It automatically handles the security nonce, JSON formatting, and retries for transient errors.
 *
 * @param {string} endpoint - The API endpoint (e.g., 'wp2-update/v1/status').
 * @param {object} [options={}] - Configuration options for the fetch request.
 * @param {number} [retries=3] - Number of retry attempts for transient errors.
 * @returns {Promise<any>} - The JSON response from the API.
 */
export const apiRequestWithRetry = async (endpoint, options = {}, retries = 3) => {
    const url = `${apiRoot.replace(/\/+$/, '')}/${endpoint.replace(/^\/+/, '')}`;

    const defaultOptions = {
        method: 'POST', // Default to POST for actions, can be overridden.
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': currentNonce,
        },
    };

    const mergedOptions = {
        ...defaultOptions,
        ...options,
        headers: {
            ...defaultOptions.headers,
            ...options.headers,
        },
    };

    // Automatically stringify body if it's an object
    if (mergedOptions.body && typeof mergedOptions.body !== 'string') {
        mergedOptions.body = JSON.stringify(mergedOptions.body);
    }

    for (let attempt = 0; attempt < retries; attempt++) {
        try {
            const response = await fetch(url, mergedOptions);

            if (response.status === 403 && attempt < retries - 1) {
                console.warn('Nonce expired. Attempting to refresh nonce.');
                await refreshNonce();
                continue;
            }

            if (!response.ok) {
                // Try to parse a JSON error message from the body, otherwise use status text.
                const errorData = await response.json().catch(() => ({
                    message: `HTTP Error: ${response.status} ${response.statusText}`,
                }));
                throw new Error(errorData.message || 'An unknown API error occurred.');
            }

            // Handle responses that might not have a body (e.g., 204 No Content)
            if (response.status === 204) {
                return null;
            }

            return response.json();
        } catch (error) {
            console.error(`[API Error] Attempt ${attempt + 1} failed for endpoint: ${endpoint}`, error);
            if (attempt === retries - 1) {
                throw error; // Re-throw the error if all retries fail
            }
        }
    }
};