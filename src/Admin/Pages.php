<?php

namespace WP2\Update\Admin;

use WP2\Update\Core\API\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\API\Service as GitHubService;
use WP2\Update\Core\Updates\PackageFinder;

/**
 * Renders the multi-stage admin page.
 */
class Pages
{
    private GitHubService $githubService;
    private PackageFinder $packages;
    private GitHubApp $githubApp;

    public function __construct(GitHubService $githubService, PackageFinder $packages, GitHubApp $githubApp)
    {
        $this->githubService = $githubService;
        $this->packages      = $packages;
        $this->githubApp     = $githubApp;
    }

    /**
     * Main render function - acts as a router for different UI stages.
     */
    public function render(): void
    {
        // This container will be controlled by JavaScript to show the correct stage.
        ?>
        <div class="container" id="wp2-update-app">
            <header style="margin-bottom: calc(var(--spacing-unit) * 6);">
                <h1>UI Workflow Showcase (v2)</h1>
                <p style="color: var(--color-text-subtle);">This document demonstrates the enhanced UI, addressing advanced features for a production-ready workflow.</p>
            </header>

            <?php
            $this->render_stage_1_pre_connection();
            $this->render_stage_2_credentials();
            $this->render_stage_2_5_sync();
            $this->render_stage_3_management();
            ?>
        </div>
        <?php
    }

    /**
     * Renders the HTML for the initial connection step.
     */
    private function render_stage_1_pre_connection(): void
    {
        ?>
        <section class="workflow-step" id="step-1-pre-connection">
            <h3>Step 1: Pre-Connection & Configuration</h3>
            <p>The initial state now includes configuration options and a clear permissions review before initiating the connection with GitHub.</p>
            <div class="card">
                <div class="card-header"><h2>Connect to GitHub <span class="app-status app-status--disconnected" style="float: right;">Disconnected</span></h2></div>
                <div class="card-body">
                    <p>Select your target environment and review the required permissions before creating the app manifest.</p>
                     <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row"><label>Install Target</label></th>
                                <td>
                                    <label><input type="radio" name="install_target" checked> Personal Account</label><br>
                                    <label><input type="radio" name="install_target"> GitHub Organization</label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label>App Variant</label></th>
                                <td>
                                    <label><input type="radio" name="app_variant" checked> Production</label><br>
                                    <label><input type="radio" name="app_variant"> Staging</label>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                     <hr style="border: 0; border-top: 1px solid var(--color-border); margin: 16px 0;">
                     <h4>Required Permissions</h4>
                     <ul class="permission-list">
                        <li><span>Repository Permissions: <code>Contents</code></span> <span>(read-only)</span></li>
                        <li><span>Repository Permissions: <code>Metadata</code></span> <span>(read-only)</span></li>
                        <li><span>Subscribed Events: <code>Release</code></span> <span>(for webhooks)</span></li>
                     </ul>
                </div>
                <div class="card-footer">
                    <button class="button">1. Create Manifest & Connect</button>
                </div>
            </div>
             <p><em>UX Note: Clicking "Create Manifest" would redirect the user to GitHub's OAuth flow and return them with a temporary code.</em></p>
        </section>
        <?php
    }

    /**
     * Renders the HTML for the credentials input step.
     */
    private function render_stage_2_credentials(): void
    {
        ?>
        <section class="workflow-step" id="step-2-credentials" hidden>
            <h3>Step 2: App Registration & Credentials</h3>
            <p>After returning from GitHub, users provide the final details. Inputs are now editable, and a webhook secret is captured. Secrets are masked.</p>
            <div class="card">
                 <div class="card-header"><h2>Enter Credentials</h2></div>
                 <div class="card-body">
                    <form>
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th scope="row"><label for="app_id">App ID</label></th>
                                    <td><input type="text" id="app_id" placeholder="e.g., 123456"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="installation_id">Installation ID</label></th>
                                    <td><input type="text" id="installation_id" placeholder="e.g., 7890123"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="private_key">Private Key (.pem)</label></th>
                                    <td><input type="file" id="private_key">
                                        <p class="description">Upload the .pem file downloaded from GitHub.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="webhook_secret">Webhook Secret</label></th>
                                    <td><input type="password" id="webhook_secret" placeholder="ghs_...">
                                        <p class="description">Secrets are encrypted and stored securely.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </form>
                </div>
                <div class="card-footer">
                    <button class="button-secondary">Cancel</button>
                    <button class="button">2. Save and Validate</button>
                </div>
            </div>
        </section>
        <?php
    }

     /**
     * Renders the HTML for the validation and sync step.
     */
    private function render_stage_2_5_sync(): void
    {
        ?>
        <section class="workflow-step" id="step-2-5-sync" hidden>
            <h3>Step 2.5: Validation & Initial Sync</h3>
            <p>After saving, the system performs a series of checks and the initial package sync, providing real-time feedback to the user.</p>
             <div class="card">
                <div class="card-header"><h2>Validating Connection...</h2></div>
                <div class="card-body">
                   <ul class="validation-checklist">
                       <li><span class="spinner"></span> <span>Validating private key format...</span></li>
                       <li><span class="dashicons"></span> <span>Minting JWT...</span></li>
                       <li><span class="dashicons"></span> <span>Checking App ID...</span></li>
                       <li><span class="dashicons"></span> <span>Verifying Installation ID...</span></li>
                       <li><span class="dashicons"></span> <span>Testing webhook delivery...</span></li>
                   </ul>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h2>Initial Sync</h2></div>
                <div class="card-body">
                    <p>Performing first-time inventory of accessible packages.</p>
                    <progress style="width: 100%;" value="0" max="100"></progress>
                    <p><em>(Initializing...)</em></p>
                    <h4>Loading Packages...</h4>
                    <table class="widefat">
                        <tbody>
                            <tr><td><div class="skeleton"></div></td><td><div class="skeleton" style="width: 60%"></div></td></tr>
                            <tr><td><div class="skeleton"></div></td><td><div class="skeleton" style="width: 70%"></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <?php
    }

    /**
     * Renders the HTML for the main package management dashboard.
     */
    private function render_stage_3_management(): void
    {
        ?>
        <section class="workflow-step" id="step-3-management" hidden>
            <h3>Step 3: Advanced Package Management</h3>
            <p>The main dashboard includes connection health, detailed package controls, and an audit trail, addressing all advanced management requirements.</p>
            <div class="card">
                 <div class="card-header"><h2>Connection Health <span class="app-status app-status--connected" style="float: right;">Connected</span></h2></div>
                 <div class="card-body" style="display: flex; justify-content: space-around; text-align: center;">
                    <div><strong>Last Sync</strong><br><span id="health-last-sync">...</span></div>
                    <div><strong>API Requests</strong><br><span id="health-api-requests">...</span></div>
                    <div><strong>Webhook Status</strong><br><span id="health-webhook-status">...</span></div>
                 </div>
                 <div class="card-footer">
                     <button class="button-secondary">Rotate Keys</button>
                     <button class="button-secondary">Refresh Permissions</button>
                 </div>
            </div>
            <div class="card">
                <div class="card-header"><h2>Managed Packages</h2></div>
                <div class="card-body">
                    <p>Select a version and click Update to change the installed package.</p>
                     <table class="widefat">
                        <thead>
                            <tr>
                                <th>Name / Channel</th>
                                <th>Installed</th>
                                <th>Available Version</th>
                                <th style="width: 150px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="packages-table">
                            <!-- JS will populate this -->
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <button class="button-destructive">Disconnect...</button>
                    <button class="button">Check for New Releases</button>
                </div>
            </div>
        </section>
        <?php
    }

    /**
     * Handles the file upload for the private key.
     */
    public function handle_file_upload(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'wp2-update'));
        }

        check_admin_referer('wp2_file_upload');

        if (!isset($_FILES['private_key']) || $_FILES['private_key']['error'] !== UPLOAD_ERR_OK) {
            wp_die(__('File upload failed. Please try again.', 'wp2-update'));
        }

        $file = $_FILES['private_key'];

        // Validate file extension
        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (strtolower($fileExtension) !== 'pem') {
            wp_die(__('Invalid file type. Only .pem files are allowed.', 'wp2-update'));
        }

        // Validate file size (e.g., max 1MB)
        $maxFileSize = 1 * 1024 * 1024; // 1MB
        if ($file['size'] > $maxFileSize) {
            wp_die(__('File is too large. Maximum size is 1MB.', 'wp2-update'));
        }

        // Process the file (e.g., move to a secure location)
        $uploadDir = wp_upload_dir();
        $destination = trailingslashit($uploadDir['basedir']) . basename($file['name']);

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            wp_die(__('Failed to save the uploaded file.', 'wp2-update'));
        }

        // Success message
        wp_redirect(add_query_arg('wp2_notice', 'file-upload-success', admin_url('admin.php?page=wp2-update')));
        exit;
    }
}
