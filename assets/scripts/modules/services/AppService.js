import { api_request } from '../api.js';
import { updateState } from '../state/store.js';
import { logger } from '../utils/logger.js';

export class AppService {
    /**
     * Fetches all configured GitHub Apps.
     */
    async fetchApps() {
        try {
            const response = await api_request('apps', { method: 'GET' });
            const apps = response?.data?.apps ?? [];
            updateState(state => ({
                apps,
                selectedAppId: state.selectedAppId ?? (apps[0]?.id ?? null),
            }));
        } catch (error) {
            logger.error('Failed to fetch apps:', error);
        }
    }

    /**
     * Creates a new GitHub App.
     * @param {object} appData - The data for the new app.
     * @returns {Promise<object|null>} The created app object or null on error.
     */
    async createApp(appData) {
        try {
            const response = await api_request('apps', {
                method: 'POST',
                body: JSON.stringify(appData),
            });
            await this.fetchApps(); // Refresh app list
            return response.data;
        } catch (error) {
            logger.error('Failed to create app:', error);
            return null;
        }
    }
}
