import debounce from 'lodash/debounce';
import { app_state } from './modules/state/store.js';
import { api_request } from './modules/api/index.js';
import { toast } from './modules/ui/toast.js';
import { confirm_modal } from './modules/ui/modal.js';
import { show_global_spinner, hide_global_spinner } from './modules/ui/spinner.js';
import { render_view } from './modules/components/views.js';
import { render_package_table } from './modules/components/table.js';
import { render_configure_manifest } from './modules/components/manifest-config.js';

// --- Main Application Logic ---

const actions = {
	'start-connection': () => {
		app_state.set({ ...app_state.get(), currentStage: 'configure-manifest' });
	},
	'disconnect': () => {
		confirm_modal(
			'Are you sure you want to disconnect?',
			async () => {
				try {
					show_global_spinner();
					await api_request('github/disconnect');
					app_state.set({ ...app_state.get(), currentStage: 'disconnected', packages: [] });
					toast('Disconnected successfully.');
				} catch (error) {
					toast(error.message, 'error');
				} finally {
					hide_global_spinner();
				}
			}
		);
	},
	'sync-packages': debounce(async () => {
		show_global_spinner();
		app_state.set({ ...app_state.get(), isLoading: true, syncError: null });
		try {
			const { packages = [] } = await api_request('sync-packages', { method: 'GET' });
			app_state.set({ ...app_state.get(), packages, isLoading: false, currentStage: 'managing' });
			toast('Successfully synced with GitHub.');
		} catch (error) {
			app_state.set({ ...app_state.get(), isLoading: false, syncError: error.message });
			toast(error.message, 'error');
		} finally {
			hide_global_spinner();
		}
	}, 500),
	'update-package': async (button) => {
		const repo = button?.dataset.packageRepo;
		const type = button?.dataset.packageType; // Added package type
		const version = document.querySelector(`select[data-package-repo="${repo}"]`)?.value;
		if (!repo || !version || !type) return;

		confirm_modal(`Update ${repo} to ${version}?`, async () => {
			try {
				show_global_spinner();
				await api_request('manage-packages', { body: { repo_slug: repo, version, action: 'update', type: type } }); // Pass type to API
				toast(`Update initiated for ${repo}.`);
			} catch (error) {
				toast(error.message, 'error');
			} finally {
				hide_global_spinner();
			}
		});
	},
	'submit-manifest-config': async (button) => {
        const state = app_state.get();
        const draft = state.manifestDraft;

        try {
            show_global_spinner();
            button.disabled = true;
            const payload = await api_request('github/connect-url', {
                method: 'POST',
                body: {
                    name: draft.name,
                    account_type: draft.accountType,
                    organization: draft.organization,
                    manifest: draft.manifestJson,
                }
            });

            // Transition to 'connecting-to-github' before submitting the form
            app_state.set({ ...app_state.get(), currentStage: 'connecting-to-github' });

            const manifestForm = document.createElement('form');
            manifestForm.action = `${payload.account}?state=${encodeURIComponent(payload.state)}`;
            manifestForm.method = 'post';
            manifestForm.target = '_blank';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'manifest';
            input.value = payload.manifest;
            manifestForm.appendChild(input);
            document.body.appendChild(manifestForm);
            manifestForm.submit();
            manifestForm.remove();

        } catch (error) {
            toast(error.message, 'error');
        } finally {
            hide_global_spinner();
            button.disabled = false;
        }
    },
};

// --- GitHub Callback Handler ---

const handleGitHubCallback = () => {
	const container = document.getElementById('wp2-update-github-callback');
	if (!container) return;

    const notice = (message, isError = false) => {
        container.innerHTML = `<div class="notice ${isError ? 'notice-error' : 'notice-success'}"><p>${message}</p></div>`;
    };

	const params = new URLSearchParams(window.location.search);
	const code = params.get('code');
	const state = params.get('state');

	if (!code || !state) {
		notice('Invalid callback parameters.', true);
		return;
	}

	notice('Finalizing GitHub connection...');

	(async () => {
		try {
			await api_request('github/exchange-code', { body: { code, state } });
			notice('Connection successful! You can close this window.');
            if (window.opener) {
                // Post message to the opener window to trigger the next steps (sync packages)
                window.opener.postMessage('wp2-update-github-connected', window.location.origin);
            }
		} catch (error) {
			notice(`Error: ${error.message}`, true);
		}
	})();
};

// --- App Initialization ---

const init_app = () => {
    try {
        console.log('Initializing app...');

        // Debugging: Verify app_state initialization
        if (!app_state || typeof app_state.get !== 'function') {
            throw new Error('app_state is not initialized or does not have a get() method.');
        }

        const initialState = app_state.get();
        console.log('Initial App State:', initialState);

        document.addEventListener('click', (e) => {
            const button = e.target.closest('button[data-action]');
            if (button?.dataset.action && actions[button.dataset.action]) {
                e.preventDefault();
                actions[button.dataset.action](button);
            }
        });

        const manifestForm = document.getElementById('manifest-config-form');
        if (manifestForm) {
            manifestForm.addEventListener('input', debounce((e) => {
                const draft = { ...app_state.get().manifestDraft };
                const { name, value } = e.target;
                if (name === 'app-name') draft.name = value;
                if (name === 'app-type') draft.accountType = value;
                if (name === 'organization') draft.organization = value;
                if (name === 'manifest-json') draft.manifestJson = value;
                app_state.set({ ...app_state.get(), manifestDraft: draft });
            }, 300));
        }

        window.addEventListener('message', (event) => {
            if (event.data === 'wp2-update-github-connected') {
                toast('GitHub App connected successfully!');
                actions['sync-packages']();
            }
        });

        app_state.listen((state) => {
            console.log('App state updated:', state);
            render_view(state.currentStage);
            render_package_table(state.packages, state.isLoading, state.syncError);
            render_configure_manifest(state.manifestDraft);
        });

        // Initial render
        render_view(initialState.currentStage);
        render_package_table(initialState.packages, initialState.isLoading, initialState.syncError);
        render_configure_manifest(initialState.manifestDraft);

        // Handle callback page separately
        if (document.getElementById('wp2-update-github-callback')) {
            handleGitHubCallback();
        }
    } catch (error) {
        console.error('Error initializing app:', error);
    }
};

document.addEventListener('DOMContentLoaded', async () => {
    try {
        show_global_spinner();
        const connectionStatus = await api_request('connection-status', { method: 'GET' });

        if (connectionStatus?.connected) {
            app_state.set({ ...app_state.get(), currentStage: 'managing' });
        } else {
            app_state.set({ ...app_state.get(), currentStage: 'configure-manifest' });
        }
    } catch (error) {
        console.error('Failed to fetch connection status:', error);
        app_state.set({ ...app_state.get(), currentStage: 'configure-manifest' });
    } finally {
        hide_global_spinner();
        init_app();
    }
});

const appTypeSelect = document.querySelector('#app-type');
const orgNameRow = document.querySelector('#org-name-row');
if (appTypeSelect && orgNameRow) {
    appTypeSelect.addEventListener('change', (event) => {
        orgNameRow.style.display = event.target.value === 'organization' ? 'block' : 'none';
    });
}
