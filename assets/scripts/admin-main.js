import { apiRequest } from './modules/api.js';
import { appState, connectionState } from './modules/state.js';
import { showToast, initTooltips, initTabs } from './modules/ui.js';

/**
 * WP2 Update Plugin: Admin UI Main Script
 *
 * This script manages the entire admin UI workflow, including state changes,
 * API requests, and dynamic DOM rendering. It's designed to be robust,
 * fixing previous initialization and API errors.
 */

// Debug log to confirm script execution
console.log('[DEBUG] admin-main.js script loaded and executed.');

document.addEventListener('DOMContentLoaded', () => {
    // Main application container
    const appContainer = document.getElementById('wp2-update-app');

    // Exit if the main container is not found on the page
    if (!appContainer) {
        console.error('[WP2 Update] Main application container #wp2-update-app not found. Script will not run.');
        return;
    }

    // --- State Management ---
    appState.listen((newState, oldState) => {
        console.log('[DEBUG] State updated:', oldState, '->', newState);
        renderApp(newState);
    });

    // --- Event Handling ---
    appContainer.addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-action]');
        if (button) {
            const action = button.dataset.action;
            console.log('[DEBUG] Button clicked:', action);
            await handleAction(action, button);
        }
    });

    // --- Initialization ---
    initTooltips();
    initTabs();
    renderApp(appState.get());
});

/**
 * Main render function. Shows the current workflow step and hides others.
 * This is called automatically whenever the state changes.
 */
const renderApp = (state) => {
    console.log('[DEBUG] Rendering application state:', state);

    // Only re-render the main view if the stage has changed
    if (state.currentStage === appState.get()?.currentStage) return;

    const currentStepEl = document.getElementById(state.currentStage);
    if (!currentStepEl) {
        console.error('[WP2 Update] No element found for current stage:', state.currentStage);
        return;
    }

    document.querySelectorAll('.workflow-step').forEach(el => {
        el.hidden = el.id !== state.currentStage;
    });
};

/**
 * Handles all user actions dispatched from buttons with a `data-action` attribute.
 * @param {string} action - The action to perform (e.g., 'connect', 'disconnect').
 * @param {HTMLElement} element - The button element that was clicked.
 */
const handleAction = async (action, button) => {
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="spinner is-active" style="margin:0 auto;"></span>';
    button.disabled = true;

    try {
        switch (action) {
            case 'connect':
                appState.set({ currentStage: 'step-2-credentials' });
                break;

            case 'cancel':
                 appState.set({ currentStage: 'step-1-pre-connection' });
                break;
            
            case 'save-validate':
                // In a real app, you'd gather form data here.
                // For this demo, we'll just proceed.
                appState.set({ currentStage: 'step-2-5-sync' });
                // Simulate validation and sync
                await new Promise(res => setTimeout(res, 2000));
                appState.set({ currentStage: 'step-3-management' });
                break;

            case 'check-releases':
                appState.set({ isLoading: true });
                const syncResult = await apiRequest('wp2-update/v1/sync-packages');
                appState.set({ packages: syncResult.repositories, isLoading: false });
                break;

            case 'disconnect':
                if (confirm('Are you sure you want to disconnect?')) {
                    await apiRequest('wp2-update/v1/disconnect');
                    appState.set({ currentStage: 'step-1-pre-connection' });
                }
                break;
            
            case 'update-package':
                const slug = button.dataset.packageSlug;
                const select = appContainer.querySelector(`.release-dropdown[data-package-slug="${slug}"]`);
                const version = select.value;
                await apiRequest('wp2-update/v1/manage-packages', { 
                    body: { action: 'update', repo_slug: slug, version } 
                });
                // Refresh data after update
                handleAction('check-releases', button);

                console.warn('[WP2 Update] Update logic is not defined. Skipping update logic.');
                break;

            case 'validate-connection':
                const result = await apiRequest('wp2-update/v1/validate-connection');
                connectionState.set({
                    connected: result.success,
                    message: result.message,
                    isLoading: false,
                });
                showToast(result.message, result.success ? 'success' : 'error');
                break;

            default:
                console.warn('[WP2 Update] Unknown action:', action);
        }
    } catch (error) {
        console.error('[WP2 Update] Action failed:', error);
        showToast(error.message, 'error');
    } finally {
        button.innerHTML = originalText;
        button.disabled = false;
    }
};
