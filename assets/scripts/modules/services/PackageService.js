import { api_request } from '../api.js';
import { updateState } from '../state/store.js';
import { logger } from '../utils/logger.js';
import { NotificationService } from './NotificationService.js';

export class PackageService {
    /**
     * Fetches all packages from the backend.
     */
    async fetchPackages() {
        try {
            const response = await api_request('packages', { method: 'GET' });
            const { packages, unlinked_packages } = response?.data || {};
            updateState({ packages: [...(packages || []), ...(unlinked_packages || [])] });
        } catch (error) {
            logger.error('Failed to fetch packages:', error);
        }
    }

    /**
     * Triggers a sync of all packages with GitHub.
     */
    async syncPackages() {
        updateState({ isProcessing: true });
        try {
            await api_request('packages/sync', { method: 'POST' });
            await this.fetchPackages();
            NotificationService.showSuccess('Packages synced successfully.');
        } catch (error) {
            logger.error('Failed to sync packages:', error);
        } finally {
            updateState({ isProcessing: false });
        }
    }

    /**
     * Fetches release notes for a specific package.
     * @param {string} packageRepo - The repository slug of the package.
     * @returns {Promise<string|null>} The release notes HTML or null on error.
     */
    async getReleaseNotes(packageRepo) {
        try {
            const response = await api_request(`packages/${packageRepo}/release-notes`);
            return response.notes;
        } catch (error) {
            logger.error(`Error fetching release notes for ${packageRepo}:`, error);
            return null;
        }
    }

    /**
     * Toggles the auto-update setting for a package.
     * @param {string} packageRepo - The repository slug of the package.
     * @param {boolean} isEnabled - The new auto-update status.
     */
    async toggleAutoUpdate(packageRepo, isEnabled) {
        try {
            await api_request(`packages/${packageRepo}/auto-update`, {
                method: 'POST',
                body: JSON.stringify({ auto_update: isEnabled }),
            });
            NotificationService.showSuccess(`Auto-update ${isEnabled ? 'enabled' : 'disabled'}.`);
        } catch (error) {
            logger.error(`Error toggling auto-update for ${packageRepo}:`, error);
        }
    }
}
