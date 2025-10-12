// Service for managing apps

import { api_request } from '../api.js';
import { updateUnifiedState } from '../state/store';
import { Logger } from '../utils.js';

export const createApp = async (appData) => {
    return api_request('apps', {
        method: 'POST',
        body: appData,
    });
};

export const deleteApp = async (appId) => {
    return api_request(`apps/${appId}`, {
        method: 'DELETE',
    });
};

export const fetchApps = async () => {
    return api_request('apps', { method: 'GET' });
};

export const updateApp = async (appId, appData) => {
    return api_request(`apps/${appId}`, {
        method: 'PUT',
        body: appData,
    });
};

export const AppService = {
    async fetchApps() {
        try {
            const response = await fetchApps();
            const apps = response?.data?.apps ?? response?.apps ?? [];
            updateUnifiedState((state) => ({
                apps,
                selectedAppId: state.selectedAppId ?? (apps[0]?.id ?? null),
            }));
            return apps;
        } catch (error) {
            Logger.error('Failed to fetch apps:', error);
            throw error;
        }
    },

    async createApp(appData) {
        try {
            const response = await createApp(appData);
            const newApp = response?.data?.app ?? response?.app ?? null;
            if (newApp) {
                updateUnifiedState((state) => ({
                    apps: [...state.apps, newApp],
                    selectedAppId: state.selectedAppId ?? newApp.id ?? null,
                }));
            }
            return newApp;
        } catch (error) {
            Logger.error('Failed to create app:', error);
            throw error;
        }
    },

    async deleteApp(appId) {
        try {
            await deleteApp(appId);
            updateUnifiedState((state) => {
                const remainingApps = state.apps.filter((app) => app.id !== appId);
                const nextSelected = state.selectedAppId === appId
                    ? (remainingApps[0]?.id ?? null)
                    : state.selectedAppId;

                return {
                    apps: remainingApps,
                    selectedAppId: nextSelected,
                };
            });
        } catch (error) {
            Logger.error('Failed to delete app:', error);
            throw error;
        }
    },

    async updateApp(appId, appData) {
        try {
            const response = await updateApp(appId, appData);
            const updatedApp = response?.data?.app ?? response?.app ?? null;
            if (updatedApp) {
                updateUnifiedState((state) => ({
                    apps: state.apps.map((app) =>
                        app.id === appId ? { ...app, ...updatedApp } : app
                    ),
                }));
            }
            return updatedApp;
        } catch (error) {
            Logger.error('Failed to update app:', error);
            throw error;
        }
    },

    selectApp(appId) {
        updateUnifiedState({ selectedAppId: appId });
    },
};
