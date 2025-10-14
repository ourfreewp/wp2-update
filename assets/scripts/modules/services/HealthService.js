import { store } from '../state/store.js';

/**
 * Service for fetching health status data.
 */
export const HealthService = {
    async fetchHealthStatus() {
        try {
            const response = await fetch('/wp-json/wp2/v1/health');
            if (!response.ok) {
                throw new Error('Failed to fetch health status');
            }

            const healthData = await response.json();
            store.update((state) => ({
                ...state,
                health: healthData,
            }));
        } catch (error) {
            console.error('Error fetching health status:', error);
        }
    },
};