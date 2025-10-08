import debounce from 'lodash/debounce';
import { api_request } from '../api/index.js';
import { app_state, is_any_pkg_updating } from '../state/store.js';
import { toast } from '../ui/toast.js';
import { confirm_modal } from '../ui/modal.js';
import { init_ui } from '../ui/init.js';
import { render_view } from '../components/views.js';
import { render_package_table } from '../components/table.js';
import { dispatch_action, register_action, with_button_busy } from '../core/dispatcher.js';
import { render_configure_manifest } from '../components/manifest-config.js';

// Loading overlay
const show_global_spinner = () => {
	const id = 'wp2-global-spinner';
	if (document.getElementById(id)) return;
	const el = document.createElement('div');
	el.id = id;
	el.className = 'wp2-global-spinner';
	document.body.appendChild(el);
};

const hide_global_spinner = () => document.getElementById('wp2-global-spinner')?.remove();

// Actions
register_action('start-connection', async () => {
	const { url } = await api_request('wp2-update/v1/github/connect-url', { method: 'GET' });
	if (url) window.location.href = url;
});

register_action('disconnect', async () => {
	confirm_modal(
		__('Are you sure you want to disconnect? This will remove your credentials.', 'wp2-update'),
		async () => {
			await api_request('wp2-update/v1/github/disconnect');
			app_state.set({ ...app_state.get(), currentStage: 'pre-connection', packages: [] });
			toast(__('Disconnected successfully.', 'wp2-update'));
		},
		() => toast(__('Disconnect action canceled.', 'wp2-update'))
	);
});

register_action('sync-packages', debounce(async () => {
	show_global_spinner();
	app_state.set({ ...app_state.get(), isLoading: true });
	const statusEl = document.querySelector('#sync-status');

	try {
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
			syncError: null,
		});
		if (statusEl) {
			statusEl.textContent = __('Last sync succeeded at', 'wp2-update') + ' ' + new Date().toLocaleString();
			statusEl.className = 'sync-status success';
		}
		toast(__('Successfully synced with GitHub.', 'wp2-update'));
	} catch (error) {
		app_state.set({ ...app_state.get(), isLoading: false, syncError: error.message || __('Sync failed.', 'wp2-update') });
		if (statusEl) {
			statusEl.textContent = __('Last sync failed at', 'wp2-update') + ' ' + new Date().toLocaleString();
			statusEl.className = 'sync-status error';
		}
		toast(__('Sync failed: ', 'wp2-update') + (error.message || __('Unknown error', 'wp2-update')), 'error');
	} finally {
		hide_global_spinner();
	}
}, 300));

register_action('update-package', async (button) => {
	const repo = button?.dataset.packageRepo;
	if (!repo) throw new Error('Missing package repo.');
	const select = document.querySelector(`.release-dropdown[data-package-repo="${repo}"]`);
	if (!select) throw new Error('Release dropdown not found.');
	const version = select.value;

	const original = app_state.get().packages;
	app_state.set({
		...app_state.get(),
		packages: original.map((p) => (p.repo === repo ? { ...p, status: 'updating' } : p)),
	});

	try {
		await api_request('wp2-update/v1/manage-packages', {
			body: { action: 'update', repo_slug: repo, version, type: button.dataset.packageType },
		});

		toast(`${repo} update to ${version} initiated.`);
		const refreshed = await api_request(`wp2-update/v1/package/${repo}`, { method: 'GET' });
		app_state.set({
			...app_state.get(),
			packages: app_state.get().packages.map((p) => (p.repo === repo ? { ...p, ...refreshed } : p)),
		});
	} catch (error) {
		toast(`Failed to update ${repo}: ${error.message}`, 'error');
		app_state.set({
			...app_state.get(),
			packages: original.map((p) => (p.repo === repo ? { ...p, status: 'error', errorMessage: error.message } : p)),
		});
	}
});

register_action('health-check', async () => {
	show_global_spinner();
	try {
		await api_request('wp2-update/v1/health-status', { method: 'GET' });
		toast('Health check completed successfully.', 'success');
	} catch (e) {
		toast('Health check failed. See console for details.', 'error');
	} finally {
		hide_global_spinner();
	}
});

register_action('configure-manifest', async () => {
	app_state.set({ ...app_state.get(), currentStage: 'configure-manifest' });
	render_configure_manifest();
	render_view('configure-manifest');
});

register_action('submit-manifest-config', async (button) => {
	const form = button.closest('form');
	const appName = form.querySelector('#app-name').value;
	const organization = form.querySelector('#organization').value;

	// Call the REST API to get the manifest creation URL
	const params = { name: appName, organization };

	try {
		const { url } = await api_request('wp2-update/v1/github/connect-url', {
			method: 'GET',
			body: params
		});

		// Redirect the user
		if (url) {
			window.location.href = url;
		} else {
			throw new Error('No redirect URL received.');
		}
	} catch (error) {
		toast(__('Failed to generate GitHub App URL. Please check your inputs.', 'wp2-update'), 'error');
		throw error; // Re-throw for dispatcher error handling
	}
});

// Refactored rollback logic to use a dedicated action.

register_action('rollback-package', async (button) => {
	const repo = button?.dataset.packageRepo;
	const version = button?.dataset.version;
	if (!repo || !version) throw new Error('Missing package repo or version.');

	try {
		await api_request('wp2-update/v1/manage-packages', {
			body: { action: 'rollback', repo_slug: repo, version },
		});
		toast(`${repo} rolled back to ${version} successfully.`);
	} catch (error) {
		toast(`Failed to rollback ${repo}: ${error.message}`, 'error');
	}
});

// Bootstrap
const init_app = async () => {
	const app = document.getElementById('wp2-update-app');
	if (!app) return console.error('[WP2 Update] #wp2-update-app not found.');

	app.addEventListener('click', async (e) => {
		const btn = e.target.closest('button[data-action]');
		if (!btn) return;
		const action = btn.dataset.action;
		try { await with_button_busy(btn, () => dispatch_action(action, btn)); }
		catch (err) { console.error(`[Action Failed: ${action}]`, err); toast(err.message || String(err), 'error'); }
	});

	init_ui();

	const syncBtn = document.querySelector('[data-action="sync-packages"]');
	const disconnectBtn = document.querySelector('[data-action="disconnect"]');
	const apply_disable = (flag) => {
		if (syncBtn) syncBtn.disabled = flag;
		if (disconnectBtn) disconnectBtn.disabled = flag;
	};
	apply_disable(is_any_pkg_updating.get());
	app_state.listen((state) => {
		apply_disable(is_any_pkg_updating.get());
		// Re-render
		render_view(state.currentStage);
		render_package_table(state.packages, state.isLoading);
		window.wp2UpdateAppState = state;
	});

	// Initial stage detection
	app_state.set({ ...app_state.get(), isLoading: true });
	try {
		const status = await api_request('wp2-update/v1/github/connection-status', { method: 'GET' });
		if (status.connected) {
			app_state.set({ ...app_state.get(), currentStage: 'managing' });
			await dispatch_action('sync-packages');
		} else {
			app_state.set({ ...app_state.get(), currentStage: 'pre-connection' });
		}
	} catch (err) {
		app_state.set({ ...app_state.get(), currentStage: 'pre-connection' });
		toast(err.message || 'Failed to load status', 'error');
	} finally {
		app_state.set({ ...app_state.get(), isLoading: false });
	}
};

document.addEventListener('DOMContentLoaded', init_app);

// Optional: rollback trigger (kept local; uses dispatcher)
document.addEventListener('click', (e) => {
	const el = e.target.closest('.rollback-button');
	if (!el) return;
	const repo = el.getAttribute('data-package-repo');
	const version = window.prompt('Enter the version to rollback to:');
	if (!version) return;

	const fakeBtn = document.createElement('button');
	fakeBtn.dataset.action = 'rollback-package';
	fakeBtn.dataset.packageRepo = repo;
	fakeBtn.dataset.version = version;
	dispatch_action('rollback-package', fakeBtn);
});