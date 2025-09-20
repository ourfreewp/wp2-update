// ========================================================================
// Description: The main application entry point.
// ========================================================================

// Import local modules
import { connectionState } from './modules/state.js';
import { showToast, initTooltips, initTabs } from './modules/ui.js';
import { apiRequest } from './modules/api.js';

// Import CSS - Vite will extract this into a separate .css file during build
import '../styles/toastify.css'; // Corrected path for Toastify CSS

/**
 * Renders the connection status block by subscribing to the global state.
 */
const renderConnectionStatus = () => {
    const statusBlock = document.getElementById('wp2-connection-status');
    if (!statusBlock) return;

    // Listen for changes in the connection state and update the DOM.
    connectionState.listen(state => {
        const noticeClass = state.connected ? 'notice-success' : (state.connected === false ? 'notice-error' : 'notice-info');
        statusBlock.innerHTML = `<p class="notice ${noticeClass}">${state.message}</p>`;
    });
};

/**
 * Fetches the current connection status from the server and updates the state.
 */
const updateConnectionStatus = async () => {
    connectionState.set({ isLoading: true });
    try {
        const data = await apiRequest('/wp2-update/v1/connection-status', {
            method: 'GET',
            headers: {
                'X-WP-Nonce': wpApiSettings.nonce, // Include the nonce
            },
        });
        connectionState.set({ connected: data.connected, message: data.message, isLoading: false });
    } catch (error) {
        connectionState.set({ connected: false, message: `Error: ${error.message}`, isLoading: false });
    }
};

/**
 * Initializes event listeners for the main Settings page.
 */
const initConnectionPage = () => {
    const testConnectionBtn = document.getElementById('wp2-test-connection-button');
    const disconnectBtn = document.getElementById('wp2-disconnect-btn');
    const loader = document.querySelector('.wp2-loader');

    if (testConnectionBtn) {
        testConnectionBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            if (loader) loader.style.display = 'inline-block';
            testConnectionBtn.disabled = true;

            try {
                const data = await apiRequest('/wp2-update/v1/test-connection');
                showToast(data.message || 'Connection test successful!', data.success ? 'success' : 'error');
                await updateConnectionStatus();
            } catch (error) {
                showToast(error.message, 'error');
            } finally {
                if (loader) loader.style.display = 'none';
                testConnectionBtn.disabled = false;
            }
        });
    }

    if (disconnectBtn) {
        disconnectBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            if (confirm('Are you sure you want to disconnect? This will remove all saved settings.')) {
                try {
                    await apiRequest('/wp2-update/v1/disconnect');
                    showToast('Successfully disconnected. The page will now reload.', 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } catch (error) {
                    showToast(error.message, 'error');
                }
            }
        });
    }
};

/**
 * Initializes event listeners for the System Health page.
 */
const initSystemHealthPage = () => {
    const clearCacheButton = document.querySelector('.wp2-clear-cache-button');
    if (clearCacheButton) {
        clearCacheButton.addEventListener('click', async (e) => {
            e.preventDefault();
            clearCacheButton.textContent = 'Processing...';
            clearCacheButton.disabled = true;

            try {
                const data = await apiRequest('/wp2-update/v1/clear-cache-force-check');
                showToast(data.message, 'success');
            } catch (error) {
                showToast(error.message, 'error');
            } finally {
                clearCacheButton.textContent = 'Clear All Caches & Force Check';
                clearCacheButton.disabled = false;
            }
        });
    }
};

/**
 * Handles the example action via REST API.
 */
const handleExampleAction = async () => {
    const exampleActionBtn = document.getElementById('example-action-button');
    if (!exampleActionBtn) return;

    exampleActionBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        try {
            const response = await apiRequest('/wp2-update/v1/example-action', {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': wpApiSettings.nonce,
                },
            });

            showToast(response.data.message, response.success ? 'success' : 'error');
        } catch (error) {
            showToast(`Error: ${error.message}`, 'error');
        }
    });
};

/**
 * Main application initializer.
 */
const initApp = () => {
    renderConnectionStatus(); // Set up the reactive listener first
    updateConnectionStatus(); // Fetch initial data to populate the store
    
    // Initialize page-specific scripts
    initConnectionPage();
    initSystemHealthPage();
    
    // Initialize global UI components
    initTooltips();
    initTabs();

    // Initialize the example action handler
    handleExampleAction();
};

// Run the application once the DOM is fully loaded.
document.addEventListener('DOMContentLoaded', initApp);
