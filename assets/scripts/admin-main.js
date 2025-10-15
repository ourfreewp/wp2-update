import { store, updateState, STATUS } from './modules/state/store.js';
import { ConnectionService } from './modules/services/ConnectionService.js';
import { PackageService } from './modules/services/PackageService.js';
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
        message: connectionStatus?.message || __("Loading", "wp2-update"),
        details: connectionStatus?.details || {},
        apps,
        selectedAppId: selectedAppId ?? (apps[0]?.id ?? null),
        packages: [...packages, ...unlinkedPackages], // Combine managed and unlinked packages
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
 * Main application initialization function.
 */
document.addEventListener('DOMContentLoaded', async () => {
    if (window.wp2UpdateInitialized) {
        logger.warn(__("Application already initialized. Skipping.", "wp2-update"));
        return;
    }
    window.wp2UpdateInitialized = true;

    logger.info(__("DOM Content Loaded. Initializing WP2 Update application.", "wp2-update"));

    // Bootstrap initial state from WP localized data
    bootstrapInitialState();
    initializeState();

    // Initialize services
    const connectionService = new ConnectionService();
    const appService = new AppService();
    const packageService = new PackageService();

    // Register all event handlers
    registerAppHandlers(appService, connectionService);
    registerPackageHandlers(packageService);
    registerFormHandlers();

    // Enhance the dashboard view if the element exists
    const dashboardElement = document.getElementById('wp2-update-dashboard');
    if (dashboardElement) {
        const dashboardView = new DashboardView();
        dashboardView.enhance(dashboardElement);
    }

    // Enhance the health view if the element exists
    const healthElement = document.getElementById('wp2-update-health');
    if (healthElement) {
        const healthView = new HealthView();
        healthView.enhance(healthElement);
    }

    // Refactored spinner logic to subscribe to `isProcessing` state
    store.subscribe(() => {
        const { isProcessing } = store.get();
        const spinner = document.getElementById('global-spinner');
        if (spinner) {
            spinner.style.display = isProcessing ? 'block' : 'none';
        }
    });

    // Perform initial data fetch and sync
    updateState({ isProcessing: true });
    try {
        await connectionService.fetchConnectionStatus();
        if (store.get().status === STATUS.INSTALLED) {
            await appService.fetchApps();
            await packageService.fetchPackages();
        }
    } catch (error) {
        logger.error(__("Failed during initial data synchronization:", "wp2-update"), error);
        NotificationService.showError(__("Failed to load initial data.", "wp2-update")); // Ensure proper toast integration
    } finally {
        updateState({ isProcessing: false });
    }

    // Initialize the Packages view
    initializePackagesView();

    // Attach event handlers to server-rendered tabs
    document.querySelectorAll('[data-tab]').forEach(tab => {
        tab.addEventListener('click', (event) => {
            event.preventDefault();
            const targetTab = tab.getAttribute('data-tab');
            document.querySelectorAll('[data-tab-content]').forEach(content => {
                content.style.display = content.getAttribute('data-tab-content') === targetTab ? 'block' : 'none';
            });
        });
    });

    // Handle package refresh on load
    handleRefreshPackages();

    // Attach event listeners to buttons that trigger modals
    const modalButtons = document.querySelectorAll('[data-bs-toggle="modal"]');

    modalButtons.forEach(button => {
        button.addEventListener('click', (event) => {
            const targetModalId = button.getAttribute('data-bs-target');

            // Special handling for the GitHub App Wizard modal
            if (targetModalId === '#addAppModal') {
                event.preventDefault(); // Prevent default Bootstrap behavior

                // Initialize and open the wizard modal
                initializeWizardModal();
            } else {
                const modalElement = document.querySelector(targetModalId);

                if (modalElement) {
                    const modalInstance = new Modal(modalElement, {
                        backdrop: true, // Ensure backdrop dismissal is enabled
                        keyboard: true  // Allow dismissal with the ESC key
                    });
                    modalInstance.show();
                } else {
                    console.error(`Modal with ID ${targetModalId} not found.`);
                }
            }
        });
    });
});

// Ensure wp.i18n is available before executing dependent code
if (typeof wp === 'undefined' || !wp.i18n) {
    console.error('wp.i18n is not available.');
} else {
    console.log('wp.i18n is loaded.');
    // Logging to confirm script load and check wp.i18n availability
    console.log('Admin script loaded successfully.');
    console.log('wp.i18n:', typeof wp !== 'undefined' && wp.i18n ? 'Loaded' : 'Not Loaded');
}
