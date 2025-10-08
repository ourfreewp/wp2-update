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
     * Main render function - determines the initial UI stage based on connection status.
     */
    public function render(): void
    {
        // This is the only HTML needed. Your JS app will handle the rest.
        echo '<div id="wp2-update-app" class="wrap"></div>';
    }

    /**
     * Renders the HTML for the initial connection step.
     */
    private function render_stage_1_pre_connection(string $initial_stage): void
    {
        ?>
        <section class="workflow-step" id="step-1-pre-connection" <?php echo ($initial_stage !== 'step-1-pre-connection') ? 'hidden' : ''; ?>>
            <h3>Step 1: Pre-Connection & Configuration</h3>
            <p class="description">The initial state now includes configuration options and a clear permissions review before initiating the connection with GitHub.</p>
            <div class="card">
                <div class="card-header"><h2>Connect to GitHub <span class="app-status app-status--disconnected" style="float: right;">Disconnected</span></h2></div>
                <div class="card-body">
                    <p class="description">Select your target environment and review the required permissions before creating the app manifest.</p>
                     <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="install_target">Install Target</label></th>
                                <td>
                                    <label><input type="radio" name="install_target" id="install_target_personal" checked> Personal Account</label><br>
                                    <label><input type="radio" name="install_target" id="install_target_org"> GitHub Organization</label>
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
                    <button class="button" data-action="connect">1. Create Manifest & Connect</button>
                </div>
            </div>
        </section>
        <?php
    }

    /**
     * Renders the HTML for the credentials input step.
     */
    private function render_stage_2_credentials(string $initial_stage): void
    {
        ?>
        <section class="workflow-step" id="step-2-credentials" <?php echo ($initial_stage !== 'step-2-credentials') ? 'hidden' : ''; ?>>
            <h3>Step 2: App Registration & Credentials</h3>
            <p>After returning from GitHub, provide the final details. Secrets are masked.</p>
            <div class="card">
                 <div class="card-header"><h2>Enter Credentials</h2></div>
                 <div class="card-body">
                    <form id="credentials-form">
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th scope="row"><label for="app_id">App ID</label></th>
                                    <td><input type="text" id="app_id" name="app_id" placeholder="e.g., 123456" required></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="installation_id">Installation ID</label></th>
                                    <td><input type="text" id="installation_id" name="installation_id" placeholder="e.g., 7890123" required></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="private_key">Private Key (.pem)</label></th>
                                    <td><input type="file" id="private_key" name="private_key" accept=".pem" required>
                                        <p class="description">Upload the .pem file downloaded from GitHub.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="webhook_secret">Webhook Secret</label></th>
                                    <td><input type="password" id="webhook_secret" name="webhook_secret" placeholder="ghs_..." required>
                                        <p class="description">Secrets are encrypted and stored securely.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </form>
                </div>
                <div class="card-footer">
                    <button class="button-secondary" data-action="cancel">Cancel</button>
                    <button class="button" data-action="save-validate">2. Save and Validate</button>
                </div>
            </div>
        </section>
        <?php
    }

     /**
     * Renders the HTML for the validation and sync step.
     */
    private function render_stage_2_5_sync(string $initial_stage): void
    {
        ?>
        <section class="workflow-step" id="step-2-5-sync" <?php echo ($initial_stage !== 'step-2-5-sync') ? 'hidden' : ''; ?>>
            <h3>Step 2.5: Validation & Initial Sync</h3>
            <p>The system is performing checks and the initial package sync, providing real-time feedback.</p>
             <div class="card">
                <div class="card-header"><h2>Validating Connection...</h2></div>
                <div class="card-body">
                   <ul class="validation-checklist" aria-live="polite">
                       </ul>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h2>Initial Sync</h2></div>
                <div class="card-body">
                    <p>Performing first-time inventory of accessible packages.</p>
                    <progress id="sync-progress" style="width: 100%;" value="0" max="100"></progress>
                    <p id="sync-status"><em>(Initializing...)</em></p>
                </div>
            </div>
        </section>
        <?php
    }

    /**
     * Renders the HTML for the main package management dashboard.
     */
    private function render_stage_3_management(string $initial_stage): void
    {
        ?>
        <section class="workflow-step" id="step-3-management" <?php echo ($initial_stage !== 'step-3-management') ? 'hidden' : ''; ?>>
            <h3>Step 3: Advanced Package Management</h3>
            <p>The main dashboard for managing your connected GitHub packages.</p>
            <div class="card">
                 <div class="card-header"><h2>Connection Health <span class="app-status app-status--connected" style="float: right;">Connected</span></h2></div>
                 <div class="card-body" style="display: flex; justify-content: space-around; text-align: center;">
                    <div><strong>Last Sync</strong><br><span id="health-last-sync">...</span></div>
                    <div><strong>API Requests</strong><br><span id="health-api-requests">...</span></div>
                    <div><strong>Webhook Status</strong><br><span id="health-webhook-status">...</span></div>
                 </div>
            </div>
            <div class="card">
                <div class="card-header"><h2>Managed Packages</h2></div>
                <div class="card-body">
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
                            </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <button class="button-destructive" data-action="disconnect">Disconnect...</button>
                    <button class="button" data-action="check-releases">Check for New Releases</button>
                </div>
            </div>
        </section>
        <?php
    }

    /**
     * Renders the HTML for the GitHub callback handling.
     */
    public function render_callback(): void
    {
        // Enqueue the GitHub callback script
        wp_enqueue_script(
            'wp2-update-github-callback',
            WP2_UPDATE_PLUGIN_URL . 'assets/scripts/github-callback.js',
            [],
            '1.0.0',
            true
        );

        // Output a minimal HTML structure
        echo '<div id="wp2-update-github-callback"></div>';
    }
}