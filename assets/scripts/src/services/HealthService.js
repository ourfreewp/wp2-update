import { apiFetch } from '../utils/apiFetch.js';
const { __ } = wp.i18n;

/**
 * Service for fetching health status data.
 */
export const HealthService = {
    async fetchHealthStatus() {
        try {
            const response = await apiFetch({ path: '/health' });
            const data = response?.data ?? response;
            if (!Array.isArray(data)) {
                console.warn('Unexpected health data format:', data);
                return data;
            }
            return data;
        } catch (error) {
            console.error('Error fetching health status:', error);
            throw error;
        }
    },

    /**
     * Refreshes the health status by sending a POST request.
     * Updates the store to reflect the loading state.
     */
    async refreshHealthStatus() {
        try {
            const response = await apiFetch({
                path: '/health', // Updated to match backend
                method: 'POST',
            });
            const data = response?.data ?? response;
            return data;
        } catch (error) {
            console.error('Error refreshing health status:', error);
            throw error;
        }
    },
};
