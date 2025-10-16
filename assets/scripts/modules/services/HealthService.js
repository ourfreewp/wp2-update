import { store } from '../state/store.js';
import { apiFetch } from '@wordpress/api-fetch';

/**
 * Service for fetching health status data.
 */
export const HealthService = {
    async fetchHealthStatus() {
        try {
            const healthData = await apiFetch({ path: '/wp2-update/v1/health' });
            store.update((state) => ({
                ...state,
                health: healthData,
            }));
        } catch (error) {
            console.error('Error fetching health status:', error);
        }
    },

    /**
     * Refreshes the health status by fetching data from the server.
     */
    async refreshHealthStatus() {
        try {
            const healthData = await apiFetch({
                path: '/wp2-update/v1/health-status',
                method: 'GET',
                headers: {
                    'X-WP-Nonce': wp2UpdateData.nonce,
                },
            });

            console.log('Health status refreshed:', healthData);

            // Optionally update the store or UI with the new health status
            store.update((state) => ({
                ...state,
                health: healthData,
            }));
        } catch (error) {
            console.error('Error refreshing health status:', error);
        }
    },
};