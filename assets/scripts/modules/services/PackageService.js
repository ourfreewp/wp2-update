// Service for managing packages

import { api_request } from '../api.js';
import { updateDashboardState } from '../state/store';

export const syncPackages = async () => {
    return api_request('sync-packages', { method: 'GET' });
};

export const assignPackage = async (packageId, appId) => {
    return api_request('packages/assign', {
        method: 'POST',
        body: { repo_id: packageId, app_id: appId },
    });
};

export const fetchPackages = async () => {
    return api_request('sync-packages', { method: 'GET' });
};

export const PackageService = {
    async fetchPackages() {
        try {
            const response = await fetchPackages();
            const packages = response?.packages ?? [];
            const unlinkedPackages = response?.unlinked_packages ?? [];
            updateDashboardState({ packages, unlinkedPackages });
            return { packages, unlinkedPackages };
        } catch (error) {
            console.error('Failed to fetch packages:', error);
            throw error;
        }
    },

    async assignPackage(packageId, appId) {
        try {
            await assignPackage(packageId, appId);
            this.fetchPackages();
        } catch (error) {
            console.error('Failed to assign package:', error);
            throw error;
        }
    },

    async unassignPackage(packageId) {
        try {
            await api_request(`packages/${packageId}/unassign`, { method: 'POST' });
            this.fetchPackages();
        } catch (error) {
            console.error('Failed to unassign package:', error);
            throw error;
        }
    },

    async syncPackages() {
        try {
            const response = await syncPackages();
            const packages = response?.packages ?? [];
            const unlinkedPackages = response?.unlinked_packages ?? [];
            updateDashboardState({ packages, unlinkedPackages });
            return { packages, unlinkedPackages };
        } catch (error) {
            console.error('Failed to sync packages:', error);
            throw error;
        }
    },
};
