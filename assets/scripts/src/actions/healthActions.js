import { HealthService } from '../services/HealthService.js';
import { updateState } from '../state/store.js';
import { NotificationService } from '../services/NotificationService.js';

/**
 * Fetches health data and updates the store.
 */
export async function fetchHealthData() {
  updateState({ isProcessing: true });
  try {
    const groups = await HealthService.fetchHealthStatus();
    if (groups) {
      updateState({ health: { groups } });
    }
  } catch (error) {
    NotificationService.showError('Failed to fetch health data.');
    console.error(error);
  } finally {
    updateState({ isProcessing: false });
  }
}

/**
 * Refreshes health data and updates the store.
 */
export async function refreshHealthData() {
  updateState({ isProcessing: true });
  try {
    const groups = await HealthService.refreshHealthStatus();
    if (groups) {
      updateState({ health: { groups } });
    }
  } catch (error) {
    NotificationService.showError('Failed to refresh health data.');
    console.error(error);
  } finally {
    updateState({ isProcessing: false });
  }
}
