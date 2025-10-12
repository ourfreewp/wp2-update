import { escapeHtml } from '../../utils.js';
import { renderAppsPanel } from '../components/renderAppsPanel.js';
import { renderPackagesPanel } from '../components/renderPackagesPanel.js';
import { api_request } from '../../api.js';
import { renderActionButton } from '../components/renderActionButton.js';
import { toast } from '../../ui/toast.js';

const checkRateLimit = async () => {
    try {
        const rateLimit = await api_request('connection-status', { method: 'GET' });
        const remaining = rateLimit?.rate_limit?.remaining || 0;
        const resetTime = rateLimit?.rate_limit?.reset || Date.now();

        if (remaining === 0) {
            document.querySelectorAll('[data-wp2-action]').forEach(button => {
                button.disabled = true;
            });
            const resetDate = new Date(resetTime * 1000);
            toast(`GitHub API rate limit exceeded. Try again after ${resetDate.toLocaleTimeString()}.`, 'error');
        } else if (remaining < 10) {
            console.warn(`Warning: GitHub API rate limit is low (${remaining} requests remaining).`);
        }
    } catch (error) {
        console.error('Failed to fetch rate limit status:', error);
    }
};

const renderWelcomeView = () => {
	return `
		<div class="wp2-welcome-view">
			<h1>Welcome to WP2 Update</h1>
			<p>Get started by connecting a GitHub App to manage your plugins and themes.</p>
			<ul>
				<li>Connect your GitHub App to sync repositories.</li>
				<li>Manage updates for plugins and themes directly from your dashboard.</li>
				<li>Stay secure with action-specific nonces and detailed error handling.</li>
			</ul>
			<button type="button" class="wp2-btn wp2-btn--primary" data-wp2-action="open-wizard">Get Started</button>
		</div>
	`;
};

const renderAssignModalContent = () => `
    <div class="wp2-modal-header">
        <h2>${escapeHtml('Assign GitHub App')}</h2>
        <p id="wp2-assign-description">${escapeHtml('Choose a GitHub App to manage this package.')}</p>
    </div>
    <form id="wp2-assign-form" class="wp2-form">
        <label class="wp2-form-label" for="wp2-assign-app-select">${escapeHtml('Available Apps')}</label>
        <select id="wp2-assign-app-select" class="wp2-input"></select>
        <div class="wp2-modal-actions">
            <button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-close="assign">${escapeHtml('Cancel')}</button>
            <button type="submit" class="wp2-btn wp2-btn--primary">${escapeHtml('Assign App')}</button>
        </div>
    </form>
`;

const renderPackageDetailsModalContent = () => `
    <div class="wp2-modal-header">
        <h2 id="wp2-package-details-title">${escapeHtml('Package details')}</h2>
    </div>
    <div id="wp2-package-details-content" class="wp2-detail-grid"></div>
    <div id="wp2-package-sync-log" class="wp2-sync-log" hidden></div>
    <div class="wp2-modal-actions">
        <button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-close="package">${escapeHtml('Close')}</button>
    </div>
`;

const renderAppDetailsModalContent = () => `
    <div class="wp2-modal-header">
        <h2 id="wp2-app-details-title">${escapeHtml('App details')}</h2>
    </div>
    <div id="wp2-app-details-content" class="wp2-detail-grid"></div>
    <div id="wp2-app-managed-packages" class="wp2-managed-list"></div>
    <div class="wp2-modal-actions">
        <button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-close="app">${escapeHtml('Close')}</button>
    </div>
`;

export const renderWizardModalContent = (uniqueId = '') => `
    <div id="wp2-wizard-step-configure-${uniqueId}" class="wp2-wizard-step is-active">
        <header class="wp2-modal-header">
            <h2>${escapeHtml('Add GitHub App')}</h2>
            <p>${escapeHtml('Generate a manifest and connect a new GitHub App for this site.')}</p>
        </header>
        <form id="wp2-wizard-form-${uniqueId}" class="wp2-form">
            <div class="wp2-form-grid">
                <div class="wp2-form-field">
                    <label class="wp2-form-label" for="wp2-wizard-app-name-${uniqueId}">${escapeHtml('App name')}</label>
                    <input id="wp2-wizard-app-name-${uniqueId}" name="app_name" class="wp2-input" type="text" required />
                </div>
                <div class="wp2-form-field">
                    <label class="wp2-form-label" for="wp2-wizard-encryption-key-${uniqueId}">${escapeHtml('Encryption key')}</label>
                    <input id="wp2-wizard-encryption-key-${uniqueId}" name="encryption_key" class="wp2-input" type="password" minlength="16" required />
                    <p class="wp2-field-help">${escapeHtml('Use at least 16 characters. Store this securely for future connections.')}</p>
                </div>
            </div>
            <div class="wp2-form-field">
                <label class="wp2-form-label">${escapeHtml('Account type')}</label>
                <div class="wp2-pill-group" id="wp2-wizard-account-${uniqueId}">
                    <label class="wp2-pill">
                        <input type="radio" name="account_type" value="user" checked />
                        <span>${escapeHtml('Personal')}</span>
                    </label>
                    <label class="wp2-pill">
                        <input type="radio" name="account_type" value="organization" />
                        <span>${escapeHtml('Organization')}</span>
                    </label>
                </div>
            </div>
            <div class="wp2-form-field" id="wp2-wizard-organization-field-${uniqueId}" hidden>
                <label class="wp2-form-label" for="wp2-wizard-organization-${uniqueId}">${escapeHtml('Organization slug')}</label>
                <input id="wp2-wizard-organization-${uniqueId}" name="organization" class="wp2-input" type="text" placeholder="your-org-name" />
            </div>
            <div class="wp2-modal-actions">
                <button type="submit" class="wp2-btn wp2-btn--primary">${escapeHtml('Generate manifest')}</button>
            </div>
        </form>
    </div>
    <div id="wp2-wizard-step-manifest-${uniqueId}" class="wp2-wizard-step" hidden>
        <header class="wp2-modal-header">
            <h2>${escapeHtml('Finish GitHub setup')}</h2>
            <p>${escapeHtml('Paste this manifest into GitHub to create the App, then install it on your repositories.')}</p>
        </header>
        <div class="wp2-form-field">
            <textarea id="wp2-wizard-manifest-${uniqueId}" class="wp2-input" rows="10" readonly></textarea>
        </div>
    </div>
`;

/**
 * Dashboard view rendered when the connection is installed.
 */
export const DashboardView = (state = {}) => {
	// FIX: Use 'allPackages' for rendering, which contains both managed and unlinked packages.
	const { apps = [], allPackages = [], health = {}, stats = {}, isProcessing } = state;
	const syncing = Boolean(isProcessing);

	console.debug('DashboardView state:', state);

	return `
        <section class="wp2-dashboard">
            <header class="wp2-dashboard__header">
                <div>
                    <h1 class="wp2-dashboard__title">WP2 Update</h1>
                    <p class="wp2-dashboard__subtitle">Manage your GitHub-hosted plugins and themes.</p>
                </div>
                <div class="wp2-dashboard__actions">
                    <button type="button" class="wp2-btn wp2-btn--primary-outline" data-wp2-action="open-wizard">Add GitHub App</button>
                    <button type="button" id="wp2-sync-all" class="wp2-btn wp2-btn--primary" ${syncing ? 'disabled' : ''}>
                        ${syncing ? 'Syncing…' : 'Sync All'}
                    </button>
                </div>
            </header>

            <div class="wp2-dashboard-grid">
                <div class="wp2-dashboard-card">
                    <h3>Health</h3>
                    <ul>
                        <li>PHP Version: ${escapeHtml(health.phpVersion || 'N/A')}</li>
                        <li>Database: ${escapeHtml(health.dbStatus || 'N/A')}</li>
                        <li>Active Plugins: ${escapeHtml(health.activePlugins ?? 'N/A')}</li>
                    </ul>
                </div>
                <div class="wp2-dashboard-card">
                    <h3>Statistics</h3>
                    <ul>
                        <li>Total Updates: ${escapeHtml(stats.totalUpdates ?? 'N/A')}</li>
                        <li>Successful: ${escapeHtml(stats.successfulUpdates ?? 'N/A')}</li>
                        <li>Failed: ${escapeHtml(stats.failedUpdates ?? 'N/A')}</li>
                    </ul>
                </div>
            </div>

            <div class="wp2-dashboard-card">
                <header class="wp2-panel-header"><h2>Packages</h2></header>
                ${renderPackagesPanel(allPackages, apps)} 
            </div>

            <div class="wp2-dashboard-card">
                <header class="wp2-panel-header"><h2>GitHub Apps</h2></header>
                ${renderAppsPanel(apps, allPackages)}
            </div>
        </section>
    `;
};

const renderPackageActions = (pkg) => {
	const actions = [];

	if (pkg.has_update) {
		actions.push(renderActionButton('update', 'Update', '🔄'));
	}

	if (pkg.is_installed) {
		actions.push(renderActionButton('rollback', 'Rollback', '↩️'));
	} else {
		actions.push(renderActionButton('install', 'Install', '⬇️'));
	}

	return actions.join('');
};

const renderReleaseDropdown = (releases, currentVersion) => {
	return `
		<select class="wp2-release-dropdown">
			${releases.map(release => `
				<option value="${release.tag_name}" ${release.tag_name === currentVersion ? 'selected' : ''}>
					${release.tag_name}
				</option>
			`).join('')}
		</select>
	`;
};

checkRateLimit();