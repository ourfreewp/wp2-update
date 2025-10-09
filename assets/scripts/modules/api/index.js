// Centralized REST helpers with nonce refresh + JSON handling
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

const json_headers = () => ({
	'Content-Type': 'application/json',
	'X-WP-Nonce': current_nonce,
});

const refresh_nonce = async () => {
    const res = await fetch(build_url('refresh-nonce'), { method: 'GET' });
    if (!res.ok) {
        console.error('Failed to refresh nonce');
        throw new Error('Failed to refresh nonce');
    }
    const data = await res.json();
    current_nonce = data.nonce;
    console.log('Nonce refreshed successfully');
};

/**
 * @param {string} endpoint
 * @param {RequestInit & {body?: any}} [options]
 * @param {number} [retries=3]
 */
export const api_request = async (endpoint, options = {}, retries = 3) => {
	for (let attempt = 0; attempt < retries; attempt++) {
		try {
			const { params, headers: headerOverrides, ...restOptions } = options;
			const url = build_url(endpoint, params);
			const init = {
				method: 'POST',
				...restOptions,
				headers: { ...json_headers(), ...(headerOverrides || {}) },
			};
			if (init.body && typeof init.body !== 'string') init.body = JSON.stringify(init.body);

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
		} catch (err) {
			if (String(err?.message || '').toLowerCase().includes('nonce') && attempt < retries - 1) {
				try { await refresh_nonce(); continue; } catch {}
			}
			if (attempt === retries - 1) throw err;
		}
	}
};
