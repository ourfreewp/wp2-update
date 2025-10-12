// AppInitializer.js
// Handles bootstrapping logic for the application

import { unified_state, updateUnifiedState, STATUS } from '../state/store.js';
import { api_request } from '../api.js';
import { show_global_spinner, hide_global_spinner } from '../ui/spinner.js';
import { ensureToast } from '../ui/toast.js';
import { PackageService } from './PackageService.js';
import { Logger } from '../utils.js';

let pollHandle = null;

const stopPolling = () => {
    if (pollHandle) {
        clearTimeout(pollHandle);
        pollHandle = null;
        updateUnifiedState({ polling: { active: false } });
    }
};

const syncPackages = async () => {
    updateUnifiedState({ isProcessing: true });
    const syncButton = document.getElementById('wp2-sync-all');
    if (syncButton) {
        syncButton.disabled = true;
        syncButton.textContent = 'Syncing...';
    }

    try {
        show_global_spinner();
        await PackageService.syncPackages();
    } catch (error) {
        Logger.error('Failed to sync packages', error);
        const toast = await ensureToast();
        toast('Failed to sync packages from GitHub.', 'error', error.message);
    } finally {
        hide_global_spinner();
        updateUnifiedState({ isProcessing: false });
        if (syncButton) {
            syncButton.disabled = false;
            syncButton.textContent = 'Sync All';
        }
    }
};

const fetchConnectionStatus = async ({ silent = false } = {}) => {
    if (!silent) {
        updateUnifiedState({ status: STATUS.LOADING, isProcessing: true });
    }

    try {
        if (!silent) show_global_spinner();
        const response = await api_request('connection-status', { method: 'GET' });
        const data = response?.data || {};
        let status = data.status || STATUS.NOT_CONFIGURED;
        const previousStatus = unified_state.get().status;

        if (status === STATUS.NOT_CONFIGURED && data.unlinked_packages?.length) {
            status = STATUS.NOT_CONFIGURED_WITH_PACKAGES;
        }

        updateUnifiedState({
            status,
            message: data.message || '',
            details: data.details || {},
            unlinkedPackages: data.unlinked_packages || [],
            isProcessing: false,
        });

        if (!silent && status === STATUS.APP_CREATED && previousStatus === STATUS.APP_CREATED) {
            const toast = await ensureToast();
            toast('Still waiting for installation. Please complete the setup on GitHub.', 'warning');
        }

        if (status === STATUS.APP_CREATED) {
            scheduleInstallationPoll();
        } else if (status === STATUS.INSTALLED) {
            stopPolling();
        }
    } catch (error) {
        Logger.error('Failed to fetch connection status', error);
    } finally {
        if (!silent) hide_global_spinner();
    }
};

export const initializeApp = async () => {
    try {
        show_global_spinner();

        // Fetch initial data
        const response = await api_request('/initialize');
        const { apps, selectedAppId } = response;

        // Update state with fetched data
        updateUnifiedState({
            apps,
            selectedAppId,
        });

        console.log('App initialized successfully.');
    } catch (error) {
        console.error('Failed to initialize app:', error);
    } finally {
        hide_global_spinner();
    }
};

export { syncPackages, fetchConnectionStatus, stopPolling };