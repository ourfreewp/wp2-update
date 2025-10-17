import { fetchReleaseNotes, packageService } from './PackageService.js';
import { NotificationService } from '../services/NotificationService.js';
import Modal from 'bootstrap/js/dist/modal';

/**
 * Handles switching the release channel.
 * @param {string} packageId - The ID of the package.
 * @param {string} channel - The new release channel.
 */
export function handleSwitchReleaseChannel(packageId, channel) {
    try {
        packageService.switchReleaseChannel(packageId, channel)
            .then(response => {
                console.log(`Switched to channel ${channel} for package ${packageId}:`, response);
            })
            .catch(error => {
                console.error(`Error switching channel for package ${packageId}:`, error);
                NotificationService.showError('An error occurred while switching the release channel. Please try again.');
            });
    } catch (error) {
        console.error(`Unexpected error switching channel for package ${packageId}:`, error);
        NotificationService.showError('An unexpected error occurred. Please try again.');
    }
}

/**
 * Handles fetching and displaying release notes.
 * @param {string} packageId - The ID of the package.
 */
export function handleFetchReleaseNotes(packageId) {
    try {
        fetchReleaseNotes(packageId)
            .then(notes => {
                displayReleaseNotes(notes);
            })
            .catch(error => {
                console.error(`Error fetching release notes for package ${packageId}:`, error);
                NotificationService.showError('An error occurred while fetching release notes. Please try again.');
            });
    } catch (error) {
        console.error('Unexpected error fetching release notes:', error);
    }
}

/**
 * Displays release notes in a modal.
 * @param {string} notes - The release notes to display.
 */
function displayReleaseNotes(notes) {
    const modal = document.getElementById('release-notes-modal');
    if (!modal) {
        console.error('Release notes modal not found in the DOM.');
        return;
    }

    const modalContent = modal.querySelector('.release-notes-content');
    if (modalContent) {
        modalContent.textContent = notes;
    } else {
        const contentElement = document.createElement('div');
        contentElement.className = 'release-notes-content';
        contentElement.textContent = notes;
        modal.appendChild(contentElement);
    }

    const modalInstance = new Modal(modal);
    modalInstance.show();
}