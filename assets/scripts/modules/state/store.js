import { atom } from 'nanostores';

export const STATUS = {
	LOADING: 'loading',
	NOT_CONFIGURED: 'not_configured',
	NOT_CONFIGURED_WITH_PACKAGES: 'not_configured_with_packages',
	CONFIGURING: 'configuring',
	MANUAL_CONFIGURING: 'manual_configuring',
	APP_CREATED: 'app_created',
	CONNECTING: 'connecting',
	INSTALLED: 'installed',
	ERROR: 'error',
};

const parseDefaultManifest = () => {
	try {
		const manifest = window.wp2UpdateData?.manifest;
		return typeof manifest === 'object' && manifest ? manifest : {};
	} catch (error) {
		console.warn('WP2 Update: unable to parse default manifest.', error);
		return {};
	}
};

const defaultManifest = parseDefaultManifest();

const withRequiredManifestFields = (manifest) => {
	const ensured = { ...(manifest || {}) };
	const redirectUrl = window.wp2UpdateData?.redirectUrl;

	if (redirectUrl) {
		if (!ensured.redirect_url) {
			ensured.redirect_url = redirectUrl;
		}

		const callbacks = Array.isArray(ensured.callback_urls) ? [...ensured.callback_urls] : [];
		if (!callbacks.includes(redirectUrl)) {
			callbacks.push(redirectUrl);
		}
		ensured.callback_urls = callbacks;
	}

	return ensured;
};

const buildDefaultManifestDraft = () => {
	const siteName = window.wp2UpdateData?.siteName;
	const manifest = withRequiredManifestFields(defaultManifest);

	return {
		name: manifest?.name || (siteName ? `${siteName} Updater` : ''),
		accountType: 'user',
		organization: '',
		manifestJson: JSON.stringify(
			manifest && Object.keys(manifest).length ? manifest : {},
			null,
			2
		),
		encryptionKey: '', // Ensure this is tracked in the state
	};
};

export const dashboard_state = atom({
	status: STATUS.LOADING,
	isProcessing: false,
	message: '',
	details: {},
	manifestDraft: buildDefaultManifestDraft(),
	packages: [],
	allPackages: [],
	unlinkedPackages: [],
	error: null,
	polling: { active: false },
});

// Add support for multiple apps
export const app_state = atom({
	apps: [], // List of apps
	selectedAppId: null, // Currently selected app ID
});

// Centralized state management
export const updateAppState = (newState) => {
	const currentState = app_state.get();
	const updates = typeof newState === 'function' ? newState(currentState) : newState;

	app_state.set({
		...currentState,
		...(updates || {}),
	});
};

// Update dashboard_state to filter packages by selected app
export const updateDashboardState = (newState) => {
	const currentDashboard = dashboard_state.get();
	const updates = typeof newState === 'function' ? newState(currentDashboard) : newState;
	const currentAppId = app_state.get().selectedAppId;
	const { packages: packagesUpdate, ...restUpdates } = updates || {};
	const hasPackagesUpdate = updates && Object.prototype.hasOwnProperty.call(updates, 'packages');
	const allPackages = hasPackagesUpdate
		? (Array.isArray(packagesUpdate) ? packagesUpdate : [])
		: (currentDashboard.allPackages || []);
	const packagesForState = currentAppId
		? allPackages.filter(pkg => pkg.app_id === currentAppId)
		: allPackages;

	dashboard_state.set({
		...currentDashboard,
		...(restUpdates || {}),
		allPackages,
		packages: packagesForState,
	});
};

// Add methods to manage apps

export const addApp = (app) => {
    const currentApps = app_state.get().apps;
    app_state.set({
        ...app_state.get(),
        apps: [...currentApps, app],
    });
};

export const removeApp = (appId) => {
    const currentApps = app_state.get().apps;
    app_state.set({
        ...app_state.get(),
        apps: currentApps.filter(app => app.id !== appId),
    });
};

export const selectApp = (appId) => {
    app_state.set({
        ...app_state.get(),
        selectedAppId: appId,
    });
};
