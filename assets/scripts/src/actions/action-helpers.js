import { updateState } from '../state/store.js';
import { NotificationService } from '../services/NotificationService.js';

/**
 * Creates a higher-order function to handle async actions with consistent state updates and notifications.
 *
 * @param {Function} action - The async function to execute.
 * @param {Object} options - Configuration options for success and error messages.
 * @param {string} [options.successMessage] - Message to display on success.
 * @param {string} [options.errorMessage] - Message to display on error.
 * @returns {Function} - A wrapped async function with consistent behavior.
 */
export function createAsyncThunk(action, { successMessage, errorMessage }) {
  return async (...args) => {
    updateState({ isProcessing: true });
    try {
      const result = await action(...args);
      if (successMessage) {
        const message = typeof successMessage === 'function' ? successMessage(...args) : successMessage;
        if (message) {
          NotificationService.showSuccess(message);
        }
      }
      return result;
    } catch (error) {
      console.error(errorMessage, error);
      if (errorMessage) {
        const message = typeof errorMessage === 'function' ? errorMessage(error, ...args) : errorMessage;
        NotificationService.showError(message);
      }
      throw error;
    } finally {
      updateState({ isProcessing: false });
    }
  };
}
