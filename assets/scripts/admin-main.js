console.log('admin-main.js loaded');

import debounce from 'lodash/debounce';
import { api_request } from './modules/api/index.js';
import { app_state } from './modules/state/store.js';
import { toast } from './modules/ui/toast.js';
import { confirm_modal } from './modules/ui/modal.js';
import { init_ui } from './modules/ui/init.js';
import { render_view } from './modules/components/views.js';
import { render_package_table } from './modules/components/table.js';
import { show_global_spinner, hide_global_spinner } from './modules/ui/spinner.js';

// Define actions
const actions = {
  'start-connection': async () => {
    try {
      const payload = await api_request('github/connect-url', { method: 'GET' });
      register_manifest(payload);
    } catch (error) {
      toast(__('Error starting GitHub connection: ', 'wp2-update') + error.message, 'error');
    }
  },
  'disconnect': async () => {
    confirm_modal(
      __('Are you sure you want to disconnect? This will remove your credentials.', 'wp2-update'),
      async () => {
        await api_request('github/disconnect');
        app_state.set({ ...app_state.get(), currentStage: 'pre-connection', packages: [] });
        toast(__('Disconnected successfully.', 'wp2-update'));
      },
      () => toast(__('Disconnect action canceled.', 'wp2-update'))
    );
  },
  'sync-packages': debounce(async () => {
    show_global_spinner();
    app_state.set({ ...app_state.get(), isLoading: true });
    try {
      const result = await api_request('sync-packages', { method: 'GET' });
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
      toast(__('Successfully synced with GitHub.', 'wp2-update'));
    } catch (error) {
      app_state.set({
        ...app_state.get(),
        isLoading: false,
        syncError: error.message || __('Sync failed.', 'wp2-update'),
      });
      toast(__('Sync failed: ', 'wp2-update') + (error.message || __('Unknown error', 'wp2-update')), 'error');
    } finally {
      hide_global_spinner();
    }
  }, 300),
  'update-package': async (button) => {
    const repo = button?.dataset.packageRepo;
    const version = document.querySelector(`.release-dropdown[data-package-repo="${repo}"]`)?.value;
    if (!repo || !version) throw new Error('Missing package repo or version.');
    confirm_modal(
      __('Are you sure you want to update this package?', 'wp2-update'),
      async () => {
        try {
          await api_request('manage-packages', {
            body: { action: 'update', repo_slug: repo, version },
          });
          toast(`${repo} update to ${version} initiated.`);
        } catch (error) {
          toast(`Failed to update ${repo}: ${error.message}`, 'error');
        }
      }
    );
  },
  'create-github-app': async () => {
    const orgInput = document.querySelector('#organization');
    const orgName = orgInput?.value.trim();

    if (!orgName) {
      toast(__('Please enter an organization name.', 'wp2-update'), 'error');
      return;
    }

    try {
      show_global_spinner();
      const { url } = await api_request('github/connect-url', {
        method: 'GET',
        params: { organization: orgName },
      });

      if (url) {
        window.location.href = url;
      } else {
        toast(__('Failed to generate GitHub App URL.', 'wp2-update'), 'error');
      }
    } catch (error) {
      toast(__('Error generating GitHub App URL: ', 'wp2-update') + error.message, 'error');
    } finally {
      hide_global_spinner();
    }
  },
};

// Helper function to register manifest
const register_manifest = ({ account, manifest, state }) => {
  const form = document.createElement('form');
  form.action = `${account}?state=${encodeURIComponent(state)}`;
  form.method = 'post';
  form.target = '_blank';

  const input = document.createElement('input');
  input.type = 'hidden';
  input.name = 'manifest';
  input.value = manifest;
  form.appendChild(input);

  document.body.appendChild(form);
  form.submit();
  form.remove();
};

// Initialize the app
const init_app = () => {
  document.addEventListener('click', async (e) => {
    const button = e.target.closest('button[data-action]');
    if (!button) return;
    const action = button.dataset.action;
    if (actions[action]) {
      try {
        await actions[action](button);
      } catch (error) {
        console.error(`[Action Failed: ${action}]`, error);
        toast(error.message || 'An error occurred.', 'error');
      }
    }
  });

  init_ui();
  app_state.listen((state) => {
    render_view(state.currentStage);
    render_package_table(state.packages, state.isLoading);
  });
};

document.addEventListener('DOMContentLoaded', init_app);

// Add fallbacks to prevent errors if wp2UpdateData or __ is undefined
if (typeof wp2UpdateData === 'undefined') {
    console.error('wp2UpdateData is not defined. Ensure it is localized properly in Manager.php.');
    window.wp2UpdateData = {
        pluginUrl: '',
        restBase: '',
        nonce: '',
        user: { id: 0, login: 'Guest' },
        availableActions: []
    }; // Provide default values
}

// Add a fallback for the __ function if wp-i18n is not loaded
if (typeof __ === 'undefined') {
    console.error('The __ function is not defined. Ensure wp-i18n is loaded.');
    window.__ = (text) => text; // Provide a fallback that returns the input text
}

console.log('Debugging WP2 Update:');
console.log('wp2UpdateData:', typeof wp2UpdateData !== 'undefined' ? wp2UpdateData : 'wp2UpdateData is not defined.');
console.log('The __ function:', typeof __ !== 'undefined' ? 'Available' : 'Not defined. Ensure wp-i18n is loaded.');
console.log('App State:', app_state.get());
console.log('Available Actions:', Object.keys(actions));
console.log('Current User:', typeof wp2UpdateData !== 'undefined' && wp2UpdateData.user ? wp2UpdateData.user : 'User data not available.');
console.log('Debugging wp2UpdateData:', typeof wp2UpdateData !== 'undefined' ? wp2UpdateData : 'wp2UpdateData is not defined.');