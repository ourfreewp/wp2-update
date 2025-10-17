// Add the correlation ID to all API requests
const apiFetch = async (options) => {
    const headers = {
        ...options.headers,
        'X-Correlation-ID': window.wp2UpdateCorrelationId,
        'X-WP-Nonce': window.wp2UpdateData.nonce, // Add nonce for authentication
    };

    // Ensure the path is prefixed with the REST API root
    const apiRoot = window.wp2UpdateData.apiRoot.replace(/\/$/, ''); // Remove trailing slash if present
    const normalizedPath = options.path.replace(/^\/wp2-update\/v1/, ''); // Remove namespace if included
    const fullPath = `${apiRoot}${normalizedPath}`;

    const response = await fetch(fullPath, {
        ...options,
        headers,
    });

    if (!response.ok) {
        throw new Error(`API request failed with status ${response.status}`);
    }

    return response.json();
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