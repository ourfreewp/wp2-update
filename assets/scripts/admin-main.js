// ========================================================================
// Description: The main application entry point.
// ========================================================================

// Import local modules
import { connectionState } from './modules/state.js';
import { showToast, initTooltips, initTabs } from './modules/ui.js';
import { apiRequest } from './modules/api.js';

// Import CSS - Vite will extract this into a separate .css file during build
/**
 * Renders the connection status block by subscribing to the global state.
 */
const renderConnectionStatus = () => {
    const statusBlock = document.getElementById('wp2-connection-status');
    if (!statusBlock) return;

    // Listen for changes in the connection state and update the DOM.
    connectionState.listen(state => {
        const noticeClass = state.connected ? 'notice-success' : (state.connected === false ? 'notice-error' : 'notice-info');
        // Safely update the status block content.
        statusBlock.textContent = state.message;
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
        // Emit an event to notify other parts of the app about the updated state
        document.dispatchEvent(new CustomEvent('connectionStatusUpdated', { detail: data }));
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

            // Add spinner for visual feedback
            const spinner = document.createElement('span');
            spinner.className = 'spinner';
            spinner.style.marginLeft = '8px';
            testConnectionBtn.appendChild(spinner);

            try {
                const appSlug = document.getElementById('wp2-app-slug').value;
                if (!appSlug) {
                    showToast('App slug is required.', 'error');
                    return;
                }

                const data = await apiRequest('/wp2-update/v1/test-connection', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpApiSettings.nonce,
                    },
                    body: JSON.stringify({ app_slug: appSlug }),
                });
                showToast(data.message || 'Connection test successful!', data.success ? 'success' : 'error');
                await updateConnectionStatus();
            } catch (error) {
                console.error('Connection test failed:', error);
                showToast('An error occurred while testing the connection. Please check your network and try again.', 'error');
            } finally {
                if (loader) loader.style.display = 'none';
                testConnectionBtn.disabled = false;
                // Remove spinner after operation
                if (spinner.parentNode) {
                    spinner.parentNode.removeChild(spinner);
                }
            }
        });
    }

    if (disconnectBtn) {
        disconnectBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            if (loader) loader.style.display = 'inline-block';
            disconnectBtn.disabled = true;

            try {
                const data = await apiRequest('/wp2-update/v1/disconnect', {
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': wpApiSettings.nonce,
                    },
                });
                showToast(data.message || 'Disconnected successfully.', 'success');
                await updateConnectionStatus();
            } catch (error) {
                console.error('Disconnection failed:', error);
                showToast('An error occurred while disconnecting. Please try again.', 'error');
            } finally {
                if (loader) loader.style.display = 'none';
                disconnectBtn.disabled = false;
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

    const manualSyncButton = document.querySelector('#wp2-run-manual-sync');
    if (manualSyncButton) {
        manualSyncButton.addEventListener('click', (event) => {
            event.preventDefault();
            const targetUrl = manualSyncButton.dataset.url;
            if (!targetUrl) {
                return;
            }

            console.log('Manual Sync Button Clicked');
            console.log('Target URL:', targetUrl);

            const runningLabel = manualSyncButton.dataset.runningLabel || 'Running…';
            manualSyncButton.disabled = true;
            manualSyncButton.textContent = runningLabel;

            // Redirect to the admin-post.php endpoint for synchronous processing
            window.location.href = targetUrl;
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
                                // Safely update the app configuration container.
                                appConfigContainer.textContent = `
                                    Pending App Name: ${data.app_name}\n
                                    Callback URL: ${data.callback_url}\n
                                    Webhook URL: ${data.webhook_url}\n
                                    Open GitHub App Configuration: ${data.github_url}`;
                            }
                        } else {
                            showToast(data.message || "Failed to create GitHub App.", 'error');
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
                showToast('App ID or Installation ID is missing in the post meta.', 'warning');
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
                showToast('Debug action completed successfully. Check the admin notice for details.', 'success');
            } else {
                showToast(`Debug action failed: ${debugResult.data}`, 'error');
            }
        } catch (error) {
            console.error('Error during debug action:', error);
            showToast('An error occurred while running the debug action. Check the console for details.', 'error');
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
