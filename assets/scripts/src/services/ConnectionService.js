import { store, updateState, STATUS } from '../state/store.js';
import { apiFetch } from '../utils/apiFetch.js';
import { logger } from '../utils/logger.js';
import { NotificationService } from './NotificationService.js';
import { PollingService } from './PollingService.js';

let pollHandle = null;

export class ConnectionService {
    constructor() {
        this.pollingInstance = null;
    }

    stopPolling() {
        if (this.pollingInstance) {
            this.pollingInstance.stopPolling();
            this.pollingInstance = null;
            this.setProcessingState(false);
            logger.info('Polling stopped.');
        }
    }

    scheduleInstallationPoll() {
        if (this.pollingInstance) return;
        this.setProcessingState(true);
        logger.info('Polling for installation status started...');

        this.pollingInstance = PollingService.startPolling({
            endpoint: '/apps/connection',
            interval: 5000,
            onSuccess: (data) => {
                const status = data.status || STATUS.NOT_CONFIGURED;
                updateState({
                    status,
                    message: data.message || '',
                    details: data.details || {},
                    packages: data.unlinked_packages || [],
                    isProcessing: false,
                });

                if (status !== STATUS.APP_CREATED) {
                    this.stopPolling();
                }
            },
            onError: (error) => {
                logger.error('Polling error:', error);
                this.stopPolling();
            },
        });
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
            console.debug('Using nonce:', window.wp2UpdateData?.nonce);
            const response = await apiFetch({ path: '/apps/status' }); // Updated to relative path
            const data = response || {};
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
                const errorMessage = error.message || 'An unexpected error occurred while fetching the connection status.';
                NotificationService.showError('Could not connect to the server.', errorMessage);
                updateState({
                    status: STATUS.ERROR,
                    message: errorMessage,
                    isProcessing: false,
                });
            }
        } finally {
            if (!silent) {
                this.setProcessingState(false);
            }
        }
    }

    setProcessingState(isProcessing) {
        updateState({ isProcessing });
    }

    // Backups API
    async listBackups(query = '') {
        const path = query ? `/backups?q=${encodeURIComponent(query)}` : '/backups';
        const res = await apiFetch({ path });
        return res?.data?.backups || res?.backups || [];
    }

    async restoreBackup(file, type) {
        if (!file || !type) throw new Error('file and type are required');
        const res = await apiFetch({ path: '/backups/restore', method: 'POST', data: { file, type } });
        return !!(res?.data?.restored || res?.restored);
    }

    async deleteBackup(file) {
        if (!file) throw new Error('file is required');
        const res = await apiFetch({ path: `/backups/delete?file=${encodeURIComponent(file)}`, method: 'DELETE' });
        return !!(res?.data?.deleted || res?.deleted);
    }

    async deleteBackups(files = []) {
        if (!Array.isArray(files) || files.length === 0) throw new Error('files are required');
        const res = await apiFetch({ path: '/backups/delete-bulk', method: 'POST', data: { files } });
        return res?.data?.results || res?.results || [];
    }
}
