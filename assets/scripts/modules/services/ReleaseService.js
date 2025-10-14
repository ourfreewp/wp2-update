import { fetchReleaseNotes, switchReleaseChannel } from './PackageService.js';
import { escapeHtml } from '../utils/string.js';

/**
 * Handles switching the release channel.
 * @param {string} packageId - The ID of the package.
 * @param {string} channel - The new release channel.
 */
export function handleSwitchReleaseChannel(packageId, channel) {
    try {
        switchReleaseChannel(packageId, channel)
            .then(response => {
                console.log(`Switched to channel ${channel} for package ${packageId}:`, response);
            })
            .catch(error => {
                console.error(`Error switching channel for package ${packageId}:`, error);
            });
    } catch (error) {
        console.error('Unexpected error during channel switch:', error);
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

    modal.innerHTML = `<div class='release-notes-content'>${escapeHtml(notes)}</div>`;
    modal.style.display = 'block';
}