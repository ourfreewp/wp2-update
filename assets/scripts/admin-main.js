/**
 * @file src-js/admin-main.js
 * @description Main script for the WP2 Update admin UI. (v3 - Complete Implementation)
 */

import { api_request } from './modules/api/index.js';
import { app_state } from './modules/state/store.js';
import { toast } from './modules/ui/toast.js';
import { init_ui } from './modules/ui/init.js';
import { confirm_modal } from './modules/ui/modal.js';
import { render_view } from './modules/components/views.js';
import { render_package_table } from './modules/components/table.js';
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
		const { url } = await api_request('wp2-update/v1/github/connect-url', { method: 'GET' });
		if (url) {
			window.location.href = url; // User is sent to GitHub
		}
	},

	/**
	 * ACTION: Disconnects the app.
	 * Tells the backend to delete the stored credentials and resets the UI.
	 */
	disconnect: async () => {
		confirm_modal(
			__('Are you sure you want to disconnect? This will remove your credentials.', 'wp2-update'),
			async () => {
				await api_request('wp2-update/v1/github/disconnect');
				app_state.set({ ...app_state.get(), currentStage: 'pre-connection', packages: [] });
				toast(__('Disconnected successfully.', 'wp2-update'));
			},
			() => {
				toast('Disconnect action canceled.', 'error');
			}
		);
	},

	/**
	 * ACTION: Fetches all repositories and their releases.
	 * This is a "sync" operation that populates the main management table.
	 */
	'sync-packages': debounce(async () => {
		showLoadingSpinner();
		app_state.setKey('isLoading', true);
		const syncStatusElement = document.querySelector('#sync-status');

		try {
			// This single endpoint is assumed to fetch repos and their latest releases.
			const result = await api_request('wp2-update/v1/sync-packages', { method: 'GET' });
			app_state.set({
				...app_state.get(),
				packages: result.repositories || [],
				isLoading: false,
				connection: {
					...app_state.get().connection,
					health: {
						...app_state.get().connection.health,
						lastSync: new Date().toISOString(),
					},
				},
				error: null, // Clear any previous error
			});
			if (syncStatusElement) {
				syncStatusElement.textContent = 'Last sync succeeded at ' + new Date().toLocaleString();
				syncStatusElement.className = 'sync-status success';
			}
			toast('Successfully synced with GitHub.');
		} catch (error) {
			console.error('[Sync Failed]', error);
			app_state.set({
				...app_state.get(),
				isLoading: false,
				error: error.message, // Set the error state
			});
			app_state.setKey('syncError', error.message || 'An unknown error occurred during sync.');
			if (syncStatusElement) {
				syncStatusElement.textContent = 'Last sync failed at ' + new Date().toLocaleString();
				syncStatusElement.className = 'sync-status error';
			}
			toast('Sync failed: ' + (error.message || 'Unknown error'), 'error');
		} finally {
			app_state.setKey('isLoading', false);
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
		const originalPackages = app_state.get().packages;

		// Optimistically set the package's status to 'updating'
		const updatedPackages = originalPackages.map(p =>
			p.repo === repo ? { ...p, status: 'updating' } : p
		);
		app_state.setKey('packages', updatedPackages);

		try {
			await api_request('wp2-update/v1/manage-packages', {
				body: { action: 'update', repo_slug: repo, version, type: button.dataset.packageType },
			});

			toast(`${repo} update to ${version} initiated.`);

			// Update only the relevant package instead of refreshing all data
			const refreshedPackage = await api_request(`wp2-update/v1/package/${repo}`, { method: 'GET' });
			const newPackages = app_state.get().packages.map(p =>
				p.repo === repo ? { ...p, ...refreshedPackage } : p
			);
			app_state.setKey('packages', newPackages);
		} catch (error) {
			console.error(`[Update Failed: ${repo}]`, error);
			toast(`Failed to update ${repo}: ${error.message}`, 'error');

			// Revert the package's status to its original state
			const updatedPackages = originalPackages.map(p =>
				p.repo === repo ? { ...p, status: 'error', errorMessage: error.message } : p
			);
			app_state.setKey('packages', updatedPackages);
		}
	},

	/**
	 * ACTION: Performs a health check on the installation.
	 * This action calls the backend health check endpoint and shows the results in the UI.
	 */
	'health-check': async () => {
		showLoadingSpinner();
		try {
			const response = await api_request('wp2-update/v1/health-status', { method: 'GET' });
			console.log('Health Check Results:', response);
			toast('Health check completed successfully.', 'success');
		} catch (error) {
			console.error('Health Check Failed:', error);
			toast('Health check failed. See console for details.', 'error');
		} finally {
			hideLoadingSpinner();
		}
	},

	/**
	 * ACTION: Configure GitHub App Manifest.
	 * Displays a form to configure the App Name and optional Organization.
	 */
	'configure-manifest': async () => {
		app_state.setKey('currentStage', 'configure-manifest');
		render_view('configure-manifest');
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
			toast(error.message, 'error');
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
	const appContainer = document.getElementById('wp2-update-app');
	if (!appContainer) return;

	app_state.setKey('isLoading', true);

	try {
		const status = await api_request('wp2-update/v1/github/connection-status', { method: 'GET' });
		if (status.connected) {
			app_state.setKey('currentStage', 'managing');
			await actions['sync-packages'](); // Sync packages automatically if connected
		} else {
			app_state.setKey('currentStage', 'pre-connection');
		}
	} catch (error) {
		app_state.setKey('currentStage', 'pre-connection');
		toast(error.message, 'error');
	} finally {
		app_state.setKey('isLoading', false);
	}
};

/**
 * Derived state to track if any package is updating
 */
const isAnyPackageUpdating = {
	get: () => app_state.get().packages.some(pkg => pkg.status === 'updating'),
	subscribe: (callback) => {
		app_state.listen((state) => {
			callback(state.packages.some(pkg => pkg.status === 'updating'));
		});
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
	// Ensure the main application container exists before initializing.
	const appContainer = document.getElementById('wp2-update-app');
	if (!appContainer) {
		console.error('[WP2 Update] Main application container #wp2-update-app not found.');
		return;
	}

	init_ui(); // Initialize tooltips, tabs, etc.
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
    window.wp2UpdateAppState = app_state.get();
    app_state.listen((state) => {
        window.wp2UpdateAppState = state;
        render_view(state.currentStage);
        render_package_table(state.packages, state.isLoading);
    });

	initializeApp();
});