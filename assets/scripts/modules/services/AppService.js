import { api_request } from '../api.js';
import { updateState } from '../state/store.js';
import { logger } from '../utils/logger.js';

export class AppService {
    /**
     * Fetches all configured GitHub Apps.
     */
    async fetchApps() {
        try {
            const response = await api_request('apps', { method: 'GET' }, 'wp2_list_apps');
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
            }, 'wp2_create_app');
            await this.fetchApps(); // Refresh app list
            return response.data;
        } catch (error) {
            logger.error('Failed to create app:', error);
            return null;
        }
    }

    /**
     * Fetches paginated GitHub Apps.
     * @param {number} page - The page number to fetch.
     * @param {number} perPage - The number of items per page.
     */
    async fetchPaginatedApps(page = 1, perPage = 10) {
        try {
            const response = await api_request(`apps?page=${page}&per_page=${perPage}`, { method: 'GET' }, 'wp2_list_apps');
            const apps = response?.data?.apps ?? [];
            updateState(state => ({
                apps: [...state.apps, ...apps], // Append new apps to the existing state
                selectedAppId: state.selectedAppId ?? (apps[0]?.id ?? null),
            }));
        } catch (error) {
            logger.error('Failed to fetch paginated apps:', error);
        }
    }
}
