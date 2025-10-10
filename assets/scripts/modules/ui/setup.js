import { dashboard_state, updateDashboardState, STATUS } from '../state/store.js';
import { api_request } from '../api.js';
import { confirm_modal } from './modal.js';
import { ensureToast } from './toast.js';
import { show_global_spinner, hide_global_spinner } from './spinner.js';
import { onSubmitManualForm, renderManualCredentialsForm } from './views/manualCredentialsView.js';
import { debounce } from '../utils.js';

// Import all view components
import { loadingView } from './views/loadingView.js';
import { notConfiguredView } from './views/notConfiguredView.js';
import { configuringView } from './views/configuringView.js';
import { appCreatedView } from './views/appCreatedView.js';
import { connectingView } from './views/connectingView.js';
import { dashboardView } from './views/dashboardView.js';
import { errorView } from './views/errorView.js';

const { __, sprintf } = window.wp?.i18n ?? { __: (text) => text, sprintf: (...parts) => parts.join(' ') };

// --- Event Handlers ---

const onAccountTypeChange = (event) => {
    const currentState = dashboard_state.get();
    updateDashboardState({
        manifestDraft: { ...currentState.manifestDraft, accountType: event.target.value },
    });
    const orgField = document.querySelector('.wp2-org-field');
    if (orgField) {
        orgField.hidden = event.target.value !== 'organization';
    }
};

const onSubmitManifestForm = async (event) => {
    event.preventDefault();
    const toast = await ensureToast();
    const form = event.currentTarget;
    const submitButton = form.querySelector('button[type="submit"]');
    const formData = new FormData(form);

    const encryptionKey = String(formData.get('encryption-key') || '').trim();
    if (!encryptionKey || encryptionKey.length < 16) {
        toast(__('Encryption Key must be at least 16 characters.', 'wp2-update'), 'error');
        return;
    }

    updateDashboardState({ isProcessing: true });
    submitButton.disabled = true;
    show_global_spinner();

    try {
        const payload = await api_request('github/connect-url', {
            method: 'POST',
            body: {
                name: formData.get('app-name'),
                account_type: formData.get('app-type'),
                organization: formData.get('organization'),
                encryption_key: encryptionKey,
                manifest: formData.get('manifest'),
            },
        });

        // Open GitHub in a new tab
        let actionUrl = payload.account;
        if (payload?.state && typeof payload.account === 'string') {
            try {
                const url = new URL(payload.account);
                url.searchParams.set('state', payload.state);
                actionUrl = url.toString();
            } catch {
                const separator = payload.account.includes('?') ? '&' : '?';
                actionUrl = `${payload.account}${separator}state=${encodeURIComponent(payload.state)}`;
            }
        }

        const formElement = document.createElement('form');
        formElement.method = 'POST';
        formElement.action = actionUrl;
        formElement.target = '_blank';
        const manifestInput = document.createElement('input');
        manifestInput.type = 'hidden';
        manifestInput.name = 'manifest';
        manifestInput.value = payload.manifest;
        formElement.appendChild(manifestInput);
        document.body.appendChild(formElement);
        formElement.submit();
        formElement.remove();

        updateDashboardState({ status: STATUS.APP_CREATED });
    } catch (error) {
        console.error('Failed to generate connect URL', error);
        toast(__('Failed to connect to GitHub. Please try again.', 'wp2-update'), 'error', error.message);
    } finally {
        hide_global_spinner();
        submitButton.disabled = false;
        updateDashboardState({ isProcessing: false });
    }
};

const onPackageAction = async (event, syncPackages) => {
    event.preventDefault();
    const toast = await ensureToast();
    const button = event.currentTarget;
    const row = button.closest('tr');
    const repo = row?.dataset.repo;
    const version = row?.querySelector('.wp2-release-select')?.value;

    if (!repo || !version) {
        toast(__('Unable to determine the selected release.', 'wp2-update'), 'error');
        return;
    }

    confirm_modal(
        sprintf(__('Install %1$s from %2$s?', 'wp2-update'), version, repo),
        async () => {
            show_global_spinner();
            try {
                await api_request('manage-packages', {
                    method: 'POST',
                    body: { action: 'install', repo_slug: repo, version },
                });
                toast(__('Package action successful.', 'wp2-update'), 'success');
                await syncPackages(); // Re-sync after action
            } catch (error) {
                console.error('Failed to manage package', error);
                toast(__('Failed to perform the action on the selected release.', 'wp2-update'), 'error', error.message);
            } finally {
                hide_global_spinner();
            }
        }
    );
};

const confirmResetConnection = async (stopPolling) => {
    const toast = await ensureToast();
    confirm_modal(
        __('Are you sure you want to disconnect? All settings will be removed.', 'wp2-update'),
        async () => {
            show_global_spinner();
            try {
                await api_request('github/disconnect', { method: 'POST' });
                stopPolling();
                updateDashboardState({
                    status: STATUS.NOT_CONFIGURED,
                    message: '',
                    details: {},
                    packages: [],
                    unlinkedPackages: [],
                });
                toast(__('Disconnected successfully.', 'wp2-update'), 'success');
            } catch (error) {
                console.error('Failed to disconnect', error);
                toast(__('Failed to disconnect. Please try again.', 'wp2-update'), 'error', error.message);
            } finally {
                hide_global_spinner();
            }
        }
    );
};

// --- View and Event Binding ---

const buildViewForState = (state) => {
    switch (state.status) {
        case STATUS.LOADING: return loadingView();
        case STATUS.NOT_CONFIGURED:
        case STATUS.NOT_CONFIGURED_WITH_PACKAGES: return notConfiguredView(state);
        case STATUS.CONFIGURING: return configuringView(state);
        case STATUS.MANUAL_CONFIGURING: return renderManualCredentialsForm();
        case STATUS.APP_CREATED: return appCreatedView(state);
        case STATUS.CONNECTING: return connectingView();
        case STATUS.INSTALLED: return dashboardView(state);
        case STATUS.ERROR: return errorView(state);
        default: return loadingView();
    }
};

const bindViewEvents = (state, { fetchConnectionStatus, syncPackages, stopPolling }) => {
    const { status } = state;

    if (status === STATUS.NOT_CONFIGURED || status === STATUS.NOT_CONFIGURED_WITH_PACKAGES) {
        document.getElementById('wp2-start-connection')?.addEventListener('click', () => updateDashboardState({ status: STATUS.CONFIGURING }));
        document.getElementById('wp2-manual-setup')?.addEventListener('click', () => updateDashboardState({ status: STATUS.MANUAL_CONFIGURING }));
    } else if (status === STATUS.CONFIGURING) {
        document.getElementById('wp2-configure-form')?.addEventListener('submit', onSubmitManifestForm);
        document.getElementById('wp2-app-type')?.addEventListener('change', onAccountTypeChange);
        document.getElementById('wp2-cancel-config')?.addEventListener('click', () => updateDashboardState({ status: STATUS.NOT_CONFIGURED, message: '' }));

        // --- THE SYSTEMIC FIX ---
        // This now correctly listens to the 'input' event (every keystroke) for all fields.
        const fieldsToBind = ['wp2-encryption-key', 'wp2-app-name', 'wp2-organization', 'wp2-manifest-json'];
        fieldsToBind.forEach(id => {
            document.getElementById(id)?.addEventListener('input', onFormFieldInput);
        });
        // --- END FIX ---
    } else if (status === STATUS.MANUAL_CONFIGURING) {
        document.getElementById('wp2-manual-configure-form')?.addEventListener('submit', (e) => onSubmitManualForm(e, fetchConnectionStatus));
        document.getElementById('wp2-cancel-config')?.addEventListener('click', () => updateDashboardState({ status: STATUS.NOT_CONFIGURED, message: '' }));
    } else if (status === STATUS.APP_CREATED) {
        document.getElementById('wp2-check-installation')?.addEventListener('click', fetchConnectionStatus);
        document.getElementById('wp2-start-over')?.addEventListener('click', () => confirmResetConnection(stopPolling));
    } else if (status === STATUS.INSTALLED) {
        document.getElementById('wp2-sync-packages')?.addEventListener('click', syncPackages);
        document.getElementById('wp2-disconnect')?.addEventListener('click', () => confirmResetConnection(stopPolling));
        document.querySelectorAll('.wp2-package-action').forEach(button => button.addEventListener('click', (e) => onPackageAction(e, syncPackages)));
    } else if (status === STATUS.ERROR) {
        document.getElementById('wp2-retry-connection')?.addEventListener('click', fetchConnectionStatus);
        document.getElementById('wp2-disconnect-reset')?.addEventListener('click', () => confirmResetConnection(stopPolling));
    }
};

// Debounce state updates to reduce render frequency
const debouncedUpdate = debounce((updates) => {
    updateDashboardState(updates);
}, 200);

export const render = (state, controllers) => {
    const root = document.getElementById('wp2-dashboard-root');
    if (!root) return;

    // Optimize rendering by selectively updating parts of the DOM
    const currentView = root.getAttribute('data-view');
    const newView = state.status;

    if (currentView !== newView) {
        root.setAttribute('data-view', newView);
        root.innerHTML = buildViewForState(state);
        bindViewEvents(state, controllers);
    } else {
        // Update only specific fields if the view remains the same
        const draft = state.manifestDraft;
        document.getElementById('wp2-encryption-key')?.setAttribute('value', draft.encryptionKey ?? '');
        document.getElementById('wp2-app-name')?.setAttribute('value', draft.name ?? '');
        document.getElementById('wp2-organization')?.setAttribute('value', draft.organization ?? '');
        const manifestJsonElement = document.getElementById('wp2-manifest-json');
        if (manifestJsonElement) {
            manifestJsonElement.value = draft.manifestJson;
        }
    }
};
