// --- State Management ---
import { unified_state, updateUnifiedState, STATUS } from '../state/store.js';

// --- API & Services ---
import { api_request } from '../api.js';
import { AppService } from '../services/AppService.js';
import { PackageService } from '../services/PackageService.js';

// --- Utilities ---
import { debounce, Logger, escapeHtml } from '../utils.js';

// --- UI Helpers ---
import { ensureToast } from './toast.js';
import { show_global_spinner, hide_global_spinner } from './spinner.js';

// --- Views ---
import { loadingView } from './views/loadingView.js';
import { notConfiguredView } from './views/notConfiguredView.js';
import { configuringView } from './views/configuringView.js';
import { appCreatedView } from './views/appCreatedView.js';
import { connectingView } from './views/connectingView.js';
import { DashboardView } from './views/DashboardView.js';
import { errorView } from './views/errorView.js';
import { appsView } from './views/AppsView.js';
import { settingsView } from './views/settingsView.js';
import { PackagesView } from './views/PackagesView.js';

// --- Manual Credentials ---
import { onSubmitManualForm, renderManualCredentialsForm } from './views/manualCredentialsView.js';

const { __, sprintf } = window.wp?.i18n ?? { __: (text) => text, sprintf: (...parts) => parts.join(' ') };

const bootstrapFromLocalization = () => {
    const localized = window.wp2UpdateData ?? {};
    const apps = Array.isArray(localized.apps) ? localized.apps : [];
    const selectedAppId = localized.selectedAppId ?? localized.app_id ?? (apps[0]?.id ?? null);

    if (apps.length > 0) {
        updateUnifiedState({
            apps,
            selectedAppId,
        });
    } else if (selectedAppId) {
        updateUnifiedState({
            selectedAppId,
        });
    }

};

bootstrapFromLocalization();

// Debounce state updates to reduce render frequency
const debouncedUpdate = debounce((updates) => {
    updateUnifiedState(updates);
}, 200);

// --- Event Handlers ---

const onFormFieldInput = (event) => {
    const { id, value } = event.target;
    const currentState = unified_state.get();
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

    updateUnifiedState({ manifestDraft: draft });
};

const toggleOrgFields = (accountType) => {
    document.querySelectorAll('.wp2-org-field').forEach((field) => {
        field.hidden = accountType !== 'organization';
    });
};

const MODAL_ACTIVE_CLASS = 'is-active';
let currentAssignRepo = null;
let wizardSession = null;

const setBodyModalState = (isOpen) => {
    document.body.classList.toggle('wp2-modal-open', isOpen);
};

const openModal = (id) => {
    const modal = document.getElementById(id);
    if (!modal) {
        return;
    }
    modal.hidden = false;
    modal.classList.add(MODAL_ACTIVE_CLASS);
    setBodyModalState(true);
};

const closeModal = (id) => {
    const modal = document.getElementById(id);
    if (!modal) {
        return;
    }
    modal.classList.remove(MODAL_ACTIVE_CLASS);
    modal.hidden = true;

    const anyOpen = Array.from(document.querySelectorAll('.wp2-modal-overlay'))
        .some(node => node.classList.contains(MODAL_ACTIVE_CLASS) && !node.hidden);
    if (!anyOpen) {
        setBodyModalState(false);
    }
};

const closeAllModals = () => {
    document.querySelectorAll('.wp2-modal-overlay').forEach((overlay) => {
        overlay.classList.remove(MODAL_ACTIVE_CLASS);
        overlay.hidden = true;
    });
    setBodyModalState(false);
};

const populateAssignModal = (repo) => {
    const description = document.getElementById('wp2-assign-description');
    const select = document.getElementById('wp2-assign-app-select');
    const state = unified_state.get();
    const apps = Array.isArray(state.apps) ? state.apps : [];

    if (description) {
        description.textContent = repo
            ? sprintf(__('Select a GitHub App to manage %s.', 'wp2-update'), repo)
            : __('Select a GitHub App to manage this package.', 'wp2-update');
    }

    if (select) {
        if (!apps.length) {
            select.innerHTML = `<option value="" disabled>${escapeHtml(__('No GitHub Apps available', 'wp2-update'))}</option>`;
            select.disabled = true;
        } else {
            select.disabled = false;
            select.innerHTML = apps
                .map(app => `<option value="${escapeHtml(app.id)}">${escapeHtml(app.name)}</option>`)
                .join('');
        }
    }
};

const openAssignModal = (repo) => {
    currentAssignRepo = repo;
    populateAssignModal(repo);
    openModal('wp2-modal-assign');
};

const renderDetailRow = (label, value) => `
    <div class="wp2-detail-item">
        <dt>${escapeHtml(label)}</dt>
        <dd>${escapeHtml(value ?? '—')}</dd>
    </div>
`;

const openPackageDetailsModal = (repo) => {
    const packageData = PackageService.getPackageByRepo(repo);
    const state = unified_state.get();
    const apps = Array.isArray(state.apps) ? state.apps : [];

    if (!packageData) {
        Logger.warn('Package details not found for repo', repo);
        return;
    }

    const appName = apps.find(app => app.id === (packageData.app_id ?? packageData.app_uid))?.name ?? __('Not assigned', 'wp2-update');
    const title = document.getElementById('wp2-package-details-title');
    const content = document.getElementById('wp2-package-details-content');
    const syncLog = document.getElementById('wp2-package-sync-log');

    if (title) {
        title.textContent = packageData.name ?? 'Package details';
    }

    if (content) {
        content.innerHTML = `
            <dl class="wp2-detail-grid">
                ${renderDetailRow(__('Repository', 'wp2-update'), packageData.repo)}
                ${renderDetailRow(__('Installed version', 'wp2-update'), packageData.installed ?? packageData.version ?? '—')}
                ${renderDetailRow(__('Latest version', 'wp2-update'), packageData.latest ?? packageData.github_data?.latest_release ?? '—')}
                ${renderDetailRow(__('Status', 'wp2-update'), packageData.status ?? 'unknown')}
                ${renderDetailRow(__('Managed by', 'wp2-update'), appName)}
                ${renderDetailRow(__('Last sync', 'wp2-update'), packageData.last_sync ?? packageData.lastSync ?? '—')}
            </dl>
        `;
    }

    if (syncLog) {
        const log = packageData.sync_log ?? packageData.syncLog ?? '';
        if (log) {
            syncLog.textContent = log;
            syncLog.hidden = false;
        } else {
            syncLog.hidden = true;
        }
    }

    openModal('wp2-modal-package');
};

const openAppDetailsModal = (appId) => {
    const state = unified_state.get();
    const apps = Array.isArray(state.apps) ? state.apps : [];
    const allPackages = Array.isArray(state.allPackages) ? state.allPackages : [];
    const app = apps.find(item => item.id === appId);

    if (!app) {
        Logger.warn('App details not found for id', appId);
        return;
    }

    const managedPackages = allPackages.filter(pkg => (pkg.app_id ?? pkg.app_uid) === appId);

    const title = document.getElementById('wp2-app-details-title');
    const content = document.getElementById('wp2-app-details-content');
    const managedList = document.getElementById('wp2-app-managed-packages');

    if (title) {
        title.textContent = app.name ?? 'App details';
    }

    if (content) {
        content.innerHTML = `
            <dl class="wp2-detail-grid">
                ${renderDetailRow(__('Account type', 'wp2-update'), app.account_type ?? 'user')}
                ${renderDetailRow(__('App ID', 'wp2-update'), app.app_id ?? app.id ?? '—')}
                ${renderDetailRow(__('Installation ID', 'wp2-update'), app.installation_id ?? '—')}
                ${renderDetailRow(__('Webhook status', 'wp2-update'), app.webhook_status ?? app.webhookStatus ?? '—')}
            </dl>
        `;
    }

    if (managedList) {
        if (managedPackages.length) {
            managedList.innerHTML = `
                <h3>${escapeHtml(sprintf(__('Managed packages (%d)', 'wp2-update'), managedPackages.length))}</h3>
                <ul class="wp2-managed-list__items">
                    ${managedPackages.map(pkg => `<li>${escapeHtml(pkg.name || pkg.repo)} <code>${escapeHtml(pkg.repo || '')}</code></li>`).join('')}
                </ul>
            `;
        } else {
            managedList.innerHTML = `<p>${escapeHtml(__('No packages are currently managed by this app.', 'wp2-update'))}</p>`;
        }
    }

    openModal('wp2-modal-app');
};

const resetWizardModal = () => {
    const form = document.getElementById('wp2-wizard-form');
    const stepConfigure = document.getElementById('wp2-wizard-step-configure');
    const stepManifest = document.getElementById('wp2-wizard-step-manifest');
    const manifestOutput = document.getElementById('wp2-wizard-manifest');
    const orgField = document.getElementById('wp2-wizard-organization-field');

    wizardSession = null;
    if (form) {
        form.reset();
    }
    if (orgField) {
        orgField.hidden = true;
    }
    if (manifestOutput) {
        manifestOutput.value = '';
    }
    if (stepConfigure) {
        stepConfigure.hidden = false;
        stepConfigure.classList.add('is-active');
    }
    if (stepManifest) {
        stepManifest.hidden = true;
        stepManifest.classList.remove('is-active');
    }
};

const openWizardModal = () => {
    resetWizardModal();
    openModal('wp2-modal-wizard');
};

const showWizardManifestStep = (manifest, accountUrl) => {
    const stepConfigure = document.getElementById('wp2-wizard-step-configure');
    const stepManifest = document.getElementById('wp2-wizard-step-manifest');
    const manifestOutput = document.getElementById('wp2-wizard-manifest');

    if (manifestOutput) {
        manifestOutput.value = manifest ?? '';
    }
    if (stepConfigure) {
        stepConfigure.hidden = true;
        stepConfigure.classList.remove('is-active');
    }
    if (stepManifest) {
        stepManifest.hidden = false;
        stepManifest.classList.add('is-active');
        if (accountUrl) {
            stepManifest.dataset.accountUrl = accountUrl;
        }
    }
};

const handleWizardAccountToggle = (value) => {
    const orgField = document.getElementById('wp2-wizard-organization-field');
    if (orgField) {
        orgField.hidden = value !== 'organization';
    }
};

const handleWizardSubmit = async (event, fetchConnectionStatus) => {
    event.preventDefault();
    const toast = await ensureToast();
    const form = event.currentTarget;
    const submitButton = form.querySelector('button[type="submit"]');

    const formData = new FormData(form);
    const appName = String(formData.get('app_name') || '').trim();
    const encryptionKey = String(formData.get('encryption_key') || '').trim();
    const accountType = formData.get('account_type') === 'organization' ? 'organization' : 'user';
    const organization = accountType === 'organization' ? String(formData.get('organization') || '').trim() : '';

    if (!appName) {
        toast(__('An app name is required.', 'wp2-update'), 'error');
        return;
    }

    if (encryptionKey.length < 16) {
        toast(__('Encryption key must be at least 16 characters.', 'wp2-update'), 'error');
        return;
    }

    if (accountType === 'organization' && !organization) {
        toast(__('An organization slug is required for organization apps.', 'wp2-update'), 'error');
        return;
    }

    submitButton.disabled = true;
    show_global_spinner();
    updateUnifiedState({ isProcessing: true });

    try {
        const newApp = await AppService.createApp({ name: appName });
        if (newApp?.id) {
            window.wp2UpdateData = window.wp2UpdateData || {};
            window.wp2UpdateData.selectedAppId = newApp.id;
            updateUnifiedState({ selectedAppId: newApp.id });
        }

        const { manifestDraft = {} } = unified_state.get();
        const manifestJson = manifestDraft.manifestJson ?? '{}';

        const connectResponse = await api_request('github/connect-url', {
            method: 'POST',
            body: {
                name: appName,
                account_type: accountType,
                organization,
                manifest: manifestJson,
                encryption_key: encryptionKey,
            },
        });

        wizardSession = {
            account: connectResponse?.account,
            manifest: connectResponse?.manifest ?? manifestJson,
            state: connectResponse?.state,
            appId: newApp?.id ?? null,
        };

        window.wp2UpdateData = window.wp2UpdateData || {};
        if (wizardSession.state) {
            window.wp2UpdateData.githubState = wizardSession.state;
        }

        showWizardManifestStep(wizardSession.manifest, wizardSession.account);
        const successToast = await ensureToast();
        successToast(__('Manifest generated. Complete setup on GitHub to finish connecting the app.', 'wp2-update'), 'success');
    } catch (error) {
        Logger.error('Failed to prepare GitHub app manifest', error);
        toast(error?.message || __('Unable to prepare GitHub manifest.', 'wp2-update'), 'error');
    } finally {
        submitButton.disabled = false;
        hide_global_spinner();
        updateUnifiedState({ isProcessing: false });
    }
};

const handleCopyManifest = async () => {
    const toast = await ensureToast();
    const manifest = document.getElementById('wp2-wizard-manifest')?.value ?? '';
    if (!manifest) {
        toast(__('No manifest is available to copy yet.', 'wp2-update'), 'error');
        return;
    }
    try {
        await navigator.clipboard.writeText(manifest);
        toast(__('Manifest copied to clipboard.', 'wp2-update'), 'success');
    } catch (error) {
        Logger.error('Failed to copy manifest', error);
        toast(__('Could not copy the manifest. Please copy it manually.', 'wp2-update'), 'error');
    }
};

const handleOpenGithub = async () => {
    const toast = await ensureToast();
    if (!wizardSession?.account) {
        toast(__('GitHub account URL is not available. Generate the manifest again.', 'wp2-update'), 'error');
        return;
    }
    window.open(wizardSession.account, '_blank', 'noopener');
};

const handleWizardFinished = async (fetchConnectionStatus) => {
    closeModal('wp2-modal-wizard');
    if (typeof fetchConnectionStatus === 'function') {
        await fetchConnectionStatus();
    }
};

const renderApp = (state) => {
    const root = document.getElementById('wp2-update-app');
    if (!root) return;

    switch (state.status) {
        case 'LOADING':
            root.innerHTML = LoadingView();
            break;
        case 'NOT_CONFIGURED':
            root.innerHTML = NotConfiguredView();
            // Bind events for the NotConfiguredView buttons here
            break;
        case 'INSTALLED':
            root.innerHTML = DashboardView(); // Render the layout with tab containers
            initializeTabs(); // Initialize Tabby
            renderTabComponents(state); // Render tables into the tabs
            bindDashboardEvents(); // Bind sync/add buttons
            break;
        // ... other cases
    }
};

const bindDashboardInteractions = (controllers = {}) => {
    const { fetchConnectionStatus = () => {}, syncPackages = () => {} } = controllers;

    const tabs = document.querySelectorAll('.wp2-dashboard-tab');
    const panels = document.querySelectorAll('.wp2-dashboard-panel');

    if (tabs.length && panels.length) {
        tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                const target = tab.dataset.wp2Tab;
                if (!target || tab.classList.contains('active')) {
                    return;
                }

                tabs.forEach(t => {
                    t.classList.toggle('active', t === tab);
                    t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
                });

                panels.forEach(panel => {
                    const isActive = panel.dataset.wp2Panel === target;
                    panel.classList.toggle('active', isActive);
                    panel.hidden = !isActive;
                });
            });
        });
    }

    document.querySelectorAll('.wp2-modal-overlay').forEach((overlay) => {
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) {
                closeModal(overlay.id);
            }
        });
    });

    document.querySelectorAll('[data-wp2-close]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = button.getAttribute('data-wp2-close');
            if (target) {
                closeModal(`wp2-modal-${target}`);
            } else {
                closeAllModals();
            }
        });
    });

    document.querySelectorAll('[data-wp2-action="open-wizard"]').forEach((btn) => {
        btn.addEventListener('click', () => openWizardModal());
    });

    const syncButton = document.getElementById('wp2-sync-all');
    if (syncButton) {
        syncButton.addEventListener('click', async () => {
            if (syncButton.disabled) return;
            const originalText = syncButton.textContent;
            syncButton.disabled = true;
            syncButton.textContent = __('Syncing…', 'wp2-update');

            try {
                show_global_spinner();
                await syncPackages();
                await PackageService.fetchPackages();
                const toast = await ensureToast();
                toast(__('Packages synced successfully.', 'wp2-update'), 'success');
            } catch (error) {
                Logger.error('Sync packages failed', error);
                const toast = await ensureToast();
                toast(error?.message || __('Failed to sync packages.', 'wp2-update'), 'error');
            } finally {
                hide_global_spinner();
                syncButton.disabled = false;
                syncButton.textContent = originalText;
            }
        });
    }

    document.querySelectorAll('[data-wp2-table="packages"]').forEach((packagesTable) => {
        packagesTable.addEventListener('click', (event) => {
            const actionButton = event.target.closest('[data-wp2-action]');
            if (!actionButton) return;
            const repo = actionButton.getAttribute('data-wp2-package');
            const action = actionButton.getAttribute('data-wp2-action');

            if (action === 'assign-app' && repo) {
                openAssignModal(repo);
            } else if (action === 'package-details' && repo) {
                openPackageDetailsModal(repo);
            }
        });
    });

    document.querySelectorAll('[data-wp2-table="apps"]').forEach((appsTable) => {
        appsTable.addEventListener('click', (event) => {
            const actionButton = event.target.closest('[data-wp2-action]');
            if (!actionButton) return;
            const appId = actionButton.getAttribute('data-wp2-app');
            const action = actionButton.getAttribute('data-wp2-action');

            if (action === 'app-details' && appId) {
                openAppDetailsModal(appId);
            }
        });
    });

    document.querySelectorAll('[data-wp2-action="refresh-packages"]').forEach((button) => {
        button.addEventListener('click', async () => {
            try {
                show_global_spinner();
                await PackageService.fetchPackages();
                const toast = await ensureToast();
                toast(__('Package list refreshed.', 'wp2-update'), 'success');
            } catch (error) {
                Logger.error('Failed to refresh packages', error);
                const toast = await ensureToast();
                toast(error?.message || __('Unable to refresh packages at this time.', 'wp2-update'), 'error');
            } finally {
                hide_global_spinner();
            }
        });
    });

    const assignForm = document.getElementById('wp2-assign-form');
    if (assignForm) {
        assignForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const toast = await ensureToast();
            const select = document.getElementById('wp2-assign-app-select');
            const appId = select?.value;
            if (!currentAssignRepo || !appId) {
                toast(__('Select an app before assigning.', 'wp2-update'), 'error');
                return;
            }
            try {
                show_global_spinner();
                await PackageService.assignPackage(currentAssignRepo, appId);
                toast(__('Package assigned successfully.', 'wp2-update'), 'success');
                closeModal('wp2-modal-assign');
            } catch (error) {
                Logger.error('Assign package failed', error);
                toast(error?.message || __('Failed to assign the package.', 'wp2-update'), 'error');
            } finally {
                hide_global_spinner();
                currentAssignRepo = null;
            }
        });
    }

    const wizardForm = document.getElementById('wp2-wizard-form');
    if (wizardForm) {
        wizardForm.addEventListener('submit', (event) => handleWizardSubmit(event, fetchConnectionStatus));
    }

    document.getElementById('wp2-wizard-account')?.addEventListener('change', (event) => {
        if (event.target?.name === 'account_type') {
            handleWizardAccountToggle(event.target.value);
        }
    });

    document.querySelector('[data-wp2-action="copy-manifest"]')?.addEventListener('click', handleCopyManifest);
    document.querySelector('[data-wp2-action="open-github"]')?.addEventListener('click', handleOpenGithub);
    document.querySelector('[data-wp2-action="wizard-finished"]')?.addEventListener('click', () => handleWizardFinished(fetchConnectionStatus));
};

const onAccountTypeChange = (event) => {
    const { value } = event.target;
    const currentState = unified_state.get();
    updateUnifiedState({
        manifestDraft: { ...currentState.manifestDraft, accountType: value },
    });
    toggleOrgFields(value);
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

    updateUnifiedState({ isProcessing: true });
    submitButton.disabled = true;
    show_global_spinner();

    try {
        const selectedAppId = unified_state.get().selectedAppId;
        if (!selectedAppId) {
            throw new Error('No app selected. Please choose an app before submitting the manifest.');
        }
        const response = await api_request(`/apps/${selectedAppId}/manifest`, {
            method: 'POST',
            body: formData,
        });

        if (response.success) {
            toast(__('Manifest submitted successfully.', 'wp2-update'), 'success');
            updateUnifiedState({ isProcessing: false });
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
        case STATUS.MANUAL_CONFIGURING: return renderManualCredentialsForm(state);
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
        document.getElementById('wp2-start-connection')?.addEventListener('click', () => {
            Logger.info('Start Connection button clicked.');
            updateUnifiedState({ status: STATUS.CONFIGURING });
        });
        document.getElementById('wp2-manual-setup')?.addEventListener('click', () => {
            Logger.info('Manual Setup button clicked.');
            updateUnifiedState({ status: STATUS.MANUAL_CONFIGURING });
        });
    } else if (status === STATUS.CONFIGURING) {
        document.getElementById('wp2-configure-form')?.addEventListener('submit', (e) => {
            Logger.info('Configure Form submitted.');
            onSubmitManifestForm(e);
        });
        document.getElementById('wp2-app-type')?.addEventListener('change', (event) => {
            Logger.info(`App Type changed to: ${event.target.value}`);
            onAccountTypeChange(event);
        });
        document.getElementById('wp2-cancel-config')?.addEventListener('click', () => {
            Logger.info('Cancel Configuration button clicked.');
            updateUnifiedState({ status: STATUS.NOT_CONFIGURED, message: '' });
        });
        const fieldsToBind = ['wp2-encryption-key', 'wp2-app-name', 'wp2-organization', 'wp2-manifest-json'];
        fieldsToBind.forEach(id => {
            document.getElementById(id)?.addEventListener('input', (event) => {
                Logger.debug(`Field ${id} updated. Value: ${event.target.value}`);
                onFormFieldInput(event);
            });
        });
    } else if (status === STATUS.MANUAL_CONFIGURING) {
        document.getElementById('wp2-manual-configure-form')?.addEventListener('submit', (e) => {
            Logger.info('Manual Configure Form submitted.');
            onSubmitManualForm(e, fetchConnectionStatus);
        });
        document.getElementById('wp2-manual-account-type')?.addEventListener('change', (event) => {
            Logger.info(`Manual Account Type changed to: ${event.target.value}`);
            toggleOrgFields(event.target.value);
        });
        document.getElementById('wp2-cancel-config')?.addEventListener('click', () => {
            Logger.info('Cancel Manual Configuration button clicked.');
            updateUnifiedState({ status: STATUS.NOT_CONFIGURED, message: '' });
        });
    } else if (status === STATUS.APP_CREATED) {
        document.getElementById('wp2-check-installation')?.addEventListener('click', () => {
            Logger.info('Check Installation button clicked.');
            fetchConnectionStatus();
        });
        document.getElementById('wp2-start-over')?.addEventListener('click', () => {
            Logger.info('Start Over button clicked.');
            confirmResetConnection(stopPolling);
        });
    } else if (status === STATUS.INSTALLED) {
        bindDashboardInteractions(controllers);
    } else if (status === STATUS.ERROR) {
        document.getElementById('wp2-retry-connection')?.addEventListener('click', () => {
            Logger.info('Retry Connection button clicked.');
            fetchConnectionStatus();
        });
        document.getElementById('wp2-disconnect-reset')?.addEventListener('click', () => {
            Logger.info('Disconnect and Reset button clicked.');
            confirmResetConnection(stopPolling);
        });
    }

    document.getElementById('wp2-refresh-status')?.addEventListener('click', () => {
        Logger.info('Refresh Status button clicked.');
        fetchConnectionStatus();
    });

    document.getElementById('wp2-open-manual-setup')?.addEventListener('click', () => {
        Logger.info('Open Manual Setup button clicked from settings panel.');
        updateUnifiedState({ status: STATUS.MANUAL_CONFIGURING });
    });

    document.getElementById('wp2-refresh-apps')?.addEventListener('click', async () => {
        Logger.info('Refresh Apps button clicked.');
        try {
            await AppService.fetchApps();
        } catch (error) {
            Logger.error('Unable to refresh apps.', error);
            const toast = await ensureToast();
            toast(__('Unable to refresh apps from the server.', 'wp2-update'), 'error', error.message);
        }
    });
};

// Centralized App Initialization
const App = {
    initialized: false, // Add a flag to track initialization

    init() {
        if (this.initialized) {
            console.warn('App.init() called multiple times. Initialization skipped.');
            return;
        }
        this.initialized = true; // Set the flag to true

        console.log('Initializing WP2 Update App...');
        // Initialize state and controllers
        this.state = { ...unified_state.get() };
        this.controllers = this.controllers || {};

        // Bind global event listeners
        this.bindGlobalEvents();

        // Initial render
        this.render();

        // Subscribe to state changes
        unified_state.subscribe(this.render.bind(this));
        console.log('App initialized successfully.');
    },

    setControllers(controllers = {}) {
        this.controllers = { ...(this.controllers || {}), ...controllers };
        if (this.initialized) {
            this.render();
        }
    },

    bindGlobalEvents() {
        // Global event listeners
        document.addEventListener('click', (event) => {
            if (event.target.matches('[data-action="refresh"]')) {
                this.refresh();
            }
        });
    },

    render() {
        const dashboardRoot = document.getElementById('wp2-dashboard-root');
        const packagesRoot = document.getElementById('wp2-packages-root');
        const appsRoot = document.getElementById('wp2-apps-root');
        const settingsRoot = document.getElementById('wp2-settings-root');

        if (!dashboardRoot) {
            console.warn('Dashboard root element not found. Skipping render.');
            return;
        }

        const currentState = { ...unified_state.get() };

        const renderMarkup = (target, markup) => {
            if (!target) {
                return;
            }
            target.innerHTML = '';
            if (markup && typeof markup === 'object' && 'nodeType' in markup) {
                target.appendChild(markup);
            } else {
                target.innerHTML = markup || '';
            }
        };

        renderMarkup(dashboardRoot, buildViewForState(currentState));
        renderMarkup(packagesRoot, PackagesView(currentState));
        renderMarkup(appsRoot, appsView(currentState));
        renderMarkup(settingsRoot, settingsView(currentState));

        bindViewEvents(currentState, this.controllers);
    },

    refresh() {
        console.log('Refreshing application state...');
        // Logic to refresh state or re-fetch data
    },
};

// Initialize the application
App.init();

export { App };
