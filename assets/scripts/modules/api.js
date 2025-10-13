import { logger } from './utils/logger.js';
import { NotificationService } from './services/NotificationService.js';

const { nonce: initial_nonce, apiRoot } = window.wp2UpdateData || {};
let current_nonce = initial_nonce;

/**
 * Constructs the full API URL.
 * @param {string} endpoint - The API endpoint.
 * @param {object} query - Optional query parameters.
 * @returns {string} The full URL.
 */
const build_url = (endpoint, query) => {
    const base = `${apiRoot.replace(/\/+$/, '')}/${String(endpoint).replace(/^\/+/, '')}`;
    if (!query || typeof query !== 'object' || !Object.keys(query).length) {
        return base;
    }
    const search = new URLSearchParams(query);
    return `${base}?${search.toString()}`;
};

/**
 * Makes a request to the backend API.
 * @param {string} endpoint - The API endpoint to call.
 * @param {object} options - Fetch options (method, body, etc.).
 * @returns {Promise<any>} The JSON response from the API.
 */
export const api_request = async (endpoint, options = {}) => {
    const { params, headers: headerOverrides, ...restOptions } = options;
    const url = build_url(endpoint, params);
    const appId = window.wp2UpdateData?.selectedAppId;

    const init = {
        method: 'GET',
        ...restOptions,
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': current_nonce,
            ...(appId ? { 'X-WP2-App-ID': appId } : {}),
            ...(headerOverrides || {}),
        },
    };

    try {
        const response = await fetch(url, init);
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(`API request failed with status ${response.status}: ${errorData.message || response.statusText}`);
        }
        return await response.json();
    } catch (error) {
        logger.error(`API request to ${endpoint} failed:`, error);
        NotificationService.showError(`Request to ${endpoint} failed.`, error.message);
        throw error;
    }
};

/**
 * Makes a DELETE request to the backend API.
 * @param {string} endpoint - The API endpoint to call.
 * @returns {Promise<any>} The JSON response from the API.
 */
export const apiDelete = async (endpoint) => {
    return api_request(endpoint, { method: 'DELETE' });
};
