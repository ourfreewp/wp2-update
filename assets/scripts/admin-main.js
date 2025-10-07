// ========================================================================
// Main application entry point for the robust UI workflow.
// ========================================================================

import '../styles/admin-main.scss'; // Import the main SCSS file for styles
import { appState } from './modules/state.js';
import { showToast } from './modules/ui.js';
// apiRequest will need to be updated to handle new endpoints
import { apiRequest } from './modules/api.js'; 

// Add logging to track the initialization of the application
console.log('Admin main script loaded. Initializing application.');

/**
 * Main render function to display the correct UI stage.
 */
function render() {
    const currentState = appState.get();
    console.log('Rendering application state:', currentState);

    // Hide all steps first
    document.querySelectorAll('.workflow-step').forEach(el => el.hidden = true);

    // Show the current step
    const currentStepEl = document.getElementById(`step-${currentState.currentStage.replace('_', '-')}`);
    if (currentStepEl) {
        console.log('Displaying step:', currentState.currentStage);
        currentStepEl.hidden = false;
    } else {
        console.warn('No element found for current stage:', currentState.currentStage);
    }

    if (currentState.currentStage === '2.5-sync') {
        renderValidationList();
    }
    if (currentState.currentStage === '3-management') {
        renderPackagesTable();
    }
}

/**
 * Renders the list of validation steps during the sync process.
 */
function renderValidationList() {
    console.log('Rendering validation list.');
    const { validation } = appState.get().connection;
    const listEl = document.querySelector('#step-2-5-sync .validation-checklist');
    if (!listEl) {
        console.warn('Validation checklist element not found.');
        return;
    }

    listEl.innerHTML = validation.steps.map(step => {
        console.log('Validation step:', step);
        let icon = '<span class="dashicons"></span>';
        if (step.status === 'pending') icon = '<span class="spinner"></span>';
        if (step.status === 'success') icon = '<span class="dashicons dashicons-yes-alt"></span>';
        if (step.status === 'error') icon = '<span class="dashicons dashicons-warning"></span>';

        return `<li>${icon} <span>${step.text}</span> ${step.detail ? `<small>${step.detail}</small>` : ''}</li>`;
    }).join('');
}

/**
 * Renders the table of managed packages with empty state handling.
 */
function renderPackagesTable() {
    console.log('Rendering packages table.');
    const { packages, isLoading } = appState.get();
    const tableBody = document.getElementById('packages-table');
    if (!tableBody) {
        console.warn('Packages table element not found.');
        return;
    }

    if (isLoading) {
        console.log('Packages are loading.');
        tableBody.innerHTML = '<tr><td colspan="4" class="loading-skeleton">Loading packages...</td></tr>';
        return;
    }

    if (packages.length === 0) {
        console.log('No packages found.');
        tableBody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: var(--color-text-subtle);">No managed packages found. Please ensure your plugins and themes have a valid Update URI header.</td></tr>';
        return;
    }

    console.log('Packages found:', packages);
    tableBody.innerHTML = packages.map(pkg => {
        const releaseOptions = pkg.releases.map(release => {
            const isSelected = release.version === pkg.installed ? 'selected' : '';
            return `<option value="${release.version}" ${isSelected}>${release.version}</option>`;
        }).join('');

        return `
            <tr data-package="${pkg.slug}">
                <td>
                    <strong>${pkg.name}</strong><br>
                    <small><a href="${pkg.changelogUrl}" target="_blank">Changelog</a> | <a href="#" class="remove-package" data-package="${pkg.slug}" style="color: var(--color-error)">Remove</a></small>
                </td>
                <td><strong>${pkg.installed}</strong></td>
                <td>
                    <select class="release-dropdown" data-package="${pkg.slug}">
                        ${releaseOptions}
                    </select>
                </td>
                <td>
                    <button class="update-button" data-package="${pkg.slug}" data-version="${pkg.installed}">Update</button>
                </td>
            </tr>
        `;
    }).join('');

    // Add event listeners for the dropdowns and buttons
    document.querySelectorAll('.release-dropdown').forEach(dropdown => {
        dropdown.addEventListener('change', (event) => {
            const packageSlug = event.target.dataset.package;
            const selectedVersion = event.target.value;
            const updateButton = document.querySelector(`.update-button[data-package="${packageSlug}"]`);
            if (updateButton) {
                updateButton.dataset.version = selectedVersion;
            }
        });
    });

    document.querySelectorAll('.update-button').forEach(button => {
        button.addEventListener('click', async (event) => {
            const packageSlug = event.target.dataset.package;
            const targetVersion = event.target.dataset.version;

            const packageRow = document.querySelector(`tr[data-package="${packageSlug}"]`);
            if (packageRow) {
                packageRow.classList.add('updating');
                const updateButton = packageRow.querySelector('.update-button');
                if (updateButton) {
                    updateButton.disabled = true;
                    updateButton.innerHTML = '<span class="spinner"></span> Updating...';
                }
            }

            try {
                await apiRequest('/wp2-update/v1/manage-packages', {
                    method: 'POST',
                    body: { action: 'update', package: packageSlug, version: targetVersion },
                });
                showToast(`Updated ${packageSlug} to version ${targetVersion}.`, 'success');
                syncPackages(); // Refresh the package list after update
            } catch (error) {
                showToast(`Failed to update ${packageSlug}. Please try again.`, 'error');
                if (packageRow) {
                    packageRow.classList.remove('updating');
                    const updateButton = packageRow.querySelector('.update-button');
                    if (updateButton) {
                        updateButton.disabled = false;
                        updateButton.innerHTML = 'Update';
                    }
                }
            }
        });
    });
}

/**
 * Initializes all event listeners for the entire application.
 */
function initEventListeners() {
    // --- Step 1 Listeners ---
    const connectButton = document.querySelector('#step-1-pre-connection .button');
    connectButton.addEventListener('click', () => {
        showToast('Redirecting to GitHub...', 'success');
        appState.set({ ...appState.get(), currentStage: '2-credentials' });
        render();
    });

    // --- Step 2 Listeners ---
    const saveButton = document.querySelector('#step-2-credentials .button');
    saveButton.addEventListener('click', () => {
        appState.set({ ...appState.get(), currentStage: '2.5-sync' });
        render();
        runValidationAndSync(); 
    });

    // --- Step 3 Listeners ---
    const disconnectButton = document.querySelector('#step-3-management .button-destructive');
    disconnectButton.addEventListener('click', async () => {
        try {
            await apiRequest('/wp2-update/v1/disconnect', { method: 'POST' });
            showToast('Disconnected successfully.', 'success');
            appState.set({ ...appState.get(), currentStage: 'pre-connection' });
            render();
        } catch (error) {
            showToast(`Failed to disconnect: ${error.message}`, 'error');
        }
    });

    const checkUpdatesButton = document.querySelector('#step-3-management .button');
    checkUpdatesButton.addEventListener('click', async () => {
        try {
            const syncResult = await apiRequest('/wp2-update/v1/sync-packages', { method: 'POST' });
            appState.set({ ...appState.get(), packages: syncResult.packages });
            renderPackagesTable();
            showToast('Checked for new releases.', 'success');
        } catch (error) {
            showToast(`Failed to check for updates: ${error.message}`, 'error');
        }
    });

    // Add event listeners for "Disconnect," "Check for New Releases," and "Update" buttons
    document.querySelector('#disconnect-button').addEventListener('click', async () => {
        try {
            await apiRequest('/wp2-update/v1/manage-packages', { method: 'POST', body: { action: 'disconnect' } });
            showToast('Disconnected successfully.', 'success');
            appState.set({ ...appState.get(), currentStage: '1-pre-connection' });
            render();
        } catch (error) {
            showToast(`Failed to disconnect: ${error.message}`, 'error');
        }
    });

    document.querySelector('#check-releases-button').addEventListener('click', async () => {
        try {
            appState.set({ ...appState.get(), isLoading: true });
            const syncResult = await apiRequest('/wp2-update/v1/sync-packages', { method: 'POST' });
            appState.set({ ...appState.get(), packages: syncResult.packages, isLoading: false });
            renderPackagesTable();
            showToast('Checked for new releases.', 'success');
        } catch (error) {
            showToast(`Failed to check for releases: ${error.message}`, 'error');
        }
    });

    document.querySelectorAll('#packages-table .update-button').forEach(button => {
        button.addEventListener('click', async (event) => {
            const packageName = event.target.dataset.package;
            const version = event.target.dataset.version;

            try {
                await apiRequest('/wp2-update/v1/manage-packages', {
                    method: 'POST',
                    body: { action: 'update', package: packageName, version },
                });
                showToast(`Updated ${packageName} to version ${version}.`, 'success');
                const syncResult = await apiRequest('/wp2-update/v1/sync-packages', { method: 'POST' });
                appState.set({ ...appState.get(), packages: syncResult.packages });
                renderPackagesTable();
            } catch (error) {
                showToast(`Failed to update ${packageName}: ${error.message}`, 'error');
            }
        });
    });

    // Utility function to show a loading indicator
    function showLoadingIndicator(button) {
        const spinner = document.createElement('span');
        spinner.className = 'spinner is-active';
        button.disabled = true;
        button.appendChild(spinner);
    }

    // Utility function to hide a loading indicator
    function hideLoadingIndicator(button) {
        const spinner = button.querySelector('.spinner');
        if (spinner) {
            spinner.remove();
        }
        button.disabled = false;
    }

    // Example usage for "Run Update Check"
    document.querySelector('#run-update-check').addEventListener('click', async (event) => {
        const button = event.currentTarget;
        showLoadingIndicator(button);

        try {
            const response = await apiRequest('/wp2-update/v1/run-update-check', 'POST');
            showToast(response.message, response.success ? 'success' : 'error');
        } catch (error) {
            showToast('An error occurred while running the update check.', 'error');
        } finally {
            hideLoadingIndicator(button);
        }
    });

    // Example usage for "Test Connection"
    document.querySelector('#test-connection').addEventListener('click', async (event) => {
        const button = event.currentTarget;
        showLoadingIndicator(button);

        try {
            const response = await apiRequest('/wp2-update/v1/test-connection', 'POST');
            showToast(response.message, response.success ? 'success' : 'error');
        } catch (error) {
            showToast('An error occurred while testing the connection.', 'error');
        } finally {
            hideLoadingIndicator(button);
        }
    });
}

/**
 * Updates the sync flow to call the /sync-packages endpoint after validation.
 */
async function runValidationAndSync() {
    try {
        // Step 1: Validate connection
        const validationResult = await apiRequest('/wp2-update/v1/validate-connection', { method: 'GET' });
        appState.set({
            ...appState.get(),
            connection: { validation: validationResult },
        });
        renderValidationList();

        if (validationResult.status !== 'success') {
            showToast('Validation failed. Please check your credentials.', 'error');
            return;
        }

        // Step 2: Sync packages
        appState.set({ ...appState.get(), isLoading: true });
        const syncResult = await apiRequest('/wp2-update/v1/sync-packages', { method: 'POST' });
        appState.set({
            ...appState.get(),
            packages: syncResult.packages,
            isLoading: false,
        });
        renderPackagesTable();
        showToast('Synchronization complete.', 'success');
    } catch (error) {
        showToast(`An error occurred: ${error.message}`, 'error');
    }
}


/**
 * Validates the GitHub App connection.
 */
async function validateConnection() {
    try {
        const response = await apiRequest('/wp2-update/v1/validate-connection', {
            method: 'POST',
        });
        appState.update(state => {
            state.connection.validation = response;
        });
        renderValidationList();
    } catch (error) {
        showToast('Validation failed. Please try again.', 'error');
    }
}

/**
 * Synchronizes the list of packages.
 */
async function syncPackages() {
    try {
        appState.update(state => {
            state.isLoading = true;
        });
        const response = await apiRequest('/wp2-update/v1/sync-packages', {
            method: 'POST',
        });
        appState.update(state => {
            state.packages = response.repositories;
            state.isLoading = false;
        });
        renderPackagesTable();
    } catch (error) {
        appState.update(state => {
            state.isLoading = false;
        });
        showToast('Failed to sync packages. Please try again.', 'error');
    }
}

/**
 * Updates a specific package.
 */
async function updatePackage(packageSlug, targetVersion) {
    try {
        await apiRequest('/wp2-update/v1/manage-packages', {
            method: 'POST',
            body: { action: 'update', package: packageSlug, version: targetVersion },
        });
        showToast(`Updated ${packageSlug} to version ${targetVersion}.`, 'success');
        syncPackages(); // Refresh the package list after update
    } catch (error) {
        showToast(`Failed to update ${packageSlug}. Please try again.`, 'error');
    }
}

// Replace simulated API calls with actual ones
validateConnection();
syncPackages();

/**
 * Main application initializer.
 */
function initApp() {
    // Subscribe to state changes to automatically re-render the UI
    appState.listen((newState, oldState) => {
        if (newState.currentStage !== oldState.currentStage) {
            render();
        }
    });
    
    initEventListeners();
    render(); // Initial render
}

document.addEventListener('DOMContentLoaded', initApp);

/**
 * Creates a reusable confirmation modal.
 */
function createConfirmationModal() {
    const modal = document.createElement('div');
    modal.id = 'confirmation-modal';
    modal.innerHTML = `
        <div class="modal-overlay">
            <div class="modal-content">
                <h3 id="modal-title">Confirm Action</h3>
                <p id="modal-message">Are you sure?</p>
                <div class="modal-actions">
                    <button id="modal-confirm" class="button button-primary">Confirm</button>
                    <button id="modal-cancel" class="button">Cancel</button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    const showModal = (title, message, onConfirm) => {
        document.getElementById('modal-title').textContent = title;
        document.getElementById('modal-message').textContent = message;
        modal.style.display = 'block';

        const confirmButton = document.getElementById('modal-confirm');
        const cancelButton = document.getElementById('modal-cancel');

        const closeModal = () => {
            modal.style.display = 'none';
            confirmButton.removeEventListener('click', onConfirm);
            cancelButton.removeEventListener('click', closeModal);
        };

        confirmButton.addEventListener('click', () => {
            onConfirm();
            closeModal();
        });
        cancelButton.addEventListener('click', closeModal);
    };

    // Replace native confirm dialogs
    document.querySelectorAll('.remove-package').forEach(button => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const packageSlug = event.target.dataset.package;

            showModal(
                'Remove Package',
                `Are you sure you want to remove the package: ${packageSlug}? This action cannot be undone.`,
                () => {
                    apiRequest('/wp2-update/v1/manage-packages', {
                        method: 'POST',
                        body: { action: 'remove', package: packageSlug },
                    })
                        .then(() => {
                            showToast(`Package ${packageSlug} removed successfully.`, 'success');
                            syncPackages();
                        })
                        .catch(() => {
                            showToast(`Failed to remove package ${packageSlug}. Please try again.`, 'error');
                        });
                }
            );
        });
    });

    document.querySelectorAll('.disconnect-button').forEach(button => {
        button.addEventListener('click', (event) => {
            event.preventDefault();

            showModal(
                'Disconnect',
                'Are you sure you want to disconnect? This will remove all associated data.',
                () => {
                    apiRequest('/wp2-update/v1/disconnect', {
                        method: 'POST',
                    })
                        .then(() => {
                            showToast('Disconnected successfully.', 'success');
                            location.reload();
                        })
                        .catch(() => {
                            showToast('Failed to disconnect. Please try again.', 'error');
                        });
                }
            );
        });
    });
}

// Call the function to create the confirmation modal
createConfirmationModal();

// Ensure the script runs after the DOM is fully loaded
document.addEventListener('DOMContentLoaded', () => {
    console.log('Admin main script initialized.');

    // Check if the main container exists
    const appContainer = document.getElementById('wp2-update-app');
    if (!appContainer) {
        console.error('Main container #wp2-update-app not found.');
        return;
    }

    console.log('Main container found. Initializing application.');

    // Call the render function to initialize the UI
    render();
});
