import { AppService } from '../services/AppService.js';
import { updateState } from '../state/store.js';
import { NotificationService } from '../services/NotificationService.js';

const appService = new AppService();

/**
 * Fetches all apps and updates the store.
 */
export async function fetchApps() {
  updateState({ isProcessing: true });
  try {
    const apps = await appService.fetchApps();
    updateState({ apps });
  } catch (error) {
    NotificationService.showError('Failed to fetch applications.');
    console.error(error);
  } finally {
    updateState({ isProcessing: false });
  }
}

/**
 * Creates a new application.
 * @param {object} appData - The data for the new app (e.g., { name: 'My New App' }).
 */
export async function createApp(appData) {
  updateState({ isProcessing: true });
  try {
    await appService.createApp(appData);
    NotificationService.showSuccess(`App "${appData.name}" created successfully.`);
    // Close the modal and refresh the app list
    updateState({ activeModal: null });
    await fetchApps();
  } catch (error) {
    NotificationService.showError('Failed to create application.');
    console.error(error);
  } finally {
    updateState({ isProcessing: false });
  }
}

/**
 * Opens the modal for creating a new application.
 */
export function openCreateAppModal() {
  updateState({ activeModal: 'createApp' });
}

/**
 * Deletes an application.
 * @param {string} appId - The ID of the app to delete.
 * @param {string} appName - The name of the app for the confirmation message.
 */
export async function deleteApp(appId, appName) {
  if (!confirm(`Are you sure you want to delete ${appName}?`)) {
    return;
  }
  updateState({ isProcessing: true });
  try {
    await appService.deleteApp(appId);
    NotificationService.showSuccess(`App "${appName}" deleted.`);
    await fetchApps(); // Refresh the list
  } catch (error) {
    NotificationService.showError('Failed to delete application.');
    console.error(error);
  } finally {
    updateState({ isProcessing: false });
  }
}

/**
 * Disconnects an app from its associated package.
 * @param {string} appId - The ID of the app to disconnect.
 */
export async function disconnectApp(appId) {
  updateState({ isProcessing: true });
  try {
    await appService.disconnectApp(appId);
    NotificationService.showSuccess('App disconnected successfully.');
    // Refresh the app list to reflect the changes
    await fetchApps();
  } catch (error) {
    NotificationService.showError('Failed to disconnect app.');
    console.error(error);
  } finally {
    updateState({ isProcessing: false });
  }
}