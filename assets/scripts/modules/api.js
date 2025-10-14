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
 * Fetches a unique nonce for a specific action.
 * @param {string} action - The action for which to fetch the nonce.
 * @returns {Promise<string>} The nonce string.
 */
const fetch_nonce = async (action) => {
    const url = build_url('/nonce', { action });
    try {
        const response = await fetch(url, { method: 'GET' });
        if (!response.ok) {
            throw new Error(`Failed to fetch nonce for action: ${action}`);
        }
        const data = await response.json();
        return data.nonce;
    } catch (error) {
        logger.error(`Failed to fetch nonce for action ${action}:`, error);
        throw error;
    }
};

/**
 * Makes a request to the backend API with action-specific nonce.
 * @param {string} endpoint - The API endpoint to call.
 * @param {object} options - Fetch options (method, body, etc.).
 * @param {string} action - The action for which to fetch the nonce.
 * @returns {Promise<any>} The JSON response from the API.
 */
export const api_request = async (endpoint, options = {}, action = null) => {
    const { params, headers: headerOverrides, ...restOptions } = options;
    const url = build_url(endpoint, params);
    const appId = window.wp2UpdateData?.selectedAppId;

    if (action) {
        current_nonce = await fetch_nonce(action);
    }

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
