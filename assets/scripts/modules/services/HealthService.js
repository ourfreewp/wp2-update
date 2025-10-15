import { store } from '../state/store.js';
import { api_request } from '../api.js';

/**
 * Service for fetching health status data.
 */
export const HealthService = {
    async fetchHealthStatus() {
        try {
            const healthData = await api_request('health', {}, 'wp2_get_health_status');
            store.update((state) => ({
                ...state,
                health: healthData,
            }));
        } catch (error) {
            console.error('Error fetching health status:', error);
        }
    },
};