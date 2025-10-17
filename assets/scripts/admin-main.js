// Modularized and cleaned up admin-main.js

// Import necessary modules
import './src/components/Wp2UpdateApp.js';
import { fetchInitialData } from './src/actions/initActions.js';
import { v4 as uuidv4 } from 'uuid';

// Generate a unique correlation ID for this session
if (!window.wp2UpdateCorrelationId) {
    window.wp2UpdateCorrelationId = uuidv4();
    console.info('Generated Correlation ID:', window.wp2UpdateCorrelationId);
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

    // Find the root element to mount the application
    const rootElement = document.getElementById('wp2-update-app');
    if (!rootElement) {
        console.error('Root element #wp2-update-app not found.');
        return;
    }

    // Inject the main app component into the root element.
    rootElement.innerHTML = '<wp2-update-app></wp2-update-app>';

    // Fetch all necessary data after the component is in the DOM.
    try {
        await fetchInitialData();
    } catch (error) {
        console.error('Failed to fetch initial data:', error);
    }

    // Ensure modal accessibility
    const modalElement = document.querySelector('.modal[aria-hidden="true"]');
    if (modalElement) {
        modalElement.setAttribute('inert', '');
        modalElement.addEventListener('focus', (event) => {
            event.preventDefault();
            console.warn('Focus blocked on hidden modal.');
        });
    }
});
