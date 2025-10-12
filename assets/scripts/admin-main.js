import { unified_state, updateUnifiedState, STATUS } from './modules/state/store.js';
import { api_request } from './modules/api.js';
import { show_global_spinner, hide_global_spinner } from './modules/ui/spinner.js';
import { App } from './modules/ui/setup.js';
import { ensureToast } from './modules/ui/toast.js';
import { AppService } from './modules/services/AppService.js';
import { PackageService } from './modules/services/PackageService.js';
import { Logger } from './modules/utils.js';
import { HealthView } from './modules/ui/views/HealthView.js';
import { initializeApp } from './modules/services/AppInitializer.js';
import { onFormFieldInput, toggleOrgFields } from './modules/ui/handlers/appEventHandlers.js';
import { openModal, closeModal } from './modules/ui/handlers/packageEventHandlers.js';

// Import Bootstrap's JavaScript
import * as bootstrap from 'bootstrap';

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
            await syncPackages();
            try {
                await AppService.fetchApps();
            } catch (error) {
                Logger.error('Failed to refresh apps after installation.', error);
            }
        }
    } catch (error) {
        Logger.error('Failed to fetch connection status', error);
        const toast = await ensureToast();
        toast('Could not connect to the server.', 'error', error.message);
        updateUnifiedState({
            status: STATUS.ERROR,
            message: error.message || 'An unexpected error occurred.',
            isProcessing: false,
        });
    } finally {
        if (!silent) hide_global_spinner();
    }
};

const scheduleInstallationPoll = () => {
    if (pollHandle) return;
    updateUnifiedState({ polling: { active: true } });

    pollHandle = setTimeout(async () => {
        pollHandle = null;
        await fetchConnectionStatus({ silent: true });
        if (unified_state.get().status === STATUS.APP_CREATED) {
            scheduleInstallationPoll();
        } else {
            stopPolling();
        }
    }, 5000);
};

const handleGitHubCallback = () => {
    const container = document.getElementById('wp2-update-github-callback');
    if (!container) return;

    const notice = (message, isError = false) => {
        container.innerHTML = `<div class="wp2-notice ${isError ? 'wp2-notice-error' : 'wp2-notice-success'}"><p>${message}</p></div>`;
    };

    const params = new URLSearchParams(window.location.search);
    const code = params.get('code');
    const state = params.get('state');

    if (!code || !state) {
        notice('Invalid callback parameters. Please try again.', true);
        return;
    }

    notice('Finalizing GitHub connection, please wait…');

    (async () => {
        try {
            await api_request('github/exchange-code', { body: { code, state } });
            notice('Connection successful! You may close this tab.');
            window.opener?.postMessage('wp2-update-github-connected', window.location.origin);
            window.close();
        } catch (error) {
            Logger.error('GitHub exchange failed', error);
            notice(`An error occurred: ${error.message}. Please try again.`, true);
        }
    })();
};

const bootstrapInitialState = () => {
    const {
        connectionStatus,
        apps = [],
        selectedAppId = null,
        packages = [],
        unlinkedPackages = [],
        packageError = '',
        health = {},
        stats = {}
    } = window.wp2UpdateData || {};

    updateUnifiedState({
        status: connectionStatus?.status || STATUS.LOADING,
        message: connectionStatus?.message || '',
        details: connectionStatus?.details || {},
        apps,
        selectedAppId: selectedAppId ?? (apps[0]?.id ?? null),
        allPackages: [...packages, ...unlinkedPackages],
        packages: packages,
        unlinkedPackages,
        isProcessing: false,
        health,
        stats
    });

    if (packageError) {
        Logger.error('Package preload error:', packageError);
    }
};

const openWizardModal = () => {
    const modal = document.getElementById('wp2-modal-app');
    if (!modal) {
        Logger.error('Modal content container not found in the DOM.');
        return;
    }

    modal.hidden = false;
    modal.querySelector('.wp2-modal-close').addEventListener('click', () => {
        modal.hidden = true;
    });

    Logger.debug('Modal opened successfully.');
};

const controllers = { fetchConnectionStatus, syncPackages, stopPolling };
App.setControllers(controllers);

window.addEventListener('message', (event) => {
    if (event.data === 'wp2-update-github-connected') {
        fetchConnectionStatus();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    if (window.wp2UpdateInitialized) {
        console.warn('App.init() called multiple times. Initialization skipped.');
        return;
    }
    window.wp2UpdateInitialized = true;

    Logger.info('DOM Content Loaded. Initializing WP2 Update SPA.');

    if (document.getElementById('wp2-update-github-callback')) {
        handleGitHubCallback();
        return;
    }

    // Bootstrap the data that was loaded by PHP.
    bootstrapInitialState();

    // Attach event listeners to the server-rendered dashboard.
    App.init();
    initializeApp();
    onFormFieldInput();
    toggleOrgFields();
    packageEventHandlers.register();

    // Now, fetch fresh data to ensure the tables are up-to-date.
    PackageService.fetchPackages();
    AppService.fetchApps();

    const activeTab = new URLSearchParams(window.location.search).get('tab');

    if (activeTab === 'health') {
        HealthView.init();
    }
});
