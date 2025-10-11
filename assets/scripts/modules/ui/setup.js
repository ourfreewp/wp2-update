import { dashboard_state, app_state, updateDashboardState, updateAppState, STATUS } from '../state/store.js';
import { api_request } from '../api.js';
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
import { DashboardView } from './views/DashboardView.js';
import { errorView } from './views/errorView.js';
import { WaitingView } from './views/WaitingView.js';

const { __, sprintf } = window.wp?.i18n ?? { __: (text) => text, sprintf: (...parts) => parts.join(' ') };

let activeControllers = {};

// Debounce state updates to reduce render frequency
const debouncedUpdate = debounce((updates) => {
    updateDashboardState(updates);
}, 200);

// --- Event Handlers ---

const onAppSelectionChange = (event) => {
    const selectedAppId = event.target.value;
    updateAppState({ selectedAppId });
    updateDashboardState((state) => ({ packages: state.allPackages }));
};

const onFormFieldInput = (event) => {
    const { id, value } = event.target;
    const currentState = dashboard_state.get();
    const draft = { ...currentState.manifestDraft };

    switch (id) {
        case 'wp2-encryption-key':
            draft.encryptionKey = value;
            break;
        case 'wp2-app-name':
            draft.name = value;
            break;
        case 'wp2-organization':
            draft.organization = value;
            break;
        case 'wp2-manifest-json':
            draft.manifestJson = value;
            break;
        default:
            return;
    }

    debouncedUpdate({ manifestDraft: draft });
};

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
        const selectedAppId = app_state.get().selectedAppId;
        if (!selectedAppId) {
            throw new Error('No app selected. Please choose an app before submitting the manifest.');
        }
        const response = await api_request(`/apps/${selectedAppId}/manifest`, {
            method: 'POST',
            body: formData,
        });

        if (response.success) {
            toast(__('Manifest submitted successfully.', 'wp2-update'), 'success');
            updateDashboardState({ isProcessing: false });
        } else {
            throw new Error(response.message || __('An error occurred.', 'wp2-update'));
        }
    } catch (error) {
        toast(error.message, 'error');
    } finally {
        submitButton.disabled = false;
        hide_global_spinner();
    }
};

// Subscribe to state updates
const renderAppSelection = () => {
    const { apps = [], selectedAppId = null } = app_state.get();
    const dropdown = document.querySelector('#wp2-app-selection');
    if (!dropdown) {
        return;
    }
    dropdown.innerHTML = apps.map(app => `<option value="${app.id}"${app.id === selectedAppId ? ' selected' : ''}>${app.name}</option>`).join('');
    dropdown.value = selectedAppId ?? '';
    dropdown.removeEventListener('change', onAppSelectionChange);
    dropdown.addEventListener('change', onAppSelectionChange);
};

app_state.subscribe(renderAppSelection);

// Initial render
renderAppSelection();

// Absorbed confirm_modal logic

/**
 * Show a confirmation modal.
 * @param {string} message - The message to display.
 * @param {() => void} onConfirm - Callback for confirm action.
 * @param {() => void} [onCancel] - Callback for cancel action.
 */
export const showConfirmationModal = (message, onConfirm, onCancel) => {
    const modal = document.getElementById('wp2-disconnect-modal');
    if (!modal) return console.error('Modal #wp2-disconnect-modal not found');

    const msg = modal.querySelector('.wp2-modal-message');
    const ok = modal.querySelector('[data-wp2-action="confirm-disconnect"]');
    const cancel = modal.querySelector('[data-wp2-action="cancel-disconnect"]');

    if (msg) msg.textContent = message;

    const close = () => {
        modal.classList.remove('is-visible');
        modal.hidden = true;
        ok.removeEventListener('click', on_ok);
        cancel.removeEventListener('click', on_cancel);
    };

    const on_ok = () => {
        close();
        onConfirm();
    };

    const on_cancel = () => {
        close();
        if (onCancel) onCancel();
    };

    ok.addEventListener('click', on_ok);
    cancel.addEventListener('click', on_cancel);

    modal.hidden = false;
    modal.classList.add('is-visible');
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
        case STATUS.INSTALLED: return DashboardView(state);
        case STATUS.ERROR: return errorView(state);
        default: return loadingView();
    }
};

const bindViewEvents = (state, controllers = {}) => {
    const {
        fetchConnectionStatus = () => {},
        syncPackages = () => {},
        stopPolling = () => {},
    } = controllers;
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

// Centralized App Initialization
const App = {
    init() {
        // Initialize state and controllers
        this.state = { ...dashboard_state.get(), ...app_state.get() };
        this.controllers = {};

        // Bind global event listeners
        this.bindGlobalEvents();

        // Initial render
        this.render();

        // Subscribe to state changes
        dashboard_state.subscribe(this.render.bind(this));
        app_state.subscribe(this.render.bind(this));
    },

    bindGlobalEvents() {
        // Example: Global event listeners
        document.addEventListener('click', (event) => {
            if (event.target.matches('[data-action="refresh"]')) {
                this.refresh();
            }
        });
    },

    render() {
        const root = document.getElementById('wp2-dashboard-root');
        if (!root) return;

        // Render dashboard content based on state
        const content = buildViewForState(this.state);
        root.innerHTML = '';
        if (content && typeof content === 'object' && 'nodeType' in content) {
            root.appendChild(content);
        } else {
            root.innerHTML = content || '';
        }

        // Bind view-specific events
        bindViewEvents(this.state, this.controllers);
    },

    refresh() {
        console.log('Refreshing application state...');
        // Logic to refresh state or re-fetch data
    },
};

// Initialize the application
App.init();

export { App };
