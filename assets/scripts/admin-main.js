import { unified_state, updateUnifiedState, STATUS } from './modules/state/store.js';
import { api_request } from './modules/api.js';
import { show_global_spinner, hide_global_spinner } from './modules/ui/spinner.js';
import { App } from './modules/ui/setup.js';
import { ensureToast } from './modules/ui/toast.js';
import { AppService } from './modules/services/AppService.js';
import { PackageService } from './modules/services/PackageService.js';
import { Logger } from './modules/utils.js';
import { initializeTabs } from './modules/lib/tabby.js';

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
        syncButton.textContent = 'Syncing...'; // Update button text to indicate loading
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
            syncButton.textContent = 'Sync All'; // Restore original button text
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
    // Prefer localized values but fall back to the URL if needed.
    const code = window.wp2UpdateData?.githubCode ?? params.get('code');
    const state = window.wp2UpdateData?.githubState ?? params.get('state');

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

// --- Initialization ---

// Pass controller functions to the render module
const controllers = { fetchConnectionStatus, syncPackages, stopPolling };
App.setControllers(controllers);

window.addEventListener('message', (event) => {
    if (event.data === 'wp2-update-github-connected') {
        fetchConnectionStatus();
    }
});

// Ensure wp2UpdateData exists with default values
const wp2UpdateData = window.wp2UpdateData || {};

// Localize app list and selected app ID
const localizeAppData = () => {
    const localizedData = wp2UpdateData.apps || [];
    const selectedAppId = wp2UpdateData.selectedAppId || null;

    if (!Array.isArray(localizedData)) {
        console.warn('WP2 Update: localizedData.apps is not an array or is undefined.');
    }

    updateUnifiedState({
        apps: localizedData,
        selectedAppId,
    });
};

const initializeApp = () => {
    const state = unified_state.get();
    renderApp(state);

    unified_state.subscribe((newState) => {
        renderApp(newState);
    });
};

// Centralized Event Controller
const handleAction = async (action, target) => {
    const toast = await ensureToast();
    try {
        switch (action) {
            case 'open-wizard':
                openWizardModal();
                break;
            case 'refresh-packages':
                show_global_spinner();
                await PackageService.fetchPackages();
                toast(__('Package list refreshed.', 'wp2-update'), 'success');
                break;
            case 'assign-app':
                const repo = target.getAttribute('data-wp2-package');
                if (repo) openAssignModal(repo);
                break;
            case 'package-details':
                const packageRepo = target.getAttribute('data-wp2-package');
                if (packageRepo) openPackageDetailsModal(packageRepo);
                break;
            case 'app-details':
                const appId = target.getAttribute('data-wp2-app');
                if (appId) openAppDetailsModal(appId);
                break;
            case 'copy-manifest':
                handleCopyManifest();
                break;
            case 'open-github':
                handleOpenGithub();
                break;
            case 'wizard-finished':
                handleWizardFinished(fetchConnectionStatus);
                break;
            default:
                Logger.warn(`Unhandled action: ${action}`);
        }
    } catch (error) {
        Logger.error(`Action failed: ${action}`, error);
        toast(__('An error occurred while processing your request.', 'wp2-update'), 'error');
    } finally {
        hide_global_spinner();
    }
};

document.addEventListener('click', (event) => {
    const actionButton = event.target.closest('[data-wp2-action]');
    if (!actionButton) return;

    const action = actionButton.getAttribute('data-wp2-action');
    handleAction(action, actionButton);
});

// Call this once on DOMContentLoaded
document.addEventListener('DOMContentLoaded', async () => {
    Logger.info('DOMContentLoaded event fired. Initializing WP2 Update SPA.');

    try {
        await fetchConnectionStatus();
        Logger.info('Connection status fetched successfully.');
    } catch (error) {
        Logger.error('Error fetching connection status.', error);
    }

    localizeAppData();
    Logger.info('App data localized.');

    initializeTabs();
    Logger.info('Top-level tabs initialized with Tabby.');

    const currentApps = unified_state.get().apps;
    if (!Array.isArray(currentApps) || !currentApps.length) {
        try {
            await AppService.fetchApps();
        } catch (error) {
            Logger.error('Failed to load apps from the server.', error);
            const toast = await ensureToast();
            toast('Unable to load apps from the server.', 'error', error.message);
        }
    }

    Logger.info('WP2 Update SPA initialized successfully.');
    initializeApp();
    bindAppEvents();
});
