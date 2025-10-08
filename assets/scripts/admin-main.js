/**
 * @file src-js/admin-main.js
 * @description Main script for the WP2 Update admin UI. (v3 - Complete Implementation)
 */

import { apiRequest } from './modules/api.js';
import { appState } from './modules/state.js';
import { showToast, initUI, showConfirmationModal } from './modules/ui.js';
import { renderAppView, renderPackageTable } from './modules/components.js';
import debounce from 'lodash/debounce';

/**
 * Maps `data-action` attributes to API calls and state changes. This is the
 * command center for all user interactions.
 * @type {Object.<string, (button?: HTMLElement) => Promise<void>>}
 */
const actions = {
	/**
	 * ACTION: Begins the connection process.
	 * Fetches the secure GitHub App creation URL from our backend and redirects the user.
	 */
	'start-connection': async () => {
		const { url } = await apiRequest('wp2-update/v1/github/connect-url', { method: 'GET' });
		if (url) {
			window.location.href = url; // User is sent to GitHub
		}
	},

	/**
	 * ACTION: Disconnects the app.
	 * Tells the backend to delete the stored credentials and resets the UI.
	 */
	disconnect: async () => {
		showConfirmationModal(
			'Are you sure you want to disconnect? This will remove your credentials.',
			async () => {
				await apiRequest('wp2-update/v1/github/disconnect');
				appState.set({ ...appState.get(), currentStage: 'pre-connection', packages: [] });
				showToast('Disconnected successfully.');
			},
			() => {
				showToast('Disconnect action canceled.', 'error');
			}
		);
	},

	/**
	 * ACTION: Fetches all repositories and their releases.
	 * This is a "sync" operation that populates the main management table.
	 */
	'sync-packages': debounce(async () => {
		showLoadingSpinner();
		appState.setKey('isLoading', true);
		try {
			// This single endpoint is assumed to fetch repos and their latest releases.
			const result = await apiRequest('wp2-update/v1/sync-packages', { method: 'GET' });
			appState.set({
				...appState.get(),
				packages: result.repositories || [],
				isLoading: false,
				connection: {
					...appState.get().connection,
					health: {
						...appState.get().connection.health,
						lastSync: new Date().toISOString(),
					},
				},
				error: null, // Clear any previous error
			});
			showToast('Successfully synced with GitHub.');
		} catch (error) {
			console.error('[Sync Failed]', error);
			appState.set({
				...appState.get(),
				isLoading: false,
				error: error.message, // Set the error state
			});
			showToast('Failed to sync packages. Please try again.', 'error');
		} finally {
			hideLoadingSpinner();
		}
	}, 300), // Debounce with a 300ms delay

	/**
	 * ACTION: Installs or updates a specific package.
	 * Optimistically updates the UI to show the 'updating' state before the API call completes.
	 * Reverts the state and shows an error if the API call fails.
	 * @param {HTMLElement} button - The button that was clicked.
	 */
	'update-package': async (button) => {
		const repo = button.dataset.packageRepo;
		const select = document.querySelector(`.release-dropdown[data-package-repo="${repo}"]`);
		if (!select) throw new Error('Could not find the release dropdown for this package.');

		const version = select.value;
		const originalPackages = appState.get().packages;

		// Optimistically set the package's status to 'updating'
		const updatedPackages = originalPackages.map(p =>
			p.repo === repo ? { ...p, status: 'updating' } : p
		);
		appState.setKey('packages', updatedPackages);

		try {
			await apiRequest('wp2-update/v1/manage-packages', {
				body: { action: 'update', repo_slug: repo, version },
			});

			showToast(`${repo} update to ${version} initiated.`);

			// Update only the relevant package instead of refreshing all data
			const refreshedPackage = await apiRequest(`wp2-update/v1/package/${repo}`, { method: 'GET' });
			const newPackages = appState.get().packages.map(p =>
				p.repo === repo ? { ...p, ...refreshedPackage } : p
			);
			appState.setKey('packages', newPackages);
		} catch (error) {
			console.error(`[Update Failed: ${repo}]`, error);
			showToast(`Failed to update ${repo}: ${error.message}`, 'error');

			// Revert the package's status to its original state
			const updatedPackages = originalPackages.map(p =>
				p.repo === repo ? { ...p, status: 'error', errorMessage: error.message } : p
			);
			appState.setKey('packages', updatedPackages);
		}
	},
};

/**
 * Handles all delegated click events on elements with a `data-action`.
 * @param {Event} event
 */
const handleAction = async (event) => {
	const button = event.target.closest('button[data-action]');
	if (!button) return;

	const action = button.dataset.action;
	if (actions[action]) {
		const originalHtml = button.innerHTML;
		button.innerHTML = '<span class="spinner is-active"></span>';
		button.disabled = true;

		try {
			await actions[action](button);
		} catch (error) {
			console.error(`[Action Failed: ${action}]`, error);
			showToast(error.message, 'error');
		} finally {
			// Restore button only if it hasn't been re-rendered
			const currentButton = document.querySelector(`[data-action="${action}"]`);
			if (currentButton) {
				currentButton.innerHTML = originalHtml;
				currentButton.disabled = false;
			}
		}
	}
};

/**
 * Initializes the application on page load.
 * It determines if the site is connected and sets the correct initial state.
 */
const initializeApp = async () => {
	const appContainer = document.getElementById('wp2-update_app');
	if (!appContainer) return;

	appState.setKey('isLoading', true);

	try {
		const status = await apiRequest('wp2-update/v1/github/connection-status', { method: 'GET' });
		if (status.connected) {
			appState.setKey('currentStage', 'managing');
			await actions['sync-packages'](); // Sync packages automatically if connected
		} else {
			appState.setKey('currentStage', 'pre-connection');
		}
	} catch (error) {
		appState.setKey('currentStage', 'pre-connection');
		showToast(error.message, 'error');
	} finally {
		appState.setKey('isLoading', false);
	}
};

/**
 * Add a global loading spinner to provide better user feedback during loading states
 */
const showLoadingSpinner = () => {
	const spinner = document.createElement('div');
	spinner.id = 'global-loading-spinner';
	spinner.style.position = 'fixed';
	spinner.style.top = '50%';
	spinner.style.left = '50%';
	spinner.style.transform = 'translate(-50%, -50%)';
	spinner.style.zIndex = '9999';
	spinner.style.width = '50px';
	spinner.style.height = '50px';
	spinner.style.border = '5px solid rgba(0, 0, 0, 0.1)';
	spinner.style.borderTop = '5px solid #3498db';
	spinner.style.borderRadius = '50%';
	spinner.style.animation = 'spin 1s linear infinite';
	document.body.appendChild(spinner);
};

const hideLoadingSpinner = () => {
	const spinner = document.getElementById('global-loading-spinner');
	if (spinner) {
		spinner.remove();
	}
};

/**
 * Main function to set up the application.
 */
document.addEventListener('DOMContentLoaded', () => {
	const appContainer = document.getElementById('wp2-update_app');
	if (!appContainer) {
		console.error('[WP2 Update] Main application container #wp2-update-app not found.');
		return;
	}

	initUI(); // Initialize tooltips, tabs, etc.
	appContainer.addEventListener('click', handleAction);

	// Disable global actions if any package is updating
	const syncButton = document.querySelector('[data-action="sync-packages"]');
	const disconnectButton = document.querySelector('[data-action="disconnect"]');

	if (syncButton) syncButton.disabled = isAnyPackageUpdating.get();
	if (disconnectButton) disconnectButton.disabled = isAnyPackageUpdating.get();

	// Subscribe to state changes to dynamically update button states
	isAnyPackageUpdating.subscribe((isUpdating) => {
		if (syncButton) syncButton.disabled = isUpdating;
		if (disconnectButton) disconnectButton.disabled = isUpdating;
	});

	// Expose appState for timestamp updates in components.js
    window.wp2UpdateAppState = appState.get();
    appState.listen((state) => {
        window.wp2UpdateAppState = state;
        renderAppView(state.currentStage);
        renderPackageTable(state.packages, state.isLoading);
    });

	initializeApp();
});