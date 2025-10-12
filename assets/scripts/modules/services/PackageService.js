// Service helpers for managing packages within the WP2 Update dashboard.

import { api_request } from '../api.js';
import { updateUnifiedState, unified_state } from '../state/store.js';
import { Logger } from '../utils.js';
import { toast } from '../ui/toast.js';

const normalizePackages = (packages = [], { managed } = { managed: false }) => {
	return packages
		.filter(pkg => pkg && typeof pkg === 'object')
		.map(pkg => ({
			name: pkg.name ?? pkg.title ?? 'Unknown Package',
			version: pkg.version ?? pkg.installed ?? '',
			repo: pkg.repo ?? pkg.repository ?? '',
			status: pkg.status ?? 'unknown',
			app_id: pkg.app_id ?? pkg.app_uid ?? null,
			installed: pkg.installed ?? pkg.version ?? '',
			latest: pkg.latest ?? pkg.github_data?.latest_release ?? '',
			is_managed: managed ? true : Boolean(pkg.is_managed ?? pkg.app_id ?? pkg.app_uid),
			last_sync: pkg.last_sync ?? pkg.lastChecked ?? pkg.last_checked ?? '',
			sync_log: pkg.sync_log ?? pkg.syncLog ?? '',
			...pkg,
		}));
};

const persistPackagesToState = (packages = [], unlinkedPackages = []) => {
	const normalizedManaged = normalizePackages(packages, { managed: true });
	const normalizedUnlinked = normalizePackages(unlinkedPackages).map(pkg => ({
		...pkg,
		is_managed: false,
		app_id: null,
	}));

	const combined = [...normalizedManaged];
	normalizedUnlinked.forEach(pkg => {
		if (!combined.some(existing => existing.repo === pkg.repo)) {
			combined.push(pkg);
		}
	});

	updateUnifiedState({
		allPackages: combined,
		packages: combined,
		unlinkedPackages: normalizedUnlinked,
	});

	return {
		packages: normalizedManaged,
		unlinkedPackages: normalizedUnlinked,
	};
};

export const PackageService = {
	async fetchPackages() {
		try {
			const response = await api_request('sync-packages', { method: 'GET' });
			return persistPackagesToState(response?.packages, response?.unlinked_packages);
		} catch (error) {
			Logger.error('Failed to fetch packages', error);
			toast('Failed to fetch packages. Please try again.', 'error');
			throw error;
		}
	},

	async syncPackages() {
		try {
			const response = await api_request('sync-packages', { method: 'GET' });
			return persistPackagesToState(response?.packages, response?.unlinked_packages);
		} catch (error) {
			Logger.error('Failed to sync packages', error);
			toast('Failed to sync packages. Please try again.', 'error');
			throw error;
		}
	},

	async assignPackage(repoId, appId) {
		try {
			await api_request('packages/assign', {
				method: 'POST',
				body: { repo_id: repoId, app_id: appId },
			});
			return this.fetchPackages();
		} catch (error) {
			Logger.error('Failed to assign package to app', error);
			toast('Failed to assign package. Please try again.', 'error');
			throw error;
		}
	},

	async unassignPackage(repoId) {
		try {
			await api_request(`packages/${encodeURIComponent(repoId)}/unassign`, { method: 'POST' });
			return this.fetchPackages();
		} catch (error) {
			Logger.error('Failed to unassign package', error);
			toast('Failed to unassign package. Please try again.', 'error');
			throw error;
		}
	},

	getPackageByRepo(repo) {
		const { allPackages = [], unlinkedPackages = [] } = unified_state.get();
		return [...allPackages, ...unlinkedPackages].find(pkg => pkg.repo === repo);
	},
};

export const syncPackages = () => PackageService.syncPackages();
