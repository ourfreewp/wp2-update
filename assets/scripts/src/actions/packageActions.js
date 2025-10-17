import { PackageService } from '../services/PackageService.js';
import { updateState } from '../state/store.js';
import { NotificationService } from '../services/NotificationService.js';

const packageService = new PackageService();

/**
 * Fetches all packages and updates the store with graceful error handling.
 */
export async function fetchPackages() {
  updateState({ isProcessing: true });
  try {
    const packages = await packageService.fetchPackages();
    updateState({ packages });
  } catch (error) {
    NotificationService.showError(error.message || 'An unexpected error occurred while fetching packages.');
    updateState({ packages: [] }); // Clear packages to show an empty state
  } finally {
    updateState({ isProcessing: false });
  }
}

/**
 * Updates a specific package to a new release.
 * @param {string} packageId 
 * @param {string} releaseTag 
 */
export async function updatePackage(packageId, releaseTag) {
  updateState({ isProcessing: true });
  try {
    await packageService.updatePackage(packageId, releaseTag);
    NotificationService.showSuccess('Package update initiated!');
    // After the update, refresh the package list to show the new status
    await fetchPackages();
  } catch (error) {
    NotificationService.showError('Failed to update package.');
    console.error(error);
  } finally {
    updateState({ isProcessing: false });
  }
}

/**
 * Rolls back a specific package to a previous release.
 * @param {string} packageId 
 * @param {string} releaseTag 
 */
export async function rollbackPackage(packageId, releaseTag) {
  updateState({ isProcessing: true });
  try {
    await packageService.rollbackPackage(packageId, releaseTag);
    NotificationService.showSuccess('Package rollback initiated!');
    // Refresh the package list to show the updated status
    await fetchPackages();
  } catch (error) {
    NotificationService.showError('Failed to rollback package.');
    console.error(error);
  } finally {
    updateState({ isProcessing: false });
  }
}

/**
 * Deletes a specific package.
 * @param {string} packageId 
 */
export async function deletePackage(packageId) {
  updateState({ isProcessing: true });
  try {
    await packageService.deletePackage(packageId);
    NotificationService.showSuccess('Package deleted successfully!');
    // Refresh the package list to reflect the deletion
    await fetchPackages();
  } catch (error) {
    NotificationService.showError('Failed to delete package.');
    console.error(error);
  } finally {
    updateState({ isProcessing: false });
  }
}

/**
 * Synchronizes a specific package with the latest data from the server.
 * @param {string} packageId - The ID of the package to synchronize.
 */
export async function syncPackage(packageId) {
  updateState({ isProcessing: true });
  try {
    const updatedPackage = await packageService.syncPackage(packageId);
    NotificationService.showSuccess('Package synchronized successfully!');
    // Update the store with the synchronized package data
    updateState((state) => {
      const packages = state.packages.map((pkg) =>
        pkg.id === packageId ? updatedPackage : pkg
      );
      return { packages };
    });
  } catch (error) {
    NotificationService.showError('Failed to synchronize package.');
    console.error(error);
  } finally {
    updateState({ isProcessing: false });
  }
}

/**
 * Assigns an app to a specific package.
 * @param {string} packageId - The ID of the package.
 * @param {string} appId - The ID of the app to assign.
 */
export async function assignAppToPackage(packageId, appId) {
  updateState({ isProcessing: true });
  try {
    await packageService.assignAppToPackage(packageId, appId);
    NotificationService.showSuccess('App assigned to package successfully!');
    // Refresh the package list to reflect the changes
    await fetchPackages();
  } catch (error) {
    NotificationService.showError('Failed to assign app to package.');
    console.error(error);
  } finally {
    updateState({ isProcessing: false });
  }
}

/**
 * Creates a new package.
 * @param {object} packageData - The data for the new package (e.g., { name: 'My Package', repo: 'my-repo' }).
 */
export async function createPackage(packageData) {
  updateState({ isProcessing: true });
  try {
    await packageService.createPackage(packageData);
    NotificationService.showSuccess(`Package "${packageData.name}" created successfully.`);
    // Refresh the package list to reflect the new package
    await fetchPackages();
  } catch (error) {
    NotificationService.showError('Failed to create package.');
    console.error(error);
  } finally {
    updateState({ isProcessing: false });
  }
}