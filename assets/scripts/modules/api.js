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

const refresh_nonce = async () => {
    const res = await fetch(build_url('refresh-nonce'), { method: 'GET' });
    if (!res.ok) {
        throw new Error('Failed to refresh nonce');
    }
    const data = await res.json();
    current_nonce = data.nonce;
};

const addAppIdToParams = (params = {}) => {
    const appId = window.wp2UpdateData?.app_id;
    if (!appId) {
        return params;
    }
    return { ...params, app_id: appId };
};

export const api_request = async (endpoint, options = {}, retries = 3) => {
    for (let attempt = 0; attempt < retries; attempt++) {
        try {
            const { params, headers: headerOverrides, ...restOptions } = options;
            const url = build_url(endpoint, addAppIdToParams(params));
            const init = {
                method: 'POST',
                ...restOptions,
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': current_nonce,
                    ...(headerOverrides || {}),
                },
            };

            if (init.body instanceof FormData) {
                delete init.headers['Content-Type'];
            } else if (init.body && typeof init.body !== 'string') {
                init.body = JSON.stringify(init.body);
            }

            if ((init.method || 'POST').toUpperCase() === 'GET') {
                delete init.body;
            }

            const res = await fetch(url, init);
            if (res.status === 204) return null;
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                throw new Error(data.message || `HTTP ${res.status} ${res.statusText}`);
            }
            return res.json();
        } catch (error) {
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
    return api_request('sync-packages', { method: 'GET' });
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
    return api_request('packages/assign', {
        method: 'POST',
        body: packageData,
    });
};
