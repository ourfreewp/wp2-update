// Service for managing apps

import { api_request } from '../api.js';
import { updateAppState, updateDashboardState } from '../state/store';

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
            updateAppState((state) => ({
                apps,
                selectedAppId: state.selectedAppId ?? (apps[0]?.id ?? null),
            }));
            updateDashboardState((state) => ({ packages: state.allPackages }));
            return apps;
        } catch (error) {
            console.error('Failed to fetch apps:', error);
            throw error;
        }
    },

    async createApp(appData) {
        try {
            const response = await createApp(appData);
            const newApp = response?.data?.app ?? response?.app ?? null;
            if (newApp) {
                updateAppState((state) => ({
                    apps: [...state.apps, newApp],
                    selectedAppId: state.selectedAppId ?? newApp.id ?? null,
                }));
                updateDashboardState((state) => ({ packages: state.allPackages }));
            }
            return newApp;
        } catch (error) {
            console.error('Failed to create app:', error);
            throw error;
        }
    },

    async deleteApp(appId) {
        try {
            await deleteApp(appId);
            updateAppState((state) => {
                const remainingApps = state.apps.filter((app) => app.id !== appId);
                const nextSelected = state.selectedAppId === appId
                    ? (remainingApps[0]?.id ?? null)
                    : state.selectedAppId;

                return {
                    apps: remainingApps,
                    selectedAppId: nextSelected,
                };
            });
            updateDashboardState((state) => ({ packages: state.allPackages }));
        } catch (error) {
            console.error('Failed to delete app:', error);
            throw error;
        }
    },

    async updateApp(appId, appData) {
        try {
            const response = await updateApp(appId, appData);
            const updatedApp = response?.data?.app ?? response?.app ?? null;
            if (updatedApp) {
                updateAppState((state) => ({
                    apps: state.apps.map((app) =>
                        app.id === appId ? { ...app, ...updatedApp } : app
                    ),
                }));
                updateDashboardState((state) => ({ packages: state.allPackages }));
            }
            return updatedApp;
        } catch (error) {
            console.error('Failed to update app:', error);
            throw error;
        }
    },

    selectApp(appId) {
        updateAppState({ selectedAppId: appId });
        updateDashboardState((state) => ({ packages: state.allPackages }));
    },
};
