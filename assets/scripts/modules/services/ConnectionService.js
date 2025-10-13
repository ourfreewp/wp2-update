import { store, updateState, STATUS } from '../state/store.js';
import { api_request } from '../api.js';
import { logger } from '../utils/logger.js';
import { NotificationService } from './NotificationService.js';

let pollHandle = null;

export class ConnectionService {
    /**
     * Updates the processing state dynamically.
     * @param {boolean} isProcessing - Whether the application is processing.
     */
    setProcessingState(isProcessing) {
        updateState({ isProcessing });
    }

    /**
     * Stops the polling for installation status.
     */
    stopPolling() {
        if (pollHandle) {
            clearTimeout(pollHandle);
            pollHandle = null;
            this.setProcessingState(false);
            logger.info('Polling stopped.');
        }
    }

    /**
     * Schedules a poll to check the connection status.
     */
    scheduleInstallationPoll() {
        if (pollHandle) return;
        this.setProcessingState(true);
        logger.info('Polling for installation status started...');

        pollHandle = setTimeout(async () => {
            pollHandle = null;
            await this.fetchConnectionStatus({ silent: true });
            if (store.get().status === STATUS.APP_CREATED) {
                this.scheduleInstallationPoll();
            } else {
                this.stopPolling();
            }
        }, 5000);
    }

    /**
     * Fetches the current connection status from the backend.
     * @param {object} [options={}] - Options for fetching status.
     * @param {boolean} [options.silent=false] - If true, no global spinner is shown.
     */
    async fetchConnectionStatus({ silent = false } = {}) {
        if (!silent) {
            this.setProcessingState(true);
        }

        try {
            const response = await api_request('connection-status', { method: 'GET' });
            const data = response?.data || {};
            let status = data.status || STATUS.NOT_CONFIGURED;

            if (status === STATUS.NOT_CONFIGURED && data.unlinked_packages?.length) {
                status = STATUS.NOT_CONFIGURED_WITH_PACKAGES;
            }

            updateState({
                status,
                message: data.message || '',
                details: data.details || {},
                packages: data.unlinked_packages || [],
                isProcessing: false,
            });

            if (status === STATUS.APP_CREATED) {
                this.scheduleInstallationPoll();
            } else if (status === STATUS.INSTALLED) {
                this.stopPolling();
            }
        } catch (error) {
            logger.error('Failed to fetch connection status:', error);
            if (!silent) {
                NotificationService.showError('Could not connect to the server.', error.message);
                updateState({
                    status: STATUS.ERROR,
                    message: error.message || 'An unexpected error occurred.',
                    isProcessing: false,
                });
            }
        } finally {
            if (!silent) {
                this.setProcessingState(false);
            }
        }
    }
}
