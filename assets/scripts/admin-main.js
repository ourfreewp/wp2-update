// Modularized and cleaned up admin-main.js

// Import necessary modules
import './src/components/Wp2UpdateApp.js';
import { fetchInitialData } from './src/actions/initActions.js';
import { v4 as uuidv4 } from 'uuid';
import './src/components/shared/UiNotification.js';
import { fetchBackups, restoreBackup } from './src/actions/backupActions.js';

// Generate a unique correlation ID for this session
if (!window.wp2UpdateCorrelationId) {
    window.wp2UpdateCorrelationId = uuidv4();
    console.info('Generated Correlation ID:', window.wp2UpdateCorrelationId);
}

// Trusted Types policy for WP2 Update
if (window.trustedTypes && window.trustedTypes.createPolicy) {
    window.wp2UpdatePolicy = window.trustedTypes.createPolicy('wp2-update-policy', {
        createHTML: (input) => input,
        createScript: (input) => input,
        createScriptURL: (input) => input
    });
}

/**
 * Main application initialization function.
 */
document.addEventListener('DOMContentLoaded', async () => {
    if (window.wp2UpdateInitialized) {
        console.warn('Application already initialized. Skipping.');
        return;
    }
    window.wp2UpdateInitialized = true;

    console.info('Initializing WP2 Update application.');

    const rootElement = document.getElementById('wp2-update-app');
    if (!rootElement) {
        console.error('Root element #wp2-update-app not found.');
        return;
    }

    rootElement.innerHTML = '<wp2-update-app></wp2-update-app>';

    const notificationContainer = document.createElement('div');
    notificationContainer.id = 'wp2-update-notifications';
    notificationContainer.setAttribute('aria-live', 'polite');
    document.body.prepend(notificationContainer);

    try {
        await fetchInitialData();
    } catch (error) {
        console.error('Failed to fetch initial data:', error);
    }

    // Expose simple backup debug helpers (optional)
    window.wp2Backups = {
        list: fetchBackups,
        restore: restoreBackup,
    };

    const modalElement = document.querySelector('.modal[aria-hidden="true"]');
    if (modalElement) {
        modalElement.removeAttribute('aria-hidden');
        modalElement.setAttribute('inert', '');
        modalElement.addEventListener('focus', (event) => {
            event.preventDefault();
            console.warn('Focus blocked on hidden modal.');
        });
    } else {
        console.warn('No modal element found to attach event listeners.');
    }

    // Global error handlers to surface issues to users
    window.addEventListener('unhandledrejection', (event) => {
        try {
            const msg = event?.reason?.message || 'An unexpected error occurred.';
            console.error('Unhandled promise rejection:', event.reason);
            const { NotificationService } = require('./src/services/NotificationService.js');
            NotificationService.showError(msg);
        } catch (e) {
            // no-op
        }
    });

    window.addEventListener('error', (event) => {
        try {
            const msg = event?.error?.message || event?.message || 'A runtime error occurred.';
            console.error('Global error:', event.error || event.message);
            const { NotificationService } = require('./src/services/NotificationService.js');
            NotificationService.showError(msg);
        } catch (e) {
            // no-op
        }
    }, true);
});
