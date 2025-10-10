import debounce from 'lodash/debounce';
import { app_state } from '../state/store.js';
import { api_request } from '../api.js';
import { confirm_modal } from '../ui/modal.js';
import { show_global_spinner, hide_global_spinner } from '../ui/spinner.js';

let toast; // Declare toast variable

// Dynamic import of toast.js
const loadToast = async () => {
    if (!toast) {
        const module = await import('../ui/toast.js');
        toast = module.toast;
    }
};

export const actions = {
    disconnect: () => {
        confirm_modal(
            'Are you sure you want to disconnect?',
            async () => {
                try {
                    show_global_spinner();
                    await api_request('disconnect', { method: 'POST' });
                    app_state.set({ ...app_state.get(), currentStage: 'configure-manifest' });
                    toast('Disconnected successfully.', 'success');
                } catch (error) {
                    toast('Failed to disconnect.', 'error', error.message);
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
            toast('Failed to sync packages.', 'error', error.message);
        } finally {
            hide_global_spinner();
        }
    }, 500),
    installPackage: async ({ repo, version }) => {
        if (!repo || !version) return;

        confirm_modal(`Install ${repo} version ${version}?`, async () => {
            try {
                show_global_spinner();
                await api_request('manage-packages', {
                    method: 'POST',
                    body: JSON.stringify({ action: 'install', repo, version })
                });
                toast(`Installed ${repo} version ${version}.`, 'success');
                actions.syncPackages();
            } catch (error) {
                toast(`Failed to install ${repo}.`, 'error', error.message);
            } finally {
                hide_global_spinner();
            }
        });
    },
    rollbackPackage: async ({ repo, version }) => {
        if (!repo || !version) return;

        confirm_modal(`Rollback ${repo} to version ${version}?`, async () => {
            try {
                show_global_spinner();
                await api_request('manage-packages', {
                    method: 'POST',
                    body: JSON.stringify({ action: 'rollback', repo, version })
                });
                toast(`Rolled back ${repo} to version ${version}.`, 'success');
                actions.syncPackages();
            } catch (error) {
                toast(`Failed to rollback ${repo}.`, 'error', error.message);
            } finally {
                hide_global_spinner();
            }
        });
    },
    submitManifestConfig: async (button) => {
        const state = app_state.get();
        const draft = state.manifestDraft;

        if (!draft.name) {
            toast('App Name is required.', 'error');
            return;
        }

        const encryptionKey = document.getElementById('wp2-encryption-key')?.value;
        if (!encryptionKey || encryptionKey.length < 16) {
            toast('Encryption Key is required and must be at least 16 characters.', 'error');
            return;
        }

        try {
            show_global_spinner();
            button.disabled = true;

            const payload = await api_request('github/connect-url', {
                method: 'POST',
                body: {
                    name: draft.name,
                    account_type: draft.accountType,
                    organization: draft.organization,
                    encryption_key: encryptionKey
                }
            });

            app_state.set({ ...app_state.get(), currentStage: 'connecting-to-github' });

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = payload.account;
            form.target = '_blank';

            const manifestInput = document.createElement('input');
            manifestInput.type = 'hidden';
            manifestInput.name = 'manifest';
            manifestInput.value = payload.manifest;
            form.appendChild(manifestInput);

            document.body.appendChild(form);
            form.submit();
            form.remove();

        } catch (error) {
            toast('Failed to connect to GitHub.', 'error', error.message);
        } finally {
            button.disabled = false;
            hide_global_spinner();
        }
    }
};

// Preload the toast module
loadToast();