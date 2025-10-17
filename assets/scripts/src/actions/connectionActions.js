import { ConnectionService } from '../services/ConnectionService.js';
import { updateState } from '../state/store.js';
import { NotificationService } from '../services/NotificationService.js';

const connectionService = new ConnectionService();

/**
 * Fetches the connection status and updates the store.
 */
export async function fetchConnectionStatus() {
  updateState({ isProcessing: true });
  try {
    const status = await connectionService.fetchConnectionStatus();
    updateState({ connectionStatus: status });
  } catch (error) {
    NotificationService.showError('Failed to fetch connection status.');
    console.error(error);
  } finally {
    updateState({ isProcessing: false });
  }
}