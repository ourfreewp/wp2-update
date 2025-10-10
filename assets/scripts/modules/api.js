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
 * Handles API requests with automatic nonce refresh.
 *
 * This function attempts to make an API request to the specified endpoint. If the request fails due to an invalid nonce,
 * it will automatically refresh the nonce and retry the request up to three times.
 *
 * @param {string} endpoint - The API endpoint to call.
 * @param {RequestInit & {body?: any}} [options] - Additional options for the fetch request, such as method, headers, and body.
 * @param {number} [retries=3] - The number of retry attempts in case of a nonce-related failure.
 *
 * @returns {Promise<any>} - The JSON-parsed response from the API.
 *
 * @throws {Error} - Throws an error if all retry attempts fail or if the API returns an error.
 *
 * @example
 * ```js
 * try {
 *   const data = await api_request('example-endpoint', { method: 'POST', body: { key: 'value' } });
 *   console.log('API response:', data);
 * } catch (error) {
 *   console.error('API request failed:', error);
 * }
 * ```
 */
export const api_request = async (endpoint, options = {}, retries = 3) => {
	for (let attempt = 0; attempt < retries; attempt++) {
		try {
			const { params, headers: headerOverrides, ...restOptions } = options;
			const url = build_url(endpoint, params);
			const init = {
				method: 'POST',
				...restOptions,
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': current_nonce,
					...(headerOverrides || {})
				},
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
