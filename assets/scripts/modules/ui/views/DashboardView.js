import { escapeHtml } from '../../utils.js';
import { STATUS } from '../../state/store.js';

const formatStatusBadge = (pkg) => {
	const isManaged = pkg.is_managed ?? Boolean(pkg.app_id);
	const installed = pkg.installed || pkg.version || '';
	const latest = pkg.latest || pkg.github_data?.latest_release || '';
	const hasUpdate = isManaged && installed && latest && installed !== latest;

	if (!isManaged) {
		return `
			<span class="wp2-status-badge wp2-status-badge--unmanaged">
				<span class="wp2-status-dot wp2-status-dot--unmanaged"></span>
				${escapeHtml('Unmanaged')}
			</span>
		`;
	}

	if (hasUpdate) {
		return `
			<span class="wp2-status-badge wp2-status-badge--update">
				<span class="wp2-status-dot wp2-status-dot--update"></span>
				${escapeHtml('Update available')}
			</span>
		`;
	}

	return `
		<span class="wp2-status-badge wp2-status-badge--ok">
			<span class="wp2-status-dot wp2-status-dot--ok"></span>
			${escapeHtml('Up to date')}
		</span>
	`;
};

const renderActionButton = (action, label, icon) => {
	return `
		<button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-action="${action}">
			<span class="wp2-icon">${icon}</span>
			${escapeHtml(label)}
		</button>
	`;
};

const renderPackageRow = (pkg = {}, apps = []) => {
	const managedAppName = apps.find(app => app.id === (pkg.app_id ?? pkg.app_uid))?.name ?? '';
	const installed = pkg.installed || pkg.version || '—';
	const latest = (pkg.latest || pkg.github_data?.latest_release || '—');
	const actionButton = (pkg.is_managed ?? Boolean(pkg.app_id))
		? `${renderActionButton('package-details', 'Details', '🔍')}`
		: `${renderActionButton('assign-app', 'Assign', '📦')}`;

	return `
		<tr data-wp2-package-row="${escapeHtml(pkg.repo || '')}">
			<td>
				<div class="wp2-table-cell__title">${escapeHtml(pkg.name || 'Unknown Package')}</div>
				<div class="wp2-table-cell__subtitle">${escapeHtml(pkg.repo || '—')}</div>
			</td>
			<td>${escapeHtml(installed)}</td>
			<td class="wp2-table-cell__highlight">${escapeHtml(latest || '—')}</td>
			<td>${formatStatusBadge(pkg)}</td>
			<td>${escapeHtml(managedAppName || '—')}</td>
			<td class="wp2-table-cell__actions">
				${actionButton}
			</td>
		</tr>
	`;
};

const renderPackagesPanel = (packages = [], apps = []) => {
	if (!packages.length) {
		return `
			<div class="wp2-empty-state">
				<h3>${escapeHtml('No packages detected yet')}</h3>
				<p>${escapeHtml('Sync your GitHub Apps to discover managed packages.')}</p>
			</div>
		`;
	}

	return `
		<div class="wp2-table-wrapper">
			<table class="wp2-table" data-wp2-table="packages">
				<thead>
					<tr>
						<th>${escapeHtml('Package')}</th>
						<th>${escapeHtml('Installed')}</th>
						<th>${escapeHtml('Latest')}</th>
						<th>${escapeHtml('Status')}</th>
						<th>${escapeHtml('Managed By')}</th>
						<th class="wp2-table-cell__actions">${escapeHtml('Actions')}</th>
					</tr>
				</thead>
				<tbody>
					${packages.map(pkg => renderPackageRow(pkg, apps)).join('')}
				</tbody>
			</table>
		</div>
	`;
};

const renderAppRow = (app = {}, packages = []) => {
	const managedPackages = packages.filter(pkg => (pkg.app_id ?? pkg.app_uid) === app.id);
	return `
		<tr data-wp2-app-row="${escapeHtml(app.id || '')}">
			<td>
				<div class="wp2-table-cell__title">${escapeHtml(app.name || 'Untitled App')}</div>
				<div class="wp2-table-cell__subtitle">${escapeHtml(app.slug || '')}</div>
			</td>
			<td>${escapeHtml((app.account_type || 'user').toString())}</td>
			<td>${managedPackages.length}</td>
			<td class="wp2-table-cell__actions">
				<button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-action="app-details" data-wp2-app="${escapeHtml(app.id || '')}">View</button>
			</td>
		</tr>
	`;
};

const renderAppsPanel = (apps = [], packages = []) => {
	if (!apps.length) {
		return `
			<div class="wp2-empty-state">
				<h3>${escapeHtml('No GitHub Apps connected')}</h3>
				<p>${escapeHtml('Connect a GitHub App to start syncing package updates.')}</p>
				<button type="button" class="wp2-btn wp2-btn--primary" data-wp2-action="open-wizard">${escapeHtml('Add GitHub App')}</button>
			</div>
		`;
	}

	return `
		<div class="wp2-table-wrapper">
			<table class="wp2-table" data-wp2-table="apps">
				<thead>
					<tr>
						<th>${escapeHtml('App Name')}</th>
						<th>${escapeHtml('Account Type')}</th>
						<th>${escapeHtml('Packages')}</th>
						<th class="wp2-table-cell__actions">${escapeHtml('Actions')}</th>
					</tr>
				</thead>
				<tbody>
					${apps.map(app => renderAppRow(app, packages)).join('')}
				</tbody>
			</table>
		</div>
	`;
};

const renderAssignModal = () => `
	<div id="wp2-modal-assign" class="wp2-modal-overlay" hidden>
		<div class="wp2-modal-window">
			<button type="button" class="wp2-modal-close" data-wp2-close="assign">×</button>
			<div class="wp2-modal-body">
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
			</div>
		</div>
	</div>
`;

const renderPackageDetailsModal = () => `
	<div id="wp2-modal-package" class="wp2-modal-overlay" hidden>
		<div class="wp2-modal-window wp2-modal-window--wide">
			<button type="button" class="wp2-modal-close" data-wp2-close="package">×</button>
			<div class="wp2-modal-body">
				<div class="wp2-modal-header">
					<h2 id="wp2-package-details-title">${escapeHtml('Package details')}</h2>
				</div>
				<div id="wp2-package-details-content" class="wp2-detail-grid"></div>
				<div id="wp2-package-sync-log" class="wp2-sync-log" hidden></div>
				<div class="wp2-modal-actions">
					<button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-close="package">${escapeHtml('Close')}</button>
				</div>
			</div>
		</div>
	</div>
`;

const renderAppDetailsModal = () => `
	<div id="wp2-modal-app" class="wp2-modal-overlay" hidden>
		<div class="wp2-modal-window wp2-modal-window--wide">
			<button type="button" class="wp2-modal-close" data-wp2-close="app">×</button>
			<div class="wp2-modal-body">
				<div class="wp2-modal-header">
					<h2 id="wp2-app-details-title">${escapeHtml('App details')}</h2>
				</div>
				<div id="wp2-app-details-content" class="wp2-detail-grid"></div>
				<div id="wp2-app-managed-packages" class="wp2-managed-list"></div>
				<div class="wp2-modal-actions">
					<button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-close="app">${escapeHtml('Close')}</button>
				</div>
			</div>
		</div>
	</div>
`;

const renderWizardModal = () => `
	<div id="wp2-modal-wizard" class="wp2-modal-overlay" hidden>
		<div class="wp2-modal-window wp2-modal-window--wide">
			<button type="button" class="wp2-modal-close" data-wp2-close="wizard">×</button>
			<div class="wp2-modal-body">
				<div id="wp2-wizard-step-configure" class="wp2-wizard-step">
					<header class="wp2-modal-header">
						<h2>${escapeHtml('Add GitHub App')}</h2>
						<p>${escapeHtml('Generate a manifest and connect a new GitHub App for this site.')}</p>
					</header>
					<form id="wp2-wizard-form" class="wp2-form">
						<div class="wp2-form-grid">
							<div class="wp2-form-field">
								<label class="wp2-form-label" for="wp2-wizard-app-name">${escapeHtml('App name')}</label>
								<input id="wp2-wizard-app-name" name="app_name" class="wp2-input" type="text" required />
							</div>
							<div class="wp2-form-field">
								<label class="wp2-form-label" for="wp2-wizard-encryption-key">${escapeHtml('Encryption key')}</label>
								<input id="wp2-wizard-encryption-key" name="encryption_key" class="wp2-input" type="password" minlength="16" required />
								<p class="wp2-field-help">${escapeHtml('Use at least 16 characters. Store this securely for future connections.')}</p>
							</div>
						</div>
						<div class="wp2-form-field">
							<label class="wp2-form-label">${escapeHtml('Account type')}</label>
							<div class="wp2-pill-group" id="wp2-wizard-account">
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
						<div class="wp2-form-field" id="wp2-wizard-organization-field" hidden>
							<label class="wp2-form-label" for="wp2-wizard-organization">${escapeHtml('Organization slug')}</label>
							<input id="wp2-wizard-organization" name="organization" class="wp2-input" type="text" placeholder="your-org-name" />
						</div>
						<div class="wp2-modal-actions">
							<button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-close="wizard">${escapeHtml('Cancel')}</button>
							<button type="submit" class="wp2-btn wp2-btn--primary">${escapeHtml('Generate manifest')}</button>
						</div>
					</form>
				</div>
				<div id="wp2-wizard-step-manifest" class="wp2-wizard-step" hidden>
					<header class="wp2-modal-header">
						<h2>${escapeHtml('Finish GitHub setup')}</h2>
						<p>${escapeHtml('Paste this manifest into GitHub to create the App, then install it on your repositories.')}</p>
					</header>
					<div class="wp2-form-field">
						<label class="wp2-form-label" for="wp2-wizard-manifest">${escapeHtml('GitHub App manifest')}</label>
						<textarea id="wp2-wizard-manifest" class="wp2-input wp2-input--code" rows="12" readonly></textarea>
						<div class="wp2-manifest-actions">
							<button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-action="copy-manifest">${escapeHtml('Copy manifest')}</button>
							<button type="button" class="wp2-btn wp2-btn--primary-outline" data-wp2-action="open-github">${escapeHtml('Open GitHub')}</button>
						</div>
					</div>
					<p class="wp2-manifest-note">${escapeHtml('GitHub will redirect you back here when installation completes. You can always retry the connection from the Settings tab.')}</p>
					<div class="wp2-modal-actions">
						<button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-close="wizard">${escapeHtml('Close')}</button>
						<button type="button" class="wp2-btn wp2-btn--primary" data-wp2-action="wizard-finished">${escapeHtml('I’ve installed the app')}</button>
					</div>
				</div>
			</div>
		</div>
	</div>
`;

const renderModals = () =>
	[
		renderAssignModal(),
		renderPackageDetailsModal(),
		renderAppDetailsModal(),
		renderWizardModal(),
	].join('');

/**
 * Dashboard view rendered when the connection is installed.
 */
export const DashboardView = (state = {}) => {
    const apps = Array.isArray(state.apps) ? state.apps : [];
    const managedPackages = Array.isArray(state.packages) ? state.packages : [];
    const unlinkedPackages = Array.isArray(state.unlinkedPackages) ? state.unlinkedPackages : [];
    const combinedPackages = [...managedPackages];

    unlinkedPackages.forEach((pkg) => {
        if (!combinedPackages.some(item => item.repo === pkg.repo)) {
            combinedPackages.push({ ...pkg, is_managed: false });
        }
    });

    const syncing = Boolean(state.isProcessing);

	return `
		<section class="wp2-dashboard">
			<header class="wp2-dashboard__header">
				<div>
					<h1 class="wp2-dashboard__title">${escapeHtml('WP2 Update')}</h1>
					<p class="wp2-dashboard__subtitle">${escapeHtml('Manage your GitHub Apps and package updates from one place.')}</p>
				</div>
				<div class="wp2-dashboard__actions">
					<button type="button" id="wp2-add-app" class="wp2-btn wp2-btn--primary-outline" data-wp2-action="open-wizard">
						<span class="wp2-icon-plus" aria-hidden="true"></span>
						${escapeHtml('Add GitHub App')}
					</button>
					<button type="button" id="wp2-sync-all" class="wp2-btn wp2-btn--primary" ${syncing ? 'disabled' : ''}>
						<span class="wp2-icon-refresh" aria-hidden="true"></span>
						${escapeHtml(syncing ? 'Syncing…' : 'Sync all')}
					</button>
				</div>
			</header>

			<div class="wp2-dashboard-card" id="wp2-dashboard-card">
				<div class="wp2-dashboard-tabs" id="wp2-dashboard-tabs" role="tablist">
					<button type="button" class="wp2-dashboard-tab active" data-wp2-tab="packages" role="tab" aria-selected="true">
						${escapeHtml('Packages')}
					</button>
					<button type="button" class="wp2-dashboard-tab" data-wp2-tab="apps" role="tab" aria-selected="false">
						${escapeHtml('GitHub Apps')}
					</button>
				</div>
				<div class="wp2-dashboard-panels">
					<div class="wp2-dashboard-panel active" data-wp2-panel="packages">
                        ${renderPackagesPanel(combinedPackages, apps)}
					</div>
					<div class="wp2-dashboard-panel" data-wp2-panel="apps">
                        ${renderAppsPanel(apps, combinedPackages)}
					</div>
				</div>
			</div>

			${renderModals()}
		</section>
	`;
};
