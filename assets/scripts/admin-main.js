import { store, updateState, STATUS } from './modules/state/store.js';
import { ConnectionService } from './modules/services/ConnectionService.js';
import { AppService } from './modules/services/AppService.js';
import { registerPackageHandlers } from './modules/handlers/packageEventHandlers.js';
import { registerFormHandlers } from './modules/handlers/formEventHandlers.js';
import { registerAppHandlers } from './modules/handlers/appEventHandlers.js';
import { DashboardView } from './modules/views/DashboardView.js';
import { HealthView } from './modules/views/HealthView.js';
import { logger } from './modules/utils/logger.js';
import { NotificationService } from './modules/services/NotificationService.js';
import { initializePackagesView } from './modules/views/PackagesView.js';
const { __ } = wp.i18n;
import { handleRefreshPackages } from './modules/handlers/refreshPackagesHandler';
import { Modal } from 'bootstrap';
import { PollingService } from './modules/services/PollingService.js';
import { CreatePackageModal } from './modules/components/modals/CreatePackageModal.js';

/**
 * Initializes the application state from localized data provided by WordPress.
 */
const bootstrapInitialState = () => {
    const {
        connectionStatus,
        apps = [],
        selectedAppId = null,
        packages = [],
        unlinkedPackages = [],
        health = {},
        stats = {},
    } = window.wp2UpdateData || {};

    updateState({
        status: connectionStatus?.status || STATUS.LOADING,
        message: connectionStatus?.message || wp2UpdateData.i18n.loading,
        details: connectionStatus?.details || {},
        apps,
        selectedAppId: selectedAppId ?? (apps[0]?.id ?? null),
        packages: [...(packages || []), ...(unlinkedPackages || [])], // Ensure both are arrays before combining
        health,
        stats,
        isProcessing: false, // Ensure initial state is not processing
    });
};

// Centralized state initialization
function initializeState() {
    const initialState = {
        ...store.get(),
        packages: store.get().packages.map(pkg => ({
            ...pkg,
            isUpdating: false,
            isRollingBack: false
        }))
    };
    store.set(initialState);
}

/**
 * Renders the entire application based on the current state.
 * This function ensures the UI reflects the latest state.
 */
function renderApp(state) {
    const spinner = document.getElementById('global-spinner');
    if (spinner) {
        spinner.style.display = state.isProcessing ? 'block' : 'none';
    }

    const activeTab = state.activeTab;
    document.querySelectorAll('[data-tab-content]').forEach(content => {
        content.style.display = content.getAttribute('data-tab-content') === activeTab ? 'block' : 'none';
    });

    const activeModal = state.activeModal;
    document.querySelectorAll('[data-bs-toggle="modal"]').forEach(button => {
        const targetModalId = button.getAttribute('data-bs-target');
        const modalElement = document.querySelector(targetModalId);

        if (modalElement) {
            if (activeModal === targetModalId) {
                const modalInstance = new Modal(modalElement, {
                    backdrop: true,
                    keyboard: true
                });
                modalInstance.show();
            }
        } else {
            console.error(`Modal with ID ${targetModalId} not found.`);
        }
    });
}

// Subscribe to state changes and re-render the app
store.subscribe(() => {
    const currentState = store.get();
    renderApp(currentState);
});

/**
 * Converts an HTML string into a DOM element.
 *
 * @param {string} htmlString - The HTML string to convert.
 * @returns {HTMLElement} - The resulting DOM element.
 */
function createElementFromHTML(htmlString) {
    const template = document.createElement('template');
    template.innerHTML = htmlString.trim();
    return template.content.firstChild;
}

/**
 * Main application initialization function.
 */
document.addEventListener('DOMContentLoaded', async () => {
    if (window.wp2UpdateInitialized) {
        logger.warn(wp2UpdateData.i18n.appInitialized);
        return;
    }
    window.wp2UpdateInitialized = true;

    logger.info(wp2UpdateData.i18n.domLoaded);

    // Bootstrap initial state from WP localized data
    bootstrapInitialState();
    initializeState();

    // Initialize services
    const connectionService = new ConnectionService();
    const appService = new AppService();

    // Register all event handlers
    registerAppHandlers(appService, connectionService);
    registerPackageHandlers();
    registerFormHandlers();

    const dashboardElement = document.getElementById('wp2-update-dashboard');
    if (dashboardElement) {
        DashboardView.initialize();

        const statusElement = document.getElementById('magic-setup-status');
        PollingService.startPolling('/wp-json/wp2-update/v1/connection-status', 5000, (error, data) => {
            if (!error && data && typeof data === 'object') {
                const { status, last_checked } = data;

                if (statusElement) {
                    statusElement.classList.remove('alert-info', 'alert-warning', 'alert-success', 'alert-danger');

                    if (status === 'connected') {
                        statusElement.classList.add('alert-success');
                        statusElement.innerHTML = `<strong>Status:</strong> Connected! Last checked: ${last_checked}`;
                    } else {
                        statusElement.classList.add('alert-warning');
                        statusElement.innerHTML = `<strong>Status:</strong> Disconnected. Last checked: ${last_checked}`;
                    }
                }
            } else {
                if (statusElement) {
                    statusElement.classList.remove('alert-info');
                    statusElement.classList.add('alert-danger');
                    statusElement.innerHTML = `<strong>Status:</strong> Error fetching status. ${error?.message || 'Unknown error'}`;
                }
            }
        });
    }

    const healthElement = document.getElementById('wp2-update-health');
    if (healthElement) {
        HealthView.initialize();
    }

    store.subscribe(() => {
        const { isProcessing } = store.get();
        const spinner = document.getElementById('global-spinner');
        if (spinner) {
            spinner.style.display = isProcessing ? 'block' : 'none';
        }
    });

    store.subscribe(() => {
        const { activeTab } = store.get();
        document.querySelectorAll('[data-tab-content]').forEach(content => {
            content.style.display = content.getAttribute('data-tab-content') === activeTab ? 'block' : 'none';
        });
    });

    document.querySelectorAll('[data-tab]').forEach(tab => {
        tab.addEventListener('click', (event) => {
            event.preventDefault();
            const targetTab = tab.getAttribute('data-tab');
            updateState({ activeTab: targetTab });
        });
    });

    updateState({ isProcessing: true });
    try {
        await connectionService.fetchConnectionStatus();
        if (store.get().status === STATUS.INSTALLED) {
            await appService.fetchApps();
            await packageService.fetchPackages();
        }
    } catch (error) {
        logger.error(wp2UpdateData.i18n.syncFailed, error);
        NotificationService.showError(wp2UpdateData.i18n.loadDataError);
    } finally {
        updateState({ isProcessing: false });
    }

    initializePackagesView();

    document.querySelectorAll('[data-tab]').forEach(tab => {
        tab.addEventListener('click', (event) => {
            event.preventDefault();
            const targetTab = tab.getAttribute('data-tab');
            document.querySelectorAll('[data-tab-content]').forEach(content => {
                content.style.display = content.getAttribute('data-tab-content') === targetTab ? 'block' : 'none';
            });
        });
    });

    handleRefreshPackages();

    store.subscribe(() => {
        const { activeModal } = store.get();
        document.querySelectorAll('[data-bs-toggle="modal"]').forEach(button => {
            const targetModalId = button.getAttribute('data-bs-target');
            const modalElement = document.querySelector(targetModalId);

            if (modalElement) {
                if (activeModal === targetModalId) {
                    const previouslyFocusedElement = document.activeElement;

                    modalElement.removeAttribute('aria-hidden');
                    modalElement.setAttribute('inert', '');
                    modalElement.focus();

                    const modalInstance = new Modal(modalElement, {
                        backdrop: true,
                        keyboard: true
                    });
                    modalInstance.show();

                    modalElement.addEventListener('hidden.bs.modal', () => {
                        updateState({ activeModal: null });
                        setTimeout(() => {
                            modalElement.setAttribute('aria-hidden', 'true');
                            modalElement.removeAttribute('inert');
                            if (previouslyFocusedElement) {
                                previouslyFocusedElement.focus();
                            }
                        }, 0);
                    });
                }
            } else {
                console.error(`Modal with ID ${targetModalId} not found.`);
            }
        });
    });

    document.querySelectorAll('[data-bs-toggle="modal"]').forEach(button => {
        button.addEventListener('click', () => {
            const targetModalId = button.getAttribute('data-bs-target');
            updateState({ activeModal: targetModalId });
        });
    });

    try {
        const createPackageModalElement = CreatePackageModal();
        const modalElement = document.querySelector('#createPackageModal');
        if (modalElement) {
            const modalTarget = modalElement.querySelector('.modal-content');

            if (modalTarget) {
                modalTarget.innerHTML = '';
                modalTarget.appendChild(createPackageModalElement);
            } else {
                console.error('Create Package Modal content container not found inside #createPackageModal.');
            }
        } else {
            console.error('Create Package Modal element #createPackageModal not found in the DOM.');
        }
    } catch (error) {
        console.error('Failed to initialize Create Package Modal:', error);
    }
});
