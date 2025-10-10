import { app_state } from './modules/state/store.js';
import { api_request } from './modules/api.js';
import { show_global_spinner, hide_global_spinner } from './modules/ui/spinner.js';
import { actions } from './modules/app/actions.js';
import { attachEventListeners } from './modules/app/events.js';
import { manageViewTransition } from './modules/app/views.js';

// --- Main Application Logic ---
const init_app = () => {
    attachEventListeners();
    app_state.listen(manageViewTransition);
};

// --- GitHub Callback Handler ---
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
        notice('Invalid callback parameters. Please try the connection process again.', true);
        return;
    }

    notice('Finalizing GitHub connection, please wait...');

    (async () => {
        try {
            await api_request('github/exchange-code', { body: { code, state } });
            notice('Connection successful! You can now close this tab. The original page will refresh automatically.');
            if (window.opener) {
                window.opener.postMessage('wp2-update-github-connected', window.location.origin);
                window.close();
            }
        } catch (error) {
            notice(`An error occurred: ${error.message}. Please try again.`, true);
        }
    })();
};

// --- App Initialization ---
document.addEventListener('DOMContentLoaded', async () => {
    const isCallbackPage = document.getElementById('wp2-update-github-callback');
    const isMainAppPage = document.getElementById('wp2-update-app');

    if (isCallbackPage) {
        handleGitHubCallback();
    } else if (isMainAppPage) {
        init_app();

        try {
            show_global_spinner();
            const connectionStatus = await api_request('connection-status', { method: 'GET' });

            if (connectionStatus?.data?.connected) {
                app_state.set({ ...app_state.get(), currentStage: 'managing' });
                actions['sync-packages']();
            } else {
                app_state.set({ ...app_state.get(), currentStage: 'configure-manifest' });
            }
        } catch (error) {
            console.error('Failed to fetch connection status:', error);
            const errorMessage = error.message || 'The server returned an error.';
            const { toast } = await import('./modules/ui/toast.js');
            toast('Could not connect to the server.', 'error', errorMessage);
            app_state.set({ ...app_state.get(), currentStage: 'configure-manifest' });
        } finally {
            hide_global_spinner();
        }
    }
});
