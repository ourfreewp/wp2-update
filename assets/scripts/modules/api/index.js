// Centralized REST helpers with nonce refresh + JSON handling
const { nonce: initial_nonce, apiRoot } = window.wp2UpdateData || {};
let current_nonce = initial_nonce;

if (!current_nonce || !apiRoot) {
	console.error('WP2 Update: missing wp2UpdateData.nonce/apiRoot');
}

const build_url = (endpoint) =>
	`${apiRoot.replace(/\/+$/, '')}/${String(endpoint).replace(/^\/+/, '')}`;

const json_headers = () => ({
	'Content-Type': 'application/json',
	'X-WP-Nonce': current_nonce,
});

const refresh_nonce = async () => {
	const res = await fetch(build_url('wp2-update/v1/refresh-nonce'), { method: 'GET' });
	if (!res.ok) throw new Error('Failed to refresh nonce');
	const data = await res.json();
	current_nonce = data.nonce;
};

/**
 * @param {string} endpoint
 * @param {RequestInit & {body?: any}} [options]
 * @param {number} [retries=3]
 */
export const api_request = async (endpoint, options = {}, retries = 3) => {
	for (let attempt = 0; attempt < retries; attempt++) {
		try {
			const url = build_url(endpoint);
			const init = {
				method: 'POST',
				headers: json_headers(),
				...options,
				headers: { ...json_headers(), ...(options.headers || {}) },
			};
			if (init.body && typeof init.body !== 'string') init.body = JSON.stringify(init.body);

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