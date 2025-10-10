import { dashboard_state, updateDashboardState, STATUS } from './modules/state/store.js';
import { api_request } from './modules/api.js';
import { show_global_spinner, hide_global_spinner } from './modules/ui/spinner.js';
import { render } from './modules/ui/setup.js';
import { ensureToast } from './modules/ui/toast.js';

let pollHandle = null;

const stopPolling = () => {
    if (pollHandle) {
        clearTimeout(pollHandle);
        pollHandle = null;
        updateDashboardState({ polling: { active: false } });
    }
};

const syncPackages = async () => {
    updateDashboardState({ isProcessing: true });
    const syncButton = document.getElementById('wp2-sync-packages');
    if (syncButton) syncButton.disabled = true;

    try {
        show_global_spinner();
        const { packages = [], unlinked_packages = [] } = await api_request('sync-packages', { method: 'GET' }) || {};
        updateDashboardState({ packages, unlinked_packages });
    } catch (error) {
        console.error('Failed to sync packages', error);
        const toast = await ensureToast();
        toast('Failed to sync packages from GitHub.', 'error', error.message);
    } finally {
        hide_global_spinner();
        updateDashboardState({ isProcessing: false });
        if (syncButton) syncButton.disabled = false;
    }
};

const fetchConnectionStatus = async ({ silent = false } = {}) => {
    if (!silent) {
        updateDashboardState({ status: STATUS.LOADING, isProcessing: true });
    }

    try {
        if (!silent) show_global_spinner();
        const response = await api_request('connection-status', { method: 'GET' });
        const data = response?.data || {};
        let status = data.status || STATUS.NOT_CONFIGURED;
        const previousStatus = dashboard_state.get().status;

        if (status === STATUS.NOT_CONFIGURED && data.unlinked_packages?.length) {
            status = STATUS.NOT_CONFIGURED_WITH_PACKAGES;
        }

        updateDashboardState({
            status,
            message: data.message || '',
            details: data.details || {},
            unlinkedPackages: data.unlinked_packages || [],
            isProcessing: false,
        });

        // --- PATCH START ---
        // Provide feedback if a manual check results in the same "app_created" state.
        if (!silent && status === STATUS.APP_CREATED && previousStatus === STATUS.APP_CREATED) {
            const toast = await ensureToast();
            toast('Still waiting for installation. Please complete the setup on GitHub.', 'warning');
        }
        // --- PATCH END ---

        if (status === STATUS.APP_CREATED) {
            scheduleInstallationPoll();
        } else if (status === STATUS.INSTALLED) {
            stopPolling();
            await syncPackages();
        }
    } catch (error) {
        console.error('Failed to fetch connection status', error);
        const toast = await ensureToast();
        toast('Could not connect to the server.', 'error', error.message);
        updateDashboardState({
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
    updateDashboardState({ polling: { active: true } });

    pollHandle = setTimeout(async () => {
        pollHandle = null;
        await fetchConnectionStatus({ silent: true });
        if (dashboard_state.get().status === STATUS.APP_CREATED) {
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
            console.error('GitHub exchange failed', error);
            notice(`An error occurred: ${error.message}. Please try again.`, true);
        }
    })();
};

// --- Initialization ---

// Pass controller functions to the render module
const controllers = { fetchConnectionStatus, syncPackages, stopPolling };
dashboard_state.listen((state) => render(state, controllers));

window.addEventListener('message', (event) => {
    if (event.data === 'wp2-update-github-connected') {
        fetchConnectionStatus();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('wp2-update-github-callback')) {
        handleGitHubCallback();
    } else if (document.getElementById('wp2-update-app')) {
        render(dashboard_state.get(), controllers); // Initial render
        fetchConnectionStatus();
    }
});
