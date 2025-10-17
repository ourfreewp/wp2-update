import { updateState, store } from '../state/store.js';
import { fetchApps } from './appActions.js';
import { fetchPackages } from './packageActions.js';
import { fetchHealthData } from './healthActions.js';
import { ConnectionService } from '../services/ConnectionService.js';
import { NotificationService } from '../services/NotificationService.js';

const connectionService = new ConnectionService();

/**
 * Fetches all the initial data needed for the application to start.
 */
export async function fetchInitialData() {
  updateState({ isProcessing: true });
  try {
    await connectionService.fetchConnectionStatus();
    // Use 'installed' from your backend status
    if (store.get().status === 'installed') { 
      // Run fetches in parallel for speed
      await Promise.all([
        fetchApps(),
        fetchPackages(),
        fetchHealthData(),
      ]);
    }
  } catch (error) {
    NotificationService.showError('Could not load initial application data.');
    console.error('Initial data sync failed:', error);
  } finally {
    updateState({ isProcessing: false });
  }
}