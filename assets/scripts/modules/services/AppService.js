import { updateState } from '../state/store.js';
import { logger } from '../utils/logger.js';
import { NotificationService } from '../services/NotificationService.js';

export class AppService {
    /**
     * Fetches all configured GitHub Apps.
     */
    async fetchApps() {
        try {
            const response = await apiFetch({ path: '/wp2-update/v1/apps' });
            const apps = response?.data?.apps ?? [];
            updateState(state => ({
                apps,
                selectedAppId: state.selectedAppId ?? (apps.length > 0 ? apps[0].id : null),
            }));
        } catch (error) {
            logger.error('Failed to fetch apps:', error);
            NotificationService.showError('An error occurred while fetching apps. Please try again.');
        }
    }

    /**
     * Creates a new GitHub App.
     * @param {object} appData - The data for the new app.
     * @returns {Promise<object|null>} The created app object or null on error.
     */
    async createApp(appData) {
        try {
            const response = await apiFetch({
                path: '/wp2-update/v1/apps',
                method: 'POST',
                data: appData,
            });
            await this.fetchApps(); // Refresh app list
            return response.data;
        } catch (error) {
            logger.error('Failed to create app:', error);
            NotificationService.showError('An error occurred while creating the app. Please try again.');
        }
    }

    /**
     * Fetches paginated GitHub Apps.
     * @param {number} page - The page number to fetch.
     * @param {number} perPage - The number of items per page.
     */
    async fetchPaginatedApps(page = 1, perPage = 10) {
        try {
            const response = await apiFetch({
                path: `/wp2-update/v1/apps?page=${page}&per_page=${perPage}`,
            });
            const apps = response?.data?.apps ?? [];
            updateState(state => ({
                apps: [...state.apps, ...apps], // Append new apps to the existing state
                selectedAppId: state.selectedAppId ?? (apps.length > 0 ? apps[0].id : null),
            }));
        } catch (error) {
            logger.error('Failed to fetch paginated apps:', error);
        }
    }

    /**
     * Generates a GitHub App manifest and setup URL.
     */
    async generateManifest() {
        try {
            const response = await apiFetch({
                path: '/wp2-update/v1/apps/manifest',
                method: 'POST',
            });
            return response.data;
        } catch (error) {
            logger.error('Failed to generate manifest:', error);
            NotificationService.showError('An error occurred while generating the manifest. Please try again.');
        }
    }

    /**
     * Exchanges a temporary code from GitHub for permanent app credentials.
     * @param {string} code - The temporary code from GitHub.
     */
    async exchangeCode(code) {
        try {
            const response = await apiFetch({
                path: '/wp2-update/v1/apps/exchange-code',
                method: 'POST',
                data: { code },
            });
            return response.data;
        } catch (error) {
            logger.error('Failed to exchange code:', error);
            NotificationService.showError('An error occurred while exchanging the code. Please try again.');
        }
    }

    /**
     * Fetches the connection status for a specific app.
     * @param {string} appId - The ID of the app.
     */
    async fetchAppStatus(appId) {
        try {
            const response = await apiFetch({
                path: `/wp2-update/v1/apps/${appId}/status`,
                method: 'GET',
            });
            return response.data;
        } catch (error) {
            logger.error('Failed to fetch app status:', error);
            NotificationService.showError('An error occurred while fetching the app status. Please try again.');
        }
    }
}
