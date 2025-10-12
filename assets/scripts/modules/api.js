import { Logger } from './utils.js';
import { ensureToast } from './ui/toast.js';

const { nonce: initial_nonce, apiRoot } = window.wp2UpdateData || {};

let current_nonce = initial_nonce;

if (!current_nonce || !apiRoot) {
    console.error('WP2 Update: missing wp2UpdateData.nonce/apiRoot');
}

const build_url = (endpoint, query) => {
    const base = `${apiRoot.replace(/\/+$/, '')}/${String(endpoint).replace(/^\/+/, '')}`;
    if (!query || typeof query !== 'object' || !Object.keys(query).length) {
        return base;
    }
    const search = new URLSearchParams(query);
    return `${base}?${search.toString()}`;
};

const refresh_nonce = async (action = 'wp_rest') => {
    try {
        const res = await fetch(build_url('refresh-nonce', { action }), { method: 'GET' });
        if (!res.ok) {
            console.error('Failed to refresh nonce: HTTP status', res.status);
            throw new Error('Failed to refresh nonce');
        }
        const data = await res.json();
        current_nonce = data.data.nonce;
        console.log(`Nonce refreshed successfully for action '${action}':`, current_nonce);
    } catch (error) {
        console.error('Error refreshing nonce:', error);
        throw error;
    }
};

export const api_request = async (endpoint, options = {}, retries = 3) => {
    for (let attempt = 0; attempt < retries; attempt++) {
        try {
            const { params, headers: headerOverrides, ...restOptions } = options;
            const url = build_url(endpoint, params);

            const appId = window.wp2UpdateData?.selectedAppId;

            const init = {
                // Set default method to GET
                method: 'GET',
                ...restOptions,
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': current_nonce,
                    ...(appId ? { 'X-WP2-App-ID': appId } : {}),
                    ...(headerOverrides || {}),
                },
            };

            if (init.body instanceof FormData) {
                delete init.headers['Content-Type'];
            } else if (init.body && typeof init.body !== 'string') {
                init.body = JSON.stringify(init.body);
            }

            // Only include a body for non-GET requests
            if (init.method.toUpperCase() === 'GET') {
                delete init.body;
            }

            const res = await fetch(url, init);
            if (res.status === 204) return null;
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                Logger.error(`API request failed: ${data.message || `HTTP ${res.status} ${res.statusText}`}`);
                const toast = await ensureToast();
                toast(data.message || `HTTP ${res.status} ${res.statusText}`, 'error');
                throw new Error(data.message || `HTTP ${res.status} ${res.statusText}`);
            }
            return res.json();
        } catch (error) {
            Logger.error(`Attempt ${attempt + 1} failed for endpoint ${endpoint}: ${error.message}`);
            if (attempt === retries - 1) {
                throw error;
            }
            await refresh_nonce();
        }
    }
};

export const fetchConnectionStatus = async () => {
    return api_request('connection-status', { method: 'GET' });
};

export const syncPackages = async () => {
    return api_request('packages/sync', { method: 'GET' });
};

export const createApp = async (appData) => {
    return api_request('apps', {
        method: 'POST',
        body: appData,
    });
};

export const updateApp = async (appId, appData) => {
    return api_request(`apps/${appId}`, {
        method: 'PUT',
        body: appData,
    });
};

export const deleteApp = async (appId) => {
    return api_request(`apps/${appId}`, {
        method: 'DELETE',
    });
};

export const assignPackageToApp = async (packageData) => {
    // Updated to use `app_uid` instead of `app_id`
    const updatedPackageData = {
        ...packageData,
        app_uid: packageData.app_id, // Map `app_id` to `app_uid`
    };
    delete updatedPackageData.app_id; // Remove `app_id` to avoid conflicts

    return api_request('packages/assign', {
        method: 'POST',
        body: updatedPackageData,
    });
};

export const validate_nonce = async (nonce, action) => {
    try {
        const res = await fetch(build_url('validate-nonce', { nonce, action }), { method: 'GET' });
        if (!res.ok) {
            console.error('Nonce validation failed: HTTP status', res.status);
            throw new Error('Nonce validation failed');
        }
        const data = await res.json();
        return data.valid;
    } catch (error) {
        console.error('Error validating nonce:', error);
        throw error;
    }
};
