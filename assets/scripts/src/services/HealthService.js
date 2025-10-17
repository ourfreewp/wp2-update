import { store } from '../state/store.js';
import { apiFetch } from '../utils/apiFetch.js';
const { __ } = wp.i18n;

/**
 * Service for fetching health status data.
 */
export const HealthService = {
    async fetchHealthStatus() {
        try {
            const healthData = await apiFetch({ path: '/health' });
            if (typeof healthData !== 'object' || healthData === null) {
                console.warn('Unexpected health data format:', healthData);
                return;
            }
            store.update((state) => ({
                ...state,
                health: healthData,
            }));
        } catch (error) {
            console.error('Error fetching health status:', error);
        }
    },

    /**
     * Refreshes the health status by sending a POST request.
     * Updates the store to reflect the loading state.
     */
    async refreshHealthStatus() {
        try {
            const data = await apiFetch({
                path: '/health', // Updated to match backend
                method: 'POST',
            });
            store.update((state) => ({
                ...state,
                health: data,
            }));
        } catch (error) {
            console.error('Error refreshing health status:', error);
        }
    },
};