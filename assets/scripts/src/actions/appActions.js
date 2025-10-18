import { AppService } from '../services/AppService.js';
import { updateState } from '../state/store.js';
import { NotificationService } from '../services/NotificationService.js';
import { createAsyncThunk } from './action-helpers.js';

const appService = new AppService();

const fetchAppsThunk = createAsyncThunk(async () => {
  const apps = await appService.fetchApps();
  if (Array.isArray(apps)) {
    updateState(state => ({
      apps,
      selectedAppId: state.selectedAppId ?? (apps.length ? apps[0].id : null),
    }));
  }
}, {
  errorMessage: 'Failed to fetch applications.'
});

const createAppThunk = createAsyncThunk(async (appData) => {
  await appService.createApp(appData);
  updateState({ activeModal: null });
  await fetchAppsThunk();
}, {
  successMessage: ({ name }) => `App "${name}" created successfully.`,
  errorMessage: 'Failed to create application.'
});

const deleteAppThunk = createAsyncThunk(async (appId, appName) => {
  if (!confirm(`Are you sure you want to delete ${appName}?`)) {
    return;
  }
  await appService.deleteApp(appId);
  await fetchAppsThunk();
}, {
  successMessage: (_, appName) => `App "${appName}" deleted.`,
  errorMessage: 'Failed to delete application.'
});

const disconnectAppThunk = createAsyncThunk(async (appId) => {
  await appService.disconnectApp(appId);
  await fetchAppsThunk();
}, {
  successMessage: 'App disconnected successfully.',
  errorMessage: 'Failed to disconnect app.'
});

export { fetchAppsThunk as fetchApps, createAppThunk as createApp, deleteAppThunk as deleteApp, disconnectAppThunk as disconnectApp };
