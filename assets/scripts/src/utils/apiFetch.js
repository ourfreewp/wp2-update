// Add the correlation ID to all API requests
const apiFetch = async (options) => {
    if (!window.wp2UpdateData || !window.wp2UpdateData.nonce || !window.wp2UpdateData.apiRoot) {
        throw new Error('WP2 Update API bootstrap data missing.');
    }

    const method = (options.method || 'GET').toUpperCase();
    const hasBody = options.data !== undefined && options.data !== null;

    const headers = {
        'Accept': 'application/json',
        ...options.headers,
        'X-Correlation-ID': window.wp2UpdateCorrelationId,
        'X-WP-Nonce': window.wp2UpdateData.nonce,
    };

    if (hasBody && !headers['Content-Type']) {
        headers['Content-Type'] = 'application/json';
    }

    // Ensure the path is prefixed with the REST API root
    const apiRoot = window.wp2UpdateData.apiRoot.replace(/\/$/, '');
    const normalizedPath = options.path.replace(/^\/wp2-update\/v1/, '');
    const fullPath = `${apiRoot}${normalizedPath}`;

    const response = await fetch(fullPath, {
        method,
        credentials: 'same-origin',
        cache: 'no-cache',
        ...options,
        headers,
        body: hasBody ? JSON.stringify(options.data) : undefined,
    });

    const contentType = response.headers.get('content-type') || '';
    const isJson = contentType.includes('application/json');
    const parse = async () => (isJson ? await response.json().catch(() => ({})) : await response.text());

    if (!response.ok) {
        const errorDetails = await parse();
        const message = (isJson ? (errorDetails.message || errorDetails.data?.message) : String(errorDetails)) || 'No additional details';
        const err = new Error(`API request failed (${response.status}): ${message}`);
        err.status = response.status;
        err.data = errorDetails;
        throw err;
    }

    return parse();
};

export { apiFetch };

/**
 * Enhanced API fetch utility to differentiate between network and API errors.
 */
export async function enhancedApiFetch(options) {
  try {
    const response = await apiFetch(options);
    return response;
  } catch (error) {
    if (error.code === 'fetch_error') {
      throw new Error('Network error: Unable to reach the server. Please check your connection.');
    }
    if (error.data && error.data.error) {
      throw new Error(`API error: ${error.data.error}`);
    }
    throw new Error('An unexpected error occurred.');
  }
}
