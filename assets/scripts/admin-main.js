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
import { __ } from '@wordpress/i18n';

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

    // Initialize services
    const connectionService = new ConnectionService();
    const appService = new AppService();
    const packageService = new PackageService();

    // Register all event handlers
    registerAppHandlers(appService, connectionService);
    registerPackageHandlers(packageService);
    registerFormHandlers();

    // Render initial view based on the current state
    const rootElement = document.getElementById('wp2-update-dashboard');
    if (rootElement) {
        const dashboardView = new DashboardView();
        dashboardView.render(rootElement);
    }

    // Perform initial data fetch and sync
    show_global_spinner();
    try {
        await connectionService.fetchConnectionStatus();
        if (store.get().status === STATUS.INSTALLED) {
            await appService.fetchApps();
            await packageService.fetchPackages();
        }
    } catch (error) {
        logger.error(__("Failed during initial data synchronization:", "wp2-update"), error);
        NotificationService.showError(__("Failed to load initial data.", "wp2-update"));
    } finally {
        hide_global_spinner();
    }

    // Centralized state management for SPA tabs
    const state = {
        activeTab: new URLSearchParams(window.location.search).get('tab') || 'dashboard',
    };

    // Initialize views
    const views = {
        dashboard: new DashboardView(),
        health: new HealthView(),
    };

    // Render the active tab dynamically
    const renderActiveTab = () => {
        const rootElement = document.getElementById('wp2-update-dashboard');
        if (rootElement && views[state.activeTab]) {
            views[state.activeTab].render(rootElement);
        }
    };

    // Initial render
    renderActiveTab();

    // Listen for tab changes
    document.querySelectorAll('[data-tab]').forEach(tab => {
        tab.addEventListener('click', (event) => {
            event.preventDefault();
            state.activeTab = tab.getAttribute('data-tab');
            renderActiveTab();
        });
    });
});
