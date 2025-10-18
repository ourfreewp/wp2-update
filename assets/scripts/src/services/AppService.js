import { updateState } from '../state/store.js';
import { logger } from '../utils/logger.js';
import { NotificationService } from '../services/NotificationService.js';
import { apiFetch } from '../utils/apiFetch.js';

export class AppService {
    /**
     * Fetches all configured GitHub Apps.
     */
    async fetchApps() {
        try {
            const response = await apiFetch({ path: '/apps' });
            const apps = Array.isArray(response?.data?.apps) ? response.data.apps : Array.isArray(response?.apps) ? response.apps : [];
            if (!Array.isArray(apps)) {
                logger.warn('Unexpected apps format:', response);
                NotificationService.showError('Invalid data format received for apps.');
                return [];
            }
            return apps;
        } catch (error) {
            logger.error('Failed to fetch apps:', error);
            NotificationService.showError('An error occurred while fetching apps. Please try again.');
            throw error;
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
                path: '/apps',
                method: 'POST',
                data: appData,
            });
            return response?.data;
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
                path: `/apps?page=${page}&per_page=${perPage}`,
            });
            const apps = Array.isArray(response?.data?.apps) ? response.data.apps : [];
            if (!Array.isArray(apps)) {
                logger.warn('Unexpected apps format:', response);
                NotificationService.showError('Invalid data format received for apps.');
                return [];
            }
            return apps;
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
                path: '/apps/manifest',
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
                path: '/apps/code',
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
                path: `/apps/${appId}/status`,
                method: 'GET',
            });
            return response.data;
        } catch (error) {
            logger.error('Failed to fetch app status:', error);
            NotificationService.showError('An error occurred while fetching the app status. Please try again.');
        }
    }

    /**
     * Assigns an app to a package.
     * @param {string} packageRepo - The repository slug.
     * @param {string} appId - The app ID.
     */
    async assignAppToPackage(packageRepo, appId) {
        if (!packageRepo || !appId) {
            logger.error('Package repo or app ID is missing.');
            NotificationService.showError('Package repo or app ID cannot be empty.');
            return;
        }

        try {
            await apiFetch({
                path: '/packages/assign',
                method: 'POST',
                data: { repo_slug: packageRepo, app_id: appId },
            });
            NotificationService.showSuccess(`App assigned to package ${packageRepo} successfully.`);
        } catch (error) {
            logger.error(`Failed to assign app to package ${packageRepo}:`, error);
            NotificationService.showError(`An error occurred while assigning app to package ${packageRepo}. Please try again.`);
        }
    }
}
