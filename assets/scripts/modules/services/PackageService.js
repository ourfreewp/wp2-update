import { apiFetch } from '@wordpress/api-fetch';
import { updateState } from '../state/store.js';
import { logger } from '../utils/logger.js';
import { NotificationService } from './NotificationService.js';

export class PackageService {
    /**
     * Fetches all packages from the backend.
     */
    async fetchPackages() {
        try {
            const response = await apiFetch({ path: '/wp2-update/v1/packages' });
            const { packages, unlinked_packages } = response || {};
            updateState({ packages: [...(packages || []), ...(unlinked_packages || [])] });
        } catch (error) {
            logger.error('Failed to fetch packages:', error);
            NotificationService.showError('An error occurred while fetching packages. Please try again.');
        }
    }

    /**
     * Triggers a sync of all packages with GitHub.
     */
    async syncPackages() {
        updateState({ isProcessing: true });
        try {
            await apiFetch({ path: '/wp2-update/v1/packages/sync', method: 'POST' });
            await this.fetchPackages();
            NotificationService.showSuccess('Packages synced successfully.');
        } catch (error) {
            logger.error('Failed to sync packages:', error);
            NotificationService.showError('An error occurred while syncing packages. Please try again.');
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
            const response = await apiFetch({ path: `/wp2-update/v1/packages/${packageRepo}/release-notes` });
            return response.notes;
        } catch (error) {
            logger.error(`Error fetching release notes for ${packageRepo}:`, error);
            NotificationService.showError('An error occurred while fetching release notes. Please try again.');
        }
    }

    /**
     * Toggles the auto-update setting for a package.
     * @param {string} packageRepo - The repository slug of the package.
     * @param {boolean} isEnabled - The new auto-update status.
     */
    async toggleAutoUpdate(packageRepo, isEnabled) {
        try {
            await apiFetch({
                path: `/wp2-update/v1/packages/${packageRepo}/auto-update`,
                method: 'POST',
                data: { auto_update: isEnabled },
            });
            NotificationService.showSuccess(`Auto-update ${isEnabled ? 'enabled' : 'disabled'}.`);
        } catch (error) {
            logger.error(`Error toggling auto-update for ${packageRepo}:`, error);
            NotificationService.showError('An error occurred while toggling auto-update. Please try again.');
        }
    }

    /**
     * Switches the release channel for a package.
     * @param {string} packageId - The ID of the package.
     * @param {string} channel - The new release channel.
     */
    async switchReleaseChannel(packageId, channel) {
        try {
            const response = await apiFetch({
                path: `/wp2-update/v1/packages/${packageId}/release-channel`,
                method: 'POST',
                data: { channel },
            });
            logger.info(`Switched to channel ${channel} for package ${packageId}:`, response);
            NotificationService.showSuccess(`Successfully switched to the ${channel} channel.`);
        } catch (error) {
            logger.error(`Error switching channel for package ${packageId}:`, error);
            NotificationService.showError('Failed to switch release channel.', error.message);
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

    /**
     * Updates a specific package to its latest release.
     * @param {string} packageRepo - The repository slug of the package.
     */
    async updatePackage(packageRepo) {
        try {
            await apiFetch({ path: `/wp2-update/v1/packages/${packageRepo}/update`, method: 'POST' });
            NotificationService.showSuccess(`Package ${packageRepo} updated successfully.`);
        } catch (error) {
            logger.error(`Failed to update package ${packageRepo}:`, error);
            NotificationService.showError(`An error occurred while updating package ${packageRepo}. Please try again.`);
        }
    }

    /**
     * Rolls back a specific package to a given version.
     * @param {string} packageRepo - The repository slug of the package.
     * @param {string} version - The version to roll back to.
     */
    async rollbackPackage(packageRepo, version) {
        try {
            await apiFetch({
                path: `/wp2-update/v1/packages/${packageRepo}/rollback`,
                method: 'POST',
                data: { version },
            });
            NotificationService.showSuccess(`Package ${packageRepo} rolled back to version ${version} successfully.`);
        } catch (error) {
            logger.error(`Failed to rollback package ${packageRepo}:`, error);
            NotificationService.showError(`An error occurred while rolling back package ${packageRepo}. Please try again.`);
        }
    }

    /**
     * Assigns a GitHub App to a package.
     * @param {string} packageRepo - The repository slug of the package.
     * @param {string} appId - The ID of the GitHub App to assign.
     */
    async assignAppToPackage(packageRepo, appId) {
        try {
            await apiFetch({
                path: '/wp2-update/v1/packages/assign',
                method: 'POST',
                data: { packageRepo, appId },
            });
            NotificationService.showSuccess(`App assigned to package ${packageRepo} successfully.`);
        } catch (error) {
            logger.error(`Failed to assign app to package ${packageRepo}:`, error);
            NotificationService.showError(`An error occurred while assigning app to package ${packageRepo}. Please try again.`);
        }
    }

    /**
     * Updates the release channel for a specific package.
     * @param {string} packageRepo - The repository slug of the package.
     * @param {string} channel - The release channel to set (e.g., 'stable', 'beta').
     */
    async updateReleaseChannel(packageRepo, channel) {
        try {
            await apiFetch({
                path: `/wp2-update/v1/packages/${packageRepo}/release-channel`,
                method: 'POST',
                data: { channel },
            });
            NotificationService.showSuccess(`Release channel for package ${packageRepo} updated to ${channel}.`);
        } catch (error) {
            logger.error(`Failed to update release channel for package ${packageRepo}:`, error);
            NotificationService.showError(`An error occurred while updating the release channel for package ${packageRepo}. Please try again.`);
        }
    }
}
