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
            const response = await api_request('packages', { method: 'GET' }, 'wp2_get_packages');
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
            await api_request('packages/sync', { method: 'POST' }, 'wp2_sync_packages');
            await this.fetchPackages();
            NotificationService.showSuccess('Packages synced successfully.'); // Ensure proper toast integration
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
            const response = await api_request(`packages/${packageRepo}/release-notes`, {}, 'wp2_get_release_notes');
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
            }, 'wp2_toggle_auto_update');
            NotificationService.showSuccess(`Auto-update ${isEnabled ? 'enabled' : 'disabled'}.`); // Ensure proper toast integration
        } catch (error) {
            logger.error(`Error toggling auto-update for ${packageRepo}:`, error);
        }
    }

    /**
     * Switches the release channel for a package.
     * @param {string} packageId - The ID of the package.
     * @param {string} channel - The new release channel.
     */
    async switchReleaseChannel(packageId, channel) {
        try {
            const response = await api_request(`packages/${packageId}/release-channel`, {
                method: 'POST',
                body: JSON.stringify({ channel }),
            }, 'wp2_switch_release_channel');
            logger.info(`Switched to channel ${channel} for package ${packageId}:`, response);
            NotificationService.showSuccess(`Successfully switched to the ${channel} channel.`); // Ensure proper toast integration
        } catch (error) {
            logger.error(`Error switching channel for package ${packageId}:`, error);
            NotificationService.showError('Failed to switch release channel.', error.message); // Ensure proper toast integration
        }
    }

    /**
     * Fetches and displays release notes for a package.
     * @param {string} packageId - The ID of the package.
     */
    async fetchAndDisplayReleaseNotes(packageId) {
        try {
            const notes = await this.getReleaseNotes(packageId);
            if (notes) {
                const modal = document.getElementById('release-notes-modal');
                if (modal) {
                    modal.innerHTML = `<div class='release-notes-content'>${notes}</div>`;
                    modal.style.display = 'block';
                } else {
                    logger.error('Release notes modal not found in the DOM.');
                }
            }
        } catch (error) {
            logger.error(`Error fetching release notes for package ${packageId}:`, error);
        }
    }

    /**
     * Handles switching the release channel.
     * @param {string} packageId - The ID of the package.
     * @param {string} channel - The new release channel.
     */
    async handleSwitchReleaseChannel(packageId, channel) {
        try {
            await this.switchReleaseChannel(packageId, channel);
        } catch (error) {
            logger.error(`Error switching channel for package ${packageId}:`, error);
        }
    }

    /**
     * Handles fetching and displaying release notes.
     * @param {string} packageId - The ID of the package.
     */
    async handleFetchReleaseNotes(packageId) {
        try {
            await this.fetchAndDisplayReleaseNotes(packageId);
        } catch (error) {
            logger.error(`Error fetching release notes for package ${packageId}:`, error);
        }
    }
}
