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
        console.error('Failed to fetch connection status:', error);
        connectionState.set({ connected: false, message: 'Error fetching connection status.', isLoading: false });
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
 * Initializes GitHub App creation button.
 */
const initGithubAppButton = () => {
    fetch("/wp-json/wp2-update/v1/settings")
        .then(response => response.json())
        .then(settings => {
            const createGithubAppButton = document.getElementById("create-github-app");

            if (createGithubAppButton) {
                createGithubAppButton.addEventListener("click", function() {
                    // Validate settings.root
                    if (!settings.root || !settings.root.endsWith('/')) {
                        console.error("Invalid REST API root URL:", settings.root);
                        return;
                    }

                    fetch(settings.root + "wp2-update/v1/create-github-app", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-WP-Nonce": settings.nonce
                        },
                        body: JSON.stringify({
                            post_id: createGithubAppButton.dataset.postId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const appConfigContainer = document.getElementById("app-config-container");
                            if (appConfigContainer) {
                                appConfigContainer.innerHTML = `
                                    <p><strong>Pending App Name:</strong> ${data.app_name}</p>
                                    <p><strong>Callback URL:</strong> ${data.callback_url}</p>
                                    <p><strong>Webhook URL:</strong> ${data.webhook_url}</p>
                                    <a href="${data.github_url}" target="_blank">Open GitHub App Configuration</a>
                                `;
                            }
                        } else {
                            alert(data.message || "Failed to create GitHub App.");
                        }
                    });
                });
            }
        })
        .catch(error => console.error("Failed to fetch settings:", error));
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

    // Initialize GitHub App button
    initGithubAppButton();
};

// Run the application once the DOM is fully loaded.
document.addEventListener('DOMContentLoaded', () => {
    initApp();

    if (!import.meta.env.DEV) {
        return;
    }

    const postIdInput = document.querySelector('#post_ID');
    if (!postIdInput) {
        console.warn('Post ID not found on the edit screen. Debug tools require a valid post context.');
        return;
    }

    const postId = postIdInput.value;
    const debugButton = document.createElement('button');
    debugButton.textContent = 'Run App Debug';
    debugButton.style.margin = '10px';
    debugButton.classList.add('button', 'button-primary');

    debugButton.addEventListener('click', async () => {
        try {
            const response = await fetch(`/wp-json/wp/v2/posts/${postId}`);
            if (!response.ok) {
                throw new Error(`Failed to fetch post meta. HTTP status: ${response.status}`);
            }

            const postData = await response.json();
            const appId = postData.meta?._wp2_app_id;
            const installationId = postData.meta?._wp2_installation_id;

            if (!appId || !installationId) {
                alert('App ID or Installation ID is missing in the post meta.');
                return;
            }

            const debugResponse = await fetch('/wp-json/wp2-update/v1/debug-app', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    app_id: appId,
                    installation_id: installationId,
                }),
            });

            if (!debugResponse.ok) {
                throw new Error(`Debug request failed. HTTP status: ${debugResponse.status}`);
            }

            const debugResult = await debugResponse.json();

            if (debugResult.success) {
                alert('Debug action completed successfully. Check the admin notice for details.');
            } else {
                alert(`Debug action failed: ${debugResult.data}`);
            }
        } catch (error) {
            console.error('Error during debug action:', error);
            alert('An error occurred while running the debug action. Check the console for details.');
        }
    });

    const submitBox = document.querySelector('#submitdiv .inside');
    if (submitBox) {
        submitBox.appendChild(debugButton);
    } else {
        console.warn('Submit box not found. Unable to add debug button.');
    }
});

//# sourceMappingURL=app.js.map
