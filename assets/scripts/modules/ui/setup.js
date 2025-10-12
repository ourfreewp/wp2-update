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
import { DashboardView, renderWizardModalContent } from './views/DashboardView.js';

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

    // Explicitly ensure no wizard modal is triggered due to lack of apps
    const wizardModal = document.getElementById('wp2-modal-wizard');
    if (wizardModal) {
        wizardModal.hidden = true;
        wizardModal.classList.remove('is-active');
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
    console.warn('openWizardModal was called. This function should only be triggered by user actions.');
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
            account: connectResponse?.data?.account_url, // Corrected to access the nested 'data' object
            manifest: connectResponse?.data?.manifest_data ?? manifestJson, // Corrected to access the nested 'data' object
            state: connectResponse?.data?.state_token, // Corrected to access the nested 'data' object
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

const App = {
    initialized: false, // Add a flag to track initialization

    init() {
        if (this.initialized) {
            console.warn('App.init() called multiple times. Initialization skipped.');
            return;
        }
        this.initialized = true;

        console.log('Initializing WP2 Update App...');
        // Initialize state and controllers
        this.state = { ...unified_state.get() };
        this.controllers = this.controllers || {};

        // Bind global event listeners
        this.bindGlobalEvents();
        this.bindDashboardInteractions(); // Ensure interactions are bound

        // Initial render without triggering any modals
        this.render();

        // Ensure no wizard modal is triggered on load
        const wizardModal = document.getElementById('wp2-modal-wizard');
        if (wizardModal) {
            wizardModal.hidden = true;
            wizardModal.classList.remove('is-active');
        }

        // Subscribe to state changes
        unified_state.subscribe(this.render.bind(this));
        console.log('App initialized successfully.');

        const addAppButton = document.getElementById('wp2-add-app-button');
        if (addAppButton) {
            Logger.debug('Add GitHub App button found. Attaching event listener.');
            addAppButton.addEventListener('click', () => {
                Logger.debug('Add GitHub App button clicked. Attempting to open modal.');
                openWizardModal();
            });
        }
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
        const state = unified_state.get();
        const root = document.getElementById('wp2-update-app');
        if (!root) return;

        const renderUnconfiguredContent = (s) => {
            if (s.status === 'MANUAL_CONFIGURING') {
                return renderManualCredentialsForm(s);
            }
            return `
                <div class="wp2-workflow-step wp2-configuring">
                    <h2 class="wp2-step-title">Welcome to WP2 Update</h2>
                    <p class="wp2-p-description">Click below to start connecting to GitHub.</p>
                    <div style="text-align: center;">
                        <button type="button" class="wp2-button wp2-button-primary" id="wp2-start-connection">Start Connection</button>
                        <button type="button" class="wp2-button wp2-button-secondary" id="wp2-manual-setup">Manual Setup</button>
                    </div>
                </div>`;
        };

        switch (state.status) {
            case 'INSTALLED':
                root.innerHTML = DashboardView(state);
                this.bindDashboardInteractions(this.controllers);
                break;
            case 'NOT_CONFIGURED':
            case 'NOT_CONFIGURED_WITH_PACKAGES':
            case 'CONFIGURING':
            case 'MANUAL_CONFIGURING':
            case 'APP_CREATED':
            case 'ERROR':
            default:
                if (!root.innerHTML.trim()) {
                    root.innerHTML = renderUnconfiguredContent(state);
                }
                // Ensure no wizard modal is triggered automatically
                document.getElementById('wp2-start-connection')?.addEventListener('click', () => {
                    console.log('Start Connection button clicked. No wizard modal will be triggered.');
                });
                document.getElementById('wp2-manual-setup')?.addEventListener('click', () => {
                    updateUnifiedState({ status: STATUS.MANUAL_CONFIGURING });
                });
                break;
        }
    },

    refresh() {
        console.log('Refreshing application state...');
        // Logic to refresh state or re-fetch data
    },

    bindDashboardInteractions(controllers = {}) {
        const { fetchConnectionStatus = () => { }, syncPackages = () => { } } = controllers;

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

        // Enhanced event listener for the 'Add GitHub App' button
        const addAppButton = document.getElementById('wp2-add-app-button');
        if (addAppButton) {
            console.log('[DEBUG] Add GitHub App button found. Attaching event listener.');
            addAppButton.addEventListener('click', () => {
                console.log('[DEBUG] Add GitHub App button clicked. Attempting to open modal.');
                const modalContainer = document.getElementById('wp2-modal-container');
                if (modalContainer) {
                    const uniqueId = Date.now().toString();
                    const newContent = document.createElement('div');
                    newContent.innerHTML = renderWizardModalContent(uniqueId);

                    // Append the new content instead of replacing existing content
                    while (newContent.firstChild) {
                        modalContainer.appendChild(newContent.firstChild);
                    }

                    openWizardModal();
                } else {
                    console.error('Modal container not found in the DOM.');
                }
            });
        } else {
            console.error('[ERROR] Add GitHub App button not found in the DOM.');
        }

        // Ensure the modal close button works
        document.querySelectorAll('[data-wp2-close="wizard"]').forEach((button) => {
            button.addEventListener('click', () => {
                const wizardModal = document.getElementById('wp2-modal-wizard');
                if (wizardModal) {
                    wizardModal.hidden = true;
                    wizardModal.classList.remove('is-active');
                }
            });
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
    },
};

document.addEventListener('DOMContentLoaded', () => {
    const addAppButton = document.getElementById('wp2-add-app-button');
    const wizardModal = document.getElementById('wp2-modal-wizard');

    if (!wizardModal) {
        console.error('[ERROR] Modal container `#wp2-modal-wizard` not found in the DOM.');
        return;
    }

    if (addAppButton) {
        addAppButton.addEventListener('click', () => {
            console.debug('[DEBUG] Add GitHub App button clicked. Attempting to open modal.');

            // Ensure the modal is visible and active
            wizardModal.style.display = 'flex';
            wizardModal.classList.add('is-active');
        });
    }

    const modalCloseButtons = document.querySelectorAll('[data-wp2-close]');
    modalCloseButtons.forEach(button => {
        button.addEventListener('click', (event) => {
            const modalId = event.target.closest('.wp2-modal')?.id;
            if (modalId) {
                const modal = document.getElementById(modalId);
                modal.style.display = 'none';
                modal.classList.remove('is-active');
            }
        });
    });
});

document.addEventListener('click', (event) => {
    const closeTrigger = event.target.closest('[data-wp2-close]');
    if (closeTrigger) {
        const modalId = closeTrigger.getAttribute('data-wp2-close');
        closeModal(`wp2-modal-${modalId}`);
    }
});

export { App };