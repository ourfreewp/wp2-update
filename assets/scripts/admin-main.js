import { dashboard_state } from './modules/state/store.js';
import { api_request } from './modules/api.js';
import { show_global_spinner, hide_global_spinner } from './modules/ui/spinner.js';
import { confirm_modal } from './modules/ui/modal.js';
import { renderManualCredentialsForm } from './modules/ui/manual-credentials';

const { __, sprintf } = window.wp?.i18n ?? {
	__: (text) => text,
	sprintf: (...parts) => parts.join(' '),
};

let toast;
const ensureToast = async () => {
	if (!toast) {
		const module = await import('./modules/ui/toast.js');
		toast = module.toast;
	}
};

const STATUS = {
	NOT_CONFIGURED: 'not_configured',
	NOT_CONFIGURED_WITH_PACKAGES: 'not_configured_with_packages',
	CONFIGURING: 'configuring',
	APP_CREATED: 'app_created',
	CONNECTING: 'connecting',
	INSTALLED: 'installed',
	ERROR: 'error',
	LOADING: 'loading',
};

let pollHandle = null;

const updateDashboardState = (updates) => {
	const currentState = dashboard_state.get();
	dashboard_state.set({
		...currentState,
		...updates,
	});
};

const stopPolling = () => {
	if (pollHandle) {
		clearTimeout(pollHandle);
		pollHandle = null;
		updateDashboardState({ polling: { active: false } });
	}
};

const scheduleInstallationPoll = () => {
	if (pollHandle) {
		return;
	}

	updateDashboardState({ polling: { active: true } });

	pollHandle = setTimeout(async () => {
		pollHandle = null;
		await fetchConnectionStatus({ silent: true });

		const state = dashboard_state.get();
		if (state.status === STATUS.APP_CREATED) {
			scheduleInstallationPoll();
		} else {
			stopPolling();
		}
	}, 5000);
};

const render = (state) => {
	const root = document.getElementById('wp2-dashboard-root');
	if (!root) {
		return;
	}

	const view = buildViewForState(state);
	root.innerHTML = view;
	bindViewEvents(state);
};

const buildViewForState = (state) => {
	switch (state.status) {
		case STATUS.LOADING:
			return loadingView();
		case STATUS.NOT_CONFIGURED:
		case STATUS.NOT_CONFIGURED_WITH_PACKAGES:
			return notConfiguredView(state);
			break;
		case STATUS.CONFIGURING:
			return configuringView(state);
			break;
		case STATUS.APP_CREATED:
			return appCreatedView(state);
			break;
		case STATUS.CONNECTING:
			return connectingView();
			break;
		case STATUS.INSTALLED:
			return dashboardView(state);
			break;
		case STATUS.ERROR:
			return errorView(state);
			break;
		default:
			return loadingView();
	}
};

const loadingView = () => `
	<div class="wp2-dashboard-card wp2-dashboard-loading">
		<div class="wp2-dashboard-spinner"></div>
		<p>${__('Loading connection status…', 'wp2-update')}</p>
	</div>
`;

const notConfiguredView = (state) => {
	const hasPackages = state.status === STATUS.NOT_CONFIGURED_WITH_PACKAGES && state.unlinkedPackages.length;
	return `
		<div class="wp2-dashboard-grid" role="region" aria-labelledby="not-configured-heading">
			<h1 id="not-configured-heading" class="screen-reader-text">${__('Not Configured', 'wp2-update')}</h1>
			<section class="wp2-dashboard-card wp2-card-centered">
				<div class="wp2-illustration wp2-illustration-connect" aria-hidden="true"></div>
				<h2>${__('Connect to GitHub', 'wp2-update')}</h2>
				<p>${__('Enable automatic updates for your themes and plugins by connecting a GitHub App.', 'wp2-update')}</p>
				<button type="button" id="wp2-start-connection" class="wp2-button wp2-button-primary" aria-label="${__('Connect to GitHub', 'wp2-update')}">${__('Connect to GitHub', 'wp2-update')}</button>
			</section>
			${hasPackages ? detectedPackagesSection(state.unlinkedPackages) : ''}
		</div>
	`;
};

const configuringView = (state) => {
	const draft = state.manifestDraft;
	return `
		<section class="wp2-dashboard-card" role="region" aria-labelledby="configuring-heading">
			<h1 id="configuring-heading" class="screen-reader-text">${__('Configuring GitHub App', 'wp2-update')}</h1>
			<h2>${__('Create Your GitHub App', 'wp2-update')}</h2>
			<p class="wp2-muted">${__('We pre-fill the manifest for you. Review the details, then continue to GitHub to finish the setup.', 'wp2-update')}</p>
			<form id="wp2-configure-form" class="wp2-form" aria-labelledby="wp2-configure-form-title">
				<label class="wp2-label">${__('Encryption Key', 'wp2-update')}
					<input type="password" name="encryption-key" id="wp2-encryption-key" class="wp2-input" placeholder="${__('Minimum 16 characters', 'wp2-update')}" required autocomplete="off" aria-describedby="wp2-encryption-key-desc" />
				</label>
				<p id="wp2-encryption-key-desc" class="wp2-muted">${__('Enter a secure encryption key.', 'wp2-update')}</p>
				<label class="wp2-label">${__('App Name', 'wp2-update')}
					<input type="text" name="app-name" id="wp2-app-name" class="wp2-input" value="${draft.name ?? ''}" required aria-label="${__('GitHub App Name', 'wp2-update')}" />
				</label>
				<label class="wp2-label">${__('Account Type', 'wp2-update')}
					<select name="app-type" id="wp2-app-type" class="wp2-select" aria-label="${__('Select Account Type', 'wp2-update')}">
						<option value="user" ${draft.accountType === 'user' ? 'selected' : ''}>${__('Personal User', 'wp2-update')}</option>
						<option value="organization" ${draft.accountType === 'organization' ? 'selected' : ''}>${__('Organization', 'wp2-update')}</option>
					</select>
				</label>
				<div class="wp2-org-field" ${draft.accountType === 'organization' ? '' : 'hidden'}>
					<label class="wp2-label">${__('Organization Slug', 'wp2-update')}
						<input type="text" name="organization" id="wp2-organization" class="wp2-input" placeholder="example-org" value="${draft.organization ?? ''}" aria-label="${__('Organization Slug', 'wp2-update')}" />
					</label>
				</div>
				<label class="wp2-label">${__('Manifest JSON', 'wp2-update')}
					<textarea name="manifest" id="wp2-manifest-json" class="wp2-input wp2-code" rows="10" aria-label="${__('Manifest JSON', 'wp2-update')}">${draft.manifestJson}</textarea>
				</label>
				<div class="wp2-form-actions">
					<button type="button" id="wp2-cancel-config" class="wp2-button wp2-button-secondary" aria-label="${__('Cancel Configuration', 'wp2-update')}">${__('Cancel', 'wp2-update')}</button>
					<button type="submit" class="wp2-button wp2-button-primary" aria-label="${__('Save and Continue to GitHub', 'wp2-update')}">${__('Save and Continue to GitHub', 'wp2-update')}</button>
				</div>
			</form>
		</section>
	`;
};

const appCreatedView = (state) => `
	<section class="wp2-dashboard-card wp2-card-centered" role="region" aria-labelledby="app-created-heading">
		<h1 id="app-created-heading" class="screen-reader-text">${__('App Created', 'wp2-update')}</h1>
		<div class="wp2-illustration wp2-illustration-install"></div>
		<h2>${__('Almost there! Install the App', 'wp2-update')}</h2>
		<p>${state.message || __('Finish installing the GitHub App in the tab that opened. Once complete, click the button below.', 'wp2-update')}</p>
		<div class="wp2-button-group">
			<button type="button" id="wp2-check-installation" class="wp2-button wp2-button-primary" aria-label="${__('Check Installation Status', 'wp2-update')}">${__('Check Installation Status', 'wp2-update')}</button>
			<button type="button" id="wp2-start-over" class="wp2-button wp2-button-secondary" aria-label="${__('Start Over and Reconfigure', 'wp2-update')}">${__('Start Over', 'wp2-update')}</button>
		</div>
		${state.polling?.active ? `<p class="wp2-muted">${__('Waiting for installation… Checking again shortly.', 'wp2-update')}</p>` : ''}
	</section>
`;

const connectingView = () => `
	<section class="wp2-dashboard-card wp2-card-centered" role="region" aria-labelledby="connecting-heading">
		<h1 id="connecting-heading" class="screen-reader-text">${__('Connecting to GitHub', 'wp2-update')}</h1>
		<div class="wp2-dashboard-spinner wp2-dashboard-spinner-lg"></div>
		<h2>${__('Finalizing Connection…', 'wp2-update')}</h2>
		<p>${__('Verifying your GitHub App credentials and installation.', 'wp2-update')}</p>
	</section>
`;

const dashboardView = (state) => {
	const packages = state.packages || [];
	return `
		<div class="wp2-dashboard-grid" role="region" aria-labelledby="dashboard-heading">
			<h1 id="dashboard-heading" class="screen-reader-text">${__('Dashboard', 'wp2-update')}</h1>
			<section class="wp2-dashboard-card">
				<header class="wp2-dashboard-header">
					<div>
						<h2>${__('Managed Packages', 'wp2-update')}</h2>
						<p class="wp2-muted">${state.message || __('Connection to GitHub is active.', 'wp2-update')}</p>
					</div>
					<div class="wp2-button-group">
						<button type="button" id="wp2-sync-packages" class="wp2-button wp2-button-secondary" aria-label="${__('Sync Packages with GitHub', 'wp2-update')}">
							${__('Sync Packages', 'wp2-update')}
						</button>
						<button type="button" id="wp2-disconnect" class="wp2-button wp2-button-danger" aria-label="${__('Disconnect from GitHub', 'wp2-update')}">
							${__('Disconnect', 'wp2-update')}
						</button>
					</div>
				</header>
				${packages.length ? managedPackagesTable(packages) : emptyPackagesState()}
			</section>
		</div>
	`;
};

const errorView = (state) => `
	<section class="wp2-dashboard-card wp2-card-centered wp2-card-error">
		<div class="wp2-illustration wp2-illustration-error"></div>
		<h2>${__('Connection Error', 'wp2-update')}</h2>
		<p>${state.message || __('GitHub returned an error while verifying your credentials.', 'wp2-update')}</p>
		<div class="wp2-button-group">
			<button type="button" id="wp2-retry-connection" class="wp2-button wp2-button-primary">${__('Retry Connection', 'wp2-update')}</button>
			<button type="button" id="wp2-disconnect-reset" class="wp2-button wp2-button-secondary">${__('Disconnect and Start Over', 'wp2-update')}</button>
		</div>
	</section>
`;

const detectedPackagesSection = (packages) => `
	<section class="wp2-dashboard-card wp2-card-table">
		<h3>${__('Detected Packages', 'wp2-update')}</h3>
		<p class="wp2-muted">${__('We found themes or plugins that can be managed once you connect to GitHub.', 'wp2-update')}</p>
		<div class="wp2-table-wrapper">
			<table class="wp2-table">
				<thead>
					<tr>
						<th>${__('Package', 'wp2-update')}</th>
						<th>${__('Installed Version', 'wp2-update')}</th>
						<th>${__('Repository', 'wp2-update')}</th>
					</tr>
				</thead>
				<tbody>
					${packages.map(pkg => `
						<tr>
							<td>${pkg.name}</td>
							<td>${pkg.version ? `v${pkg.version}` : __('Unknown', 'wp2-update')}</td>
							<td>${pkg.repo ? `<code>${pkg.repo}</code>` : '&mdash;'}</td>
						</tr>
					`).join('')}
				</tbody>
			</table>
		</div>
	</section>
`;

const emptyPackagesState = () => `
	<div class="wp2-empty-state">
		<p>${__('No managed packages found yet. Sync repositories to view release information.', 'wp2-update')}</p>
	</div>
`;

const managedPackagesTable = (packages) => `
	<div class="wp2-table-wrapper">
		<table class="wp2-table">
			<thead>
				<tr>
					<th>${__('Package', 'wp2-update')}</th>
					<th>${__('Installed', 'wp2-update')}</th>
					<th>${__('Available Releases', 'wp2-update')}</th>
					<th>${__('Action', 'wp2-update')}</th>
				</tr>
			</thead>
			<tbody>
				${packages.map(renderPackageRow).join('')}
			</tbody>
		</table>
	</div>
`;

const renderPackageRow = (pkg) => {
	const installed = pkg.installed || '—';
	const releases = Array.isArray(pkg.releases) ? pkg.releases : [];
	const latest = releases[0]?.tag_name?.replace(/^v/i, '') || null;
	const installedVersion = installed.replace(/^v/i, '');
	const hasUpdate = latest && installedVersion && latest !== installedVersion;
	const actionLabel = hasUpdate ? __('Update', 'wp2-update') : __('Re-install', 'wp2-update');

	const releaseOptions = releases.map(rel => `
		<option value="${rel.tag_name}">${rel.name || rel.tag_name}</option>
	`).join('');

	return `
		<tr data-repo="${pkg.repo}">
			<td>
				<strong>${pkg.name || pkg.repo}</strong>
				${pkg.repo ? `<div class="wp2-muted"><code>${pkg.repo}</code></div>` : ''}
			</td>
			<td>${installed}</td>
			<td>
				<select class="wp2-select wp2-release-select">
					${releaseOptions}
				</select>
			</td>
			<td>
				<button type="button" class="wp2-button wp2-button-${hasUpdate ? 'primary' : 'secondary'} wp2-package-action">
					${actionLabel}
				</button>
			</td>
		</tr>
	`;
};

const bindViewEvents = (state) => {
	switch (state.status) {
		case STATUS.NOT_CONFIGURED:
		case STATUS.NOT_CONFIGURED_WITH_PACKAGES: {
			document.getElementById('wp2-start-connection')?.addEventListener('click', () => {
				updateDashboardState({ status: STATUS.CONFIGURING });
			});
			break;
		}
		case STATUS.CONFIGURING: {
			const form = document.getElementById('wp2-configure-form');
			form?.addEventListener('submit', onSubmitManifestForm);
			document.getElementById('wp2-app-type')?.addEventListener('change', onAccountTypeChange);
			document.getElementById('wp2-cancel-config')?.addEventListener('click', () => {
				updateDashboardState({ status: STATUS.NOT_CONFIGURED, message: '' });
			});
			break;
		}
		case STATUS.APP_CREATED: {
			document.getElementById('wp2-check-installation')?.addEventListener('click', () => {
				fetchConnectionStatus();
				scheduleInstallationPoll();
			});
			document.getElementById('wp2-start-over')?.addEventListener('click', () => {
				confirmResetConnection();
			});
			break;
		}
		case STATUS.INSTALLED: {
			document.getElementById('wp2-sync-packages')?.addEventListener('click', syncPackages);
			document.getElementById('wp2-disconnect')?.addEventListener('click', confirmResetConnection);
			document.querySelectorAll('.wp2-package-action').forEach((button) => {
				button.addEventListener('click', onPackageAction);
			});
			break;
		}
		case STATUS.ERROR: {
			document.getElementById('wp2-retry-connection')?.addEventListener('click', () => {
				fetchConnectionStatus();
			});
			document.getElementById('wp2-disconnect-reset')?.addEventListener('click', confirmResetConnection);
			break;
		}
		default:
			break;
	}
};

const onAccountTypeChange = (event) => {
	const value = event.target.value;
	const state = dashboard_state.get();
	const manifestDraft = { ...state.manifestDraft, accountType: value };
	dashboard_state.set({
		...state,
		manifestDraft,
	});

	const orgField = document.querySelector('.wp2-org-field');
	if (!orgField) {
		return;
	}
	if (value === 'organization') {
		orgField.removeAttribute('hidden');
	} else {
		orgField.setAttribute('hidden', 'hidden');
	}
};

const onSubmitManifestForm = async (event) => {
	event.preventDefault();
	await ensureToast();

	const form = event.currentTarget;
	const submitButton = form.querySelector('button[type="submit"]');
	const formData = new FormData(form);
	const encryptionKey = String(formData.get('encryption-key') || '').trim();
	const name = String(formData.get('app-name') || '').trim();
	const accountType = String(formData.get('app-type') || 'user');
	const organization = String(formData.get('organization') || '').trim();
	const manifestJson = String(formData.get('manifest') || '').trim();

	if (!name) {
		toast(__('App Name is required.', 'wp2-update'), 'error');
		return;
	}

	if (!encryptionKey || encryptionKey.length < 16) {
		toast(__('Encryption Key must be at least 16 characters.', 'wp2-update'), 'error');
		return;
	}

	const state = dashboard_state.get();
	dashboard_state.set({
		...state,
		isProcessing: true,
	});

	try {
		show_global_spinner();
		submitButton.disabled = true;

		const payload = await api_request('github/connect-url', {
			method: 'POST',
			body: {
				name,
				account_type: accountType,
				organization,
				encryption_key: encryptionKey,
				manifest: manifestJson,
			},
		});

		const accountUrl = new URL(payload.account);
		if (payload.state) {
			accountUrl.searchParams.set('state', payload.state);
		}

		const formElement = document.createElement('form');
		formElement.method = 'POST';
		formElement.action = accountUrl.toString();
		formElement.target = '_blank';

		const manifestInput = document.createElement('input');
		manifestInput.type = 'hidden';
		manifestInput.name = 'manifest';
		manifestInput.value = payload.manifest;
		formElement.appendChild(manifestInput);

		document.body.appendChild(formElement);
		formElement.submit();
		formElement.remove();

		dashboard_state.set({
			...dashboard_state.get(),
			status: STATUS.APP_CREATED,
			message: __('GitHub App created. Complete the installation in the opened tab.', 'wp2-update'),
		});

		scheduleInstallationPoll();
	} catch (error) {
		console.error('Failed to generate connect URL', error);
		toast(__('Failed to connect to GitHub. Please try again.', 'wp2-update'), 'error', error.message);
	} finally {
		submitButton.disabled = false;
		hide_global_spinner();
		dashboard_state.set({
			...dashboard_state.get(),
			isProcessing: false,
		});
	}
};

const onPackageAction = async (event) => {
	event.preventDefault();
	await ensureToast();

	const button = event.currentTarget;
	const row = button.closest('tr');
	if (!row) {
		return;
	}

	const repo = row.dataset.repo;
	const select = row.querySelector('.wp2-release-select');
	const version = select?.value;

	if (!repo || !version) {
		toast(__('Unable to determine the selected release.', 'wp2-update'), 'error');
		return;
	}

	confirm_modal(
		sprintf(__('Install %1$s from %2$s?', 'wp2-update'), version, repo),
		async () => {
			try {
				show_global_spinner();
				await api_request('manage-packages', {
					method: 'POST',
					body: {
						action: 'install',
						repo_slug: repo, // Corrected key
						version,
					},
				});
				toast(__('Package installed successfully.', 'wp2-update'), 'success');
				await syncPackages();
			} catch (error) {
				console.error('Failed to install package', error);
				toast(__('Failed to install the selected release.', 'wp2-update'), 'error', error.message);
			} finally {
				hide_global_spinner();
			}
		}
	);
};

const confirmResetConnection = async () => {
	await ensureToast();
	confirm_modal(
		__('Are you sure you want to disconnect? You will need to reconnect the GitHub App to manage updates.', 'wp2-update'),
		async () => {
			try {
				show_global_spinner();
				await api_request('github/disconnect', { method: 'POST' });
				stopPolling();
				dashboard_state.set({
					...dashboard_state.get(),
					status: STATUS.NOT_CONFIGURED,
					message: '',
					details: {},
					packages: [],
					unlinkedPackages: [],
				});
				toast(__('Disconnected successfully.', 'wp2-update'), 'success');
			} catch (error) {
				console.error('Failed to disconnect', error);
				toast(__('Failed to disconnect. Please try again.', 'wp2-update'), 'error', error.message);
			} finally {
				hide_global_spinner();
			}
		}
	);
};

const syncPackages = async () => {
	const state = dashboard_state.get();
	const syncButton = document.getElementById('wp2-sync-packages');

	dashboard_state.set({
		...state,
		isProcessing: true,
	});

	if (syncButton) {
		syncButton.disabled = true;
	}

	try {
		show_global_spinner();
		const { packages = [], unlinked_packages = [] } = await api_request('sync-packages', { method: 'GET' }) || {};
		updateDashboardState({ packages, unlinked_packages });
	} catch (error) {
		console.error('Failed to sync packages', error);
		await ensureToast();
		toast(__('Failed to sync packages from GitHub.', 'wp2-update'), 'error', error.message);
	} finally {
		hide_global_spinner();
		dashboard_state.set({
			...dashboard_state.get(),
			isProcessing: false,
		});
		if (syncButton) {
			syncButton.disabled = false;
		}
	}
};

const fetchConnectionStatus = async ({ silent = false } = {}) => {
	const checkButton = document.getElementById('wp2-check-installation');

	if (!silent) {
		dashboard_state.set({
			...dashboard_state.get(),
			status: STATUS.LOADING,
			isProcessing: true,
		});
	}

	if (checkButton) {
		checkButton.disabled = true;
	}

	try {
		if (!silent) {
			show_global_spinner();
		}
		const response = await api_request('connection-status', { method: 'GET' });
		const data = response?.data || {};
		const status = data.status || STATUS.NOT_CONFIGURED;

		const base = {
			...dashboard_state.get(),
			status,
			message: data.message || '',
			details: data.details || {},
			unlinkedPackages: data.unlinked_packages || [],
			isProcessing: false,
		};

		// Determine if we should flag additional packages.
		if (status === STATUS.NOT_CONFIGURED && base.unlinkedPackages.length) {
			base.status = STATUS.NOT_CONFIGURED_WITH_PACKAGES;
		}

		dashboard_state.set(base);

		if (status === STATUS.APP_CREATED) {
			scheduleInstallationPoll();
		} else if (status === STATUS.INSTALLED) {
			stopPolling();
			await syncPackages();
		}
	} catch (error) {
		console.error('Failed to fetch connection status', error);
		await ensureToast();
		toast(__('Could not connect to the server.', 'wp2-update'), 'error', error.message);
		dashboard_state.set({
			...dashboard_state.get(),
			status: STATUS.ERROR,
			message: error.message || __('An unexpected error occurred.', 'wp2-update'),
			isProcessing: false,
		});
	} finally {
		if (!silent) {
			hide_global_spinner();
		}
		if (checkButton) {
			checkButton.disabled = false;
		}
	}
};

const handleGitHubCallback = () => {
	const container = document.getElementById('wp2-update-github-callback');
	if (!container) {
		return;
	}

	const notice = (message, isError = false) => {
		container.innerHTML = `<div class="wp2-notice ${isError ? 'wp2-notice-error' : 'wp2-notice-success'}"><p>${message}</p></div>`;
	};

	const params = new URLSearchParams(window.location.search);
	const code = params.get('code');
	const state = params.get('state');

	if (!code || !state) {
		notice(__('Invalid callback parameters. Please try again.', 'wp2-update'), true);
		return;
	}

	notice(__('Finalizing GitHub connection, please wait…', 'wp2-update'));

	(async () => {
		try {
			await api_request('github/exchange-code', { body: { code, state } });
			notice(__('Connection successful! You may close this tab.', 'wp2-update'));
			window.opener?.postMessage('wp2-update-github-connected', window.location.origin);
			window.close();
		} catch (error) {
			console.error('GitHub exchange failed', error);
			notice(
				sprintf(__('An error occurred: %s. Please try again.', 'wp2-update'), error.message),
				true
			);
		}
	})();
};

dashboard_state.listen(render);

window.addEventListener('message', (event) => {
	if (event.data === 'wp2-update-github-connected') {
		fetchConnectionStatus();
	}
});

document.addEventListener('DOMContentLoaded', () => {
	if (document.getElementById('wp2-update-github-callback')) {
		handleGitHubCallback();
		return;
	}

	if (!document.getElementById('wp2-update-app')) {
		return;
	}

	render(dashboard_state.get());
	fetchConnectionStatus();
});

// New fetchPackages function to retrieve real package data
const fetchPackages = async () => {
	try {
		const { packages = [], unlinked_packages = [] } = await api_request('sync-packages', { method: 'GET' }) || {};
		updateDashboardState({ packages, unlinked_packages });
	} catch (error) {
		console.error('Failed to fetch packages', error);
		await ensureToast();
		toast(__('Failed to fetch packages from GitHub.', 'wp2-update'), 'error', error.message);
	}
};

// Call fetchPackages during initialization
fetchPackages();

// Call renderManualCredentialsForm during initialization
renderManualCredentialsForm();
